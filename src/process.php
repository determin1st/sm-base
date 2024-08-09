<?php declare(strict_types=1);
# defs {{{
namespace SM;
use FFI,Throwable;
use function
  class_exists,function_exists,is_resource,
  json_encode,json_decode,fread,fclose,dechex,
  proc_open,proc_get_status,proc_terminate,
  pcntl_signal,pcntl_fork,pcntl_exec,pcntl_waitpid,
  posix_kill,ob_start,ob_end_flush;
use const
  PHP_BINARY,PHP_OS_FAMILY,PHP_INT_MAX,
  SIGCHLD,SIG_IGN,DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'sysapi.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'sync.php';
# }}}
class Process # {{{
{
  # TODO: (opt-out) output buffering in the slave
  # TODO: test/increase startup timeouts
  # TODO: resolve master vs slave-master conflict
  # TODO: start multiple processes
  # initializer {{{
  static string  $GID='';
  static ?object $BASE=null,$API=null;
  private function __construct()
  {}
  static function init(string $gid): ?object {
    return self::_init($gid, 0, null);
  }
  static function init_master(string $gid, ?object $fn=null): ?object {
    return self::_init($gid, 1, $fn);
  }
  static function init_slave(string $gid, ?object $fn=null): ?object {
    return self::_init($gid, 2, $fn);
  }
  static function _init(
    string $gid, int $type, ?object $fn
  ):?object
  {
    # check already initialized
    if (self::$BASE)
    {
      throw ErrorEx::fatal(
        __CLASS__, 'already initialized'
      );
    }
    # check requirements
    if (!class_exists('SyncSharedMemory', false))
    {
      return ErrorEx::fail(__CLASS__,
        'Sync extension is required'
      );
    }
    if (PHP_OS_FAMILY === 'Windows')
    {
    }
    else
    {
      # check few extensions
      if (!function_exists('pcntl_waitpid'))
      {
        return ErrorEx::fail(__CLASS__,
          'PCNTL extension is required'
        );
      }
      if (!function_exists('posix_kill'))
      {
        return ErrorEx::fail(__CLASS__,
          'POSIX extension is required'
        );
      }
      # handle SIGCHLD (child termination):
      # this signal is unreliable --
      # it is not triggered on my system;
      # there is point in wiring its handler
      # with the spawn's state;
      pcntl_signal(SIGCHLD, SIG_IGN);
    }
    # construct
    try
    {
      # set group identifier
      self::$GID = $gid;
      # create identifier instance
      $o = new SyncNum($gid);
      $i = $o->get();
      # create base instance
      switch ($type) {
      case 0:# autodetect
        if ($i)
        {
          $o = self::new_status(Fx::$PROCESS_ID);
          $O = new Process_Slave($fn, $o, $i);
        }
        else {
          $O = new Process_Master($fn, $o);
        }
        break;
      case 1:# master
        if ($i)
        {
          throw ErrorEx::fail(__CLASS__,
            'master is already running'
          );
        }
        $O = new Process_Master($fn, $o);
        break;
      case 2:# slave
        if (!$i)
        {
          throw ErrorEx::fail(__CLASS__,
            'master is not running'
          );
        }
        $o = self::new_status(Fx::$PROCESS_ID);
        $O = new Process_Slave($fn, $o, $i);
        break;
      }
      self::$BASE = $O;
      $e = null;
    }
    catch (Throwable $e) {
      $e = ErrorEx::from($e);
    }
    return $e;
  }
  # }}}
  # factory stasis {{{
  static function new_status(string $pid): object {
    return new SyncNum(self::$GID.'-'.$pid);
  }
  static function new_exchange(string $pid): object
  {
    return SyncExchange::new([
      'id'    => self::$GID.'-'.$pid.'-cmd',
      'size'  => 500
    ]);
  }
  static function new_aggregate(string $pid): object
  {
    return SyncAggregate::new([
      'id'    => self::$GID.'-'.$pid.'-evt',
      'size'  => 1000
    ]);
  }
  # }}}
  # api stasis {{{
  static function is_master(): bool {
    return self::$BASE->isMaster;
  }
  static function config(): ?array {
    return self::$BASE->config;
  }
  static function set_handler(object $f): void {
    self::$BASE->handler = $f;
  }
  static function start(string $file, array $cfg=[]): object {
    return self::$BASE->start($file, $cfg);
  }
  static function stop(string $pid): object {
    return self::$BASE->stop($pid);
  }
  static function stop_all(): object {
    return self::$BASE->stopAll();
  }
  static function count(): int {
    return self::$BASE->spawnCount;
  }
  static function list(): array
  {
    $a = [];
    foreach (self::$BASE->spawn as $o) {
      $a[] = $o->pid;
    }
    return $a;
  }
  # }}}
}
# }}}
class Process_Master # {{{
{
  # basis {{{
  const REVIVE_TIME=1000*1000000;# ms ~ ns
  public bool    $isMaster=true;
  public array   $spawnWard=[],$spawn=[],$event=[];
  public int     $spawnWardCount=0,$spawnCount=0;
  public int     $eventCount=0;
  public ?object $dispatcher;
  public ?array  $config=null;
  function __construct(
    public ?object $handler,
    public ?object $gid
  ) {
    $gid->set((int)Fx::$PROCESS_ID);
    $this->dispatcher = Loop::gear(
      new Process_Dispatcher($this)
    );
    $this->dispatcher->init();
  }
  # }}}
  # util {{{
  function eventReader(): object # {{{
  {
    return ErrorEx::peep(
      Process::new_aggregate(Fx::$PROCESS_ID)
    )
    ->read()
    ->okay(function(object $r): ?object {
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
    });
  }
  # }}}
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
      Loop::attach($this->attach($pid));
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
  function spawnChecker(): object # {{{
  {
    return Promise
    ::Func(function(object $r): ?object {
      # checkout current count
      if ($this->spawnCount === 0) {
        return $r->promiseIdle();
      }
      # generate stop events
      foreach ($this->spawn as $o)
      {
        if ($rx = $o->check())
        {
          unset($this->spawn[$o->pid]);
          $this->spawnCount--;
          $this->eventAdd(['stop', $o->pid, $rx]);
        }
      }
      # take a nap
      return $r->promiseIdle();
    });
  }
  # }}}
  function spawnCreate(string $file, array $cfg): object # {{{
  {
    # initialize dispatcher
    if (!$this->dispatcher)
    {
      return Promise
      ::Error(ErrorEx::fail('no dispatcher'));
    }
    $this->dispatcher->isReady ||
    $this->dispatcher->init();
    # extend configuration with defaults
    if (!isset($cfg['output'])) {
      $cfg['output'] = true;
    }
    # construct completable
    return new Process_Spawn($this, $file, $cfg);
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
  function dispatch(): void # {{{
  {
    ($this->handler)($this->event);
    $this->event = [];
  }
  # }}}
  # }}}
  # start/attach {{{
  function start(string $file, array $cfg): object
  {
    return Promise
    ::from($this->spawnCreate($file, $cfg))
    ->then($this->startFn(...));
  }
  function startFn(object $r): void {
    $r->confirm(__CLASS__, 'start');
  }
  function attach(string $pid): object
  {
    $cfg = ['pid' => $pid];
    return Promise
    ::from($this->spawnCreate('', $cfg))
    ->then($this->attachFn(...));
  }
  function attachFn(object $r): void
  {
    $r->confirm(__CLASS__, 'attach');
    $this->eventAdd(['attach', $r->value, $r]);
  }
  # }}}
  # stop {{{
  function stop(string $pid): object
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
  function stopAll(): object
  {
    return Promise
    ::Func($this->stopAllF1(...))
    ->then($this->stopAllFn(...));
  }
  function stopAllF1(object $r): ?object
  {
    # check
    if (!$this->spawnCount)
    {
      $r->warn('no processes to stop');
      return null;
    }
    # construct promises
    $a = [];
    foreach ($this->spawn as $o) {
      $a[] = $this->stop($o->pid);
    }
    # assemble the row
    return Promise::Row($a);
  }
  function stopAllFn(object $r): void {
    $r->confirm(__CLASS__, 'stopAll');
  }
  # }}}
  # deconstruct {{{
  function deconstruct(): object
  {
    return Promise
    ::Func($this->deconstructF1(...))
    ->then($this->deconstructFn(...));
  }
  function deconstructF1(object $r): ?object
  {
    # check already deconstructed
    if (!$this->dispatcher)
    {
      $r->promiseCancel();
      return null;
    }
    # stop dispatcher
    $this->dispatcher =
    $this->dispatcher->finit();
    # stop slaves
    $r->promiseNoDelay();
    return $this->spawnCount
      ? $this->stopAll()
      : null;
  }
  function deconstructFn(object $r): void
  {
    # clear group identifier
    $this->gid->tryReset();
    $this->gid = null;
  }
  # }}}
}
# }}}
class Process_Slave extends Process_Master # {{{
{
  # basis {{{
  public bool $isMaster=false,$buffering=false;
  public ?object $status,$eventChan,$eventQueue=null;
  function __construct(
    public ?object $handler,
    public ?object $gid,
    public int     $gidNum
  ) {
    # create and set own status
    $pid = Fx::$PROCESS_ID;
    $this->status = Process::new_status($pid);
    $this->status->set(1);
    # create event channel
    $this->eventChan = $event = ErrorEx::peep(
      Process::new_aggregate((string)$gidNum)
    );
    # create and offload dispatcher
    $this->dispatcher = Loop::gear(
      new Process_Dispatcher($this)
    );
    # offload activator
    $deconstruct = $this->deconstruct();
    Loop::attach(
      Promise::Func(function(object $r): void {
        # handler must be installed asap
        $this->handler || $r->fail('no handler');
      })
      ->failFuse($event
        ->write(json_encode(['start', $pid, 1]))
        ->then($deconstruct)
      )
      ->then($event
        ->write(json_encode(['start', $pid, 0]))
      )
      ->okay($this->activate(...))
      ->then($deconstruct)
    );
  }
  # }}}
  function activate(object $r): ?object # {{{
  {
    # create command channel
    $chan = Process::new_exchange(
      Fx::$PROCESS_ID
    );
    if (ErrorEx::is($chan))
    {
      $r->error($chan);
      return null;
    }
    # continue
    return Promise
    ::Row([
      # receive configuration
      $chan->server()
      ->okay(function(object $r): ?object
      {
        return $r->index
          ? $r->hangup()
          : $r->write('ok');
      }),
      # set the timeout
      Promise::Delay(500)
    ], 1, 1)
    ->okay(function(object $r): void
    {
      # check failed
      if (!$r->ok)
      {
        $r->fail('activation failed');
        return;
      }
      if ($r->index)
      {
        $r->fail('activation timed out (500ms)');
        return;
      }
      # set configuration
      $this->config = $cfg = json_decode(
        $r->value[0], true
      );
      # create event queue
      $this->eventQueue = Loop::queue();
      # select and create output handler
      $f = $cfg['output']
        ? $this->output(...)
        : (static function():string {return '';});
      # activate output buffering
      #$this->buffering = !!ob_start($f, 1);
    })
    ->okay(
      $chan->server()
      ->okay($this->serve(...))
    );
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
  function deconstructFn(object $r): void # {{{
  {
    # finalize
    if ($this->buffering)
    {
      $this->buffering = false;
      ob_end_flush();
    }
    $this->status->tryReset();
    $this->status = $this->eventChan = null;
    $this->eventQueue && $this->eventQueue->cancel();
    $this->eventQueue = null;
    # dump error
    if (!$r->ok) {echo ErrorLog::render($r);}
    # terminate
    exit(0);
  }
  # }}}
}
# }}}
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
    # move to the next stage
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
    $pid = $this->pid;
    $chan = Process::new_exchange($pid);
    if (ErrorEx::is($chan))
    {
      $this->result->error($chan);
      return $this->_terminate()->_cleanup();
    }
    # initialize and move to the next stage
    $this->status = Process::new_status($pid);
    $this->chan = $chan;
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
    return $this->chan
    ->client()
    ->okay(function(object $r): ?object
    {
      switch ($r->index) {
      case 0:# send configuration right away
        return $r->write(
          json_encode($this->config), 300
        );
      case 1:# read the response
        return $r->read();
      }
      # complete
      if ($r->value !== 'ok') {
        $r->fail('configure', $r->value);
      }
      return $r->hangup();
    });
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
    return $r->confirm(__CLASS__, 'check');
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
  # basis {{{
  public ?object $isReady=null;
  function __construct(
    public ?object $base
  ) {}
  # }}}
  function _complete(): bool # {{{
  {
    # TODO: refine
    if ($this->isReady)
    {
      $this->base->dispatch();
      $this->result->promiseHalt();
      return false;
    }
    if (!$this->base->spawnCount)
    {
      $this->result->promiseHalt();
      return false;
    }
    if ($N < 1)
    {
      $N++;
      $this->init();
      return false;
    }
    $this->base->dispatch();
    $this->_cancel();
    return true;
  }
  # }}}
  function _cancel(): void # {{{
  {
    # clear ready
    if ($this->isReady)
    {
      $this->isReady->cancel();
      $this->isReady = null;
    }
    # offload deconstruction
    Loop::attach($this->base->deconstruct());
    $this->base = $this->result = null;
  }
  # }}}
  function init(): void # {{{
  {
    # offload event reader and spawn checker
    Loop::attach(
      $this->isReady = Promise::Row([
        $this->base->eventReader(),
        $this->base->spawnChecker(),
      ], 1)
      ->then(function(object $r): void {
        # this handler executes only
        # upon failure in the worker
        $r->confirm(__CLASS__);
        # add error
        $this->base->eventAdd(['error', '', $r]);
        $this->result->promiseWakeup();
        $this->isReady = null;
      })
    );
  }
  # }}}
  function wakeup(): void # {{{
  {
    $this->result->promiseWakeup();
  }
  # }}}
  function finit(): void # {{{
  {
    $this->result && $this->result->promiseCancel();
  }
  # }}}
}
# }}}
###
