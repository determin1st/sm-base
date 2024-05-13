<?php declare(strict_types=1);
# defs {{{
namespace SM;
use
  SplDoublyLinkedList,SplObjectStorage,
  Closure,Error,Throwable,ArrayAccess;
use function
  is_array,count,array_unshift,array_pop,array_push,
  array_reverse,in_array,implode,end,key,prev,
  time,hrtime,usleep,function_exists,
  register_shutdown_function,pcntl_async_signals,
  pcntl_signal_get_handler,pcntl_signal;
use const
  DIRECTORY_SEPARATOR,PHP_OS_FAMILY;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'error.php';
# }}}
class Promise # {{{
{
  # TODO: composition guards
  # TODO: object tests
  # base {{{
  public ?array  $_reverse=null;
  public ?object $_done=null,$result=null;
  public int     $_idle=0,$pending=-1;
  public object  $_queue;
  ###
  function __construct(object $action)
  {
    $this->_queue = new SplDoublyLinkedList();
    $this->_queue->push($action);
  }
  function _reverseAdd(object $action): void
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
  # construction {{{
  static function from(object|array|null $x): self # {{{
  {
    return is_array($x)
      ? self::Column($x)
      : (($x instanceof self)
        ? $x : new self(Completable::from($x)));
  }
  # }}}
  static function Context(object $o): self # {{{
  {
    return new self(new PromiseContext($o));
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
      ? new PromiseFunc($f, $a)
      : new PromiseOp($f)
    );
  }
  # }}}
  static function Call(object $f, ...$a): self # {{{
  {
    return new self(new PromiseCall($f, $a));
  }
  # }}}
  static function When(# {{{
    bool $ok, object $x, ...$a
  ):self
  {
    return new self(new PromiseWhen($ok, $a
      ? new PromiseFunc($x, $a)
      : Completable::from($x)
    ));
  }
  # }}}
  static function Timeout(# {{{
    int $ms, object $x, ...$a
  ):self
  {
    return new self(new PromiseTimeout($ms, $a
      ? new PromiseFunc($x, $a)
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
  function done(object $f): self # {{{
  {
    $this->_done = $f;
    return $this;
  }
  # }}}
  function then(object $x, ...$a): self # {{{
  {
    $q = $this->_queue;
    if ($a) {# assume callable
      $q->push(new PromiseFunc($x, $a));
    }
    elseif ($x instanceof self)
    {
      # append all
      foreach ($x->_queue as $action) {
        $q->push($action);
      }
    }
    else {# append one
      $q->push(Completable::from($x));
    }
    return $this;
  }
  # }}}
  function thenCall(object $f, ...$a): self # {{{
  {
    $this->_queue->push(new PromiseCall($f, $a));
    return $this;
  }
  # }}}
  function thenTimeout(# {{{
    int $ms, object $x, ...$a
  ):self
  {
    $this->_queue->push(new PromiseTimeout($ms, $a
      ? new PromiseFunc($x, $a)
      : Completable::from($x)
    ));
    return $this;
  }
  # }}}
  function thenColumn(# {{{
    array $group, int $break=1
  ):self
  {
    $this->_queue->push(new PromiseColumn(
      $group, $break
    ));
    return $this;
  }
  # }}}
  function thenRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    $this->_queue->push(new PromiseRow(
      $group, $break, $first
    ));
    return $this;
  }
  # }}}
  # positive
  function okay(object $x, ...$a): self # {{{
  {
    $this->_queue->push(new PromiseWhen(true, $a
      ? new PromiseFunc($x, $a)
      : Completable::from($x)
    ));
    return $this;
  }
  # }}}
  function okayCall(object $f, ...$a): self # {{{
  {
    $this->_queue->push(new PromiseWhen(true,
      new PromiseCall($f, $a)
    ));
    return $this;
  }
  # }}}
  function okayTimeout(# {{{
    int $ms, object $x, ...$a
  ):self
  {
    $this->_queue->push(new PromiseWhen(true,
      new PromiseTimeout($ms, $a
        ? new PromiseFunc($x, $a)
        : Completable::from($x)
      )
    ));
    return $this;
  }
  # }}}
  function okayColumn(# {{{
    array $group, int $break=1
  ):self
  {
    $this->_queue->push(new PromiseWhen(true,
      new PromiseColumn($group, $break)
    ));
    return $this;
  }
  # }}}
  function okayRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    $this->_queue->push(new PromiseWhen(true,
      new PromiseRow($group, $break, $first)
    ));
    return $this;
  }
  # }}}
  function okayFuse(object $x): self # {{{
  {
    $this->_queue->push(new PromiseWhen(true,
      new PromiseFuse($x)
    ));
    return $this;
  }
  # }}}
  # negative
  function fail(object $x, ...$a): self # {{{
  {
    $this->_queue->push(new PromiseWhen(false, $a
      ? new PromiseFunc($x, $a)
      : Completable::from($x)
    ));
    return $this;
  }
  # }}}
  function failCall(object $f, ...$a): self # {{{
  {
    $this->_queue->push(new PromiseWhen(false,
      new PromiseCall($f, $a)
    ));
    return $this;
  }
  # }}}
  function failTimeout(# {{{
    int $ms, object $x, ...$a
  ):self
  {
    $this->_queue->push(new PromiseWhen(false,
      new PromiseTimeout($ms, $a
        ? new PromiseFunc($x, $a)
        : Completable::from($x)
      )
    ));
    return $this;
  }
  # }}}
  function failColumn(# {{{
    array $group, int $break=0
  ):self
  {
    $this->_queue->push(new PromiseWhen(false,
      new PromiseColumn($group, $break)
    ));
    return $this;
  }
  # }}}
  function failRow(# {{{
    array $group, int $break=0, int $first=0
  ):self
  {
    $this->_queue->push(new PromiseWhen(false,
      new PromiseRow($group, $break, $first)
    ));
    return $this;
  }
  # }}}
  function failFuse(object $x): self # {{{
  {
    $this->_queue->push(new PromiseWhen(false,
      new PromiseFuse($x)
    ));
    return $this;
  }
  # }}}
  # }}}
  function _init(?object $r=null): bool # {{{
  {
    if (~$this->pending) {
      return false;
    }
    $this->pending = $this->_queue->count();
    $this->result  = $r ?: new PromiseResult();
    return true;
  }
  # }}}
  function _execute(): ?object # {{{
  {
    # prepare
    $q = $this->_queue;
    if (!($a = $q->offsetGet(0))->result) {
      $a->result = $this->result;
    }
    # invoke completion routine
    if (!($x = $a->_complete()))
    {
      # action is complete,
      # collect reversible
      if ($a instanceof Reversible) {
        $this->_reverseAdd($a);
      }
      # eject and finish
      $q->shift();
      return --$this->pending
        ? null : $this->result->_done($this);
    }
    # handle repetition
    if ($x === $a) {
      return null;
    }
    # handle dynamic continuation
    if ($x === Completable::$THEN)
    {
      switch ($x->getId()) {
      case 1:# delayed repetition {{{
        $this->_idle = $x->time;
        return null;
        # }}}
      case 2:# immediate recursion {{{
        $q->shift();
        return --$this->pending
          ? $this->_execute()
          : $this->result->_done($this);
        # }}}
      case 3:# immediate expansive recursion {{{
        if (($x = $x->getAction()) instanceof self)
        {
          # remove current
          $q->shift();
          # expand
          $x = $x->_queue;
          $j = $x->count();
          $this->pending += $j - 1;
          while (--$j >= 0) {
            $q->unshift($x->offsetGet($j));
          }
        }
        else
        {
          # current action replacement
          $q->offsetSet(0, Completable::from($x));
        }
        return $this->_execute();
        # }}}
      case 4:# fusion {{{
        if (($x = $x->getAction()) instanceof self)
        {
          # many
          $this->_queue = $q = $x->_queue;
          $this->pending = $q->count();
        }
        else
        {
          # one
          $this->_queue = $q = new SplDoublyLinkedList();
          $q->push(Completable::from($x));
          $this->pending = 1;
        }
        return null;
        # }}}
      case 5:# immediate self-cancellation {{{
        return $this->cancel();
        # }}}
      case 6:# immediate self-completion {{{
        $this->pending = 0;
        return $this->result->_done($this);
        # }}}
      default:# unknown {{{
        $q->shift();
        return --$this->pending
          ? null : $this->result->_done($this);
        # }}}
      }
    }
    # collect reversible
    if ($a instanceof Reversible) {
      $this->_reverseAdd($a);
    }
    # expansive continuation
    if ($x instanceof self)
    {
      # remove current
      $q->shift();
      # expand
      $x = $x->_queue;
      $j = $x->count();
      $this->pending += $j - 1;
      while (--$j >= 0) {
        $q->unshift($x->offsetGet($j));
      }
    }
    else
    {
      # replacement
      $q->offsetSet(0, Completable::from($x));
    }
    return null;
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
    $a = $this->_queue->offsetGet(0);
    $a->result && $a->_cancel();
    # set cancelled
    $this->pending = 0;
    $r = $this->result->_cancel();
    # undo finished reversible actions
    if ($q = &$this->_reverse)
    {
      $i = count($q);
      while (--$i >= 0)
      {
        $q[$i]->result = $r;
        $q[$i]->_undo();
      }
      $q = null;
    }
    # complete
    return $r->_done($this);
  }
  # }}}
}
# }}}
class Loop # {{{
{
  # TODO: avoid execution of already executing promises
  # TODO: collect statistics
  const # {{{
    MAX_TIMEOUT = 24*60*60*1000000000,
    SIGNAL = [# unix termination signals {{{
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
    # }}}
  # }}}
  # constructor {{{
  public object $row;
  public array $column=[];
  public int $rowCnt=0,$colCnt=0,$added=0;
  private static ?self $LOOP=null;
  private function __construct()
  {
    # initialize
    $this->row = new SplObjectStorage();
    Completable::$TIME = time();
    Completable::$HRTIME = hrtime(true);
    # set termination handlers
    if (PHP_OS_FAMILY !== 'Windows') {
      $this->setSignals();
    }
    register_shutdown_function(
      $this->stop(...)
    );
  }
  static function init(): bool
  {
    self::$LOOP || self::$LOOP = new self();
    return true;
  }
  # }}}
  function setSignals(): bool # {{{
  {
    # check feasible
    if (function_exists('pcntl_signal')) {
      return false;
    }
    # create handler function
    static $f=null;
    $f || $f = (function(int $n): void {
      static $x=true;
      if ($x)
      {
        $x = false;
        exit(0x80|$n);
      }
    });
    # set termination signals
    static $DFL=\SIG_DFL;
    foreach (self::SIGNAL as $n)
    {
      # replace default handler
      if (pcntl_signal_get_handler($n) === $DFL) {
        pcntl_signal($n, $f);
      }
    }
    # enable asynchronous callbacks
    pcntl_async_signals(true);
    return true;
  }
  # }}}
  function rowAdd(object $p): self # {{{
  {
    if (!$this->row->contains($p))
    {
      $p->_init();
      $this->row->attach($p);
      $this->rowCnt++;
      $this->added++;
    }
    return $this;
  }
  # }}}
  function colAdd(object $p, string $id): bool # {{{
  {
    if (isset($this->columns[$id]))
    {
      # get existing queue
      $q = $this->columns[$id];
    }
    elseif ($p->_init())
    {
      # create new queue
      $q = new SplDoublyLinkedList();
      $this->columns[$id] = $q;
      $this->colCnt++;
      $this->added++;
    }
    else {
      return false;
    }
    # stack up
    $q->push($p);
    return true;
  }
  # }}}
  function spin(): int # {{{
  {
    # update low resolution timestamp
    # when new promises added,
    # it is used in their result construction
    if ($this->added)
    {
      $this->added = 0;
      Completable::$TIME = time();
    }
    # update high resolution timestamp and
    # determine maximal idle from now on
    Completable::$HRTIME = $t = hrtime(true);
    $idleTime = $t + self::MAX_TIMEOUT;
    $idleCnt  = 0;
    # spin columns
    # {{{
    if ($n0 = &$this->colCnt)
    {
      $q0 = &$this->columns;
      $q1 = end($q0);
      do
      {
        $p = $q1->offsetGet(0);
        if ($z = $p->_idle)
        {
          # it is idle,
          # skip for this time?
          if ($z > $t)
          {
            $idleCnt++;
            if ($idleTime > $z) {
              $idleTime = $z;
            }
            continue;
          }
          # activate
          $p->_idle = $z = 0;
        }
        if ($p->_execute())
        {
          # one complete,
          # remove it from the queue
          $q1->shift();
          if ($q1->isEmpty())
          {
            # this column is finished
            unset($q0[key($q0)]);
            $n0--;
          }
          else
          {
            # initialize and probe next item
            $p = $q1->offsetGet(0);
            $p->_init() && $p->_execute();
          }
        }
        elseif ($z)
        {
          # became idle
          $idleCnt++;
          if ($idleTime > $z) {
            $idleTime = $z;
          }
        }
      }
      while ($q1 = prev($q0));
    }
    # }}}
    # spin the row
    # {{{
    if ($n1 = &$this->rowCnt)
    {
      # its important to maintain the
      # straight forward order of execution
      # because module gears that spin forever
      # are added before promises that
      # depend on their results..
      $q1 = $this->row;
      $q1->rewind();
      for ($k=$n1; $k; --$k)
      {
        $p = $q1->current();
        if ($z = $p->_idle)
        {
          # promise is idle,
          # skip for this time?
          if ($z > $t)
          {
            $q1->next();
            $idleCnt++;
            if ($idleTime > $z) {
              $idleTime = $z;
            }
            continue;
          }
          # activate
          $p->_idle = $z = 0;
        }
        if ($p->_execute())
        {
          # promise is settled,
          # remove it from the queue
          $q1->detach($p);
          $n1--;
        }
        else
        {
          # check became idle
          if ($z)
          {
            $idleCnt++;
            if ($idleTime > $z) {
              $idleTime = $z;
            }
          }
          # march to the next
          $q1->next();
        }
      }
    }
    # }}}
    # finished?
    if (!($k = $n0 + $n1)) {
      return 0;
    }
    # the process is in alertable wait state,
    # notify OS with traditional sleep (not usleep),
    # it should invoke any pending callbacks
    # TODO: probably make an explicit promise flag
    \sleep(0);
    # cooldown?
    if ($k === $idleCnt)
    {
      # determine projected sleep time
      $i = $idleTime - $t;
      # update high resolution timestamp and
      # determine cycle execution time
      Completable::$HRTIME = $j = hrtime(true);
      $j -= $t;
      # substract cycle execution and
      # convert into microseconds
      $i = (int)(($i - $j) / 1000);
      # sleep in big chunks
      while ($i > 500000)
      {
        usleep(500000);
        $i -= 500000;
      }
      # sleep the remaining chunk
      usleep(($i > 0) ? $i : 1);
    }
    return $k;
  }
  # }}}
  function stop(): void # {{{
  {
    # stop columns
    if ($this->colCnt)
    {
      $q0 = &$this->columns;
      $q1 = end($q0);
      do {
        $q1->offsetGet(0)->cancel();
      }
      while ($q1 = prev($q0));
      $q0 = [];
      $this->colCnt = 0;
    }
    # stop the row
    if ($n = $this->rowCnt)
    {
      $q1 = $this->row;
      $q1->rewind();
      while ($n--)
      {
        $q1->current()->cancel();
        $q1->next();
      }
      $this->row = new SplObjectStorage();
      $this->rowCnt = 0;
    }
  }
  # }}}
  static function gear(object $o): object # {{{
  {
    # loop gears are completables that
    # are initialized before their container,
    # this enables cancellation
    # without a single pass/execution
    $o->result = new PromiseResult();
    $p = new Promise($o);
    $p->_init($o->result);
    # gears are placed in the row with
    # other promises.. normally,
    # they execute indefinitely and
    # are removed upon cancellation
    self::$LOOP->rowAdd($p);
    return $p;
  }
  # }}}
  static function await(object $p): object # {{{
  {
    $LOOP = self::$LOOP->rowAdd($p);
    do {
      $LOOP->spin();
    }
    while ($p->pending);
    return $p->result;
  }
  # }}}
  static function await_any(array $a): int # {{{
  {
    # check number of elements
    if (($k = count($a)) < 2) {
      return -1;
    }
    # count pending
    for ($i=0,$j=0; $i < $k; ++$i)
    {
      # filter empty slots
      if (!$a[$i]) {
        continue;
      }
      # complete at first finished
      if (!$a[$i]->pending) {
        return $i;
      }
      # count
      $j++;
    }
    # check nothing is pending
    if (!$j) {
      return -1;
    }
    # add to the loop
    $LOOP = self::$LOOP;
    for ($i=0; $i < $k; ++$i)
    {
      if ($a[$i] && $a[$i]->pending < 0) {
        $LOOP->rowAdd($a[$i]);
      }
    }
    # execute
    while (1)
    {
      $LOOP->spin();
      for ($i=0; $i < $k; ++$i)
      {
        if ($a[$i] && !$a[$i]->pending) {
          return $i;
        }
      }
    }
    throw ErrorEx::fatal('unexpected');
  }
  # }}}
}
# }}}
abstract class Completable # {{{
{
  public ?object $result=null;
  function _cancel(): void {}
  abstract function _complete(): ?object;
  ###
  static object $THEN;# dynamic continuator
  static int    $TIME=0,$HRTIME=0;# current time
  static function from(?object $x): object
  {
    return $x
      ? (($x instanceof self)
        ? $x
        : (($x instanceof Closure)
          ? new PromiseOp($x)
          : (($x instanceof Error)
            ? new PromiseError($x)
            : new PromiseValue($x))))
      : new PromiseNop();
  }
}
# }}}
abstract class Contextable extends Completable # {{{
{
  abstract function _done(): void;
}
# }}}
abstract class Reversible extends Completable # {{{
{
  abstract function _undo(): void;
}
# }}}
# actions {{{
abstract class PromiseAction extends Completable # {{{
{
  function repeat(int $ms=0): object {
    return $ms ? self::$THEN->wait($ms) : $this;
  }
  function abort(): object {
    return self::$THEN->abort();
  }
  function __call(string $m, array $a): mixed {
    return $this->result->context->$m(...$a);
  }
}
# }}}
class PromiseOp extends PromiseAction # {{{
{
  function __construct(
    public object $func
  ) {}
  function _complete(): ?object
  {
    try {
      return ($this->func)($this);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e, true);
    }
  }
}
# }}}
class PromiseFunc extends PromiseAction # {{{
{
  function __construct(
    public object $func,
    public array  $arg,
  ) {}
  function _complete(): ?object
  {
    try {
      return ($this->func)($this, ...$this->arg);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e, true);
    }
  }
}
# }}}
class PromiseCall extends PromiseFunc # {{{
{
  function _complete(): ?object
  {
    try {
      return ($this->func)(...$this->arg);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e, true);
    }
  }
}
# }}}
# }}}
# action helpers {{{
class PromiseNop extends Completable # {{{
{
  function _complete(): ?object {
    return null;
  }
}
# }}}
class PromiseError extends PromiseNop # {{{
{
  function __construct(
    public object $error
  ) {}
  function _complete(): ?object
  {
    $this->result->error($this->error);
    return self::$THEN->hop();
  }
}
# }}}
class PromiseContext extends PromiseNop # {{{
{
  function __construct(
    public object $context
  ) {}
  function _complete(): ?object
  {
    # check current
    if (($r = $this->result)->context)
    {
      # check for equality (dont switch)
      if ($r->context === $this->context) {
        return self::$THEN->hop();
      }
      # switch
      $r->context->_done();
    }
    # continue
    return $r->context = $this->context;
  }
}
# }}}
class PromiseValue extends PromiseNop # {{{
{
  function __construct(
    public mixed $value
  ) {}
  function _complete(): ?object
  {
    $this->result->extend()->setRef($this->value);
    return self::$THEN->hop();
  }
}
# }}}
class PromiseWhen extends PromiseNop # {{{
{
  function __construct(
    public bool   $ok,
    public object $action
  ) {}
  function _complete(): ?object
  {
    # select action when condition met
    $a = ($this->result->ok === $this->ok)
      ? $this->action
      : null;
    # hop to the next or selected action
    return self::$THEN->hop($a);
  }
}
# }}}
class PromiseFuse extends PromiseNop # {{{
{
  function __construct(
    public object $action
  ) {}
  function _complete(): ?object
  {
    $this->result->_fuse();
    return self::$THEN->fuse($this->action);
  }
}
# }}}
class PromiseTimeout extends PromiseNop # {{{
{
  const MAX_DELAY = 24*60*60*1000;# millisec
  static function check(int $ms): ?object # {{{
  {
    static $E0='incorrect delay, less than zero';
    static $E1='incorrect delay, greater than maximum';
    if ($ms < 0) {
      return ErrorEx::fail($E0, $ms);
    }
    if ($ms > self::MAX_DELAY) {
      return ErrorEx::fail($E1, $ms);
    }
    return null;# good
  }
  # }}}
  function __construct(# {{{
    public int     $delay,
    public ?object $action
  ) {
    ErrorEx::peep(self::check($delay));
  }
  # }}}
  function _complete(): ?object # {{{
  {
    # delay once
    if ($ms = $this->delay)
    {
      $this->delay = 0;
      return self::$THEN->wait($ms);
    }
    # continue
    return self::$THEN->hop($this->action);
  }
  # }}}
}
# }}}
# }}}
# action groups {{{
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
  function _complete(): ?object # {{{
  {
    # prepare
    $q = &$this->group;
    $i = &$this->idx;
    # check finished
    if ($i >= ($j = $this->cnt)) {
      return null;
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
    if (!($r = $p->_execute()))
    {
      return $p->_idle
        ? self::$THEN->idle($p->_idle)
        : $this;
    }
    # one complete,
    # collect reversible
    $p->_reverse && $this->_reverseAdd($p);
    # check all complete
    if (++$i >= $j)
    {
      $this->result->_columnEnd($i);
      return null;
    }
    # check breakable failed
    if ($this->break && !$r->ok)
    {
      if (--$this->break) {
        return $this;# more to break
      }
      $this->_cancel();
      return null;
    }
    # continue
    return $this;
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
  function _complete(): ?object # {{{
  {
    # prepare
    $q = &$this->group;
    $i = &$this->idx;
    # check finished
    if ($i >= ($j = $this->cnt)) {
      return null;
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
    $idleTime = self::$HRTIME + Loop::MAX_TIMEOUT;
    $idleCnt  = 0;
    # execute all
    for ($k=0; $k < $j; ++$k)
    {
      # checkout
      if (!($p = $q[$k]))
      {
        $idleCnt++;
        continue;
      }
      # execute
      if (!($r = $p->_execute()))
      {
        if ($p->_idle)
        {
          $idleCnt++;
          if ($idleTime > $p->_idle) {
            $idleTime = $p->_idle;
          }
        }
        continue;
      }
      # one complete
      $q[$k] = $this->result->_rowDone($r, $k, ++$i);
      $idleCnt++;
      # collect reversibles
      $p->_reverse && $this->_reverseAdd($p);
      # check break condition
      if ($this->break && !$r->ok)
      {
        if (--$this->break) {
          continue;# more to break
        }
        $this->_cancel();
        return null;
      }
      # check race condition
      if ($this->first)
      {
        if (--$this->first) {
          continue;# more to come
        }
        $this->_cancel();
        return null;
      }
    }
    # complete idle, active or finished
    return ($i < $j)
      ? (($idleCnt === $j)
        ? self::$THEN->idle($idleTime)
        : $this)
      : null;
  }
  # }}}
}
# }}}
# }}}
# result {{{
class PromiseResult
  implements ArrayAccess,Loggable
{
  # constructor {{{
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
  public ?object $context=null;
  public int     $time,$status=0;
  public object  $track;
  public bool    $ok;
  public array   $store=[null];
  public mixed   $value;
  ###
  function __construct(?array &$x=null)
  {
    $this->time  = Completable::$TIME;
    $this->track = new PromiseResultTrack();
    $this->ok    = &$this->track->ok;
    $this->setRef($x);
  }
  # }}}
  # hlp {{{
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
    if ($this->status || !$this->track->title) {
      return $this->track;
    }
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
      self::IS_COLUMN, $r, 0, $total,
      Completable::$HRTIME
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
    $e[4] = Completable::$HRTIME - $e[4];
    ###
    if (!$r->ok && $this->ok) {
      $this->ok = false;
    }
    array_pop($r->store);
    $this->extend()->setRef($r->store);
  }
  # }}}
  function _row(array &$q, int $total): int # {{{
  {
    for ($v=[],$i=0; $i < $total; ++$i) {
      $v[$i] = &$q[$i]->result->store;
    }
    $this->_track()->trace[] = [
      self::IS_ROW, [], 0, $total,
      Completable::$HRTIME
    ];
    $this->extend()->setRef($v);
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
      $e[4] = Completable::$HRTIME - $e[4];
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
    $e[4] = Completable::$HRTIME - $e[4];
    return $total;
  }
  # }}}
  function _fuse(): self # {{{
  {
    if (!($t0 = $this->track)->title) {
      $t0->span = Completable::$HRTIME - $t0->span;
    }
    $t1 = new PromiseResultTrack();
    $this->track = $t1;
    $this->ok    = &$t1->ok;
    $t1->trace[] = [self::IS_FUSION, $t0];
    return $this;
  }
  # }}}
  function _cancel(): self # {{{
  {
    if (!($t0 = $this->track)->title) {
      $t0->span = Completable::$HRTIME - $t0->span;
    }
    $t1 = new PromiseResultTrack(null, false);
    $this->track  = $t1;
    $this->ok     = &$t1->ok;
    $this->status = -1;
    $t1->trace[]  = [self::IS_CANCELLATION, $t0];
    return $this;
  }
  # }}}
  function _done(object $p): self # {{{
  {
    if (!$this->track->title) {
      $this->confirm('{}');
    }
    if ($this->context)
    {
      $this->context->_done();
      $this->context = null;
    }
    if (!$this->status) {
      $this->status = 1;
    }
    $p->_done && ($p->_done)($this);
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
  # api {{{
  function extend(): self # {{{
  {
    array_unshift($this->store, null);
    $this->value = &$this->store[0];
    return $this;
  }
  # }}}
  function setRef(mixed &$value): self # {{{
  {
    $this->store[0] = &$value;
    $this->value    = &$this->store[0];
    return $this;
  }
  # }}}
  function set(mixed $value): self # {{{
  {
    return $this->extend()->setRef($value);
  }
  # }}}
  function info(...$msg): void # {{{
  {
    $this->_track()->trace[] = [
      self::IS_INFO, ErrorEx::stringify($msg)
    ];
  }
  # }}}
  function warn(...$msg): void # {{{
  {
    $this->_track()->trace[] = [
      self::IS_WARNING, ErrorEx::stringify($msg)
    ];
  }
  # }}}
  function fail(...$msg): void # {{{
  {
    $this->_track()->trace[] = [
      self::IS_FAILURE, ErrorEx::stringify($msg)
    ];
    $this->ok = false;
  }
  # }}}
  function error(object $e): void # {{{
  {
    $this->_track()->trace[] = [
      self::IS_ERROR, ErrorEx::set($e)
    ];
    if ($e->hasError() && $this->ok) {
      $this->ok = false;
    }
  }
  # }}}
  function confirm(...$msg): void # {{{
  {
    if (($t = $this->_track())->title) {
      $t->title = ErrorEx::stringify($msg);
    }
    else
    {
      $t->title = ErrorEx::stringify($msg);
      $t->span  = Completable::$HRTIME - $t->span;
    }
  }
  # }}}
  # }}}
}
class PromiseResultTrack
{
  function __construct(# {{{
    public ?object $prev  = null,
    public bool    $ok    = true,
    public ?array  $title = null,
    public array   $trace = [],
    public int     $span  = 0
  ) {
    $this->span = Completable::$HRTIME;
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
# continuator {{{
if (!isset(Completable::$THEN))
{
  Completable::$THEN = new class() extends PromiseNop
  {
    const DEF_DELAY = 50000000;# 50ms in nano
    public int     $id=0,$time=0;
    public ?object $action=null;
    # setters
    function wait(int $ms=0): object # {{{
    {
      $this->id = 1;
      $this->time = self::$HRTIME + ($ms
        ? (int)($ms * 1000000) # milli => nano
        : self::DEF_DELAY
      );
      return $this;
    }
    # }}}
    function nanowait(int $ns): object # {{{
    {
      $this->id = 1;
      $this->time = self::$HRTIME + $ns;
      return $this;
    }
    # }}}
    function idle(int $ns): self # {{{
    {
      $this->id = 1;
      $this->time = $ns;
      return $this;
    }
    # }}}
    function hop(?object $action=null): self # {{{
    {
      if ($action)
      {
        $this->id = 3;
        $this->action = $action;
      }
      else {
        $this->id = 2;
      }
      return $this;
    }
    # }}}
    function fuse(object $action): self # {{{
    {
      $this->id = 4;
      $this->action = $action;
      return $this;
    }
    # }}}
    function abort(): self # {{{
    {
      $this->id = 5;
      return $this;
    }
    # }}}
    function done(): self # {{{
    {
      $this->id = 6;
      return $this;
    }
    # }}}
    # getters
    function getId(): int # {{{
    {
      $id = $this->id;
      $this->id = 0;
      return $id;
    }
    # }}}
    function getAction(): object # {{{
    {
      $a = $this->action;
      $this->action = null;
      return $a;
    }
    # }}}
  };
}
# }}}
return Loop::init();
###