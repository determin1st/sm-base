<?php declare(strict_types=1);
# defs {{{
namespace SM;
use
  FFI,Closure,Error,Throwable,
  SplDoublyLinkedList,ArrayAccess;
use function
  is_array,count,array_unshift,array_pop,
  array_push,array_reverse,array_fill,array_slice,
  array_merge,in_array,implode,end,key,prev,
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
  # TODO: refine/test column result
  # TODO: cancellation on abolishment/gc, refs in the loop?
  # TODO: await in await?
  # TODO: time stats?
  # basis {{{
  public ?array  $_reverse=null;
  public ?object $_context=null,$result=null;
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
  static function Delay(int $ms, ?object $x=null): self # {{{
  {
    $x && $x = Completable::from($x);
    return new self(new Completable_Delay($ms, $x));
  }
  # }}}
  static function Row(# {{{
    array $group, int $break=0,
    int $first=0, bool $any=false
  ):self
  {
    return new self(
      new Completable_Row($group, $break, $first, $any)
    );
  }
  # }}}
  static function Column(# {{{
    array $group, int $break=1
  ):self
  {
    return new self(
      new Completable_Column($group, $break)
    );
  }
  # }}}
  # }}}
  # composition {{{
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
  function thenRow(# {{{
    array $group, int $break=0,
    int $first=0, bool $any=false
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_Row($group, $break, $first)
    );
  }
  # }}}
  function thenColumn(# {{{
    array $group, int $break=1
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_Column($group, $break)
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
  function okayRow(# {{{
    array $group, int $break=0,
    int $first=0, bool $any=false
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_OkayThen(
      new Completable_Row($group, $break, $first)
    ));
  }
  # }}}
  function okayColumn(# {{{
    array $group, int $break=1
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_OkayThen(
      new Completable_Column($group, $break)
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
  function failRow(# {{{
    array $group, int $break=0,
    int $first=0, bool $any=false
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_FailThen(
      new Completable_Row($group, $break, $first)
    ));
  }
  # }}}
  function failColumn(# {{{
    array $group, int $break=0
  ):self
  {
    return $this->_queueAppendOne(
      new Completable_FailThen(
      new Completable_Column($group, $break)
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
  function _reverseAdd(object $reversible): void # {{{
  {
    if ($this->_reverse) {
      $this->_reverse[] = $reversible;
    }
    else {
      $this->_reverse = [$reversible];
    }
  }
  # }}}
  function _queueGet(): object # {{{
  {
    if ($this->pending >= 0)
    {
      throw ErrorEx::fail(__CLASS__,
        'composition is only possible '.
        'with a fresh promise'
      );
    }
    return $this->_queue;
  }
  # }}}
  function _queueAppend(object $promise): self # {{{
  {
    $q = $this->_queueGet();
    foreach ($promise->_queueGet() as $o) {
      $q->push($o);
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
    # prepare result object
    $r || $r = new PromiseResult();
    $r->promise = $this;
    # initialize self
    $this->pending = $this->_queue->count();
    $this->result  = $r;
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
      if (--$this->pending > 0)
      {
        # more completables pending,
        # check immediate execution
        return ($this->_time === 0)
          ? $this->_execute() # recurse now
          : true;# repeat later
      }
      # finish execution
      if ($this->_context)
      {
        $this->_context->_done();
        $this->_context = null;
      }
      $this->result->_done();
      return false;# not valid anymore
    }
    # repeat on the next tick
    return true;
  }
  # }}}
  function cancel(): ?object # {{{
  {
    # check did not start or finished
    if ($this->pending <= 0) {
      return $this->result;
    }
    # cancel current completable
    $o = $this->_queue->offsetGet(0);
    $o->result && $o->_cancel();
    # clear context
    if ($this->_context)
    {
      $this->_context->_done();
      $this->_context = null;
    }
    # clear pending and set the result
    $this->pending = 0;
    $r = $this->result->_cancel();
    # undo reversibles
    if ($a = $this->_reverse)
    {
      $this->_reverse = null;
      $i = count($a);
      while (--$i >= 0) {
        $a[$i]->_undo();
      }
    }
    return $r;
  }
  # }}}
}
# }}}
class Loop # {{{
{
  const # {{{
    COLUMN_PREFIX = 'sm-column-',
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
  public array  $col=[];
  public int    $rowCnt=0,$colNdx=0,$added=0;
  public int    $spinLevel=0,$spinYield=0;
  static int    $TIME=0,$HRTIME=PHP_INT_MAX;
  static ?object $LOOP=null;
  static function _init(): void
  {
    if (!self::$LOOP)
    {
      self::$LOOP = new self();
      PromiseResult::$HRTIME = &self::$HRTIME;
    }
  }
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
    $this->row    = new SplDoublyLinkedList();
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
  # }}}
  # dynamis {{{
  function rowAttach(object $p): void # {{{
  {
    $this->row->push($p->_init());
    $this->rowCnt++;
    $this->added++;
  }
  # }}}
  function rowFrom(array $a): void # {{{
  {
    $row = $this->row;
    for ($i=0,$j=count($a); $i < $j; ++$i) {
      $row->push($a[$i]->_init());
    }
    $this->rowCnt += $j;
    $this->added++;
  }
  # }}}
  function colAttach(object $p, string $id): object # {{{
  {
    # check already exists
    if (isset($this->col[$id])) {
      return $this->col[$id]->push($p);
    }
    # attach new untied column
    $o = new Completable_Column([$p], 0, true);
    $this->rowAttach((new Promise($o))
    ->then(function() use ($id): void {
      # detach
      unset($this->col[$id]);
    }));
    return $this->col[$id] = $o;
  }
  # }}}
  function colFrom(array $a): string # {{{
  {
    # create unique id
    $id = self::COLUMN_PREFIX.$this->colNdx;
    $this->colNdx++;
    # attach first and the rest
    $this
      ->colAttach($a[0], $id)
      ->pushAll(array_slice($a, 1));
    ###
    return $id;
  }
  # }}}
  function enter(): self # {{{
  {
    if ($this->spinLevel)
    {
      throw ErrorEx::fatal(
        "the loop is locked\n".
        "nested loops are not supported\n".
        "resort to promise chaining and queues"
      );
    }
    $this->spinLevel++;
    return $this;
  }
  # }}}
  function spin(): int # {{{
  {
    # update timestamps
    if ($this->added)
    {
      $this->added = 0;
      self::$TIME  = time();
    }
    self::$HRTIME = $t0 = hrtime(true);
    # prepare vars
    $idle = $pending = $done = 0;
    $t = $t0 + 999999999;# ~1s
    $q = $this->row;
    $n = &$this->rowCnt;
    $i = 0;
    # iterate
  x1:# get the promise
    $p = $q->offsetGet($i);
    # check cancelled
    if ($p->pending <= 0) {
      goto x2;
    }
    # check idle
    if ($p->_time > $t0)
    {
      if ($t > $p->_time) {
        $t = $p->_time;
      }
      $idle++;
      $pending++;
      goto x3;
    }
    # do the work
    if ($p->_execute())
    {
      $pending++;
      goto x3;
    }
  x2:# promise is settled
    $done++;
    $q->offsetUnset($i);
    if (--$n > $i) {
      goto x1;
    }
    goto x4;
  x3:# proceed to the next item
    if (++$i < $n) {
      goto x1;
    }
  x4:# check nothing is left
    if ($pending === 0) {
      return $done;
    }
    # check idle
    if ($pending === $idle)
    {
      # process shall enter a sleeping state -
      # either IO resource isnt currently available
      # or there's nothing left to do.
      # this implementation does not sleep
      # on "magical" wait queues/channels -
      # the time estimation is performed
      # by "gears" and individual authors;
      ###
      # determine sleep span and relinquish cpu
      $this->sleep($t - $t0);
    }
    elseif ($this->spinYield)
    {
      # the loop is in the yield mode -
      # its giving up CPU on every tick;
      # this behaviour is useful when
      # there is another process or thread
      # responsible for the job..
      ###
      $this->sleep(0);
    }
    return $done;
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
    if ($n = $this->rowCnt)
    {
      # cancel all promises
      $q = $this->row;
      for ($i=0; $i < $n; ++$i) {
        $q->offsetGet($i)->cancel();
      }
      # cancellation may cause offloading,
      # spin the loop until its empty
      while ($this->rowCnt) {
        $this->spin();
      }
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
    self::$LOOP->rowAttach((new Promise($o))->_init(
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
  static function row_attach(object $p): void # {{{
  {
    self::$LOOP->rowAttach($p);
  }
  # }}}
  static function row_from(array $a): void # {{{
  {
    self::$LOOP->rowFrom($a);
  }
  # }}}
  static function col_attach(object $p, string $id): void # {{{
  {
    self::$LOOP->colAttach($p, $id);
  }
  # }}}
  static function col_from(array $a): string # {{{
  {
    return self::$LOOP->colFrom($a);
  }
  # }}}
  # outer
  static function await(object $p): object # {{{
  {
    $loop = self::$LOOP->enter();
    if ($p->pending < 0) {
      $loop->rowAttach($p);
    }
  a1:
    if ($loop->spin() === 0 || $p->pending) {
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
    for ($i=0,$j=count($a) - 1; $i <= $j; ++$i)
    {
      if ($a[$i]->pending < 0) {
        $loop->rowAttach($a[$i]);
      }
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
          $loop->rowAttach($p);
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
    if ($loop->spin() === 0) {
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
  abstract function _done(): bool;
  function _cancel(): void {$this->_done();}
  function reset(): void {}
}
abstract class Reversible extends Completable
{
  abstract function _undo(): bool;
  function _cancel(): void {$this->_undo();}
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
class Completable_Delay extends Completable # {{{
{
  public int $stage=1;
  function __construct(
    public int     $delay,
    public ?object $what
  ) {}
  function _complete(): bool
  {
    if ($this->stage)
    {
      $this->result->promiseDelay($this->delay);
      $this->stage = 0;
      return false;
    }
    if ($this->what) {
      $this->result->promiseInject($this->what);
    }
    $this->result->promiseNoDelay();
    $this->stage = 1;
    return true;
  }
}
# }}}
# }}}
# groups {{{
class Completable_Row extends Completable # {{{
{
  # basis {{{
  public ?object $help=null;# helper object
  public int     $count,$index=0;
  function __construct(
    public ?array $group,
    public int    $break,
    public int    $first,
    public bool   $firstAny
  ) {
    $this->count = $n = count($group);
    if ($break > $n || $break <= 0) {
      $this->break = $n;# wont break
    }
    if ($first > $n || $first <= 0) {
      $this->first = $n;# none first
    }
  }
  # }}}
  function _complete(): bool # {{{
  {
    # initialized means finished
    if ($this->help)
    {
      $this->result->_row(
        $this->help->result, $this->group,
        $this->index, $this->count
      );
      $this->help  = null;
      $this->group = null;
      return true;
    }
    # initialize
    $this->help = new Completable_RowHelp($this);
    Loop::row_from($this->group);
    Loop::row_attach(new Promise($this->help));
    # suspend til completion
    $this->result->promiseHalt();
    return false;
  }
  # }}}
  function _cancel(): void # {{{
  {
    # cancellation is implemented in the helper
    $this->result->_row(
      $this->help->result->promiseCancel(),
      $this->group, $this->index, $this->count
    );
    $this->help  = null;
    $this->group = null;
  }
  # }}}
}
# }}}
class Completable_RowHelp extends Completable # {{{
{
  # basis {{{
  public ?array $ready;
  function __construct(public ?object $base) {
    $this->ready = array_fill(0, $base->count, false);
  }
  # }}}
  function _complete(): bool # {{{
  {
    # prepare
    $base = $this->base;
    $time = PHP_INT_MAX;
    $more = 0;
    # check group promises
    foreach ($base->group as $i => $p)
    {
      # skip ready
      if ($this->ready[$i]) {
        continue;
      }
      # skip pending
      if ($p->pending)
      {
        # select smallest timestamp
        if ($time > $p->_time) {
          $time = $p->_time;
        }
        $more++;
        continue;
      }
      # set ready and check successful
      $this->ready[$i] = true;
      if ($p->result->ok)
      {
        $p->result->index = $base->index++;
        goto x1;
      }
      # failed, check break condition
      $p->result->index = -1;
      if ($base->break && --$base->break === 0)
      {
        $base->result->promiseWakeup();
        $this->_cancel();
        return true;
      }
      if ($base->firstAny)
      {
      x1:# check race condition
        if ($base->first && --$base->first === 0)
        {
          $base->result->promiseWakeup();
          $this->_cancel();
          return true;
        }
      }
    }
    # check more to go
    if ($more)
    {
      $this->result->promiseWakeup($time);
      return false;
    }
    # all finished
    $base->result->promiseWakeup();
    $this->base  = null;
    $this->ready = null;
    return true;
  }
  # }}}
  function _cancel(): void # {{{
  {
    # cancel all unfinished
    foreach ($this->base->group as $i => $p)
    {
      if ($p->pending > 0)
      {
        $p->cancel();
        $p->result->index = -1;
      }
    }
    $this->base  = null;
    $this->ready = null;
  }
  # }}}
}
# }}}
class Completable_Column extends Completable # {{{
{
  # basis {{{
  public int  $count,$index=0;
  function __construct(
    public ?array $group,
    public int    $break,
    public bool   $untied = false
  ) {
    $this->count = $n = count($group);
    if ($break >= $n || $break < 0) {
      $this->break = 0;# wont break
    }
  }
  # }}}
  function _complete(): bool # {{{
  {
    # get current promise
    $p = $this->group[$this->index];
    # check active
    if ($p->pending > 0) {
      goto x1;
    }
    # check untouched
    if ($p->pending < 0)
    {
      $p->_init();
      goto x1;
    }
    # promise cancelled
    goto x2;
  x1:# do the work and check still active
    if ($p->_execute()) {
      goto x3;
    }
  x2:# one has finished
    # check break condition
    if ($this->break && !$p->result->ok &&
        --$this->break === 0)
    {
      $this->index++;
      $this->_cancel();
      return true;
    }
    # check more to go
    if (++$this->index < $this->count)
    {
      # initialize and pass the value
      $r = $p->result;
      $p = $this->group[$this->index]->_init();
      $p->result->value = $r->value;
      goto x1;
    }
    # finilize
    $this->result->promiseNoDelay();
    $this->untied || $this->result->_column(
      true, $this->group, $this->index, $this->count
    );
    $this->group = null;
    return true;
  x3:# still active
    # copy timestamp and resume later
    $this->result->promiseWakeup($p->_time);
    return false;
  }
  # }}}
  function _cancel(): void # {{{
  {
    # cancel current promise
    $p = $this->group[$this->index];
    if ($p->pending >= 0)
    {
      $p->cancel();
      $this->index++;
    }
    # finilize
    $this->result->promiseNoDelay();
    $this->untied || $this->result->_column(
      false, $this->group, $this->index, $this->count
    );
    # cleanup
    $this->group = null;
  }
  # }}}
  function push(object $p): self # {{{
  {
    $this->group[] = $p;
    $this->count++;
    return $this;
  }
  # }}}
  function pushAll(array $a): self # {{{
  {
    if (($n = count($a)) > 1)
    {
      $this->group  = array_merge($this->group, $a);
      $this->count += $n;
    }
    elseif ($n) {
      $this->push($a[0]);
    }
    return $this;
  }
  # }}}
}
# }}}
# }}}
# result {{{
class PromiseResult implements ArrayAccess,Loggable
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
  static int     $HRTIME;# Loop::$HRTIME
  public ?object $promise=null;
  public int     $started,$index;
  public object  $track;
  public bool    $ok,$isCancelled=false;
  public array   $store;
  public mixed   $value;
  ###
  function __construct() {
    $this->_init();
  }
  function _init(): void
  {
    $this->started = Loop::$TIME;
    $this->index = 0;
    $this->track = new PromiseResultTrack();
    $this->ok    = &$this->track->ok;
    $this->store = [null];
    $this->value = &$this->store[0];
  }
  # }}}
  # internals {{{
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
  function _row(# {{{
    object $ctrl, array $group, int $done, int $total
  ):void
  {
    # extract data required
    $tracks = [];
    $values = [];
    $order  = [];
    foreach ($group as $p)
    {
      $r = $p->result;
      $tracks[] = $r->track;
      $values[] = $r->value;
      $order[]  = $r->index;
    }
    # add the row track
    $this->_track()->trace[] = [
      self::IS_ROW, $tracks, $order,
      $done, $total, $ctrl->ok,
      $ctrl->track->duration()
    ];
    # set values
    $this->value = $values;
    $this->ok = $ctrl->ok;
  }
  # }}}
  function _column(# {{{
    bool $ok, array $group, int $done, int $total
  ):void
  {
    # extract data required
    if ($done < $total) {
      $group = array_slice($group, 0, $done);
    }
    $tracks = [];
    foreach ($group as $p) {
      $tracks[] = $p->result->track;
    }
    # add the column track
    $this->_track()->trace[] = [
      self::IS_COLUMN, $tracks, $done, $total
    ];
    # store resulting value (the last one)
    $this->value = $p->result->value;
    $this->ok = $ok;
  }
  # }}}
  function _cancel(): self # {{{
  {
    if (!($t0 = $this->track)->title) {
      $t0->span = self::$HRTIME - $t0->span;
    }
    $t1 = new PromiseResultTrack(null, false);
    $t1->trace[] = [self::IS_CANCELLATION, $t0];
    $this->track = $t1;
    $this->ok    = &$t1->ok;
    $this->isCancelled = true;
    $this->promise     = null;
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
      'span'  => $t->duration(),
      'time'  => $this->started,
      'logs'  => $a
    ];
  }
  # }}}
  function logLevel(): int # {{{
  {
    return $this->ok ? 0 : 2;
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
  static function trace_logs(array $trace): array # {{{
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
        $a[] = [
          'level' => self::track_level($t[1]),
          'msg'   => ['COLUMN',$t[2].'/'.$t[3]],
          'span'  => $t[1]->duration(),
          'logs'  => self::trace_logs($t[1]->trace)
        ];
        break;
      case self::IS_ROW:
        $b = [];
        foreach ($t[1] as $j => $trk)
        {
          $level = $trk->ok ? 0 : 2;
          $order = $t[2][$j];
          $logs  = self::trace_logs($trk->trace);
          $trk->prev && array_push(
            $logs, ...self::track_logs($trk->prev)
          );
          $b[] = [
            'level' => $level,
            'msg'   => (($order >= 0)
              ? ['#'.$order, ...$trk->title]
              : $trk->title
            ),
            'span'  => $trk->duration(),
            'logs'  => $logs
          ];
        }
        $a[] = [
          'level' => $t[5] ? 0 : 2,
          'msg'   => ['ROW', $t[3].'/'.$t[4]],
          'span'  => $t[6],
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
  # dynamis {{{
  # error controls
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
  # misc
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
    $p->_context && $p->_context->reset();
    $this->_init();
    return $this;
  }
  # }}}
  function valueSet(mixed $value): self # {{{
  {
    $this->value = $value;
    return $this;
  }
  # }}}
  function valueNext(mixed $v=null): self # {{{
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
  # promise controls
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
  function promiseContextClear(): void # {{{
  {
    $this->promise->_context = null;
  }
  # }}}
  function promiseReverse(object $o): self # {{{
  {
    $this->promise->_reverseAdd($o);
    return $this;
  }
  # }}}
  function promiseFuse(object $o): void # {{{
  {
    # settle current track timespan
    if (!($track = $this->track)->title) {
      # TODO: set title too
      $track->span = Loop::$HRTIME - $track->span;
    }
    # create new and replace current track
    $this->track = new PromiseResultTrack(
      null, true, [[self::IS_FUSION, $track]]
    );
    $this->ok = &$this->track->ok;
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
  function promiseCancel(): self # {{{
  {
    $this->promise->cancel();
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
  function duration(): int # {{{
  {
    # get base duration
    $x = $this->title
      ? $this->span
      : Loop::$HRTIME - $this->span;
    # add special cases from the trace
    foreach ($this->trace as $e)
    {
      switch ($e[0]) {
      case PromiseResult::IS_FUSION:
      case PromiseResult::IS_CANCELLATION:
        $x += $e[1]->duration();
        break;
      }
    }
    # complete
    return $this->prev
      ? $x + $this->prev->duration()
      : $x;
  }
  # }}}
}
# }}}
Loop::_init();
###
