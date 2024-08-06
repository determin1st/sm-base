<?php declare(strict_types=1);
# defs {{{
namespace SM;
use FFI,Throwable;
use function
  class_exists,function_exists,is_resource,
  json_encode,json_decode,fread,fclose,
  proc_open,proc_get_status,proc_terminate,
  pcntl_signal,pcntl_fork,pcntl_exec,pcntl_waitpid,
  posix_kill;
use const
  PHP_BINARY,PHP_INT_MAX,DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'sync.php';
# }}}
class Process # {{{
{
  # TODO: turn on output buffering
  # TODO: check all the requirements
  # initializer {{{
  static string  $ID='';
  static ?object $BASE=null,$API=null;
  private function __construct()
  {}
  static function init(string $id): ?object {
    return self::_init($id, 0, null);
  }
  static function init_master(string $id, ?object $hand=null): ?object {
    return self::_init($id, 1, $hand);
  }
  static function init_slave(string $id, ?object $hand=null): ?object {
    return self::_init($id, 2, $hand);
  }
  static function _init(
    string $id, int $type, ?object $hand
  ):?object
  {
    # check already initialized
    if (self::$BASE)
    {
      return ErrorEx::warn(__CLASS__,
        'already initialized'
      );
    }
    # check requirements
    if (!class_exists('SyncSharedMemory'))
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
      # ...
      $defs = <<<CDEF
char **environ;
int posix_spawn(
  int*,char*, void*,void*, char**,char**
);
CDEF;
      self::$API = FFI::cdef($defs, 'libc.so.6');
      # ...
      # handle SIGCHLD (child termination):
      # this signal is unreliable --
      # it is not triggered on my system;
      # there is point in wiring its handler
      # with the spawn's state;
      pcntl_signal(\SIGCHLD, \SIG_IGN);
    }
    # construct
    try
    {
      # set identifier before construction
      self::$ID = $id;
      # get the status of the master
      $o = new SyncNum($id);
      $i = $o->get();
      # construct base instance
      switch ($type) {
      case 0:# autodetect
        switch ($i) {
        case 0:# master
          $O = new Process_Master($hand, $o);
          break;
        case 1:# slave
          $o = self::new_status(Fx::$PROCESS_ID);
          $O = new Process_Slave($hand, $o);
          break;
        default:
          throw ErrorEx::fail(__CLASS__,
            'incorrect master status='.$i
          );
        }
        break;
      case 1:# master
        if ($i)
        {
          throw ErrorEx::fail(__CLASS__,
            'process master is already up and running'
          );
        }
        $O = new Process_Master($hand, $o);
        break;
      case 2:# slave
        if (!$i)
        {
          throw ErrorEx::fail(__CLASS__,
            'process master is not running'
          );
        }
        $o = self::new_status(Fx::$PROCESS_ID);
        $O = new Process_Slave($hand, $o);
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
    return new SyncNum(self::$ID.'-'.$pid);
  }
  static function new_exchange(string $pid): object
  {
    return SyncExchange::new([
      'id'    => self::$ID.'-'.$pid.'-chan',
      'size'  => 500
    ]);
  }
  static function new_aggregate(string $pid): object
  {
    return SyncAggregate::new([
      'id'    => self::$ID.'-'.$pid.'-evt',
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
  public ?object $dispatcher;
  public ?array  $config=null;
  function __construct(
    public ?object $handler,
    public ?object $status
  ) {
    $status->set(1);
    $this->dispatcher = Loop::gear(
      new Process_Dispatcher($this)
    );
  }
  # }}}
  function eventReader(): object # {{{
  {
    return ErrorEx::peep(
      Process::new_aggregate(Fx::$PROCESS_ID)
    )
    ->read()
    ->okay(function(object $r): ?object {
      # accumulate events
      foreach ($r->value as $s)
      {
        $a = json_decode($s, true);
        $s = $a[1];
        if (!isset($this->spawn[$s]))
        {
          # set startup code
          if (isset($this->spawnWard[$s])) {
            $this->spawnWard[$s]->time = $a[2];
          }
          continue;
        }
        $this->event[] = $a;
      }
      # wakeup
      $this->spawnWardCount ||
      $this->dispatcher->wakeup();
      # resume reading
      return $r->reset();
    });
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
      $n = 0;
      foreach ($this->spawn as $o)
      {
        if ($rx = $o->check())
        {
          unset($this->spawn[$o->pid]);
          $this->event[] = ['stop', $o->pid, $rx];
          $n++;
        }
      }
      # update and wakeup
      if ($n)
      {
        $this->spawnCount -= $n;
        $this->spawnWardCount ||
        $this->dispatcher->wakeup();
      }
      # take a nap
      return $r->promiseIdle();
    });
  }
  # }}}
  function spawnWard(string $pid, ?object $spawn): void # {{{
  {
    if ($spawn)
    {
      $this->spawnWard[$pid] = $spawn;
      $this->spawnWardCount++;
    }
    elseif (isset($this->spawnWard[$pid]))
    {
      unset($this->spawnWard[$pid]);
      $this->spawnWardCount--;
    }
  }
  # }}}
  function spawn(string $pid, bool $start): object # {{{
  {
    if ($start)
    {
      $spawn = $this->spawnWard[$pid];
      $this->spawn[$pid] = $spawn;
      $this->spawnCount++;
      unset($this->spawnWard[$pid]);
      $this->spawnWardCount--;
      if (!$this->spawnWardCount && $this->event) {
        $this->dispatcher->wakeup();
      }
    }
    else
    {
      # stopping, move back to the ward
      $spawn = $this->spawn[$pid];
      unset($this->spawn[$pid]);
      $this->spawnCount--;
      $this->spawnWard[$pid] = $spawn;
      $this->spawnWardCount++;
    }
    return $spawn;
  }
  # }}}
  function start(string $file, array $cfg): object # {{{
  {
    # check possible
    if (!($o = $this->dispatcher))
    {
      return Promise
      ::Error(ErrorEx::fail('no dispatcher'));
    }
    # initialize dispatcher
    $o->isReady || $o->init();
    # extend configuration
    $cfg['parent-pid'] = Fx::$PROCESS_ID;
    # construct launcher promise
    return Promise
    ::from(new Process_Spawn($this, $file, $cfg))
    ->then($this->startFn(...));
  }
  # }}}
  function startFn(object $r): void # {{{
  {
    $r->confirm(__CLASS__, 'start');
  }
  # }}}
  function dispatch(): void # {{{
  {
    ($this->handler)($this->event);
    $this->event = [];
  }
  # }}}
  function stop(string $pid): object # {{{
  {
    return Promise
    ::Func($this->stopF1(...), $pid)
    ->then($this->stopFn(...), $pid);
  }
  # }}}
  function stopF1(object $r, string $pid): ?object # {{{
  {
    # check exists
    if (!isset($this->spawn[$pid]))
    {
      $r->warn('process not found');
      return null;
    }
    # continue
    $r->promiseNoDelay();
    return $this->spawn($pid, false)->stop();
  }
  # }}}
  function stopFn(object $r, string $pid): void # {{{
  {
    $this->spawnWard($pid, null);
    $r->confirm(__CLASS__, $pid, 'stop');
  }
  # }}}
  function stopAll(): object # {{{
  {
    return Promise
    ::Func($this->stopAllF1(...))
    ->then($this->stopAllFn(...));
  }
  # }}}
  function stopAllF1(object $r): ?object # {{{
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
  # }}}
  function stopAllFn(object $r): void # {{{
  {
    $r->confirm(__CLASS__, 'stopAll');
  }
  # }}}
  function deconstruct(): object # {{{
  {
    return Promise
    ::Func(function(object $r): ?object {
      # check already been deconstructed
      if (!$this->dispatcher)
      {
        $r->promiseCancel();
        return null;
      }
      # finalize
      $this->dispatcher = $this->dispatcher->finit();
      $this->status->tryReset();
      $this->status = null;
      # stop slaves
      $r->promiseNoDelay();
      return $this->spawnCount
        ? $this->stopAll()
        : null;
    });
  }
  # }}}
}
# }}}
class Process_Slave extends Process_Master # {{{
{
  # basis {{{
  public bool    $isMaster=false;
  public ?object $eventWriter;
  function __construct(
    public ?object $handler,
    public ?object $status
  ) {
    # create command channel
    $pid  = Fx::$PROCESS_ID;
    $chan = ErrorEx::peep(Process::new_exchange($pid));
    # set configuration
    $this->config = $cfg = $this->configure(
      $chan, $status
    );
    # set event writer
    $this->eventWriter = $event = ErrorEx::peep(
      Process::new_aggregate($cfg['parent-pid'])
    );
    # set and offload dispatcher
    $this->dispatcher = Loop::gear(
      new Process_Dispatcher($this)
    );
    # prepare helpers
    $deconstruct = $this->deconstruct();
    $serveCommands = $chan
      ->server()
      ->okay($this->serve(...));
    ###
    $sayStarted = $event
      ->write(json_encode(['start', $pid, 0]));
    ###
    $sayHandlerProblem = $event
      ->write(json_encode(['start', $pid, 1]))
      ->then($deconstruct);
    # offload activation
    Loop::attach(
      Promise::Func(function(object $r): void {
        # handler must be set at the same tick
        $this->handler || $r->fail('no handler');
      })
      ->failFuse($sayHandlerProblem)
      ->then($sayStarted)
      ->okay($serveCommands)
      ->then($deconstruct)
    );
  }
  # }}}
  static function configure(# {{{
    object $chan, object $status
  ):array
  {
    # construct activator promise
    $p = Promise::Func(
    function(object $r) use ($status): ?object
    {
      return $status->get()
        ? null : $r->promiseDelay(3);
    })
    ->okay($chan->server())
    ->okay(function(object $r): ?object
    {
      return $r->index
        ? $r->hangup() : $r->write('ok');
    });
    # execute with a reasonable timeout
    $r = Loop::await_any(
      [$p, Promise::Delay(500)], true
    );
    # check the result
    if ($r->index)
    {
      throw ErrorEx::fail(
        __CLASS__, __FUNCTION__,
        'timed out (500ms)'
      );
    }
    if (!$r->ok) {
      throw ErrorEx::loggable($r);
    }
    # decode configuration and complete
    return json_decode($r->value, true);
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
    return parent
    ::deconstruct()
    ->then(function(object $r): void {
      # terminate
      if (!$r->ok) {echo ErrorLog::render($r);}
      exit();
    });
  }
  # }}}
}
# }}}
class Process_Spawn extends Reversible # {{{
{
  const # {{{
    WAIT = 1000*1000000,# ms ~ ns
    CHECK_INTVL = 3000*1000000,# ms ~ ns
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
  public int     $stage=1,$time=PHP_INT_MAX;
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
      1 => $this->_1_start(),
      2 => $this->_2_configure(),
      3 => $this->_3_activate(),
      4 => $this->_4_finish(),
      default => true
    };
  }
  # }}}
  function _1_start(): bool # {{{
  {
    # prevent start after base deconstruction or
    # before dispatcher is operational
    if (!$this->base->dispatcher)
    {
      $this->result->fail(
        "unable to spawn new process\n".
        "deconstruction in progress"
      );
      return $this->_undo();
    }
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
      $api = Process::$API;
      $_fileLen = 1+strlen($this->file);
      $_pathLen = 1+strlen(PHP_BINARY);
      $_pid  = $api->new('int', false);
      $_path = $api->new('char['.$_pathLen.']', false);
      $_argv = $api->new('char*[3]', false);
      $_argv[0] = $api->new('char[3]', false);
      $_argv[1] = $api->new('char['.$_fileLen.']', false);
      $_argv[2] = null;
      FFI::memcpy($_path, PHP_BINARY."\x00", $_pathLen);
      FFI::memcpy($_argv[0], "-f\x00", 3);
      FFI::memcpy($_argv[1], $this->file."\x00", $_fileLen);
      ###
      $n = $api->posix_spawn(
        FFI::addr($_pid), $_path,
        null, null, $_argv, $api->environ
      );
      $i = $_pid->cdata;
      ###
      FFI::free($_argv[1]); FFI::free($_argv[0]);
      FFI::free($_argv); FFI::free($_path);
      FFI::free($_pid);
      unset(
        $api, $_argv, $_path, $_pid,
        $_fileLen, $_pathLen
      );
      # check failed
      if ($i === 0)
      {
        $this->result->error('posix_spawn', $n);
        return $this->_cleanup();
      }
      /***/
    }
    # set identifier
    $this->pid = $pid = (string)$i;
    # create status object
    $status = Process::new_status($pid);
    if (ErrorEx::is($status))
    {
      $this->result->error($status);
      return $this->_terminate()->_cleanup();
    }
    # create communication channel
    $chan = Process::new_exchange($pid);
    if (ErrorEx::is($chan))
    {
      $this->result->error($chan);
      return $this->_terminate()->_cleanup();
    }
    # set status value
    if ($e = $status->trySet(1))
    {
      $this->result->error($e);
      return $this->_terminate()->_cleanup();
    }
    # move to the next stage
    $this->time   = self::$HRTIME + self::WAIT;
    $this->status = $status;
    $this->chan   = $chan;
    $this->result
      ->promiseReverse($this)
      ->promisePrepend($this->_configure());
    ###
    $this->stage++;
    $this->base->spawnWard($pid, $this);
    return false;
  }
  # }}}
  function _2_configure(): bool # {{{
  {
    if ($this->result->ok)
    {
      $this->stage++;
      Loop::yield_more();
      return $this->_complete();
    }
    return $this->_undo();
  }
  # }}}
  function _3_activate(): bool # {{{
  {
    # spawn must send startup event
    # that is read by event reader and
    # assigned to the time property.
    ###
    # check diagnosis (code)
    switch ($this->time) {
    case 0:# REVIVED!
      $this->base->spawn($this->pid, true);
      $this->stage++;
      return $this->_complete();
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
        "activation timed out (".
        (int)(self::WAIT / 1000000).
        "ms)"
      );
      return $this->_undo();
    }
    # repeat
    return false;
  }
  # }}}
  function _4_finish(): bool # {{{
  {
    Loop::yield_less();
    $this->result->value = $this->pid;
    $this->time = self::$HRTIME;
    $this->base = null;
    return true;
  }
  # }}}
  function _undo(): bool # {{{
  {
    switch ($this->stage) {
    case 3:
      Loop::yield_less();
    case 2:
      $this->isRunning() && $this->_terminate();
      $this->status->tryReset();
      $this->_pipeClose();
      $this->base->spawnWard($this->pid, null);
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
    $this->proc   = $this->status = $this->chan = null;
    $this->config = $this->result = $this->base = null;
    $this->stage  = 0;
    return true;
  }
  # }}}
  function _terminate(): self # {{{
  {
    if (PHP_OS_FAMILY === 'Windows') {
      proc_terminate($this->proc);
    }
    else
    {
      $i = 0;
      $n = (int)$this->pid;
      posix_kill($n, 9);
      pcntl_waitpid($n, $i);
    }
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
      if (proc_get_status($this->proc)['running']) {
        return true;
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
      $this->time = self::$HRTIME + self::WAIT;
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
        $r->fail('timed out');
        $this->_terminate();
        return null;
      }
      # wait
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
    static $N=0;
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
        $this->base->event[] = ['error', '', $r];
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
