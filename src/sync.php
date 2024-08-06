<?php declare(strict_types=1);
# defs {{{
namespace SM;
use
  SyncSharedMemory,SyncSemaphore,SyncEvent,
  Throwable,ArrayAccess;
use function
  is_bool,is_int,is_string,is_object,is_callable,
  is_dir,intval,strval,preg_match,
  strlen,substr,str_repeat,pack,unpack,rtrim,
  array_shift;
use const
  DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
# }}}
# primitives {{{
class SyncBuffer # {{{
{
  # basis {{{
  public object $mem;
  function __construct(
    public string $id,
    public int    $size,
  ) {
    if ($size < 1 || $size > 0xFFFFFF) {
      throw ErrorEx::fatal(__CLASS__, $size);
    }
    $mem = new SyncSharedMemory($id, 4 + $size);
    if ($mem->first()) {
      $mem->write("\x00\x00\x00\x00");
    }
    $this->mem = $mem;
  }
  # }}}
  function size(): int # {{{
  {
    $n = unpack('L', $this->mem->read(0, 4))[1];
    return ($n === 0xFFFFFFFF) ? -1 : $n;
  }
  # }}}
  function isEmpty(): bool # {{{
  {
    return (
      $this->mem->read(0, 4) === "\x00\x00\x00\x00"
    );
  }
  # }}}
  function state(): int # {{{
  {
    return match ($this->mem->read(0, 4))
    {
      "\xFF\xFF\xFF\xFF" => 2,
      "\x00\x00\x00\x00" => 0,
      default => 1
    };
  }
  # }}}
  function read(int $size): string # {{{
  {
    return $this->mem->read(4, $size);
  }
  # }}}
  function write(string $data, int $len, int $offs=0): int # {{{
  {
    # prepare
    $mem  = $this->mem;
    $size = $this->size - $offs;
    # check data fits into the buffer
    if ($len <= $size)
    {
      $mem->write(pack('L', $offs + $len), 0);
      $mem->write($data, 4 + $offs);
      return $len;
    }
    # overflow, write first chunk
    $mem->write("\xFF\xFF\xFF\xFF", 0);
    $mem->write(substr($data, 0, $size), 4 + $offs);
    return $size;
  }
  # }}}
  function append(string $data, int $len): int # {{{
  {
    return $this->write($data, $len,
      unpack('L', $this->mem->read(0, 4))[1]
    );
  }
  # }}}
  function clear(): void # {{{
  {
    $this->mem->write("\x00\x00\x00\x00", 0);
  }
  # }}}
}
# }}}
class SyncNum implements ArrayAccess # {{{
{
  # basis {{{
  public object $mem;
  function __construct(
    public string  $id,
    public int     $count=1
  ) {
    $this->mem = $mem = new SyncSharedMemory(
      $id, 4*$count
    );
    $mem->first() && $this->reset();
  }
  # }}}
  # [] access {{{
  function offsetExists(mixed $k): bool {
    return ($k >= 0 || $k < $this->count);
  }
  function offsetGet(mixed $k): mixed {
    return $this->get($k);
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->set($v, $k);
  }
  function offsetUnset(mixed $k): void {
    $this->set(0, $k);
  }
  # }}}
  # dynamis {{{
  function get(int $k=0): int {
    return unpack('L', $this->mem->read(4*$k, 4))[1];
  }
  function set(int $x, int $k=0): void {
    $this->mem->write(pack('L', $x), 4*$k);
  }
  function reset(): void
  {
    $this->mem->write(
      str_repeat("\x00\x00\x00\x00", $this->count)
    );
  }
  function tryGet(int $k=0, ?object &$error=null): int
  {
    try {
      return $this->get($k);
    }
    catch (Throwable $e)
    {
      ErrorEx::set($error, $e);
      return -1;
    }
  }
  function trySet(int $x, int $k=0): ?object
  {
    try {
      return $this->set($x, $k);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  function tryReset(): ?object
  {
    try {
      return $this->reset();
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  # }}}
}
# }}}
class SyncLock # {{{
{
  # basis {{{
  public object $num,$sem;
  public int    $locked=0;
  function __construct(
    public string $id,
    public int    $max    = 1,
    public int    $weight = 1
  ) {
    $this->num = new SyncNum($id);
    $this->sem = new SyncSemaphore($id.'-sem', 1, 0);
  }
  function __destruct()
  {
    try {$this->unlock();}
    catch (Throwable) {}
  }
  # }}}
  # dynamis {{{
  function get(): int {
    return $this->num->get();
  }
  function set(): bool
  {
    # check locked by this instance
    if ($this->locked) {
      return true;
    }
    # check locked by another instance
    $m = $this->max;
    $w = $this->weight;
    $n = ($num = $this->num)->get();
    if ($n + $w  > $m) {
      return false;
    }
    # set the guard
    if (!($sem = $this->sem)->lock(1000)) {
      throw ErrorEx::fatal('SyncSemaphore::lock');
    }
    # determine increment and check again
    if (($n = $num->get() + $w) > $m)
    {
      if (!$sem->unlock()) {
        throw ErrorEx::fatal('SyncSemaphore::unlock');
      }
      return false;
    }
    # complete
    $num->set($n);
    $this->locked = $w;
    if (!$sem->unlock()) {
      throw ErrorEx::fatal('SyncSemaphore::unlock');
    }
    return true;
  }
  function clear(): bool
  {
    # check not locked
    if (!($w = $this->locked)) {
      return true;
    }
    # set the guard
    if (!($sem = $this->sem)->lock(1000)) {
      throw ErrorEx::fatal('SyncSemaphore::lock');
    }
    # determine decrement
    $num = $this->num;
    if (($n = $num->get() - $w) < 0) {
      $n = 0;
    }
    # complete
    $num->set($n);
    $this->locked = 0;
    if (!$sem->unlock()) {
      throw ErrorEx::fatal('SyncSemaphore::unlock');
    }
    return true;
  }
  # }}}
}
# }}}
# }}}
# abstract ReaderWriter {{{
abstract class Sync_ReaderWriter # {{{
{
  const
    DEF_SIZE = 500,# bytes
    MAX_SIZE = 1000000;# bytes
  ###
  abstract static function new(array $o):object;
  ###
  static function o_id(array $o): string # {{{
  {
    static $EXP_ID='/^[a-z0-9-]{1,64}$/i';
    static $k='id';
    if (!isset($o[$k]) || ($id = $o[$k]) === '' ||
        !preg_match($EXP_ID, $id))
    {
      throw self::o_fail($k);
    }
    return $id;
  }
  # }}}
  static function o_size(array $o): int # {{{
  {
    if (!isset($o[$k = 'size'])) {
      return self::DEF_SIZE;
    }
    if (!is_int($i = $o[$k])) {
      throw self::o_fail($k);
    }
    if ($i < 1 || $i > self::MAX_SIZE) {
      throw self::o_fail($k);
    }
    return $i;
  }
  # }}}
  static function o_fail(string $k): object # {{{
  {
    return ErrorEx::fail(
      'option "'.$k.'" is incorrect'
    );
  }
  # }}}
}
# }}}
abstract class Sync_ReaderWriterOp extends Contextable # {{{
{
  # basis {{{
  const
    IDLE_THRESHOLD = 5000,
    IDLE_TIME = 1*1000000,# ms ~ ns
    WAIT_DATA = 100*1000000;# ms ~ ns
  public string
    $value='';
  public int
    $stage=1,$size=0,$waits=0,
    $time=PHP_INT_MAX,$timeout=0;
  public bool
    $yielding=false,$partial=false;
  ###
  function __construct(
    public ?object $base,
    int $timeout=0
  ) {
    if ($timeout) {# set and convert ms to ns
      $this->timeout = (int)(1000000 * $timeout);
    }
  }
  # }}}
  function _yielding(bool $set): self # {{{
  {
    if ($set)
    {
      # activate yielding
      if (!$this->yielding)
      {
        Loop::yield_more();
        $this->yielding = true;
      }
    }
    elseif ($this->yielding)
    {
      # deactivate
      Loop::yield_less();
      $this->yielding = false;
    }
    return $this;
  }
  # }}}
  function _init(): bool # {{{
  {
    if ($this->base->op) {
      return $this->_baseOpExists();
    }
    $this->result->promiseContextSet(
      $this->base->op = $this
    );
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _baseOpExists(): bool # {{{
  {
    $this->result->fail(
      'already running: '.
      $this->base->op::class."\n".
      'operations must execute sequentially'
    );
    return $this->_done();
  }
  # }}}
  function _timeSet(): bool # {{{
  {
    $this->waits = 0;
    $this->time  = self::$HRTIME;
    return false;
  }
  # }}}
  function _timeWait(): bool # {{{
  {
    # first do active waiting, then become idle
    if ($this->waits < self::IDLE_THRESHOLD)
    {
      $this->waits++;
      $this->result->promiseDelayNs(self::IDLE_TIME);
    }
    else {
      $this->result->promiseIdle();
    }
    return false;
  }
  # }}}
  function _timeCheck(int $t): bool # {{{
  {
    if ($this->time + $t > self::$HRTIME) {
      return false;
    }
    $this->result->fail(
      'timed out ('.(int)($t / 1000000).'ms)'
    );
    return $this->_done();
  }
  # }}}
  function _timeCheckWait(): bool # {{{
  {
    $t = $this->timeout;
    if ($t && $this->_timeCheck($t)) {
      return true;
    }
    $this->result->promiseDelayNs(self::IDLE_TIME);
    return false;
  }
  # }}}
  function _dataSet(): bool # {{{
  {
    # write the value
    $n = $this->size;
    $i = $this->base->data->write($this->value, $n);
    # update size and value
    if ($this->size = $n - $i) {
      $this->value = substr($this->value, $i);
    }
    else {# everything was written
      $this->value = '';
    }
    return false;
  }
  # }}}
  function _dataSetFirst(): bool # {{{
  {
    $this->stage++;
    return $this->_yielding(true)->_dataSet();
  }
  # }}}
  function _dataSetRest(): bool # {{{
  {
    # check dirty (not read yet)
    if (!$this->base->data->isEmpty()) {
      return $this->_timeCheck(self::WAIT_DATA);
    }
    # check more bytes to write
    if ($this->size) {
      return $this->_dataSet();
    }
    # everything is written and read
    $this->stage++;
    return $this->_yielding(true)->_complete();
  }
  # }}}
  function _dataGet(): bool # {{{
  {
    $data = $this->base->data;
    if (($n = $data->size()) === 0) {
      return $this->_timeCheck(self::WAIT_DATA);
    }
    # complete and move to the next stage
    # when all the data received
    if ($n > 0)
    {
      $this->size  += $n;
      $this->value .= $data->read($n);
      $this->stage++;
      $data->clear();
      return $this->_complete();
    }
    # accumulate and repeat otherwise
    $this->size  += $n = $data->size;
    $this->value .= $data->read($n);
    $data->clear();
    return $this->_timeSet();
  }
  # }}}
}
# }}}
# }}}
# Exchange: reader + writer {{{
class SyncExchange extends Sync_ReaderWriter # {{{
{
  public ?object $op=null;
  private function __construct(
    public string $id,
    public object $data,
    public object $state
  ) {}
  static function new(array $o): object
  {
    try
    {
      $id   = self::o_id($o);
      $size = self::o_size($o);
      return new self($id,
        new SyncBuffer($id, $size),
        new SyncNum($id.'-x'),
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  function server(): object {
    return new Promise(new SyncExchange_Server($this));
  }
  function client(): object {
    return new Promise(new SyncExchange_Client($this));
  }
}
# }}}
abstract class SyncExchange_Op extends Sync_ReaderWriterOp # {{{
{
  public int $state=0;
  function _enter(): bool # {{{
  {
    $this->_timeSet();
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _stateWaitFirst(int $x): bool # {{{
  {
    if ($x === $this->base->state->get())
    {
      $this->stage++;
      return $this->_complete();
    }
    return $this->_timeWait();
  }
  # }}}
  function _stateWait(int $x, int $y): bool # {{{
  {
    switch ($n = $this->base->state->get()) {
    case $x:# stasis
      return $this->_timeCheckWait();
    case $y:# activation
      $this->_state(0);
      $this->_timeSet();
      $this->stage++;
      return $this->_complete();
    }
    $this->result->fail(
      "protocol is out of sync\n".
      "unexpected state=".$n
    );
    return $this->_done();
  }
  # }}}
  function _stateSet(int $x): bool # {{{
  {
    $this->base->state->set($this->_state($x));
    $this->stage++;
    return $this->_timeSet();
  }
  # }}}
  function _state(int $v): int # {{{
  {
    $this->_yielding($v !== 0);
    return $this->state = $v;
  }
  # }}}
  function _stop(int $stage): bool # {{{
  {
    $this->stage = $stage;
    return true;
  }
  # }}}
  function _stopReading(int $stage): bool # {{{
  {
    # set the result
    $this->result->value = $this->value;
    # reset
    $this->_yielding(false);
    $this->partial = false;
    $this->size    = 0;
    $this->value   = '';
    $this->stage   = $stage;
    return true;
  }
  # }}}
  function _done(): bool # {{{
  {
    if (!$this->stage) {
      return true;
    }
    $this->_yielding(false);
    if ($this->state) {
      $this->base->state->set($this->_state(0));
    }
    if ($this->stage > 1)
    {
      $this->base->op = null;
      $this->result->promiseContextClear();
    }
    $this->result->confirm(
      static::class, $this->base->id,
      'stage='.$this->stage.','.
      'index='.$this->result->index
    );
    $this->base  = $this->result = null;
    $this->stage = 0;
    return true;
  }
  # }}}
  ###
  function read(int $timeout=0): ?object # {{{
  {
    if ($this->stage !== static::STAGE_READ)
    {
      $this->result->error(ErrorEx::fatal_up(
        2, __FUNCTION__, 'incorrect stage'
      ));
      $this->_done();
      return null;
    }
    if ($timeout) {
      $this->timeout = (int)(1000000 * $timeout);
    }
    return $this->result
      ->indexPlus()
      ->promisePrependOne($this);
  }
  # }}}
  function write(string $data, int $timeout=0): ?object # {{{
  {
    if ($this->stage === static::STAGE_READ)
    {
      $this->result->error(ErrorEx::fatal_up(
        2, __FUNCTION__, 'incorrect stage'
      ));
      $this->_done();
      return null;
    }
    if ($timeout) {
      $this->timeout = (int)(1000000 * $timeout);
    }
    $this->size  = strlen($data);
    $this->value = $data;
    return $this->result
      ->indexPlus()
      ->promisePrependOne($this);
  }
  # }}}
  function hangup(): void # {{{
  {
    $this->_done();
  }
  # }}}
}
# }}}
class SyncExchange_Server extends SyncExchange_Op # {{{
{
  const STAGE_READ=6;
  function _complete(): bool
  {
    return match ($this->stage) {
      ### server entry
      1  => $this->_init(),
      ### read first (?=>1=>2)
      2  => $this->_stateWaitFirst(1),# infinite
      3  => $this->_stateSet(2),
      4  => $this->_dataGet(),
      5  => $this->_stopReading(11),
      ### read next (4=>5=>2)
      6  => $this->_enter(),
      7  => $this->_stateWait(4, 5),# timeout
      8  => $this->_stateSet(2),
      9  => $this->_dataGet(),
      10 => $this->_stopReading(11),
      ### write (3=>4)
      11 => $this->_stateSet(3),
      12 => $this->_stateWait(3, 4),# timeout
      13 => $this->_dataSetFirst(),
      14 => $this->_dataSetRest(),
      15 => $this->_stop(6),
      default => true
    };
  }
  function reset(): void
  {
    # always clear the state
    $this->base->state->set($this->_state(0));
    $this->partial = false;
    $this->size    = 0;
    $this->value   = '';
    $this->stage   = 2;
    $this->result->promisePrependOne($this);
  }
}
# }}}
class SyncExchange_Client extends SyncExchange_Op # {{{
{
  const STAGE_READ=13;
  function _complete(): bool
  {
    return match ($this->stage) {
      ### client entry
      1  => $this->_init(),
      2  => $this->_stop(3),
      ### write first (1=>2)
      3  => $this->_stateSet(1),
      4  => $this->_stateWait(1, 2),# timeout
      5  => $this->_dataSetFirst(),
      6  => $this->_dataSetRest(),
      7  => $this->_stop(13),
      ### write next (5=>2)
      8  => $this->_stateSet(5),
      9  => $this->_stateWait(5, 2),# timeout
      10 => $this->_dataSetFirst(),
      11 => $this->_dataSetRest(),
      12 => $this->_stop(13),
      ### read (2=>3=>4)
      13 => $this->_enter(),
      14 => $this->_stateWait(2, 3),# timeout
      15 => $this->_stateSet(4),
      16 => $this->_dataGet(),
      17 => $this->_stopReading(8),
      default => true
    };
  }
}
# }}}
# }}}
# Aggregate: reader + writers {{{
class SyncAggregate extends Sync_ReaderWriter # {{{
{
  public ?object $op=null;
  private function __construct(
    public string $id,
    public object $data,
    public object $lock
  ) {}
  static function new(array $o): object
  {
    try
    {
      $id   = self::o_id($o);
      $size = self::o_size($o);
      return new self($id,
        new SyncBuffer($id, $size),
        new SyncLock($id.'-lock')
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  function read(): object {
    return new Promise(new SyncAggregate_Read($this));
  }
  function write(string $data): object
  {
    $o = new SyncAggregate_Write($this);
    $o->value = $data;
    $o->size  = strlen($data);
    return new Promise($o);
  }
}
# }}}
class SyncAggregate_Read extends Sync_ReaderWriterOp # {{{
{
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      1 => $this->_init(),
      2 => $this->_dataWait(4),
      3 => $this->_lock(),
      4 => $this->_dataGet(),
      5 => $this->_unlock(2),
      default => true
    };
  }
  # }}}
  function _dataWait(int $stage): bool # {{{
  {
    switch ($this->base->data->state()) {
    case 0:
      return $this->_timeWait();
    case 1:
      $this->stage++;
      break;
    case 2:
      $this->partial = true;
      $this->stage   = $stage;
      break;
    }
    return $this->_yielding(true)->_complete();
  }
  # }}}
  function _lock(): bool # {{{
  {
    if ($this->base->lock->set())
    {
      $this->stage++;
      return $this->_complete();
    }
    return false;
  }
  # }}}
  function _unlock(int $stage): bool # {{{
  {
    # parse the value
    $a = [];
    $i = 0;
    $n = $this->size;
    $v = $this->value;
    do
    {
      $j   = unpack('L', substr($v, $i, 4))[1];
      $i  += 4;
      $a[] = substr($v, $i, $j);
      $i  += $j;
    }
    while ($i < $n);
    # set the result
    $this->result->value = $a;
    # reset
    $this->partial = false;
    $this->value   = '';
    $this->size    = 0;
    # unlock and suspend at the given stage
    $this->_yielding(false)->base->lock->clear();
    $this->stage = $stage;
    return true;
  }
  # }}}
  function _done(): bool # {{{
  {
    switch ($this->stage) {
    case 5:
    case 4:
      $this->base->lock->clear();
    case 3:
      $this->_yielding(false);
    case 2:
      $this->base->op = null;
      $this->result
      ->promiseContextClear()
      ->confirm(
        $this->base::class,
        $this->base->id, 'read'
      );
    case 1:
      $this->base = $this->result = null;
    }
    $this->stage = 0;
    return true;
  }
  # }}}
  function reset(): void # {{{
  {
    $this->result->promisePrependOne($this);
  }
  # }}}
}
# }}}
class SyncAggregate_Write extends Sync_ReaderWriterOp # {{{
{
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      1 => $this->_init(),
      2 => $this->_enter(),
      3 => $this->_lock(),
      4 => $this->_dataAppend(6),
      5 => $this->_dataSetRest(),
      default => $this->_done()
    };
  }
  # }}}
  function _enter(): bool # {{{
  {
    # prepend data with its length
    $this->value = pack('L', $this->size).$this->value;
    $this->size += 4;
    # activate yielding and
    # immediately proceed to the next stage
    $this->stage++;
    return $this->_yielding(true)->_complete();
  }
  # }}}
  function _lock(): bool # {{{
  {
    if ($this->base->lock->set())
    {
      $this->stage++;
      return $this->_complete();
    }
    return false;
  }
  # }}}
  function _dataAppend(int $stage): bool # {{{
  {
    # write the value
    $n = $this->size;
    $i = $this->base->data->append($this->value, $n);
    # check everything is written
    if ($i === $n)
    {
      $this->stage = $stage;
      return $this->_complete();
    }
    # more chunks to write
    $this->value = substr($this->value, $i);
    $this->size  = $n - $i;
    # move to the next stage
    $this->stage++;
    return false;
  }
  # }}}
  function _done(): bool # {{{
  {
    switch ($this->stage) {
    case 6:
    case 5:
    case 4:
      if ($this->stage < 6) {
        $this->base->data->clear();
      }
      $this->base->lock->clear();
    case 3:
      $this->_yielding(false);
    case 2:
      $this->base->op = null;
      $this->result
      ->promiseContextClear()
      ->confirm(
        $this->base::class,
        $this->base->id, 'write'
      );
    case 1:
      $this->base = $this->result = null;
    }
    $this->stage = 0;
    return true;
  }
  # }}}
}
# }}}
# }}}
/*** TODO {{{
class SyncBroadcastMaster extends SyncReaderWriter # {{{
{
  # Broadcast: one writer, many readers
  # constructor {{{
  static function new(array $o): object
  {
    try
    {
      $id   = self::o_id($o);
      $size = self::o_size($o);
      $flag = ErrorEx::peep(SyncFlagMaster::new(
        $id.'-master', self::o_dir($o)
      ));
      $info = ErrorEx::peep(SyncExchange::new([
        'id'   => $id.'-info',
        'size' => 200,
      ]));
      return new self(
        $id, $flag, $info,
        ErrorEx::peep(SyncBuffer::new($id, $size)),
        self::o_callback($o)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        class_basename(self::class), __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string  $id,
    public ?object $writer,
    public object  $info,
    public object  $data,
    public ?object $callback,
    public array   $queue  = [],
    public array   $reader = [],
    public array   $state  = []
  ) {}
  # }}}
  # hlp {{{
  protected function isReady(?object &$error): bool # {{{
  {
    # check data is not pending
    if (!$this->time) {
      return true;
    }
    # prepare
    $f = $this->callback;
    $x = hrtime_expired(
      self::DEF_TIMEWAIT, $this->time
    );
    $n = 0;
    # count pending readers
    foreach ($this->reader as $id => &$a)
    {
      # check escaped (closed without a notice)
      if (!$a[0]->getShared())
      {
        unset($this->reader[$id]);
        $f && $f(0, $id, 'escape');
      }
      # check charged and pending
      if ($a[2] && $a[1]->getShared())
      {
        # check timeout
        if ($x)
        {
          $a[1]->clearShared($error);
          unset($this->reader[$id]);
          $f && $f(0, $id, 'timeout');
        }
        else {
          $n++;# count pending
        }
      }
    }
    # check still pending
    if ($n) {
      return false;
    }
    # reset and count readers that read the data
    foreach ($this->reader as &$a)
    {
      if ($a[2])
      {
        $a[2] = 0;
        $n++;
      }
    }
    # complete
    $this->time = 0;
    $f && $f(4, '*', strval($n));
    return true;
  }
  # }}}
  protected function dataWrite(# {{{
    string &$data, ?object &$error
  ):bool
  {
    # check data fits into the buffer
    $i = strlen($data);
    $j = $this->data->sizeMax;
    if ($i > $j)
    {
      $e = ErrorEx::warn(
        'unable to fit the data('.$i.') '.
        'into the buffer('.$j.')'
      );
      return ErrorEx
        ::set($error, $e)
        ->val(false);
    }
    # write
    if ($this->data->write($data, $error) < 0) {
      return false;
    }
    # enter pending state
    $this->time = hrtime(true);
    foreach ($this->reader as &$a)
    {
      $a[1]->setShared($error);
      $a[2] = 1;
    }
    return true;
  }
  # }}}
  static function parseInfo(string &$s, ?object &$e): ?array # {{{
  {
    static $ERR='incorrect info format';
    static $EXP_INFO = (
      '/^'.
      '([0-9]{1})'.         # case
      ':([a-z0-9-]{1,128})'.# id
      '(:(.+)){0,1}'.       # info
      '$/i'
    );
    if (preg_match($EXP_INFO, $s, $a)) {
      return [intval($a[1]),$a[2],$a[4]??''];
    }
    ErrorEx::set($e, ErrorEx::fail($ERR, $s));
    return null;
  }
  # }}}
  protected function infoRead(?object &$error): ?array # {{{
  {
    if (($a = $this->info->read($error)) &&
        ($b = self::parseInfo($a, $error)))
    {
      return $b;
    }
    return null;
  }
  # }}}
  protected function readerDetach(# {{{
    string $id, ?object &$error
  ):void
  {
    # invalidate reader object
    $this->reader[$id][1]->clearShared($error);
    unset($this->reader[$id]);
    $this->state = [];
    # close exchange
    $this->info->pending &&
    $this->info->close($error);
  }
  # }}}
  protected function flushState(?object &$error): bool # {{{
  {
    # check no reader state
    if (!($s = &$this->state)) {
      return true;
    }
    # get info
    if (!($a = $this->infoRead($error)))
    {
      # invalidate upon error or lack of activity
      if ($error || !$this->info->pending) {
        $this->readerDetach($id, $error);
      }
      return false;
    }
    # match operation and identifier
    if ($s[0] !== $a[0] || $s[1] !== $a[1])
    {
      $error = ErrorEx::fail(
        'reader='.$s[1].' operation='.$s[0].
        ' does not match info='.$a[0].':'.$a[1]
      );
      $this->readerDetach($id, $error);
      return false;
    }
    # operate
    switch ($a[0]) {
    case 1:# attachment
      break;
    case 2:# retransmission
      # buffer is populated with the data,
      # activate all readers except the one,
      # which initiated the retransmission
      $this->time = hrtime(true);
      foreach ($this->reader as $id => &$reader)
      {
        if ($s[1] === $id) {
          continue;
        }
        $reader[1]->setShared($error);
        $reader[2] = 1;
      }
      break;
    }
    # clear state
    $s = [];
    # invoke user callback
    if ($fn = $this->callback) {
      $fn($a[0], $a[1], $a[2]);
    }
    return false;
  }
  # }}}
  protected function flushInfo(?object &$error): bool # {{{
  {
    # checkout info
    if (($a = $this->infoRead($error)) === null) {
      return true;
    }
    # check reader is not attached
    if ($a[0] !== 1 &&
        !isset($this->reader[$a[1]]))
    {
      $error = ErrorEx::warn(
        'reader='.$a[1].' is not attached'
      );
      $this->info->pending &&
      $this->info->close($error);
      return false;
    }
    # handle operation
    switch ($a[0]) {
    case 0:# detachment signal {{{
      $this->readerDetach($a[1], $error);
      if ($fn = $this->callback) {
        $fn(0, $a[1], $a[2]);
      }
      break;
    # }}}
    case 1:# attachment request {{{
      # create the "running" flag
      $f0 = SyncFlag::new($a[1]);
      if (ErrorEx::is($f0))
      {
        $error = $f0;
        break;
      }
      # the flag must be set by the reader,
      # make sure it is set
      if (!$f0->getShared())
      {
        $error = ErrorEx::warn(
          'reader='.$a[1].' is not running'
        );
        break;
      }
      # create the "pending" flag
      $f1 = SyncFlag::new($a[1].'-data');
      if (ErrorEx::is($f1))
      {
        $error = $f1;
        break;
      }
      # initially, no data is pending,
      # make sure that flag is cleared
      if ($f1->getShared() &&
          !$f1->clearShared($error))
      {
        break;# failed to clear
      }
      # reader have to create the buffer,
      # send the size of the buffer
      $b = strval($this->data->sizeMax);
      if (!$this->info->write($b, $error)) {
        break;
      }
      # success
      $this->state = $a;
      $this->reader[$a[1]] = [$f0,$f1,0];
      return false;
    # }}}
    case 2:# retransmission request {{{
      # flushing is performed when ready,
      # so retransmission is always allowed
      $b = '1';
      if (!$this->info->write($b, $error)) {
        break;
      }
      # success
      $this->state = $a;
      return false;
    # }}}
    case 3:# custom signal {{{
      if ($fn = $this->callback) {
        $fn(3, $a[1], $a[2]);
      }
      break;
    # }}}
    default:# unknown {{{
      $error = ErrorEx::warn(
        'unknown operation='.$a[0].
        ' from the reader='.$a[1]
      );
      break;
    # }}}
    }
    $this->info->pending &&
    $this->info->close($error);
    return false;
  }
  # }}}
  protected function flushQueue(?object &$error): bool # {{{
  {
    if ($this->queue)
    {
      $data = array_shift($this->queue);
      $this->dataWrite($data, $error);
    }
    return true;
  }
  # }}}
  # }}}
  function write(string $data, ?object &$error=null): bool # {{{
  {
    # check empty or closed
    $error = null;
    if ($data === '' || !$this->writer) {
      return false;
    }
    # write when ready
    if (!$this->time && $this->isReady($error)) {
      return $this->dataWrite($data, $error);
    }
    elseif ($error) {
      return false;
    }
    # postpone otherwise
    $this->queue[] = $data;
    return true;
  }
  # }}}
  function flush(?object &$error=null): bool # {{{
  {
    # check closed or not ready
    if (!$this->writer ||
        !$this->isReady($error))
    {
      return true;
    }
    # operate
    $error = null;
    $this->flushState($error) &&
    $this->flushInfo($error)   &&
    $this->flushQueue($error);
    return $error === null;
  }
  # }}}
  function close(?object &$error=null): bool # {{{
  {
    # check
    if (!$this->writer) {
      return true;
    }
    # close
    $this->writer = $error = null;
    $this->info->pending &&
    $this->info->close($error);
    $this->reader = $this->queue = [];
    return $error === null;
  }
  # }}}
}
# }}}
class SyncBroadcast extends SyncReaderWriter # {{{
{
  # constructor {{{
  static function new(array $o): object
  {
    try
    {
      # prepare
      $id     = self::o_id($o);
      $reader = self::o_instance($o);
      $writer = ErrorEx::peep(
        SyncFlag::new($id.'-master')
      );
      $info = ErrorEx::peep(SyncExchange::new([
        'id'   => $id.'-info',
        'size' => 200,
      ]));
      # construct
      return new self(
        $id, self::o_timeout($o),
        $reader, $writer, $info,
        ErrorEx::peep(SyncFlag::new(
          $reader->id.'-data'
        )),
        self::o_callback($o)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        class_basename(self::class), __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string  $id,
    public int     $timeWait,
    public object  $reader,
    public object  $writer,
    public object  $info,
    public object  $dataFlag,
    public ?object $callback,
    public ?object $dataBuf = null,
    public array   $queue   = [],
    public array   $store   = ['',''],# read,write
    public int     $state   = 0
  ) {
    $dataFlag->clearShared();
  }
  # }}}
  # hlp {{{
  protected function isReady(?object &$error): bool # {{{
  {
    static $E1='incorrect master response';
    $error = null;
    switch ($this->state) {
    case -1:# on hold {{{
      $this->timeout() &&
      $this->stateSet(0, 'retry', $error);
      break;
    # }}}
    case  0:# attachment (1) {{{
      # check master escaped
      if (!$this->writer->getShared($error)) {
        break;
      }
      # try to initiate
      $a = '1:'.$this->reader->id;
      if (!$this->info->write($a, $e))
      {
        if ($e && $e->level)
        {
          $error = $e;
          $this->stateSet(-1, 'fail', $error);
        }
        break;
      }
      # move to the next stage
      $this->stateSet(1, '', $error);
      break;
    # }}}
    case  1:# attachment (2) {{{
      # get the response
      if (($a = $this->info->read($error)) === null)
      {
        $error && $this->stateSet(-1, 'fail', $error);
        break;
      }
      # parse info
      if (($i = intval($a)) < 1 ||
          $i > self::MAX_SIZE)
      {
        $error = ErrorEx::fail($E1, $a);
        $this->stateSet(-1, 'fail', $error);
        break;
      }
      # construct data buffer
      if ($this->dataBuf === null ||
          $this->dataBuf->sizeMax !== $i)
      {
        $o = SyncBuffer::new($this->id, $i);
        if (ErrorEx::is($o))
        {
          $error = $o;
          $this->stateSet(-1, 'fail', $error);
          break;
        }
        $this->dataBuf = $o;
      }
      # notify about success
      $a = '1:'.$this->reader->id;
      if (!$this->info->notify($a, $error))
      {
        $this->stateSet(-1, 'fail', $error);
        break;
      }
      # move to the next stage
      $this->stateSet(2, '', $error);
      break;
    # }}}
    case  2:# confirmation {{{
      # wait confirmed
      if (!$this->info->flush($error))
      {
        $error && $this->stateSet(-1, 'fail', $error);
        break;
      }
      # move to the next stage
      $this->stateSet(3, '', $error);
      break;
    # }}}
    case  3:# ready {{{
      # check master escaped
      if (!$this->writer->getShared($error))
      {
        $this->stateSet(0, 'escape', $error);
        break;
      }
      # positive
      return true;
    # }}}
    case  4:# retransmission (1) {{{
      # check master escaped
      if (!$this->writer->getShared($error))
      {
        $this->stateSet(0, 'escape', $error);
        break;
      }
      # flush pending data
      if (!$this->dataFlush($error))
      {
        $this->stateSet(-1, 'fail', $error);
        break;
      }
      # try to initiate
      $a = '2:'.$this->reader->id;
      if (!$this->info->write($a, $e))
      {
        if ($e && $e->level)
        {
          $error = $e;
          $this->stateSet(-1, 'fail', $error);
        }
        break;
      }
      # move to the next stage
      $this->stateSet(5, '', $error);
      break;
    # }}}
    case  5:# retransmission (2) {{{
      # get the response
      if (($a = $this->info->read($error)) === null)
      {
        $error && $this->stateSet(-1, 'fail', $error);
        break;
      }
      # restart when denied
      if ($a !== '1')
      {
        $this->stateSet(4, 'retry', $error);
        break;
      }
      # write stored data
      $i = $this->dataBuf->write($this->store[1], $error);
      $this->store[1] = '';
      if ($i < 0)
      {
        $this->stateSet(-1, 'fail', $error);
        break;
      }
      # notify about success
      $a = '2:'.$this->reader->id;
      if (!$this->info->notify($a, $error))
      {
        $this->stateSet(-1, 'fail', $error);
        break;
      }
      # move to confirmation
      $this->stateSet(2, '', $error);
      break;
    # }}}
    }
    return false;
  }
  # }}}
  protected function stateSet(# {{{
    int $new, string $info, ?object &$error
  ):bool
  {
    # when entering..
    $old = $this->state;
    switch ($new) {
    case -2:
      # try to quit gracefully
      if ($this->state === 3 &&
          $this->writer->getShared($error))
      {
        $a = '0:'.$this->reader->id;
        if ($this->info->signal($a, $error)) {
          $info = 'graceful';
        }
      }
      break;
    case -1:
      $this->time = hrtime(true);
      # falltrough..
    case 0:
      $this->info->pending &&
      $this->info->close($error);
      break;
    }
    # set new state
    $this->state = $new;
    if ($f = $this->callback) {
      $f($old, $new, $info);
    }
    return $error === null;
  }
  # }}}
  protected function dataFlush(?object &$error): bool # {{{
  {
    if (!$this->dataFlag->getShared()) {
      return true;
    }
    $data = &$this->dataBuf->readShared($error);
    if ($data === null) {
      return false;
    }
    $this->store[0] = $data;
    return $this->dataFlag->clearShared($error);
  }
  # }}}
  # }}}
  function isPending(): bool # {{{
  {
    return $this->dataFlag->getShared();
  }
  # }}}
  function &read(?object &$error=null): ?string # {{{
  {
    # prepare
    static $NONE=null;
    $error = null;
    # check read store
    if ($this->store[0] !== '')
    {
      $data = $this->store[0];
      $this->store[0] = '';
      return $data;
    }
    # check not ready or no data pending
    if (!$this->isReady($error) ||
        !$this->dataFlag->getShared())
    {
      return $NONE;
    }
    # read and clear
    $data = $this->dataBuf->readShared($error);
    $this->dataFlag->clearShared($error);
    return $data;
  }
  # }}}
  function write(string $data, ?object &$error=null): bool # {{{
  {
    # check not ready
    $error = null;
    if ($data === '' || !$this->isReady($error))
    {
      $error = ErrorEx::skip();
      return false;
    }
    # enter retransmission state
    $this->store[1] = $data;
    $this->stateSet(4, '', $error);
    return true;
  }
  # }}}
  function signal(string &$data, ?object &$error=null): bool # {{{
  {
    # check not ready
    $error = null;
    if ($data === '' || !$this->isReady($error))
    {
      $error = ErrorEx::skip();
      return false;
    }
    # try to send signal
    $a = '3:'.$this->reader->id.':'.$data;
    return $this->info->signal($a, $error);
  }
  # }}}
  function flush(?object &$error=null): bool # {{{
  {
    $error = null;
    return $this->isReady($error);
  }
  # }}}
  function close(?object &$error=null): bool # {{{
  {
    return ($this->state !== -2)
      ? $this->stateSet(-2, '', $error)
      : true;
  }
  # }}}
}
# }}}
/*}}}*/
###
