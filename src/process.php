<?php declare(strict_types=1);
# defs {{{
namespace SM;
use FFI,Throwable;
use function
  class_exists,function_exists,is_resource,is_object,
  is_string,substr,json_encode,json_decode,
  fread,fclose,dechex,count,
  proc_open,proc_get_status,proc_terminate,
  pcntl_signal,pcntl_fork,pcntl_exec,pcntl_waitpid,
  posix_kill,ob_start,ob_end_flush;
use const
  PHP_BINARY,PHP_OS_FAMILY,PHP_INT_MAX,
  SIGCHLD,SIG_IGN,DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'sync.php';
# }}}
class Process # {{{
{
  # TODO: stop/start hundreds of processes
  # TODO: test/increase startup timeouts
  # TODO: slamaster
  # TODO: created or attached? (prop)
  # initializer {{{
  static ?object $BASE=null;
  private function __construct()
  {}
  static function init(array $o): ?object
  {
    if (self::$BASE)
    {
      return ErrorEx::fail(
        __CLASS__, 'already initialized'
      );
    }
    try
    {
      self::$BASE = Process_Base::construct($o);
      return null;
    }
    catch (Throwable $e)
    {
      return ErrorEx::chain(
        ErrorEx::fail(__CLASS__), $e
      );
    }
  }
  # }}}
  # stasis {{{
  static function is_master(): bool {
    return self::$BASE->isMaster;
  }
  static function set_handler(object $f): void {
    self::$BASE->handlerSet($f);
  }
  static function get_config(): ?array {
    return self::$BASE->config;
  }
  static function start(
    string $file, array $cfg=[]
  ):object
  {
    return self::$BASE->start($file, $cfg);
  }
  static function start_group(
    string $file, int $count, array $cfg=[]
  ):object
  {
    return ($count < 2)
      ? self::$BASE->start($file, $cfg)
      : self::$BASE->startGroup($file, $count, $cfg);
  }
  static function stop(string $pid): object {
    return self::$BASE->stop($pid);
  }
  static function stop_group(array $pids): object {
    return self::$BASE->stopGroup($pids);
  }
  static function stop_all(): object {
    return self::$BASE->stopAll();
  }
  static function count(): int {
    return self::$BASE->spawnCount;
  }
  static function list(): array {
    return self::$BASE->spawnGetPids();
  }
  # }}}
}
# }}}
### BASE
abstract class Process_Base # {{{
{
  # basis {{{
  public bool    $isMaster=true;
  public ?array  $config=null;
  public ?object $status,$dispatcher;
  function __construct(
    public ?object $handler,
    public bool    $autonomy,
    public string  $g0name,
    public ?object $g0so,
    public int     $g0id,
    public string  $g1name=''
  ) {
    $this->init();
    $this->status = $status =
      $this->newStatus(Fx::$PROCESS_ID);
    ###
    $status->set(1);
    $this->dispatcher = Loop::gear(
      new Process_Dispatcher($this)
    );
  }
  # }}}
  static function construct(array $o): object # {{{
  {
    # parse options (TODO:more checks)
    # {{{
    if (!isset($o[$k = 'group']))
    {
      throw ErrorEx::fail(
        'option "'.$k.'" is required'
      );
    }
    if (!is_string($o[$k]))
    {
      throw ErrorEx::fail(
        'option "'.$k.'" is not a string'
      );
    }
    $g0name = $o[$k];
    $g1name = '';
    if (isset($o[$k = 'role']))
    {
      if (!is_string($o[$k]))
      {
        throw ErrorEx::fail(
          'option "'.$k.'" is not a string'
        );
      }
      switch ($s = $o[$k]) {
      case 'auto':
        $role = 0;
        break;
      case 'master':
        $role = 1;
        break;
      case 'slave':
        $role = 2;
        break;
      default:
        $role = 3;
        if (substr($s, 0, 7) !== 'master:')
        {
          throw ErrorEx::fail(
            'option "'.$k.'" is incorrect'
          );
        }
        $g1name = substr($s, 7);
        break;
      }
    }
    else {
      $role = 0;# auto
    }
    $autonomy = (
      isset($o[$k = 'autonomy']) && !!$o[$k]
    );
    if (isset($o[$k = 'handler']))
    {
      if (!is_object($o[$k]))
      {
        throw ErrorEx::fail(
          'option "'.$k.'" is not an object'
        );
      }
      $hand = $o[$k];
    }
    else {
      $hand = null;
    }
    # }}}
    # check requirements
    # {{{
    if (!class_exists('SyncSharedMemory', false))
    {
      throw ErrorEx::fail(
        'Sync','required extension'
      );
    }
    if (PHP_OS_FAMILY !== 'Windows')
    {
      if (!function_exists('pcntl_waitpid'))
      {
        return ErrorEx::fail(
          'PCNTL','required extension'
        );
      }
      if (!function_exists('posix_kill'))
      {
        return ErrorEx::fail(
          'POSIX','required extension'
        );
      }
      # handle SIGCHLD (child termination):
      # this signal is unreliable --
      # it is not triggered on my system;
      # there is no point in handler, but
      # some systems take care of zombies when
      # this signal is explicitly ignored
      pcntl_signal(SIGCHLD, SIG_IGN);
    }
    # }}}
    # create mastergroup status object and
    # get status number (master process id)
    $g0so = new SyncNum($g0name);
    $g0id = $g0so->get();
    # autoselect role
    $role || $role = $g0id ? 2 : 1;
    # create base instance
    return match ($role) {
      1 => new Process_Master(
        $hand,$autonomy, $g0name,$g0so,$g0id
      ),
      2 => new Process_Slave(
        $hand,$autonomy, $g0name,$g0so,$g0id
      ),
      3 => new Process_Slamaster(
        $hand,$autonomy, $g0name,$g0so,$g0id, $g1name
      ),
    };
  }
  # }}}
  function deconstructF1(object $r): void # {{{
  {
    if ($this->dispatcher)
    {
      $this->dispatcher->cancel();
      $this->status->tryReset();
      $this->dispatcher = $this->status = null;
      $r->promiseNoDelay();
    }
    else {
      $r->promiseCancel();
    }
  }
  # }}}
  function newStatus(string $pid): object # {{{
  {
    return new SyncNum($this->g0name.'-'.$pid);
  }
  # }}}
  function newEventChan(string $pid): object # {{{
  {
    return ErrorEx::peep(SyncAggregate::new([
      'id'    => $this->g0name.'-'.$pid.'-evt',
      'size'  => 1000
    ]));
  }
  # }}}
  function newCmdChan(string $pid): object # {{{
  {
    return SyncExchange::new([
      'id'    => $this->g0name.'-'.$pid.'-cmd',
      'size'  => 500
    ]);
  }
  # }}}
  abstract function init(): void;
  abstract function deconstruct(): object;
}
# }}}
trait Process_MasterTrait # {{{
{
  # props {{{
  public array
    $event=[],$spawn=[],
    $spawnWard=[];
  public int
    $eventCount=0,$spawnCount=0,
    $spawnWardCount=0;
  public ?object
    $spawnChecker=null,$eventReader=null;
  ###
  # }}}
  # components {{{
  function eventReader(bool $set=true): self # {{{
  {
    $o = &$this->eventReader;
    if ($set && !$o)
    {
      $o = Loop::attach(
        $this->newEventChan(Fx::$PROCESS_ID)
        ->read()
        ->okay($this->eventReaderFn(...))
      );
    }
    elseif (!$set && $o)
    {
      $o->cancel();
      $o = null;
    }
    return $this;
  }
  function eventReaderFn(object $r): ?object
  {
    # handle events
    $n = 0;
    foreach ($r->value as $s) {
      $n += $this->eventHandle(json_decode($s, true));
    }
    # activate dispatcher
    if ($n && !$this->spawnWardCount) {
      $this->dispatcher->wakeup();
    }
    # resume reading
    return $r->reset();
  }
  # }}}
  function spawnChecker(bool $set=true): self # {{{
  {
    $o = &$this->spawnChecker;
    if ($set && !$o)
    {
      $o = Loop::attach(Promise
        ::Func($this->spawnCheckerFn(...))
        ->halt()
      );
    }
    elseif (!$set && $o)
    {
      $o->cancel();
      $o = null;
    }
    return $this;
  }
  function spawnCheckerFn(object $r): ?object
  {
    foreach ($this->spawn as $o)
    {
      if ($rx = $o->check())
      {
        # non-empty check is a result of termination,
        # remove the spawn and generate a stop event
        unset($this->spawn[$o->pid]);
        $this->spawnCount--;
        $this->eventAdd(['stop', $o->pid, $rx]);
      }
    }
    return $r->promiseIdle();
  }
  # }}}
  # }}}
  # hlp {{{
  function eventHandle(array $a): int # {{{
  {
    $pid = $a[1];
    switch ($a[0]) {
    case 'start':
      # startup?
      if (isset($this->spawnWard[$pid]))
      {
        $this->spawnWard[$pid]->time = $a[2];
        break;
      }
      # ignore issues
      if ($a[2] !== 0) {
        break;
      }
      # offload attachment
      Loop::attach($this->startAttach($pid));
      break;
    default:
      # accumulate events
      if (!isset($this->spawn[$pid])) {
        break;
      }
      $this->event[] = $a;
      $this->eventCount++;
      return 1;
    }
    return 0;
  }
  # }}}
  function eventAdd(array $a): void # {{{
  {
    $n = $this->eventCount;
    $this->event[$n]  = $a;
    $this->eventCount = $n + 1;
    $this->spawnWardCount ||
    $this->dispatcher->wakeup();
  }
  # }}}
  function spawnCreate(# {{{
    string $file, int $count, array $cfg
  ):object
  {
    # extend configuration with defaults
    if (!isset($cfg['output'])) {
      $cfg['output'] = true;
    }
    # construct one
    if ($count < 2)
    {
      return new Promise(new Process_Spawn(
        $this, $file, $cfg
      ));
    }
    # construct many
    for ($a=[],$i=0; $i < $count; ++$i)
    {
      $a[$i] = new Promise(new Process_Spawn(
        $this, $file, $cfg
      ));
    }
    return Promise::Row($a, 1);
  }
  # }}}
  function spawnWardAdd(object $spawn): void # {{{
  {
    $this->spawnWard[$spawn->pid] = $spawn;
    $this->spawnWardCount++;
  }
  # }}}
  function spawnWardRem(string $pid): void # {{{
  {
    if (isset($this->spawnWard[$pid]))
    {
      unset($this->spawnWard[$pid]);
      if (--$this->spawnWardCount === 0 &&
          $this->event)
      {
        $this->dispatcher->wakeup();
      }
    }
  }
  # }}}
  function spawnAttach(object $spawn): void # {{{
  {
    $pid = $spawn->pid;
    $this->spawn[$pid] = $spawn;
    $this->spawnCount++;
    $this->spawnWardRem($pid);
  }
  # }}}
  function spawnDetach(string $pid): object # {{{
  {
    $spawn = $this->spawn[$pid];
    unset($this->spawn[$pid]);
    $this->spawnCount--;
    $this->spawnWard[$pid] = $spawn;
    $this->spawnWardCount++;
    return $spawn;
  }
  # }}}
  function spawnGetPids(): array # {{{
  {
    $pids = [];
    foreach ($this->spawn as $o) {
      $pids[] = $o->pid;
    }
    return $pids;
  }
  # }}}
  function handlerSet(object $f): void # {{{
  {
    $this->handler = $f;
  }
  # }}}
  # }}}
  function dispatch(): void # {{{
  {
    ($this->handler)($this->event);
    $this->event = [];
    $this->eventCount = 0;
  }
  # }}}
  function start(string $file, array $cfg): object # {{{
  {
    return $this
    ->spawnCreate($file, 1, $cfg)
    ->then($this->startFn(...));
  }
  function startFn(object $r): void {
    $r->confirm(__CLASS__, 'start');
  }
  # }}}
  function startGroup(# {{{
    string $file, int $count, array $cfg
  ):object
  {
    return $this
    ->spawnCreate($file, $count, $cfg)
    ->then($this->startGroupFn(...));
  }
  function startGroupFn(object $r): ?object
  {
    # startup phase complete
    $r->confirm(__CLASS__, 'startGroup');
    # check successful
    if ($r->ok) {
      return null;
    }
    # check no tracks (nonthing started)
    if (!($tracks = $r->rowTracks())) {
      return null;
    }
    # when one of the spawns fails to start
    # all or nothing rule applies -
    # stop others that started
    $a = [];
    foreach ($tracks as $i => $t) {
      $t->ok && $a[] = $r->value[$i];
    }
    return $this->stopGroup($a);
  }
  # }}}
  function startAttach(string $pid): object # {{{
  {
    return $this
    ->spawnCreate('', 1, ['pid'=>$pid])
    ->then($this->startAttachFn(...));
  }
  function startAttachFn(object $r): void
  {
    $r->confirm(__CLASS__, 'attach');
    $this->eventAdd(['attach', $r->value, $r]);
  }
  # }}}
  function stop(string $pid): object # {{{
  {
    return Promise
    ::Func($this->stopF1(...), $pid)
    ->then($this->stopFn(...), $pid);
  }
  function stopF1(object $r, string $pid): ?object
  {
    # check exists
    if (!isset($this->spawn[$pid]))
    {
      $r->warn('process not found');
      return null;
    }
    # continue
    $r->promiseNoDelay();
    return $this->spawnDetach($pid)->stop();
  }
  function stopFn(object $r, string $pid): void
  {
    $this->spawnWardRem($pid);
    $r->confirm(__CLASS__, $pid, 'stop');
  }
  # }}}
  function stopGroup(array $pids): object # {{{
  {
    return Promise
    ::Func($this->stopGroupF1(...), $pids)
    ->then($this->stopGroupFn(...), $pids);
  }
  function stopAll(): object {
    return $this->stopGroup($this->spawnGetPids());
  }
  function stopGroupF1(object $r, array $pids): ?object
  {
    # immediate pass
    $r->promiseNoDelay();
    # construct individual stops
    $a = [];
    foreach ($pids as $pid)
    {
      isset($this->spawn[$pid]) &&
      $a[] = $this->stop($pid);
    }
    # check there is nothing
    if (!$a)
    {
      $r->warn('no processes to stop');
      return null;
    }
    # proceed in the row
    return Promise::Row($a);
  }
  function stopGroupFn(object $r, array $pids): void
  {
    foreach ($pids as $pid) {
      $this->spawnWardRem($pid);
    }
    $r->confirm(__CLASS__, 'stopGroup');
  }
  # }}}
  function deconstruct(): object # {{{
  {
    return Promise
    ::Func($this->deconstructF1(...))
    ->then($this->deconstructF2(...))
    ->then($this->deconstructFn(...));
  }
  function deconstructF2(object $r): ?object
  {
    # stop components
    $this
      ->spawnChecker(false)
      ->eventReader(false);
    # stop slaves
    $r->promiseNoDelay();
    return $this->spawnCount
      ? $this->stopAll()
      : null;
  }
  function deconstructFn(object $r): void
  {
    # leave group
    $this->g0so->tryReset();
    $this->g0so = null;
  }
  # }}}
}
# }}}
trait Process_SlaveTrait # {{{
{
  # props {{{
  public bool
    $buffering=false;
  public ?object
    $groupChecker=null,
    $eventWriter=null,$eventQueue=null;
  ###
  # }}}
  # components {{{
  function eventQueue(bool $set=true): self # {{{
  {
    $o = &$this->eventQueue;
    if ($set && !$o)
    {
      $o = Loop::Queue();
      $this->eventWriter = $this->newEventChan(
        (string)$this->g0id
      );
    }
    elseif (!$set && $o)
    {
      $o->cancel();
      $o = $this->eventWriter = null;
    }
    return $this;
  }
  # }}}
  function buffering(bool $set=true): self # {{{
  {
    $o = &$this->buffering;
    if ($set && !$o)
    {
      # select and create output handler
      $f = $this->config['output']
        ? $this->output(...)
        : self::output_zap(...);
      # activate output buffering
      $o = !!ob_start($f, 1);
    }
    elseif (!$set && $o)
    {
      $o = false;
      ob_end_flush();
    }
    return $this;
  }
  # }}}
  function groupChecker(bool $set=true): self # {{{
  {
    $o = &$this->groupChecker;
    if ($set && !$o)
    {
      $o = Loop::attach(Promise
        ::Func($this->groupCheckerFn(...))
      );
    }
    elseif (!$set && $o)
    {
      $o->cancel();
      $o = null;
    }
    return $this;
  }
  function groupCheckerFn(object $r): ?object
  {
    # check group is still unmanaged
    if (!($id = $this->g0so->get())) {
      return $r->promiseIdle();
    }
    # complete and offload attachment
    $this->g0id = $id;
    return $this->attach();
  }
  # }}}
  # }}}
  function attach(bool $init=false): void # {{{
  {
    Loop::attach(Promise::Func($init
      ? $this->attachF1(...)
      : $this->attachF2(...)
    ));
  }
  function attachF1(object $r): ?object
  {
    if (!$this->handler)
    {
      $r->fail('process handler is not installed');
      return $this->deconstruct();
    }
    if (!$this->g0id)
    {
      $this->groupChecker();
      return null;
    }
    $r->promiseNoDelay();
    return $this->attachF2(...);
  }
  function attachF2(object $r): ?object
  {
    # TODO: determine timeout from status
    $timeout = 500;
    $pid = Fx::$PROCESS_ID;
    $cmd = $this->newCmdChan($pid);
    if (ErrorEx::is($cmd))
    {
      $r->error($cmd);
      return $this->deconstruct();
    }
    return $this
    ->eventQueue()->eventWriter
    ->write(json_encode(['start', $pid]))
    ->thenRow([
      $cmd->server()->okay($this->attachF3(...)),
      Promise::Delay($timeout)
    ], 1, 1)
    ->okay($this->attachF4(...), $timeout)
    ->okay($cmd->server()->okay($this->serve(...)))
    ->then($this->detach(...));
  }
  function attachF3(object $r): ?object
  {
    # set configuration
    $this->config = json_decode($r->value[0], true);
    return $r->hangup();
  }
  function attachF4(object $r, int $t): void
  {
    if ($r->index) {
      $r->fail('timeout ('.$t.'ms)');
    }
    else {# activate output buffering
      $this->buffering();
    }
    $r->promiseNoDelay();
  }
  # }}}
  function detach(object $r): ?object # {{{
  {
    if (!$r->ok || !$this->autonomy) {
      return $this->deconstruct();
    }
    $this->g0id = 0;
    $this->eventQueue(false);
    $this->groupChecker();
    return null;
  }
  # }}}
  function handlerSet(object $f): void # {{{
  {
    $firstTime = !$this->handler;
    $this->handler = $f;
    $firstTime && Loop::await(
      Promise::Func($this->handlerSetFn(...))
    );
  }
  function handlerSetFn(object $r): ?object
  {
    # wait relaxed until process recieves configuration
    return !$this->config
      ? $r->promiseIdle()
      : null;
  }
  # }}}
  function output(string $s): string # {{{
  {
    # check empty
    if ($s === '') {
      return '';
    }
    # create and encode the message
    $s = json_encode([
      'output', Fx::$PROCESS_ID, $s
    ]);
    # enqueue the event
    $this->eventQueue->push(
      $this->eventChan->write($s)
    );
    # output nothing
    return '';
  }
  static function output_zap(): string {
    return '';
  }
  # }}}
  function serve(object $r): ?object # {{{
  {
    if ($r->index === 0)
    {
      # command arrived
      if ($r->value === 'stop') {
        return $r->hangup();
      }
      # TODO: invoke handler
      return $r->write('ok');
    }
    # TODO: invoke handler
    return $r->reset();
  }
  # }}}
  function deconstruct(): object # {{{
  {
    return Promise
    ::Func($this->deconstructF1(...))
    ->then($this->deconstructF3(...))
    ->then($this->deconstructFn(...));
  }
  function deconstructF3(object $r): ?object
  {
    # stop components
    $this
      ->groupChecker(false)
      ->buffering(false)
      ->eventQueue(false);
    ###
    return $r->promiseNoDelay();
  }
  function deconstructFn(object $r): void
  {
    # dump error
    if (!$r->ok) {echo ErrorLog::render($r);}
    # terminate
    exit(0);
  }
  # }}}
}
# }}}
class Process_Master extends Process_Base # {{{
{
  use Process_MasterTrait;
  function init(): void
  {
    if ($this->g0id)
    {
      throw ErrorEx::fail(
        __CLASS__, $this->g0name,
        'master role is already occupied'.
        ' by process id='.$this->g0id
      );
    }
    $this
      ->eventReader()
      ->spawnChecker()
      ->g0so->set((int)Fx::$PROCESS_ID);
    ###
  }
}
# }}}
class Process_Slave extends Process_Base # {{{
{
  use Process_SlaveTrait;
  public bool $isMaster=false;
  function init(): void
  {
    if (!$this->autonomy && !$this->g0id)
    {
      throw ErrorEx::fail(
        __CLASS__, $this->g0name,
        'master is not running'
      );
    }
    $this->attach();
  }
}
# }}}
class Process_Slamaster extends Process_Slave # {{{
{
  use Process_MasterTrait;
  public ?object $g1so;
  function init(): void # {{{
  {
    if (!$this->autonomy && !$this->g0id)
    {
      throw ErrorEx::fail(
        __CLASS__, $this->g0name,
        'master is not running'
      );
    }
    $g1so = new SyncNum($this->g1name);
    if ($g1so->get())
    {
      throw ErrorEx::fail(
        __CLASS__, $this->g1name,
        'master is already running'
      );
    }
    $g1so->set((int)Fx::$PROCESS_ID);
    $this->g1so = $g1so;
    ###
    $this
      ->eventReader()
      ->spawnChecker()
      ->attach();
    ###
  }
  # }}}
  function deconstruct(): object # {{{
  {
    return Promise
    ::Func($this->deconstructF1(...))
    ->then($this->deconstructF2(...))
    ->then($this->deconstructF3(...))
    ->then($this->deconstructFn(...));
  }
  function deconstructFn(object $r): void
  {
    # leave group
    $this->g1so->tryReset();
    $this->g1so = null;
    # invoke slave handler
    parent::deconstructFn($r);
  }
  # }}}
}
# }}}
### HELPERS
class Process_Spawn extends Reversible # {{{
{
  const # {{{
    WAIT_START  = 1000*1000000,# ms ~ ns
    CHECK_INTVL = 2000*1000000,# ms ~ ns
    WAIT_STOP   = 3000*1000000,# ms ~ ns
    DESC = [
      #0 => ['pipe','r'],# stdin
      1 => ['pipe','w'],# stdout
      2 => ['pipe','w'],# stderr
    ],
    OPTS = [# options (windows)
      'suppress_errors' => false,
      'bypass_shell'    => true,
      'blocking_pipes'  => true,
      'create_process_group' => false,
      'create_new_console'   => false,
    ];
  ###
  # }}}
  # basis {{{
  public $proc=null;
  public int     $handle=0,$stage=1,$time=PHP_INT_MAX;
  public string  $pid;
  public ?object $status=null,$chan=null;
  public ?array  $pipe=null;
  public bool    $isRunning=true;
  function __construct(
    public ?object $base,
    public string  $file,
    public ?array  $config
  ) {}
  # }}}
  # {} Reversible {{{
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      1 => $this->_1_enter(),
      2 => $this->_2_start(),
      3 => $this->_3_init(),
      4 => $this->_4_activate(),
      5 => $this->_5_configure(),
      6 => $this->_6_finish(),
      default => true
    };
  }
  # }}}
  function _1_enter(): bool # {{{
  {
    # prevent start after base deconstruction or
    # before dispatcher became operational
    if (!$this->base->dispatcher)
    {
      $this->result->fail(
        "unable to spawn new process\n".
        "deconstruction in progress"
      );
      return $this->_undo();
    }
    # check attachment
    if ($this->file === '') {
      return $this->_1_attach();
    }
    # go and start new process
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _1_attach(): bool # {{{
  {
    # take identifier from configuration
    $this->pid = $this->config['pid'];
    if (PHP_OS_FAMILY === 'Windows')
    {
      # checking and termination of attached process
      # requires process handle to be fetched
      $handle = Sys::open_process((int)$this->pid);
      if ($handle === 0)
      {
        $err = Sys::last_error();
        $this->result->fail('OpenProcess',
          'ERROR='.$err[0], $err[1]
        );
        return $this->_cleanup();
      }
      $this->handle = $handle;
    }
    # jump to initialization
    $this->stage = 3;
    return $this->_complete();
  }
  # }}}
  function _2_start(): bool # {{{
  {
    # start new PHP process
    if (PHP_OS_FAMILY === 'Windows')
    {
      # execute
      $cmd  = '"'.PHP_BINARY.'" -f "'.$this->file.'"';
      $pipe = null;
      $proc = proc_open(
        $cmd, self::DESC, $pipe,
        null, null, self::OPTS
      );
      # check
      if ($proc === false)
      {
        $this->result->fail('proc_open', $cmd);
        return $this->_cleanup();
      }
      # set
      $i = proc_get_status($proc)['pid'];
      $this->proc = $proc;
      $this->pipe = $pipe;
    }
    else
    {
      /*** IDIOMATIC VERSION ***
      # divide
      if (($i = pcntl_fork()) === 0)
      {
        # child
        pcntl_exec(PHP_BINARY, ['-f', $this->file]);
        exit(0);
      }
      # check
      if ($i === -1)
      {
        $this->result->fail('pcntl_fork');
        return $this->_cleanup();
      }
      /*** FASTER VERSION (vfork) ***/
      $i = Sys::posix_spawn($this->file);
      if ($i === 0)
      {
        $this->result->fail('posix_spawn',
          'ERROR='.Sys::$ERRNO
        );
        return $this->_cleanup();
      }
      /***/
    }
    # set identifier and move to the next stage
    $this->pid = (string)$i;
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _3_init(): bool # {{{
  {
    # create communication channel
    $pid  = $this->pid;
    $chan = $this->base->newCmdChan($pid);
    if (ErrorEx::is($chan))
    {
      $this->result->error($chan);
      return $this->_terminate()->_cleanup();
    }
    # initialize and move to the next stage
    $this->status = $this->base->newStatus($pid);
    $this->chan   = $chan;
    if ($this->file === '')
    {
      $this->time = 0;
      $this->stage++;
      return $this->_complete();
    }
    $this->base->spawnWardAdd($this);
    $this->time = self::$HRTIME + self::WAIT_START;
    $this->stage++;
    return false;
  }
  # }}}
  function _4_activate(): bool # {{{
  {
    # spawn must send startup result
    # that is read and set by event reader
    ###
    # check diagnosis (code)
    switch ($this->time) {
    case 0:# REVIVED!
      $this->base->spawnAttach($this);
      $this->result
        ->promiseReverse($this)
        ->promisePrepend($this->_configure());
      ###
      $this->stage++;
      return false;
    case 1:# ERROR: handler issue
      $this->result->fail(
        "process handler is not installed\n".
        "slave process must set its handler early"
      );
      return $this->_undo();
    }
    # check expired
    if ($this->time < self::$HRTIME)
    {
      $this->result->fail(
        "activation timeout (".
        (int)(self::WAIT_START / 1000000).
        "ms)"
      );
      return $this->_undo();
    }
    # active waiting
    $this->result->promiseDelay(1);
    return false;
  }
  # }}}
  function _5_configure(): bool # {{{
  {
    if ($this->result->ok)
    {
      $this->stage++;
      return $this->_complete();
    }
    return $this->_undo();
  }
  # }}}
  function _6_finish(): bool # {{{
  {
    $this->result->value = $this->pid;
    $this->time = self::$HRTIME;
    $this->base = null;
    return true;
  }
  # }}}
  function _undo(): bool # {{{
  {
    switch ($this->stage) {
    case 5:
      $this->base->spawnDetach($this->pid);
    case 4:
      $this->base->spawnWardRem($this->pid);
    case 3:
      $this->isRunning() && $this->_terminate();
      $this->_pipeClose();
    case 2:
    case 1:
      $r = $this->result;
      $this->result->confirm(
        __CLASS__, 'stage='.$this->stage
      );
      $this->_cleanup();
    }
    return true;
  }
  # }}}
  # }}}
  # hlp {{{
  function _configure(): object # {{{
  {
    return $this
      ->chan->client()
      ->okay($this->_configureFn(...));
  }
  function _configureFn(object $r): ?object
  {
    # send configuration
    if ($r->index === 0)
    {
      return $r->write(
        json_encode($this->config), 300
      );
    }
    return $r->hangup();
  }
  # }}}
  function _pipeClose(): void # {{{
  {
    if ($this->pipe)
    {
      # pipes of a closed process will not block,
      # so it's safe to read remaining output,
      # otherwise reading will probably block
      # until process terminates as sm-process
      # is not supposed to output anything.
      foreach ($this->pipe as $i => $p)
      {
        if (!$p || !is_resource($p)) {
          continue;
        }
        if ($s = fread($p, 4000)) {
          $this->result->warn('pipe', $i, "\n".$s);
        }
        fclose($p);
      }
      $this->pipe = null;
    }
  }
  # }}}
  function _cleanup(): bool # {{{
  {
    if (PHP_OS_FAMILY === 'Windows')
    {
      if ($this->handle)
      {
        Sys::close_handle($this->handle);
        $this->handle = 0;
      }
    }
    $this->proc   = $this->status = $this->chan = null;
    $this->config = $this->result = $this->base = null;
    $this->stage  = 0;
    return true;
  }
  # }}}
  function _terminate(): self # {{{
  {
    if (PHP_OS_FAMILY === 'Windows')
    {
      if ($this->proc) {
        proc_terminate($this->proc);
      }
      else {
        Sys::terminate_process($this->handle);
      }
    }
    else
    {
      $i = 0;
      $n = (int)$this->pid;
      posix_kill($n, 9);
      pcntl_waitpid($n, $i);
    }
    $this->result->warn('TERMINATION',
      'may result in corrupted or inconsistent data'
    );
    return $this;
  }
  # }}}
  # }}}
  function isRunning(): bool # {{{
  {
    # check cache
    if (!$this->isRunning) {
      return false;
    }
    # check status
    if (PHP_OS_FAMILY === 'Windows')
    {
      if ($this->handle)
      {
        if (Sys::is_process_active($this->handle)) {
          return true;
        }
      }
      else
      {
        if (proc_get_status($this->proc)['running']) {
          return true;
        }
      }
    }
    else
    {
      $i = 0;
      $n = (int)$this->pid;
      if (!pcntl_waitpid($n, $i, \WNOHANG)) {
        return true;
      }
    }
    return $this->isRunning = false;
  }
  # }}}
  function check(): ?object # {{{
  {
    # select check variant,
    # check process itself,
    # othewise its status flag
    if ($this->time < self::$HRTIME)
    {
      if ($this->isRunning()) {
        return null;# fine
      }
      $this->result->fail('unauthorized termination');
    }
    elseif ($this->status->tryGet())
    {
      $this->time = self::$HRTIME + self::CHECK_INTVL;
      return null;# fine
    }
    elseif ($this->isRunning())
    {
      $this->result->fail('unauthorized deactivation');
      $this->_terminate();
    }
    else {
      $this->result->fail('unauthorized termination');
    }
    # cleanup
    $r = $this->result;
    $this->_pipeClose();
    $this->_cleanup();
    # complete
    return $r->confirm(
      __CLASS__, $this->pid, 'check'
    );
  }
  # }}}
  function stop(): object # {{{
  {
    return $this->chan
    ->client()
    ->okay(function(object $r): ?object {
      # send termination command
      if ($r->index === 0) {
        return $r->write('stop', 1000);
      }
      # set timeout and complete
      $this->time = self::$HRTIME + self::WAIT_STOP;
      return $r->hangup();
    })
    ->okay(function(object $r): ?object {
      # check deconstructed
      if (!$this->status->get() &&
          !$this->isRunning())
      {
        return null;
      }
      # check expired
      if ($this->time < self::$HRTIME)
      {
        $this->result = $r->warn(
          'timeout ('.
          (int)(self::WAIT_STOP / 1000000).
          'ms)'
        );
        $this->_terminate();
        return null;
      }
      # relaxed waiting
      return $r->promiseIdle();
    })
    ->then(function(object $r): void {
      # cleanup
      $this->result = $r;
      $this->_pipeClose();
      $this->_cleanup();
      # complete
      $r->value = $this->pid;
      $r->confirm(__CLASS__, $this->pid, 'stop');
    });
  }
  # }}}
}
# }}}
class Process_Dispatcher extends Completable # {{{
{
  function __construct(
    public ?object $base
  ) {}
  function _complete(): bool
  {
    # invoke handler
    $this->base->dispatch();
    $this->result->promiseHalt();
    return false;
  }
  function _cancel(): void
  {
    # offload deconstruction
    if ($this->base)
    {
      Loop::attach($this->base->deconstruct());
      $this->base = $this->result = null;
    }
  }
  function wakeup(): void {
    $this->result->promiseWakeup();
  }
  function cancel(): void {
    $this->result && $this->result->promiseCancel();
  }
}
# }}}
###
