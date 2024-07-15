<?php declare(strict_types=1);
# defs {{{
namespace SM;
use
  SplDoublyLinkedList,SplObjectStorage,
  FFI,Closure,Error,Throwable,ArrayAccess;
use function
  is_array,count,array_unshift,array_pop,array_push,
  array_reverse,in_array,implode,end,key,prev,
  time,hrtime,time_nanosleep,class_exists,
  function_exists,register_shutdown_function,
  pcntl_signal_get_handler,pcntl_signal;
use const
  DIRECTORY_SEPARATOR,PHP_INT_MAX,PHP_OS_FAMILY;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'error.php';
# }}}
class Promise # {{{
{
  # TODO: rename glue to completables
  # TODO: then(<Promise>)?
  # TODO: row/column result is incorrectly stored/passed
  # TODO: cancellation on abolishment/gc, refs in the loop?
  # TODO: time stats?
  # basis {{{
  public ?array  $_reverse=null;
  public ?object $_context=null;
  public ?object $_done=null,$result=null;
  public int     $_time=PHP_INT_MIN,$pending=-1;
  public object  $_queue;
  ###
  function __construct(object $o)
  {
    $this->_queue = new SplDoublyLinkedList();
    $this->_queue->push($o);
  }
  function __destruct() {# TODO: references?
    $this->cancel();
  }
  # }}}
  # construction (stasis) {{{
  static function from(object|array|null $x): self # {{{
  {
    return is_array($x)
      ? self::Column($x)
      : (($x instanceof self)
        ? $x : new self(Completable::from($x)));
  }
  # }}}
  static function Value(...$v): self # {{{
  {
    $a = match (count($v))
    {
      0 => new PromiseValue(null),
      1 => new PromiseValue($v[0]),
      default => new PromiseValue($v)
    };
    return new self($a);
  }
  # }}}
  static function Func(object $f, ...$a): self # {{{
  {
    return new self($a
      ? new Completable_Fn($f, $a)
      : new Completable_Op($f)
    );
  }
  # }}}
  static function When(bool $ok, object $x): self # {{{
  {
    return new self($ok
      ? Completable_OkayThen::from($x)
      : Completable_FailThen::from($x)
    );
  }
  # }}}
  static function Delay(# {{{
    int $ms, object $x, ...$a
  ):self
  {
    return new self(new PromiseDelay($ms, $a
      ? new Completable_Fn($x, $a)
      : Completable::from($x)
    ));
  }
  # }}}
  static function Column(# {{{
    array $group, int $break=1
  ):self
  {
    return new self(
      new PromiseColumn($group, $break)
    );
  }
  # }}}
  static function Row(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    return new self(
      new PromiseRow($group, $break, $first)
    );
  }
  # }}}
  # }}}
  # composition {{{
  function whenDone(object $f): self # {{{
  {
    $this->_done = $f;
    return $this;
  }
  # }}}
  function then(object $x, ...$a): self # {{{
  {
    if ($a) {
      $this->_queueAppendOne(new Completable_Fn($x, $a));
    }
    elseif ($x instanceof self) {
      $this->_queueAppend($x);
    }
    else {
      $this->_queueAppendOne(Completable::from($x));
    }
    return $this;
  }
  # }}}
  function thenColumn(# {{{
    array $group, int $break=1
  ):self
  {
    return $this->_queueAppendOne(
      new PromiseColumn($group, $break)
    );
  }
  # }}}
  function thenRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    return $this->_queueAppendOne(
      new PromiseRow($group, $break, $first)
    );
  }
  # }}}
  # positive
  function okay(object $x, ...$a): self # {{{
  {
    return $this->_queueAppendOne($a
      ? new Completable_OkayFn($x, $a)
      : Completable_OkayThen::from($x)
    );
  }
  # }}}
  function okayColumn(# {{{
    array $group, int $break=1
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_OkayThen(
      new PromiseColumn($group, $break)
    ));
  }
  # }}}
  function okayRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_OkayThen(
      new PromiseRow($group, $break, $first)
    ));
  }
  # }}}
  function okayFuse(object $x): self # {{{
  {
    return $this->_queueAppendOne(
      new Completable_OkayThen(
      new PromiseFuse($x)
    ));
  }
  # }}}
  # negative
  function fail(object $x, ...$a): self # {{{
  {
    return $this->_queueAppendOne($a
      ? new Completable_FailFn($x, $a)
      : Completable_FailThen::from($x)
    );
  }
  # }}}
  function failColumn(# {{{
    array $group, int $break=0
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_FailThen(
      new PromiseColumn($group, $break)
    ));
  }
  # }}}
  function failRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_FailThen(
      new PromiseRow($group, $break, $first)
    ));
  }
  # }}}
  function failFuse(object $x): self # {{{
  {
    return $this->_queueAppendOne(
      new Completable_FailThen(
      new PromiseFuse($x)
    ));
  }
  # }}}
  # }}}
  # management {{{
  function _reverseAdd(object $action): void # {{{
  {
    if (!($q = &$this->_reverse)) {
      $q = [];
    }
    if ($action instanceof PromiseGroup)
    {
      $action->_reverse &&
      array_push($q, ...$action->_reverse);
    }
    elseif (!in_array($action, $q, true)) {
      $q[] = $action;
    }
  }
  # }}}
  function _queueGet(): object # {{{
  {
    if ($this->pending >= 0)
    {
      throw ErrorEx::fail(__CLASS__,
        'composition is only possible '.
        'with fresh promises'
      );
    }
    return $this->_queue;
  }
  # }}}
  function _queueAppend(object $promise): self # {{{
  {
    $q = $this->_queueGet();
    foreach ($queue as $action) {
      $q->push($action);
    }
    return $this;
  }
  # }}}
  function _queueAppendOne(object $comp): self # {{{
  {
    $this->_queueGet()->push($comp);
    return $this;
  }
  # }}}
  function _queuePrepend(object $promise): void # {{{
  {
    $q0 = $this->_queue;
    $q1 = $promise->_queue;
    $i  = $q1->count();
    $this->pending += $i;
    while (--$i >= 0) {
      $q0->unshift($q1->offsetGet($i));
    }
  }
  # }}}
  function _queuePrependOne(object $completable): void # {{{
  {
    $this->pending++;
    $this->_queue->unshift($completable);
  }
  # }}}
  function _queueInject(object $promise): void # {{{
  {
    $q0 = $this->_queue;
    $o0 = $q0->shift();
    $q1 = $promise->_queue;
    $i  = $q1->count();
    $this->pending += $i;
    while (--$i >= 0) {
      $q0->unshift($q1->offsetGet($i));
    }
    $q0->unshift($o0);
  }
  # }}}
  function _queueInjectOne(object $completable): void # {{{
  {
    $q0 = $this->_queue;
    $o0 = $q0->shift();
    $this->pending++;
    $q0->unshift($completable);
    $q0->unshift($o0);
  }
  # }}}
  function _queueTruncate(): void # {{{
  {
    $q = new SplDoublyLinkedList();
    $q->push($this->_queue->offsetGet(0));
    $this->_queue  = $q;
    $this->pending = 1;
  }
  # }}}
  # }}}
  function _init(?object $r=null): self # {{{
  {
    if ($this->pending < 0)
    {
      # prepare result object
      $r || $r = new PromiseResult();
      $r->promise = $this;
      # initialize self
      $this->pending = $this->_queue->count();
      $this->result  = $r;
    }
    return $this;
  }
  # }}}
  function _execute(): bool # {{{
  {
    # prepare
    $o = $this->_queue->offsetGet(0);
    $o->result || $o->result = $this->result;
    # execute completion routine
    if ($o->_complete())
    {
      # eject one
      $this->_queue->shift();
      if (--$this->pending)
      {
        # more completables pending,
        # check immediate execution
        return ($this->_time === 0)
          ? $this->_execute() # recurse now
          : true;# repeat later
      }
      # finish execution
      $this->_finit();
      return false;# not valid anymore
    }
    # repeat on the next tick
    return true;
  }
  # }}}
  function _finit(): void # {{{
  {
    if ($this->_context)
    {
      $this->_context->_done();
      $this->_context = null;
    }
    if ($this->_done)
    {
      ($this->_done)($this->result->_done());
      $this->_done = null;
    }
    else {
      $this->result->_done();
    }
  }
  # }}}
  function cancel(): ?object # {{{
  {
    # check did not start or finished
    if ($this->pending <= 0) {
      return null;
    }
    # get current action and
    # cancel it if it was initialized
    $o = $this->_queue->offsetGet(0);
    $o->result && $o->_cancel();
    # set cancelled
    $this->pending = 0;
    $r = $this->result->_cancel();
    # undo finished reversible actions
    if ($q = $this->_reverse)
    {
      $i = count($q);
      while (--$i >= 0)
      {
        $q[$i]->result = $r;
        $q[$i]->_undo();
      }
      $this->_reverse = null;
    }
    # finish
    $this->_finit();
    return $r;
  }
  # }}}
}
# }}}
class Loop # {{{
{
  const # {{{
    SIGNAL = [# nix termination signals
      1,# SIGHUP: terminal is closed
      2,# SIGINT: terminal interrupt (Ctrl+C)
      3,# SIGQUIT: terminal asks to quit
      4,# SIGILL: illegal instruction
      5,# SIGTRAP: trace/breakpoint trap
      6,# SIGABRT/SIGIOT: abort
      7,# SIGBUS: bus error
      8,# SIGFPE: arithmetic error
      #9,# SIGKILL: cant be handled!
      10,# SIGUSR1: user-defined
      11,# SIGSEGV: segmentation violation
      12,# SIGUSR1: user-defined
      13,# SIGPIPE: pipe error
      14,# SIGALRM: time limit
      15,# SIGTERM: termination request
      24,# SIGXCPU: CPU limit
      25,# SIGXFSZ: file size limit
      26,# SIGVTALRM: time limit
      27,# SIGPROF: profiling timer expired
      29,# SIGPOLL: pollable event
      31,# SIGSYS: bad system call
    ];
  ###
  # }}}
  # basis {{{
  public object $row,$sys;
  public array $column=[];
  public int $rowCnt=0,$colCnt=0,$added=0;
  public int $spinLevel=0,$spinYield=0;
  static int $TIME=0,$HRTIME=0;# current time
  private static ?self $LOOP=null;
  private function __construct()
  {
    # check the requirement
    if (!class_exists('FFI'))
    {
      throw ErrorEx::fail(__CLASS__,
        'FFI extension is required'
      );
    }
    # initialize
    self::$TIME   = time();
    self::$HRTIME = hrtime(true);
    $this->row    = new SplObjectStorage();
    if (PHP_OS_FAMILY === 'Windows')
    {
      # windows usleep/nanosleep implementation
      # in PHP relies on a waitable timer event
      # that prevents process entering
      # the generic alertable state and
      # only wastes time on that timer;
      $this->sys = FFI::cdef(
        'uint32_t SleepEx(uint32_t,uint32_t);',
        'kernel32.dll'
      );
    }
    else
    {
      $this->sys = FFI::cdef(
        #'struct timespec '.
        #"{uint64_t tv_sec,tv_nsec;}\n".
        #"int nanosleep".
        #"(struct timespec*, struct timespec*);",
        'int sched_yield();',
        'libc.so.6'
      );
      # the OS can axe the runtime without
      # it running shutdown handlers on certain
      # signals been set to default;
      # replace default handlers to avoid that
      if (function_exists('pcntl_signal'))
      {
        $def = \SIG_DFL;
        $fun = (function(int $n): void {
          static $DONE=false;
          if (!$DONE)
          {
            $DONE = true;
            exit(0x80|$n);
          }
        });
        foreach (self::SIGNAL as $n)
        {
          if (pcntl_signal_get_handler($n) === $def) {
            pcntl_signal($n, $fun);
          }
        }
      }
    }
    # enable graceful termination
    register_shutdown_function(
      $this->stop(...)
    );
  }
  static function _init(): void {
    self::$LOOP || self::$LOOP = new self();
  }
  # }}}
  # internals {{{
  function enter(): self # {{{
  {
    if ($this->spinLevel)
    {
      throw ErrorEx::fatal(
        "main loop is locked\n".
        "nested loops are not supported\n".
        "use promise chaining instead"
      );
    }
    $this->spinLevel++;
    return $this;
  }
  # }}}
  function attach(object $p, string $id=''): self # {{{
  {
    if ($id === '')
    {
      if (!$this->row->contains($p))
      {
        $this->row->attach($p->_init());
        $this->rowCnt++;
        $this->added++;
      }
    }
    elseif (isset($this->columns[$id]))
    {
      $q = $this->columns[$id];
      $q->push($p);
    }
    else
    {
      $q = new SplDoublyLinkedList();
      $q->push($p->_init());
      $this->columns[$id] = $q;
      $this->colCnt++;
      $this->added++;
    }
    return $this;
  }
  # }}}
  function spin(): int # {{{
  {
    # update timestamps
    if ($this->added)
    {
      $this->added = 0;
      self::$TIME = time();
    }
    self::$HRTIME = $t = hrtime(true);
    # prepare vars
    $idleStop = $t + 999999999;# ~1s
    $idleCnt  = $n0 = $n1 = $n2 = 0;
    # spin the row {{{
    if ($i = $this->rowCnt)
    {
      # its important to follow
      # the natural (straight) order of execution
      # because module gears that spin forever
      # are added before promises that depend on them
      $q = $this->row;
      $q->rewind();
    a1:# get the promise
      $p = $q->current();
      # check cancelled
      if ($p->pending === 0) {
        goto a2;
      }
      # check in the idle state
      if ($p->_time > $t) {
        goto a3;
      }
      # do the work
      if ($p->_execute()) {
        goto a4;
      }
    a2:# promise is done (completed/cancelled)
      $q->detach($p);
      $n1++;
      if (--$i) {
        goto a1;
      }
      goto a5;
    a3:# promise is idle
      $idleCnt++;
      if ($idleStop > $p->_time) {
        $idleStop = $p->_time;
      }
    a4:# proceed to the next item
      $n0++;
      if (--$i)
      {
        $q->next();
        goto a1;
      }
    a5:# done
    }
    # }}}
    # spin columns {{{
    if ($this->colCnt)
    {
      # columns could be executed in reverse order,
      # other stuff is similar to the row
      $a = &$this->columns;
      $q = end($a);
    b1:# get the promise
      $p = $q->offsetGet(0);
      # check cancelled
      if ($p->pending === 0) {
        goto b2;
      }
      # check in the idle state
      if ($p->_time > $t) {
        goto b3;
      }
      # do the work
      if ($p->_execute())
      {
        $n0++;
        goto b4;
      }
    b2:# promise is done
      $q->shift();
      if ($q->isEmpty()) {# remove exhausted column
        unset($a[key($a)]);
      }
      else {# initialize the next promise
        $q->offsetGet(0)->_init();
      }
      $n2++;
      goto b4;
    b3:# promise is idle
      $n0++;
      $idleCnt++;
      if ($idleStop > $p->_time) {
        $idleStop = $p->_time;
      }
    b4:# proceed to the next item
      if ($q = prev($a)) {
        goto b1;
      }
      # done
    }
    # }}}
    # update counters
    $n1 && $this->rowCnt -= $n1;
    $n2 && $this->colCnt -= $n2;
    # check nothing is pending/running
    if ($n0 === 0) {
      return $n1;
    }
    # when running and idle counts are equal,
    # there's no need in CPU consumption
    if ($n0 === $idleCnt)
    {
      # process must enter the sleeping state,
      # either IO resource isnt currently available,
      # or, there's nothing left to do.
      # this implementation does not sleep
      # on "magical" wait queues/channels -
      # the proper time estimation is delegated
      # to the "gears" - individual promise authors;
      ###
      # determine projected sleep time and
      # relinquish cpu
      $this->sleep($idleStop - $t);
    }
    elseif ($this->spinYield)
    {
      # the loop is in the yield mode -
      # its giving up CPU at every tick;
      # this behaviour might be useful when
      # there is another process or thread
      # doing the main job..
      ###
      $this->sleep(0);
    }
    return $n1;
  }
  # }}}
  function sleep(int $ns): void # {{{
  {
    if (PHP_OS_FAMILY === 'Windows')
    {
      $this->sys->SleepEx(
        (int)($ns / 1000000),# nano => milli
        1 # alertable
      );
    }
    else
    {
      # it's said that delays smaller than 2ms
      # are implemented as busy-waits, so
      # check and switch to another process
      if ($ns > 2000000) {
        time_nanosleep(0, $ns);
      }
      else {
        $this->sys->sched_yield();
      }
    }
  }
  # }}}
  function leave(): void # {{{
  {
    $this->spinLevel--;
  }
  # }}}
  function stop(): void # {{{
  {
    # stop columns
    if ($this->colCnt)
    {
      foreach ($this->columns as $id => $q) {
        $q->offsetGet(0)->cancel();
      }
      $this->columns = [];
      $this->colCnt = 0;
    }
    # stop the row
    if ($i = $this->rowCnt)
    {
      # the row promises should be cancelled
      # from the last to the first one,
      # this requires unloading into a list
      $a = [];
      $q = $this->row;
      $q->rewind();
      for ($j=0; $j < $i; ++$j)
      {
        $a[] = $q->current();
        $q->next();
      }
      while (--$i >= 0) {
        $a[$i]->cancel();
      }
      unset($a);
      $this->row = new SplObjectStorage();
      $this->rowCnt = 0;
    }
  }
  # }}}
  # }}}
  # stasis {{{
  # inner
  static function gear(object $o): object # {{{
  {
    # a gear is a completable that
    # is initialized with its carrier promise -
    # it enables cancellation without a single run;
    # gears are placed in the loop's row
    # prior to other/dependant promises;
    # they suppose to execute indefinitely and
    # are removed upon cancellation which
    # usually happens on shutdown.
    ###
    self::$LOOP->attach((new Promise($o))->_init(
      $o->result = new PromiseResult()
    ));
    return $o;
  }
  # }}}
  static function cooldown(int $ms=0): void # {{{
  {
    self::$LOOP->sleep(
      $ms ? (int)($ms * 1000000) : 0
    );
  }
  # }}}
  static function yield_more(): void # {{{
  {
    self::$LOOP->spinYield++;
  }
  # }}}
  static function yield_less(): void # {{{
  {
    self::$LOOP->spinYield--;
  }
  # }}}
  # outer
  static function await(object $p): object # {{{
  {
    $loop = self::$LOOP->enter()->attach($p);
  a1:
    if ($loop->spin() === 0) {
      goto a1;
    }
    if ($p->pending) {
      goto a1;
    }
    $loop->leave();
    return $p->result;
  }
  # }}}
  static function await_all(array $a): array # {{{
  {
    # add to the loop
    $loop = self::$LOOP->enter();
    for ($i=0,$j=count($a) - 1; $i <= $j; ++$i) {
      $loop->attach($a[$i]);
    }
  a1:
    # execute
    if ($loop->spin() === 0) {
      goto a1;
    }
  a2:
    # check the last one
    if ($a[$j]->pending) {
      goto a1;
    }
    # replace promise with its result
    $a[$j] = $a[$j]->result;
    # check more to complete
    if ($j > 0)
    {
      $j--;
      goto a2;
    }
  a3:
    # complete
    $loop->leave();
    return $a;
  }
  # }}}
  static function await_any(array $a, bool $cancel): ?object # {{{
  {
    # prepare
    $loop = self::$LOOP->enter();
    for ($i=0,$j=0,$k=count($a) - 1; $i <= $k; ++$i)
    {
      # for practical reasons,
      # some slots allowed to be void,
      # have to check it's a promise
      if ($p = $a[$i])
      {
        # the promise state may vary,
        # this is different from previous awaits,
        # check it and decide
        if ($p->pending < 0) {
          $loop->attach($p);
        }
        elseif ($p->pending === 0) {
          goto a2;# instant completion
        }
        $j++;
      }
    }
    # check there is nothing
    if ($j === 0)
    {
      $loop->leave();
      return null;
    }
  a1:
    # execute
    if (($kk = $loop->spin()) === 0) {
      goto a1;
    }
    # find first completed
    for ($i=0; $i <= $k; ++$i)
    {
      if ($a[$i] && $a[$i]->pending === 0) {
        goto a2;
      }
    }
    goto a1;
  a2:
    # cancel the rest when necessary
    if ($cancel)
    {
      do
      {
        if ($k !== $i && $a[$k]) {
          $a[$k]->cancel();
        }
      }
      while ($k--);
    }
    # complete
    $r = $a[$i]->result;
    $r->index = $i;
    $loop->leave();
    return $r;
  }
  # }}}
  # }}}
}
# }}}
abstract class Completable # {{{
{
  public ?object $result=null;
  abstract function _complete(): bool;
  function _cancel(): void {}
  ###
  static function from(?object $x): object
  {
    return $x
      ? (($x instanceof self)
        ? $x
        : (($x instanceof Closure)
          ? new Completable_Op($x)
          : (($x instanceof Error)
            ? new PromiseError($x)
            : new PromiseValue($x))))
      : new PromiseNop();
  }
}
abstract class Contextable extends Completable
{
  function _cancel(): void {$this->_done();}
  function _reset(): void {}
  abstract function _done(): bool;
}
abstract class Reversible extends Completable {
  abstract function _undo(): void;
}
# }}}
# actions {{{
abstract class Completable_Action extends Completable
{
  abstract function invoke(object $r): ?object;
  function _complete(): bool
  {
    # user handler is guarded
    try
    {
      $r = $this->result;
      if ($o = $this->invoke($r))
      {
        # user wants to repeat
        if ($o === $r) {
          return false;
        }
        # user wants to swap
        $r->promiseInject($o);
      }
    }
    catch (Throwable $e) {
      $r->error($e);
    }
    return true;
  }
}
class Completable_Op extends Completable_Action
{
  function __construct(
    public object $func
  ) {}
  function invoke(object $r): ?object {
    return ($this->func)($r);
  }
}
class Completable_OkayOp extends Completable_Op
{
  function invoke(object $r): ?object
  {
    return $r->ok
      ? ($this->func)($r)
      : $r->promiseNoDelay();
  }
}
class Completable_FailOp extends Completable_Op
{
  function invoke(object $r): ?object
  {
    return $r->ok
      ? $r->promiseNoDelay()
      : ($this->func)($r);
  }
}
class Completable_Fn extends Completable_Action
{
  function __construct(
    public object $func,
    public array  $args
  ) {}
  function invoke(object $r): ?object {
    return ($this->func)($r, ...$this->args);
  }
}
class Completable_OkayFn extends Completable_Fn
{
  function invoke(object $r): ?object
  {
    return $r->ok
      ? ($this->func)($r, ...$this->args)
      : $r->promiseNoDelay();
  }
}
class Completable_FailFn extends Completable_Fn
{
  function invoke(object $r): ?object
  {
    return $r->ok
      ? $r->promiseNoDelay()
      : ($this->func)($r, ...$this->args);
  }
}
# }}}
# composition glue {{{
class PromiseNop extends Completable # {{{
{
  function _complete(): bool {
    return true;
  }
}
# }}}
abstract class Completable_Then extends Completable # {{{
{
  function __construct(
    public object $what
  ) {}
}
class Completable_OkayThen extends Completable_Then
{
  function _complete(): bool
  {
    $r = $this->result;
    $r->ok && $r->promiseInject($this->what);
    $r->promiseNoDelay();
    return true;
  }
  static function from(?object $x): object
  {
    return ($x instanceof Closure)
      ? new Completable_OkayOp($x)
      : new self($x);
  }
}
class Completable_FailThen extends Completable_Then
{
  function _complete(): bool
  {
    $r = $this->result;
    $r->ok || $r->promiseInject($this->what);
    $r->promiseNoDelay();
    return true;
  }
  static function from(?object $x): object
  {
    return ($x instanceof Closure)
      ? new Completable_FailOp($x)
      : new self($x);
  }
}
# }}}
class PromiseError extends Completable # {{{
{
  function __construct(
    public object $error
  ) {}
  function _complete(): bool
  {
    $this->result
      ->error($this->error)
      ->promiseNoDelay();
    ###
    return true;
  }
}
# }}}
class PromiseValue extends Completable # {{{
{
  function __construct(
    public mixed $value
  ) {}
  function _complete(): bool
  {
    $this->result
      ->valueSet($this->value)
      ->promiseNoDelay();
    ###
    return true;
  }
}
# }}}
class PromiseFuse extends Completable # {{{
{
  function __construct(
    public object $fuse
  ) {}
  function _complete(): bool
  {
    $this->result->promiseFuse($this->fuse);
    return true;
  }
}
# }}}
class PromiseDelay extends Completable # {{{
{
  public int $stage=1;
  function __construct(
    public int     $delay,
    public ?object $todo
  ) {}
  function _complete(): bool
  {
    switch ($this->stage) {
    case 1:
      $this->stage = 2;
      $this->result->promiseDelay($this->delay);
      return false;
    case 2:
      $this->stage = 1;
      if ($o = $this->todo) {
        $this->result->promiseInject($o);
      }
      $this->result->promiseNoDelay();
      break;
    }
    return true;
  }
}
# }}}
# }}}
# groups {{{
abstract class PromiseGroup extends Reversible # {{{
{
  public ?array $_reverse=null;
  public int    $idx=-1,$cnt=0;
  function __construct(
    public array &$group,
    public int   $break
  ) {
    # groups operate on promises,
    # convert all items
    foreach ($group as &$p) {
      $p = Promise::from($p);
    }
    # set number of promises
    $this->cnt = $n = count($group);
    # set correct break number
    if ($break > $n) {
      $this->break = 0;# none
    }
    elseif ($break < 0) {
      $this->break = 1;# one
    }
  }
  function _reverseAdd(object $promise): void
  {
    if ($q = &$this->_reverse) {
      array_push($q, ...$promise->_reverse);
    }
    else {
      $q = $promise->_reverse;
    }
  }
  function _undo(): void
  {}
}
# }}}
class PromiseColumn extends PromiseGroup # {{{
{
  function _cancel(): void # {{{
  {
    # check started and not finished
    if (($i = &$this->idx) >= 0 &&
        ($j = $this->cnt) > $i)
    {
      # cancel the current one
      $this->group[$i]->cancel();
      $this->result->_columnEnd($i);
      # skip the rest
      $i = $j;
    }
  }
  # }}}
  function _complete(): bool # {{{
  {
    # prepare
    $q = &$this->group;
    $i = &$this->idx;
    # check finished
    if ($i >= ($j = $this->cnt)) {
      return true;
    }
    # initialize
    if ($i < 0)
    {
      $p = $q[$i = 0];
      $p->_init($this->result->_column($j));
    }
    elseif (!($p = $q[$i])->result) {
      $p->_init($q[$i - 1]->result);
    }
    # execute
    if ($p->_execute())
    {
      $this->result->promise->_time = $p->_time;
      return false;
    }
    # one complete,
    # collect reversible
    $p->_reverse && $this->_reverseAdd($p);
    # check all complete
    if (++$i >= $j)
    {
      $this->result->_columnEnd($i);
      return true;
    }
    # check breakable failed
    if ($this->break && !$p->result->ok)
    {
      if (--$this->break) {
        return $this;# more to break
      }
      $this->_cancel();
      return true;
    }
    return false;
  }
  # }}}
}
# }}}
class PromiseRow extends PromiseGroup # {{{
{
  function __construct(# {{{
    public array &$group,
    public int   $break,
    public int   $first
  ) {
    parent::__construct($group, $break);
    if ($first > $this->cnt) {
      $this->first = 0;# all
    }
    elseif ($first < 0) {
      $this->first = 1;# one
    }
  }
  # }}}
  function _cancel(): void # {{{
  {
    # check started and not finished
    if (($i = &$this->idx) >= 0 &&
        ($j = $this->cnt) > $i)
    {
      $i = $this->result->_rowCancel($this->group, $j);
    }
  }
  # }}}
  function _complete(): bool # {{{
  {
    # prepare
    $t = Loop::$HRTIME;
    $q = &$this->group;
    $i = &$this->idx;
    # check finished
    if ($i >= ($j = $this->cnt)) {
      return true;
    }
    # initialize
    if ($i < 0)
    {
      $r = $this->result;
      for ($k=0; $k < $j; ++$k) {
        $q[$k]->_init(new PromiseResult($r->store));
      }
      $i = $r->_row($q, $j);
    }
    # to enter idle state, the number of idle items and
    # the closest idle timeout must be determined
    $idleStop = Loop::$HRTIME + 999999999;# ~1s
    $idleCnt  = $j;
    # execute all
    for ($k=0; $k < $j; ++$k)
    {
      # checkout
      if (!($p = $q[$k])) {
        continue;
      }
      if ($p->_time > $t)
      {
        if ($idleStop > $p->_time) {
          $idleStop = $p->_time;
        }
        continue;
      }
      # execute
      if ($p->_execute())
      {
        $idleCnt--;
        continue;
      }
      # one complete
      $q[$k] = $this->result->_rowDone(
        $p->result, $k, ++$i
      );
      # collect reversibles
      $p->_reverse && $this->_reverseAdd($p);
      # check break condition
      if ($this->break && !$p->result->ok)
      {
        if (--$this->break) {
          continue;# more to break
        }
        $this->_cancel();
        return true;
      }
      # check race condition
      if ($this->first)
      {
        if (--$this->first) {
          continue;# more to come
        }
        $this->_cancel();
        return true;
      }
    }
    # check finished
    if ($i === $j) {
      return true;
    }
    # check idle
    if ($idleCnt === $j) {
      $this->result->promiseWakeup($idleStop);
    }
    return false;
  }
  # }}}
}
# }}}
# }}}
class PromiseResult # {{{
  implements ArrayAccess,Loggable
{
  # basis {{{
  const
    IS_INFO    = 0,
    IS_WARNING = 1,
    IS_FAILURE = 2,
    IS_ERROR   = 3,# ErrorEx object
    IS_COLUMN  = 4,# column group
    IS_ROW     = 5,# row group
    IS_FUSION  = 6,# all => fuse track
    IS_CANCELLATION = 9;# all => cancellation track
  ###
  public ?object $promise=null;
  public int     $time=PHP_INT_MAX,$index=0;
  public object  $track;
  public bool    $ok=true,$isCancelled=false;
  public array   $store;
  public mixed   $value;
  ###
  function __construct(?array $x=null)
  {
    $this->_init();
    $x && $this->value = $x;
  }
  function _init(): void
  {
    $this->time  = Loop::$TIME;
    $this->index = 0;
    $this->track = new PromiseResultTrack();
    $this->ok    = &$this->track->ok;
    $this->store = [null];
    $this->value = &$this->store[0];
  }
  # }}}
  # util {{{
  function __debugInfo(): array # {{{
  {
    return [
      'store' => $this->store,
      'track' => $this->track,
    ];
  }
  # }}}
  static function trace_info(array $t): array # {{{
  {
    foreach ($t as &$a)
    {
      $a = match ($a[0]) {
      self::IS_INFO
        => 'INFO: '.implode('·', $a[1]),
      self::IS_WARNING
        => 'WARNING: '.implode('·', $a[1]),
      self::IS_FAILURE
        => 'FAILURE: '.implode('·', $a[1]),
      self::IS_ERROR
        => 'ERROR: '.$a[1]->message(),
      self::IS_COLUMN
        => ['COLUMN: '.$a[2].'/'.$a[3], $a[1]],
      self::IS_ROW
        => ['ROW: '.$a[2].'/'.$a[3], $a[1]],
      self::IS_FUSION
        => ['FUSION', $a[1]],
      self::IS_CANCELLATION
        => ['CANCELLATION', $a[1]],
      default
        => '?',
      };
    }
    return $t;
  }
  # }}}
  function _track(): object # {{{
  {
    # check finished or doesnt have title yet
    if (!$this->promise || !$this->track->title) {
      return $this->track;# use current track
    }
    # create new track
    $t = new PromiseResultTrack($this->track);
    $this->track = $t;
    $this->ok = &$t->ok;
    return $t;
  }
  # }}}
  function _column(int $total): object # {{{
  {
    $r = new PromiseResult($this->store);
    $this->_track()->trace[] = [
      self::IS_COLUMN, $r, 0, $total, Loop::$HRTIME
    ];
    return $r;
  }
  # }}}
  function _columnEnd(int $done): void # {{{
  {
    $e = &$this->track->trace;
    $e = &$e[count($e) - 1];
    $r = $e[1];
    $e[1] = $r->track;
    $e[2] = $done;
    $e[4] = Loop::$HRTIME - $e[4];
    ###
    if (!$r->ok && $this->ok) {
      $this->ok = false;
    }
    array_pop($r->store);
    $this->valuePut($r->store);
  }
  # }}}
  function _row(array &$q, int $total): int # {{{
  {
    for ($v=[],$i=0; $i < $total; ++$i) {
      $v[$i] = &$q[$i]->result->store;
    }
    $this->_track()->trace[] = [
      self::IS_ROW, [], 0, $total, Loop::$HRTIME
    ];
    $this->valuePut($v);
    return 0;
  }
  # }}}
  function _rowDone(# {{{
    object $r, int $i, int $n
  ):void
  {
    # get row element
    $e = &$this->track->trace;
    $e = &$e[count($e) - 1];
    # store completed result's track
    $e[1][] = [$r->track, $i];
    $e[2]++;
    # determine total duration at last completion
    if ($n >= $e[3]) {
      $e[4] = Loop::$HRTIME - $e[4];
    }
    # change current state upon failure
    if (!$r->ok && $this->ok) {
      $this->ok = false;
    }
    # remove initial value from individual store
    array_pop($this->value[$i]);
  }
  # }}}
  function _rowCancel(array &$q, int $total): int # {{{
  {
    $e = &$this->track->trace;
    $e = &$e[count($e) - 1];
    for ($i=0; $i < $total; ++$i)
    {
      if ($p = $q[$i])
      {
        $e[1][] = [$p->cancel()->track, $i];
        array_pop($this->value[$i]);
      }
    }
    $e[4] = Loop::$HRTIME - $e[4];
    return $total;
  }
  # }}}
  function _cancel(): self # {{{
  {
    if (!($t0 = $this->track)->title) {
      $t0->span = Loop::$HRTIME - $t0->span;
    }
    $t1 = new PromiseResultTrack(null, false);
    $t1->trace[] = [self::IS_CANCELLATION, $t0];
    $this->track = $t1;
    $this->ok    = &$t1->ok;
    $this->isCancelled = true;
    return $this;
  }
  # }}}
  function _done(): self # {{{
  {
    $this->track->title || $this->confirm('{}');
    $this->promise = null;
    return $this;
  }
  # }}}
  # }}}
  # [] access {{{
  function offsetExists(mixed $k): bool {
    return $k >= 0 && $k < count($this->store);
  }
  function offsetGet(mixed $k): mixed
  {
    $n = count($this->store);
    if ($k < 0) {
      $k += $n;
    }
    return ($k >= 0 && $k < $n)
      ? $this->store[$k]
      : null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  # loggable {{{
  function log(): array # {{{
  {
    $t = $this->track;
    $a = self::trace_logs($t->trace);
    $t->prev && array_push(
      $a, ...self::track_logs($t->prev)
    );
    return [
      'level' => $t->ok ? 0 : 2,
      'msg'   => $t->title,
      'span'  => self::track_span($t),
      'time'  => $this->time,
      'logs'  => $a
    ];
  }
  # }}}
  static function track_level(object $t): int # {{{
  {
    if (!$t->ok) {
      return 2;
    }
    foreach ($t->trace as $e)
    {
      switch ($e[0]) {
      case self::IS_WARNING:
        return 1;
      case self::IS_ERROR:
        if ($e[1]->hasIssue()) {
          return 1;
        }
        break;
      case self::IS_COLUMN:
        if (self::track_level($e[1])) {
          return 1;
        }
        break;
      }
    }
    return 0;
  }
  # }}}
  static function track_span(object $t): int # {{{
  {
    $x = 0;
    do {
      $x += $t->span + self::trace_span($t->trace);
    }
    while ($t = $t->prev);
    return $x;
  }
  # }}}
  static function track_logs(object $t): array # {{{
  {
    $a = [];
    do
    {
      if ($t->trace)
      {
        $a[] = [
          'level' => self::track_level($t),
          'msg'   => $t->title,
          'span'  => $t->span,
          'logs'  => self::trace_logs($t->trace)
        ];
      }
      else
      {
        $a[] = [
          'level' => $t->ok ? 0 : 2,
          'msg'   => $t->title,
          'span'  => $t->span,
        ];
      }
    }
    while ($t = $t->prev);
    return $a;
  }
  # }}}
  static function trace_span(array &$t): int # {{{
  {
    $x = 0;
    $i = count($t);
    while (--$i >= 0)
    {
      switch ($t[$i][0]) {
      case self::IS_COLUMN:
      case self::IS_ROW:
        $x += $t[$i][4];
        break;
      case self::IS_FUSION:
      case self::IS_CANCELLATION:
        $x += self::track_span($t[$i][1]);
        break;
      }
    }
    return $x;
  }
  # }}}
  static function trace_logs(array &$trace): array # {{{
  {
    # trace should be iterated in reverse order
    $a = [];
    $i = count($trace);
    while (--$i >= 0)
    {
      switch (($t = $trace[$i])[0]) {
      case self::IS_INFO:
      case self::IS_WARNING:
      case self::IS_FAILURE:
        # the simpliest node form
        $a[] = [
          'level' => $t[0],
          'msg'   => $t[1],
        ];
        break;
      case self::IS_ERROR:
        # an error object is loggable
        $a[] = $t[1]->log();
        break;
      case self::IS_COLUMN:
        # a dependent group (a sequence)
        $a[] = [
          'level' => self::track_level($t[1]),
          'msg'   => ['COLUMN',$t[2].'/'.$t[3]],
          'span'  => self::track_span($t[1]),
          'logs'  => self::trace_logs($t[1]->trace)
        ];
        break;
      case self::IS_ROW:
        # an independent group
        $j = 0;
        $b = [];
        foreach ($t[1] as $c)
        {
          $trk = $c[0];
          $k = $trk->ok ? 0 : 2;
          $m = '#'.$c[1];
          $d = self::trace_logs($trk->trace);
          $trk->prev && array_push(
            $d, ...self::track_logs($trk->prev)
          );
          $b[] = [
            'level' => $k,
            'msg'   => [$m, ...$trk->title],
            'span'  => self::track_span($trk),
            'logs'  => $d
          ];
          if ($k && !$j) {
            $j = 2;
          }
        }
        $a[] = [
          'level' => $j,
          'msg'   => ['ROW',$t[2].'/'.$t[3]],
          'span'  => $t[4],
          'logs'  => $b
        ];
        break;
      case self::IS_FUSION:
      case self::IS_CANCELLATION:
        # a nesting,
        # compose logs group
        if ($t[1]->title) {
          $b = self::track_logs($t[1]);
        }
        else
        {
          $b = self::trace_logs($t[1]->trace);
          $t[1]->prev && array_push(
            $b, ...self::track_logs($t[1]->prev)
          );
        }
        # compose node
        if ($t[0] === self::IS_FUSION)
        {
          $a[] = [
            'level' => 3,
            'msg'   => ['FUSED'],
            'logs'  => $b
          ];
        }
        else
        {
          $a[] = [
            'level' => 3,
            'msg'   => ['CANCELLED'],
            'logs'  => $b
          ];
        }
        break;
      }
    }
    return $a;
  }
  # }}}
  # }}}
  # base controls {{{
  function __call(string $method, array $args): mixed # {{{
  {
    # interface to contextable
    if (!$this->ok)
    {
      throw ErrorEx::fatal(
        "unable to invoke `".$method."` method\n".
        "result is in a failed state"
      );
    }
    if (!($o = $this->promise->_context))
    {
      throw ErrorEx::fatal(
        "unable to invoke `".$method."` method\n".
        "context is not attached"
      );
    }
    return $o->$method(...$args);
  }
  # }}}
  function reset(): self # {{{
  {
    if (!($p = $this->promise))
    {
      throw ErrorEx::fatal(
        "cannot reset a finished state"
      );
    }
    if (!$this->ok)
    {
      throw ErrorEx::fatal(
        "cannot reset a failed state"
      );
    }
    $p->_context && $p->_context->_reset();
    $this->_init();
    return $this;
  }
  # }}}
  function info(...$msg): self # {{{
  {
    $this->_track()->trace[] = [
      self::IS_INFO, ErrorEx::stringify($msg)
    ];
    return $this;
  }
  # }}}
  function warn(...$msg): self # {{{
  {
    $this->_track()->trace[] = [
      self::IS_WARNING, ErrorEx::stringify($msg)
    ];
    return $this;
  }
  # }}}
  function fail(...$msg): self # {{{
  {
    $this->_track()->trace[] = [
      self::IS_FAILURE, ErrorEx::stringify($msg)
    ];
    $this->ok = false;
    return $this;
  }
  # }}}
  function error(object $e): self # {{{
  {
    if ($e = ErrorEx::from($e, true))
    {
      $this->_track()->trace[] = [
        self::IS_ERROR, $e
      ];
      if ($this->ok && $e->hasError()) {
        $this->ok = false;
      }
    }
    return $this;
  }
  # }}}
  function confirm(...$msg): self # {{{
  {
    if (($t = $this->_track())->title) {
      $t->title = ErrorEx::stringify($msg);
    }
    else
    {
      $t->title = ErrorEx::stringify($msg);
      $t->span  = Loop::$HRTIME - $t->span;
    }
    return $this;
  }
  # }}}
  # }}}
  # specific controls {{{
  function promiseDelay(int $ms): self # {{{
  {
    # set delay in milliseconds
    $this->promise->_time = 
      Loop::$HRTIME + (int)($ms * 1000000);
    ###
    return $this;
  }
  # }}}
  function promiseDelayNs(int $ns): self # {{{
  {
    # set delay in nanoseconds
    $this->promise->_time = Loop::$HRTIME + $ns;
    return $this;
  }
  # }}}
  function promiseIdle(): self # {{{
  {
    # TODO: improve based on recent activity/stats
    # TODO: improve based on CPU caps
    # set relaxed waiting
    $this->promise->_time =
      Loop::$HRTIME + 70*1000000;# ms
    ###
    return $this;
  }
  # }}}
  function promiseHalt(): self # {{{
  {
    $this->promise->_time = PHP_INT_MAX;
    return $this;
  }
  # }}}
  function promiseWakeup(int $hrtime=1): self # {{{
  {
    # set exact time to wakeup
    $this->promise->_time = $hrtime;
    return $this;
  }
  # }}}
  function promiseNoDelay(): void # {{{
  {
    $this->promise->_time = 0;
  }
  # }}}
  function promiseInject(object $o): void # {{{
  {
    if ($o instanceof Promise) {
      $this->promise->_queueInject($o);
    }
    else
    {
      $this->promise->_queueInjectOne(
        Completable::from($o)
      );
    }
  }
  # }}}
  function promiseContextSet(object $o, int $t=1): self # {{{
  {
    $p = $this->promise;
    if ($p->_context && $p->_context !== $o) {
      $p->_context->_done();
    }
    $p->_context = $o;
    $p->_time    = $t;
    return $this;
  }
  # }}}
  function promiseContextClear(object $o): void # {{{
  {
    $p = $this->promise;
    if ($p->_context === $o) {
      $p->_context = null;
    }
  }
  # }}}
  function promiseFuse(object $o): void # {{{
  {
    # settle current track timespan
    if (!($track = $this->track)->title) {
      $track->span = Loop::$HRTIME - $track->span;
    }
    # create new and replace current track
    $this->track = $track = new PromiseResultTrack(
      null, true, [[self::IS_FUSION, $track]]
    );
    $this->ok = &$track->ok;
    # set nodelay and replace queue
    $this->promise->_time = 0;
    $this->promise->_queueTruncate();
    $this->promiseInject($o);
  }
  # }}}
  function promisePrepend(object $o): self # {{{
  {
    $this->promise->_queuePrepend($o);
    return $this;
  }
  # }}}
  function promisePrependOne(object $o): self # {{{
  {
    $this->promise->_queuePrependOne($o);
    return $this;
  }
  # }}}
  function promiseCancel(): void # {{{
  {
    $this->promise->cancel();
  }
  # }}}
  function valueSet(mixed $value): self # {{{
  {
    $this->value = $value;
    return $this;
  }
  # }}}
  function valuePut(mixed $v=null): self # {{{
  {
    array_unshift($this->store, $v);
    $this->value = &$this->store[0];
    return $this;
  }
  # }}}
  function indexPlus(int $i=1): self # {{{
  {
    $this->index += $i;
    return $this;
  }
  # }}}
  # }}}
}
class PromiseResultTrack
{
  # constructor {{{
  public int $span;
  function __construct(
    public ?object $prev  = null,
    public bool    $ok    = true,
    public array   $trace = [],
    public ?array  $title = null
  ) {
    $this->span = Loop::$HRTIME;
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    $a['ok'] = $this->ok;
    if ($this->title)
    {
      $a['title'] = implode('·', $this->title);
      $a['span(ms)'] = (int)($this->span / 1000000);
    }
    $a['trace'] = PromiseResult::trace_info(
      array_reverse($this->trace)
    );
    if ($this->prev) {
      $a['prev'] = $this->prev;
    }
    return $a;
  }
  # }}}
}
# }}}
Loop::_init();
###
