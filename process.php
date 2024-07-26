<?php declare(strict_types=1);
# defs {{{
namespace SM;
use Throwable;
use function
  hrtime,fread,fclose,json_encode,json_decode,
  proc_open,proc_get_status,proc_terminate;
use const
  PHP_BINARY,PHP_INT_MAX,DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'sync.php';
# }}}
class Process # {{{
{
  # initializer {{{
  static ?object $BASE=null;
  private function __construct()
  {}
  static function init(): ?object
  {
    if (self::$BASE) {
      return null;
    }
    try
    {
      $status = ErrorEx::peep(SyncNum::new(
        'sm-process-'.Fx::$PROCESS_ID
      ));
      switch ($i = $status->get()) {
      case 0:
        $base = new Process_Master($status);
        break;
      case 1:
        $base = new Process_Slave($status);
        break;
      default:
        throw ErrorEx::fail(__CLASS__,
          'incorrect process status='.$i
        );
      }
      Loop::gear(new Process_Gear($base));
      self::$BASE = $base;
      return null;
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  # }}}
  # stasis {{{
  static function is_master(): bool {
    return self::$BASE->isMaster;
  }
  static function count(): int {
    return self::$BASE->spawnCount;
  }
  static function start(string $file, array $cfg=[]): object {
    return self::$BASE->start($file, $cfg);
  }
  static function stop(string $pid): object {
    return self::$BASE->stop($pid);
  }
  static function stop_all(): object {
    return self::$BASE->checkStopAll();
  }
  static function set_handler(object $f): void {
    self::$BASE->handlerSet($f);
  }
  # }}}
}
# }}}
class Process_Gear extends Completable # {{{
{
  function __construct(
    public ?object $base
  ) {}
  function _complete(): bool
  {
    if ($this->base->check())
    {
      $this->result->promiseIdle();
      return false;
    }
    $this->_cancel();
    return true;
  }
  function _cancel(): void
  {
    # offload base deactivation and cleanup
    Loop::row_attach($this->base->deactivate());
    $this->base = null;
  }
}
# }}}
class Process_Master # {{{
{
  # basis {{{
  public bool    $isMaster=true,$active=true;
  public ?object $handler=null;
  public array   $spawn=[];# pid => Process_Spawn
  public int     $spawnCount=0;
  function __construct(object $status)
  {
    # master doesnt need a status
  }
  # }}}
  # stasis {{{
  static function new_channel(string $pid): object # {{{
  {
    return SyncExchange::new([
      'id'    => 'sm-process-chan-'.$pid,
      'size'  => 1000,
      'boost' => true
    ]);
  }
  # }}}
  # }}}
  function spawnAttach(object $o): void # {{{
  {
    $this->spawn[$o->pid] = $o;
    $this->spawnCount++;
  }
  # }}}
  function spawnDetach(object $o): void # {{{
  {
    unset($this->spawn[$o->pid]);
    $this->spawnCount--;
  }
  # }}}
  function handlerSet(object $f): void # {{{
  {
    $this->handler = $f;
  }
  # }}}
  function check(): bool # {{{
  {
    if ($this->spawnCount)
    {
      foreach ($this->spawn as $o)
      {
        if (($r = $o->check()) &&
            ($f = $this->handler))
        {
          $f('stop', $r);
        }
      }
    }
    return true;
  }
  # }}}
  function checkStopAll(): object # {{{
  {
    if ($this->spawnCount) {
      return $this->stopAll();
    }
    return Promise::Func(function(object $r): void {
      $r->warn('no processes to stop');
      $r->confirm(__CLASS__);
    });
  }
  # }}}
  function start(string $file, array $cfg): object # {{{
  {
    return new Promise(new Process_Spawn(
      $this, $file, $cfg
    ));
  }
  # }}}
  function stop(string $pid): object # {{{
  {
  }
  # }}}
  function stopAll(): object # {{{
  {
    $a = [];
    foreach ($this->spawn as $o) {
      $a[] = $o->stop();
    }
    return Promise
    ::Row($a)
    ->then(function(object $r): void {
      $r->confirm(__CLASS__, 'stopAll');
    });
  }
  # }}}
  function deactivate(): object # {{{
  {
    return Promise
    ::Func(function(object $r): ?object {
      # check inactive
      if (!$this->active)
      {
        $r->promiseCancel();
        return null;
      }
      # clear flag and stop slaves
      $this->active = false;
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
  # TODO: notify master about handler
  # TODO: turn on output buffer
  # basis {{{
  public bool    $isMaster=false;
  public ?object $status,$server;
  public ?array  $config;
  function __construct(object $status)
  {
    # create communication channel
    $chan = ErrorEx::peep(
      self::new_channel(Fx::$PROCESS_ID)
    );
    # set status
    $status->set(2);
    $this->status = $status;
    # set configuration
    $r = Loop::await($chan
      ->server()
      ->okay($this->configSet(...))
    );
    if (!$r->ok)
    {
      $status->tryReset();
      throw ErrorEx::value($r);
    }
    # set and offload command handler
    Loop::row_attach($this->server = $chan
      ->server()
      ->okay($this->serve(...))
      ->then($this->deactivate(...))
    );
  }
  # }}}
  function handlerSet(object $f): void # {{{
  {
    $this->handler = $f;
  }
  # }}}
  function configSet(object $r): ?object # {{{
  {
    if ($r->index === 0)
    {
      $this->config = json_decode($r->value, true);
      return $r->write('ok');
    }
    return $r->hangup();
  }
  # }}}
  function serve(object $r): ?object # {{{
  {
    switch ($r->index) {
    case 0:# read the command
      return $r->read();
    case 1:# handle command
      if ($r->value === 'stop') {
        break;
      }
      # TODO: invoke handler
      return $r->write('ok');
    default:
      # TODO: invoke handler
      return $r->reset();
    }
    # complete
    return $r->hangup();
  }
  # }}}
  function deactivate(): object # {{{
  {
    return parent
    ::deactivate()
    ->then(function(): void {
      # reset status and cleanup
      $this->status->reset();
      $this->status = $this->server =
      $this->config = null;
      # terminate
      exit();
    });
  }
  # }}}
}
# }}}
class Process_Spawn extends Reversible # {{{
{
  # TODO: wait until handler established
  const # {{{
    WAIT = 3*1000000000,# ns
    DESC = [
      #0 => ['pipe','r'],# stdin
      1 => ['pipe','w'],# stdout
      2 => ['pipe','w'],# stderr
    ],
    OPTS = [# options
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
  public int     $stage=1,$started=PHP_INT_MAX;
  public ?object $status,$chan;
  public ?array  $pipe;
  public string  $pid;
  function __construct(
    public ?object $base,
    public string  $file,
    public ?array  $config
  ) {}
  # }}}
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      1 => $this->_start(),
      2 => $this->_activate(),
      3 => $this->_finish(),
      default => true
    };
  }
  # }}}
  function _undo(): bool # {{{
  {
    if (!$this->stage) {
      return true;
    }
    if ($this->stage > 1)
    {
      $this->isRunning() &&
      proc_terminate($this->proc);
      $this->status->tryReset();
      $this->_pipeClose();
    }
    return $this->_cleanup();
  }
  # }}}
  function _start(): bool # {{{
  {
    # start new PHP process
    $cmd  = '"'.PHP_BINARY.'" -f "'.$this->file.'"';
    $pipe = null;
    $proc = proc_open(
      $cmd, self::DESC, $pipe,
      null, null, self::OPTS
    );
    # check started
    if ($proc === false)
    {
      $this->result->fail('proc_open', $cmd);
      return $this->_cleanup();
    }
    # check running
    if (!($a = proc_get_status($proc))['running'])
    {
      $this->result->fail('process is not running');
      return $this->_cleanup();
    }
    # create status object
    $pid = (string)$a['pid'];
    $status = SyncNum::new('sm-process-'.$pid);
    if (ErrorEx::is($status))
    {
      $this->result->error($status);
      proc_terminate($proc);
      return $this->_cleanup();
    }
    # set status value
    if ($e = $status->trySet(1))
    {
      $this->result->error($e);
      proc_terminate($proc);
      return $this->_cleanup();
    }
    # store and move to the next stage
    $this->started = hrtime(true);
    $this->proc    = $proc;
    $this->status  = $status;
    $this->pipe    = $pipe;
    $this->pid     = $pid;
    $this->result->promiseReverse($this);
    $this->stage++;
    return false;
  }
  # }}}
  function _activate(): bool # {{{
  {
    # get status value
    $i = $this->status->tryGet(0, $e);
    if ($i < 0)
    {
      $this->result->error($e);
      return $this->_undo();
    }
    # check process became active
    if ($i === 2) {
      return $this->_configure();
    }
    # check timed out
    if ($this->started + self::WAIT < Loop::$HRTIME)
    {
      $this->result->fail('activation timed out');
      return $this->_undo();
    }
    # check still running
    if ($this->isRunning()) {
      return false;
    }
    # abolish further attempts
    $this->result->fail('process has quit');
    $this->status->tryReset();
    $this->_pipeClose();
    return $this->_cleanup();
  }
  # }}}
  function _configure(): bool # {{{
  {
    # create communication channel
    $chan = Process_Master::new_channel($this->pid);
    if (ErrorEx::is($chan))
    {
      $this->result->error($chan);
      return $this->_undo();
    }
    # construct configuration command
    $p = $chan
    ->client()
    ->okay(function(object $r): ?object {
      switch ($r->index) {
      case 0:# send configuration
        $s = json_encode($this->config);
        return $r->write($s);
      case 1:# get the response
        return $r->read();
      }
      # complete
      if ($r->value !== 'ok') {
        $r->fail('configure', $r->value);
      }
      return $r->hangup();
    });
    # expand promise and
    # move to the next stage
    $this->chan = $chan;
    $this->result->promisePrepend($p);
    $this->stage++;
    return false;
  }
  # }}}
  function _finish(): bool # {{{
  {
    # check failed
    if (!$this->result->ok) {
      return $this->_undo();
    }
    # successful launch,
    # attach to the base and set the result
    $this->base->spawnAttach($this);
    $this->result->value = $this->pid;
    # complete
    $this->stage++;
    return true;
  }
  # }}}
  function _pipeClose(bool $read=true): void # {{{
  {
    # pipes of a closed process will not block,
    # so it's safe to read remaining output,
    # otherwise reading will probably block
    # until process terminates as sm-process
    # is not supposed to output anything.
    foreach ($this->pipe as $i => $p)
    {
      if (!$p) {
        continue;
      }
      if ($read && ($s = fread($p, 4000)))
      {
        $this->result->warn(
          __CLASS__, $this->pid,
          "std:".$i."\n".$s
        );
      }
      fclose($p);
    }
    $this->pipe = null;
  }
  # }}}
  function _cleanup(): bool # {{{
  {
    switch ($this->stage) {
    case 4:# was successful
    case 3:# failed to configure
      $this->chan = null;
    case 2:# failed to activate
      $this->proc = $this->status = null;
    case 1:# failed to start
      $this->base = $this->config = null;
      break;
    }
    $this->result = null;
    $this->stage  = 0;
    return true;
  }
  # }}}
  function isRunning(): bool # {{{
  {
    return proc_get_status($this->proc)['running'];
  }
  # }}}
  function check(): ?object # {{{
  {
    # check active
    if ($this->status->tryGet())
    {
      # TODO: once in a while should check is actually running
      return null;
    }
    # terminate
    $this->base->spawnDetach($this);
    $this->isRunning() &&
    proc_terminate($this->proc);
    # get result object before cleanup
    $r = $this->result;
    # cleanup
    $this->_pipeClose();
    $this->_cleanup();
    # set the result
    return $r
      ->fail('unauthorized deactivation')
      ->confirm(__CLASS__, 'check');
  }
  # }}}
  function stop(int $wait=0): object # {{{
  {
    $wait = $wait
      ? (int)($wait * 1000000) # ms => ns
      : self::WAIT;
    ###
    return Promise
    ::Func(function(object $r): void {
      # first, to avoid collisions (object use),
      # detach it from the base
      $this->base->spawnDetach($this);
      $r->promiseNoDelay();
    })
    ->then($this->chan->client())
    ->okay(function(object $r) use ($wait): ?object {
      # send termination command
      if ($r->index === 0) {
        return $r->write('stop');
      }
      # set waiting period and complete
      $r->value = $r::$HRTIME + $wait;
      return $r->hangup();
    })
    ->okay(function(object $r): ?object {
      # check status cleared
      if (!$this->status->get()) {
        return null;
      }
      # check expired
      if ($r::$HRTIME > $r->value)
      {
        $r->fail('timed out');
        return null;
      }
      # wait relaxed
      return $r->promiseIdle();
    })
    ->then(function(object $r): void {
      # detach from the base and cleanup
      $this->_pipeClose(false);
      $this->_cleanup();
      # confirm operation
      $r->confirm(
        __CLASS__, $r->value = $this->pid, 'stop'
      );
    });
  }
  # }}}
}
# }}}
###
