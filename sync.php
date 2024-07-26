<?php declare(strict_types=1);
# defs {{{
namespace SM;
use
  SyncSharedMemory,SyncSemaphore,SyncEvent,
  Throwable,ArrayAccess;
use function
  is_bool,is_int,is_string,is_object,is_callable,
  is_dir,sys_get_temp_dir,intval,strval,preg_match,
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
  # base {{{
  static function new(string $id, int $size): object
  {
    try
    {
      if ($size <= 0)
      {
        throw ErrorEx::fail(
          'incorrect size='.$size
        );
      }
      return new self($id, $size,
        new SyncSharedMemory($id, 4 + $size)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string $id,
    public int    $size,# total size
    public object $mem
  ) {
    $mem->first() && $this->clear();
  }
  # }}}
  function sizeGet(): int # {{{
  {
    $s = $this->mem->read(0, 4);
    if (strlen($s) !== 4)
    {
      throw ErrorEx::fatal(
        __CLASS__, __FUNCTION__,
        'SyncSharedMemory::read'
      );
    }
    return unpack('l', $s)[1];
  }
  # }}}
  function sizeSet(int $size): int # {{{
  {
    $n = $this->mem->write(pack('l', $size), 0);
    if ($n !== 4)
    {
      throw ErrorEx::fatal(
        __CLASS__, __FUNCTION__,
        'SyncSharedMemory::write'
      );
    }
    return $size;
  }
  # }}}
  function get(?int &$n=0): string # {{{
  {
    # read content size
    if (!($n = $this->sizeGet())) {
      return '';
    }
    $size = ($n < 0)
      ? $this->size # overflown
      : $n;
    # read content
    $data = $this->mem->read(4, $size);
    if (strlen($data) !== $size)
    {
      throw ErrorEx::fatal(
        __CLASS__, __FUNCTION__,
        'SyncSharedMemory::read'
      );
    }
    return $data;
  }
  # }}}
  function set(# {{{
    string $data, int $size, int $offs=0
  ):int
  {
    # determine and check available space
    if (($n = $this->size - $offs) <= 0) {
      return 0;
    }
    # check data fits into the buffer
    if ($size <= $n) {
      $n = $offs + $size;
    }
    else
    {
      # overflow, partial write
      $data = substr($data, 0, $n);
      $size = $n;
      $n = -1;
    }
    # write
    if ($this->mem->write($data, 4+$offs) !== $size)
    {
      throw ErrorEx::fatal(
        __CLASS__, __FUNCTION__,
        'SyncSharedMemory::write'
      );
    }
    $this->sizeSet($n);
    return $size;# written data size
  }
  # }}}
  function append(string $data, int $size): int # {{{
  {
    return (($offs = $this->sizeGet()) >= 0)
      ? $this->set($data, $len, $offs)
      : 0;
  }
  # }}}
  function clear(): void # {{{
  {
    $this->sizeSet(0);
  }
  # }}}
}
# }}}
class SyncFlag # {{{
{
  # base {{{
  static function new(string $id): object
  {
    try
    {
      return new self(
        $id, new SyncEvent($id, 1, 0)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string $id,
    public object $event,
    public bool   $state = false
  ) {}
  function __destruct() {
    $this->clear();
  }
  # }}}
  # helpers {{{
  function eventSet(bool $shared): bool # {{{
  {
    # check
    if ($this->state || $this->event->wait(0)) {
      return true;# already set
    }
    # set
    if (!$this->event->fire()) {
      throw ErrorEx::fail('SyncEvent::fire');
    }
    if ($shared) {# dont appropriate
      return true;
    }
    return $this->state = true;
  }
  # }}}
  function eventClear(bool $shared): bool # {{{
  {
    # check
    if (!$this->state)
    {
      if (!$this->event->wait(0)) {
        return true;# already clean
      }
      if (!$shared) {
        return false;# appropriated
      }
    }
    # clear
    if (!$this->event->reset()) {
      throw ErrorEx::fail('SyncEvent::reset');
    }
    $this->state = false;
    return true;
  }
  # }}}
  function _set(# {{{
    ?object &$error, bool $shared
  ):bool
  {
    try {
      return $this->eventSet($shared);
    }
    catch (Throwable $e)
    {
      ErrorEx::set($error, $e);
      return false;
    }
  }
  # }}}
  function _clear(# {{{
    ?object &$error, bool $shared
  ):bool
  {
    try {
      return $this->eventClear($shared);
    }
    catch (Throwable $e)
    {
      ErrorEx::set($error, $e);
      return false;
    }
  }
  # }}}
  # }}}
  # api {{{
  function sync(): bool {
    return $this->state = $this->event->wait(0);
  }
  function get(): bool {
    return $this->state || $this->event->wait(0);
  }
  function getShared(): bool {
    return $this->event->wait(0);
  }
  function set(?object &$error=null): bool {
    return $this->_set($error, false);
  }
  function setShared(?object &$error=null): bool {
    return $this->_set($error, true);
  }
  function clear(?object &$error=null): bool {
    return $this->_clear($error, false);
  }
  function clearShared(?object &$error=null): bool {
    return $this->_clear($error, true);
  }
  # }}}
}
# }}}
class SyncFlagMaster # {{{
{
  # base {{{
  static function new(
    string $id, string $dir=''
  ):object
  {
    try
    {
      # prepare directory
      if ($dir === '') {
        $dir = sys_get_temp_dir();
      }
      else
      {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir))
        {
          throw ErrorEx::fail(
            $id, 'directory not found'.
            "\n".$dir
          );
        }
      }
      # determine file path
      $file =
        $dir.DIRECTORY_SEPARATOR.
        $id.'.flag';
      # complete
      return new self(
        $id, $file, new SyncEvent($id, 1, 0)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string $id,
    public string $file,
    public object $event
  ) {
    # master flag must auto-set itself
    # create lockfile
    if (Fx::file_persist($file))
    {
      throw ErrorEx::fail(
        $id, 'master flag has been already set'.
        "\nlockfile: ".$file.
        "\nremove it manually to override"
      );
    }
    else {
      Fx::file_touch($file);
    }
    # set when necessary
    if (!$event->wait(0) && !$event->fire())
    {
      throw ErrorEx::fail(
        $id, 'SyncEvent::fire'
      );
    }
  }
  function __destruct()
  {
    Fx::try_file_unlink($this->file);
    $this->event->reset();
  }
  # }}}
  function get(): bool # {{{
  {
    return (
      $this->event->wait(0) &&
      Fx::file_persist($this->file)
    );
  }
  # }}}
}
# }}}
class SyncNum implements ArrayAccess # {{{
{
  # base {{{
  static function new(string $id, int $count=1): object
  {
    try
    {
      return new self($id, $count,
        new SyncSharedMemory($id, 4*$count)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string  $id,
    public int     $count,
    public object  $mem
  ) {
    if ($mem->first()) {
      $this->reset();
    }
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
  function get(int $k=0): int # {{{
  {
    $a = $this->mem->read(4*$k, 4);
    if (strlen($a) !== 4) {
      throw ErrorEx::fatal('SyncSharedMemory::read');
    }
    return unpack('l', $a)[1];
  }
  # }}}
  function set(int $x, int $k=0): void # {{{
  {
    $x = $this->mem->write(pack('l', $x), 4*$k);
    if ($x !== 4) {
      throw ErrorEx::fatal('SyncSharedMemory::write');
    }
  }
  # }}}
  function reset(): void # {{{
  {
    $a = 4 * $this->count;
    $b = $this->mem->write(str_repeat("\x00", $a));
    if ($a !== $b) {
      throw ErrorEx::fatal('SyncSharedMemory::write');
    }
  }
  # }}}
  function tryGet(int $k=0, ?object &$error=null): int # {{{
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
  # }}}
  function trySet(int $x, int $k=0): ?object # {{{
  {
    try {
      return $this->set($x, $k);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  # }}}
  function tryReset(): ?object # {{{
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
  # base {{{
  static function new(
    string $id, int $max=1, int $weight=1
  ):object
  {
    try
    {
      if ($max < 1 || $weight < 1 ||
          $max < $weight)
      {
        throw ErrorEx::fail(
          'incorrect argument(s): '.
          'max='.$max.', weight='.$weight
        );
      }
      return new self(
        $id, $max, $weight,
        ErrorEx::peep(SyncNum::new($id)),
        new SyncSemaphore($id.'-sem', 1, 0)
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string $id,
    public int    $max,
    public int    $weight,
    public object $num,
    public object $sem,
    public int    $locked = 0
  ) {}
  function __destruct()
  {
    try {$this->unlock();}
    catch (Throwable) {}
  }
  # }}}
  function get(): int # {{{
  {
    return $this->num->get();
  }
  # }}}
  function set(): bool # {{{
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
  # }}}
  function clear(): bool # {{{
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
    DEF_SIZE = 1000,# bytes
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
  static function o_dir(array $o): string # {{{
  {
    static $k='dir';
    if (!isset($o[$k])) {
      return '';
    }
    if (!is_string($dir = $o[$k])) {
      throw self::o_fail($k);
    }
    if ($dir === '') {
      $dir = sys_get_temp_dir();
    }
    return $dir;
  }
  # }}}
  static function o_int(# {{{
    array $o, string $k, int $def,
    ?array $minmax=null
  ):int
  {
    if (!isset($o[$k])) {
      return $def;
    }
    if (!is_int($i = $o[$k])) {
      throw self::o_fail($k);
    }
    if ($minmax &&
        ($i < $minmax[0] || $i > $minmax[1]))
    {
      throw self::o_fail($k);
    }
    return $i;
  }
  # }}}
  static function o_bool(# {{{
    array $o, string $k, bool $def
  ):bool
  {
    if (!isset($o[$k])) {
      return $def;
    }
    if (!is_bool($v = $o[$k])) {
      throw self::o_fail($k);
    }
    return $v;
  }
  # }}}
  static function o_instance(array $o): object # {{{
  {
    static $k0='instance-flag';
    static $k1='instance-id';
    if (isset($o[$k0]))
    {
      if (!is_object($x = $o[$k0])) {
        throw self::o_fail($k0);
      }
      return $x;
    }
    if (isset($o[$k1]))
    {
      if (!is_string($o[$k1]) ||
          ($id = $o[$k1]) === '')
      {
        throw self::o_fail($k1);
      }
    }
    else {
      $id = self::o_id($o).'-'.Fx::$PROCESS_ID;
    }
    return ErrorEx::peep(
      SyncFlagMaster::new($id, self::o_dir($o))
    );
  }
  # }}}
  static function o_callback(array $o): ?object # {{{
  {
    static $k='callback';
    if (!isset($o[$k])) {
      return null;
    }
    if (!is_callable($f = $o[$k])) {
      throw self::o_fail($k);
    }
    return $f;
  }
  # }}}
  static function o_fail(string $k): object # {{{
  {
    return ErrorEx::fail(static::class,
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
    # number of active waits before idle
    IDLE_THRESHOLD = 5000,
    # timespan before transfer fails
    DATA_TIMEOUT = 100*1000000,# 100ms ~ ns
    # default time to wait for activity
    WAIT_TIME = 1000000;# 1ms
  public string
    $value='';
  public int
    $stage=1,$size=0,$waits=0,
    $time=PHP_INT_MAX;
  ###
  function __construct(public ?object $base)
  {}
  # }}}
  function _timeSet(): bool # {{{
  {
    $this->waits = 0;
    $this->time  = Loop::$HRTIME;
    return false;
  }
  # }}}
  function _timeCheck(int $t=self::DATA_TIMEOUT): bool # {{{
  {
    # check expired
    if (Loop::$HRTIME > $this->time + $t)
    {
      # projected repeat rate is 1ms,
      # in case of high CPU utilization or fluctuations
      # timeout may happen in few ticks,
      # this is compensated with a simple reset,
      # check the 50% threshold
      if ($this->waits >= (int)($t / 2000000)) {
        return $this->_timeExpired();
      }
      # reset
      $this->time = Loop::$HRTIME;
    }
    # increment and continue
    $this->waits++;
    return false;
  }
  # }}}
  function _timeWait(): bool # {{{
  {
    # first do active waiting, then become idle
    if ($this->waits < self::IDLE_THRESHOLD)
    {
      $this->waits++;
      $this->result->promiseDelayNs(self::WAIT_TIME);
    }
    else {
      $this->result->promiseIdle();
    }
    return false;
  }
  # }}}
  function _timeCheckWait(): bool # {{{
  {
    # check expired
    if (($t = $this->base->timeout) &&
        $this->_timeCheck($t))
    {
      return true;
    }
    # active waiting
    $this->result->promiseDelayNs(self::WAIT_TIME);
    return false;
  }
  # }}}
  function _timeExpired(): bool # {{{
  {
    $this->result->fail(
      static::class, $this->base->id,
      'timed out (stage='.$this->stage.
      ',index='.$this->result->index.')'
    );
    return $this->_done();
  }
  # }}}
  function _dataGet(): bool # {{{
  {
    # read a chunk of data
    $s = $this->base->data->get($n);
    # check empty
    if ($n === 0)
    {
      # chunk transfers here must complete
      # in a limited timespan, custom timing
      # may be implemented in the promise handler
      return $this->_timeCheck();
    }
    # accumulate and clear
    $this->result->value .= $s;
    $this->base->data->clear();
    # upon the last (or the whole) chunk is read,
    # move to the next stage
    if ($n > 0) {
      $this->stage++;
    }
    return $this->_timeSet();
  }
  # }}}
  function _dataSetFirst(bool $append=false): bool # {{{
  {
    # write the value
    $n = $this->size;
    $i = $append
      ? $this->base->data->append($this->value, $n)
      : $this->base->data->set($this->value, $n);
    # check the result
    if ($i === 0) {
      return $this->_dataFail();
    }
    if ($i < $n)
    {
      # more chunks to write
      $this->value = substr($this->value, $i);
      $this->size  = $n - $i;
    }
    else
    {
      # everything is written
      $this->size = 0;
    }
    # immediately move to the next stage
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _dataSetLast(): bool # {{{
  {
    # check buffer is dirty (not read yet)
    if ($this->base->data->sizeGet()) {
      return $this->_timeCheck();
    }
    # write remaining data
    if ($n = $this->size)
    {
      $i = $this->base->data->set($this->value, $n);
      if ($i < $n)
      {
        # more data to write,
        # repeat at the next tick
        $this->value = substr($this->value, $i);
        $this->size  = $n - $i;
        return $this->_timeSet();
      }
      $this->size = 0;
    }
    # all data is written,
    # immediately proceed to the next stage
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _dataFail(): bool # {{{
  {
    $this->result->fail(
      static::class, $this->base->id,
      "protocol desynchronization\n".
      "buffer is dirty, unable to write"
    );
    return $this->_done();
  }
  # }}}
}
# }}}
# }}}
# Exchange: reader + writer {{{
class SyncExchange extends Sync_ReaderWriter # {{{
{
  # basis {{{
  protected function __construct(
    public string  $id,
    public int     $timeout,# response timeout
    public bool    $shareRead,# multiple readers
    public bool    $shareWrite,# multiple writers
    public bool    $boost,# accelerate when possible
    public object  $data,# buffer
    public object  $reader,
    public object  $writer,
    public object  $state,
    public ?object $action = null
  ) {
    # convert timeout (ms) to nanoseconds
    $this->timeout = (int)($timeout * 1000000);
  }
  static function new(array $o): object
  {
    try
    {
      return new self(
        $id = self::o_id($o),
        self::o_int(
          $o, 'timeout', 3000, [100, 30000]
        ),
        self::o_bool($o, 'share-read', false),
        self::o_bool($o, 'share-write', false),
        self::o_bool($o, 'boost', false),
        ErrorEx::peep(SyncBuffer::new(
          $id, self::o_int(
            $o, 'size', 100, [1, self::MAX_SIZE]
          )
        )),
        ErrorEx::peep(SyncLock::new($id.'-r')),
        ErrorEx::peep(SyncLock::new($id.'-w')),
        ErrorEx::peep(SyncNum::new($id.'-x', 2)),
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  # }}}
  function server(): object # {{{
  {
    return new Promise($this->shareRead
      ? new SyncExchange_ShareServer($this)
      : ($this->boost
        ? new SyncExchange_TerseServer($this)
        : new SyncExchange_Server($this))
    );
  }
  # }}}
  function client(): object # {{{
  {
    return new Promise($this->shareWrite
      ? new SyncExchange_ShareClient($this)
      : ($this->boost
        ? new SyncExchange_TerseClient($this)
        : new SyncExchange_Client($this))
    );
  }
  # }}}
}
# }}}
abstract class SyncExchange_Op extends Sync_ReaderWriterOp # {{{
{
  public int $dirty=0;
  function _done(): bool # {{{
  {
    if ($i = $this->stage)
    {
      if ($this->dirty)
      {
        $this->_dirtySet(0);
        $this->base->state[0] = 0;
      }
      if ($i > 2) {
        $this->_unlock();
      }
      if ($i > 1)
      {
        $this->base->action = null;
        $this->result->promiseContextClear();
      }
      $this->base  = null;
      $this->stage = 0;
    }
    return true;
  }
  # }}}
  function _init(): bool # {{{
  {
    if ($this->base->action)
    {
      $this->result->fail(
        static::class, $this->base->id,
        'another exchange is running'
      );
      return $this->_done();
    }
    $this->result->promiseContextSet(
      $this->base->action = $this
    );
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _enter(): bool # {{{
  {
    $this->_timeSet();
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _enterClean(): bool # {{{
  {
    $this->result->value = '';
    $this->_timeSet();
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _stop(int $stage): bool # {{{
  {
    $this->stage = $stage;
    return true;
  }
  # }}}
  function _stateWait(int $x, int $y): bool # {{{
  {
    switch ($n = $this->base->state[0]) {
    case $x:# stasis
      return $this->_stateWaitAt($x);
    case $y:# instant activation
      $this->_dirtySet(0);
      $this->stage++;
      $this->_timeSet();
      return $this->_complete();
    }
    $this->result->fail(
      static::class, $this->base->id,
      "protocol desynchronization\n".
      "unexpected state=".$n.
      " at stage=".$this->stage
    );
    return $this->_done();
  }
  # }}}
  function _stateSet(int $x): bool # {{{
  {
    $this->base->state[0] = $this->_dirtySet($x);
    $this->stage++;
    return $this->_timeSet();
  }
  # }}}
  function _dirtySet(int $x): int # {{{
  {
    if ($x) {
      $this->dirty || Loop::yield_more();
    }
    else {
      $this->dirty && Loop::yield_less();
    }
    return $this->dirty = $x;
  }
  # }}}
  function _lockFail(): bool # {{{
  {
    $this->result->fail(
      static::class, $this->base->id,
      "protocol desynchronization\n".
      "unable to lock"
    );
    return $this->_done();
  }
  # }}}
  ###
  function read(): object # {{{
  {
    if ($this->stage !== static::STAGE_READ)
    {
      throw ErrorEx::fatal(
        static::class, $this->base->id,
        "unable to read, ".
        "incorrect stage=".$this->stage
      );
    }
    return $this->result
      ->indexPlus()
      ->promisePrependOne($this);
    ###
    return $this->result->promisePrependOne($this);
  }
  # }}}
  function write(string $data): object # {{{
  {
    if ($this->stage !== static::STAGE_WRITE)
    {
      throw ErrorEx::fatal(
        static::class, $this->base->id,
        "unable to write, ".
        "incorrect stage=".$this->stage
      );
    }
    $this->value = $data;
    $this->size  = strlen($data);
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
  ###
  abstract function _unlock():void;
  abstract function _stateWaitAt(int $x):bool;
}
# }}}
class SyncExchange_Server extends SyncExchange_Op # {{{
{
  const STAGE_READ=3,STAGE_WRITE=9;
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      ### server entry
      1  => $this->_init(),
      2  => $this->_lock(),
      ### request read
      3  => $this->_enterClean(),
      4  => $this->_stateWait(0, 1),# infinite/timeout
      5  => $this->_stateSet(2),
      6  => $this->_dataGet(),
      7  => $this->_stateWait(2, 0),# active
      8  => $this->_stop(9),
      ### response write
      9  => $this->_dataSetFirst(),
      10 => $this->_stateSet(3),
      11 => $this->_stateWait(3, 4),# timeout
      12 => $this->_dataSetLast(),
      13 => $this->_stateSet(0),
      14 => $this->_stop(3),
      default => true
    };
  }
  # }}}
  function _stateWaitAt(int $x): bool # {{{
  {
    return match ($x) {
      0 => ($this->result->index
        ? $this->_timeCheckWait()
        : $this->_timeWait()
      ),
      2 => $this->_timeCheck(),
      default => $this->_timeCheckWait()
    };
  }
  # }}}
  function _lock(): bool # {{{
  {
    if (!$this->base->reader->set()) {
      return $this->_lockFail();
    }
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _unlock(): void # {{{
  {
    $this->base->reader->clear();
  }
  # }}}
  function reset(): void # {{{
  {
    $this->stage = 3;
    $this->result->promisePrependOne($this);
  }
  # }}}
}
class SyncExchange_TerseServer extends SyncExchange_Server
{
  function _lock(): bool # {{{
  {
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _unlock(): void {}
}
class SyncExchange_ShareServer extends SyncExchange_Server
{
  public bool $shareAppeal=false;
  function _lock(): bool # {{{
  {
    # try to lock
    if (!$this->base->reader->set()) {
      return $this->_lockFailSh();
    }
    # revoke appeal
    if ($this->shareAppeal)
    {
      $this->base->state[1] = 0;
      $this->shareAppeal = false;
    }
    # hop to the next stage
    $this->stage++;
    return $this->_complete();
  }
  # }}}
  function _lockFailSh(): bool # {{{
  {
    # when no other instance made an appeal,
    # appeal to the read share
    if (!$this->shareAppeal &&
        $this->base->state[1] === 0)
    {
      $this->base->state[1] = 1;
      $this->shareAppeal = true;
    }
    # wait relaxed
    $this->result->promiseIdle();
    return false;
  }
  # }}}
  function _timeWait(): bool # {{{
  {
    # when idle on the first request,
    # check the appeal is made
    if ($this->result->index === 0 &&
        $this->waits >= self::IDLE_THRESHOLD &&
        $this->base->state[1])
    {
      # release the lock and
      # get back to locking after a pause
      $this->base->reader->clear();
      $this->result->promiseIdle();
      $this->stage = 2;
      return false;
    }
    return parent::_timeWait();
  }
  # }}}
  function _done(): bool # {{{
  {
    if ($this->shareAppeal)
    {
      $this->base->state[1] = 0;
      $this->shareAppeal = false;
    }
    return parent::_done();
  }
  # }}}
}
# }}}
class SyncExchange_Client extends SyncExchange_Op # {{{
{
  const STAGE_READ=9,STAGE_WRITE=3;
  function _complete(): bool # {{{
  {
    return match ($this->stage) {
      ### client entry
      1  => $this->_init(),
      2  => $this->_lock(),
      ### request write
      3  => $this->_dataSetFirst(),
      4  => $this->_stateSet(1),
      5  => $this->_stateWait(1, 2),# timeout
      6  => $this->_dataSetLast(),
      7  => $this->_stateSet(0),
      8  => $this->_stop(9),
      ### response read
      9  => $this->_enterClean(),
      10 => $this->_stateWait(0, 3),# timeout
      11 => $this->_stateSet(4),
      12 => $this->_dataGet(),
      13 => $this->_stateWait(4, 0),# active
      14 => $this->_stop(3),
      default => true
    };
  }
  # }}}
  function _stateWaitAt(int $x): bool # {{{
  {
    return ($x < 2)
      ? $this->_timeCheckWait()
      : $this->_timeCheck();
  }
  # }}}
  function _lock(): bool # {{{
  {
    if (!$this->base->writer->set()) {
      return $this->_lockFail();
    }
    $this->stage++;
    return true;
  }
  # }}}
  function _unlock(): void # {{{
  {
    $this->base->writer->clear();
  }
  # }}}
}
class SyncExchange_TerseClient extends SyncExchange_Client
{
  function _lock(): bool # {{{
  {
    $this->stage++;
    return true;
  }
  # }}}
  function _unlock(): void {}
}
class SyncExchange_ShareClient extends SyncExchange_Client
{
  function _lockFail(): bool # {{{
  {
    # shared writer may fail to lock
    # because another writer holds the lock.
    # simply wait until it is released
    $this->result->promiseIdle();
    return false;
  }
  # }}}
}
# }}}
# }}}
/*** TODO {{{
# Aggregate: reader + writers {{{
class SyncAggregate extends Sync_ReaderWriter # {{{
{
  # base {{{
  static function new(array $o): object
  {
    try
    {
      return new self(
        $id = self::o_id($o),
        ErrorEx::peep(SyncLock::new($id.'-lock')),
        ErrorEx::peep(SyncBuffer::new(
          $id, self::o_int(
            $o, 'size', 100, [1, self::MAX_SIZE]
          )
        ))
      );
    }
    catch (Throwable $e)
    {
      return ErrorEx::set($e, ErrorEx::fail(
        __CLASS__, __FUNCTION__
      ));
    }
  }
  protected function __construct(
    public string  $id,
    public object  $lock,
    public object  $data,
    public ?object $action = null
  ) {}
  # }}}
  function read(): object # {{{
  {
    if ($this->action)
    {
      throw ErrorEx::fail(
        __CLASS__, __FUNCTION__,
        'previous action is not finished'
      );
    }
    return Promise::Context(
      $this->action = new SyncAggregateRead($this)
    );
  }
  # }}}
  function write(string $data, int $timeout=-1): object # {{{
  {
    if ($this->action)
    {
      throw ErrorEx::fail(__CLASS__, __FUNCTION__,
        'previous action is not finished'
      );
    }
    return Promise::Context(
      $this->action =
      new SyncAggregateWrite($this, $timeout, $data)
    );
  }
  # }}}
}
# }}}
abstract class SyncAggregateOp extends Sync_ReaderWriterOp # {{{
{
  function _unlockStop(int $stage): void # {{{
  {
    $this->base->lock->clear();
    $this->stage = $stage;
  }
  # }}}
  function _done(): bool # {{{
  {
    if ($this->stage)
    {
      $base = $this->base;
      $base->lock->clear();
      $base->action = null;
      $this->stage  = 0;
    }
    return true;
  }
  # }}}
}
# }}}
class SyncAggregateRead extends SyncAggregateOp # {{{
{
  function _entry(): object # {{{
  {
    $this->result->value = '';
    return $this->_timeSet();
  }
  # }}}
  function _dataWait(): ?object # {{{
  {
    # check data arrived
    if ($n = $this->base->data->sizeGet())
    {
      # normally lock to read,
      # skip locking when in the drain mode
      $this->stage += ($n > 0) ? 1 : 2;
      return $this->_timeSet();
    }
    # cooldown
    return $this->_timeWait();
  }
  # }}}
  function _lock(): ?object # {{{
  {
    # try to acquire the lock
    if ($this->base->lock->set())
    {
      $this->stage++;
      $this->time = Loop::$HRTIME;
      return $this->thenHop();
    }
    # get back to waiting..
    $this->stage--;
    $this->time = Loop::$HRTIME;
    return $this->thenWait(1);
  }
  # }}}
  function _complete(): ?object # {{{
  {
    return match ($this->stage) {
      1 => $this->_entry(),
      2 => $this->_dataWait(),
      3 => $this->_lock(),
      4 => $this->_dataGet(),
      5 => $this->_unlockStop(1),
      default => $this->_done()
    };
  }
  # }}}
  function next(): object # {{{
  {
    if ($this->stage !== 1)
    {
      throw ErrorEx::fatal(
        static::class, $this->base->id,
        "unable to continue reading\n".
        "incorrect stage=".$this->stage
      );
    }
    return new Promise($this);
  }
  # }}}
}
# }}}
class SyncAggregateWrite extends SyncAggregateOp # {{{
{
  function _entry(): ?object # {{{
  {
    if ($this->size = strlen($this->value)) {
      return $this->_timeSet();
    }
    return null;
  }
  # }}}
  function _lock(): ?object # {{{
  {
    if ($this->base->lock->set())
    {
      $this->stage++;
      return $this->thenHop();
    }
    return ($t = $this->timeout)
      ? (($t > 0)
        ? $this->_timeCheckWait($t)
        : $this->thenWait(1))
      : $this->_timeExpired();
  }
  # }}}
  function _complete(): ?object # {{{
  {
    return match ($this->stage) {
      1 => $this->_entry(),
      2 => $this->_lock(),
      3 => $this->_dataSetFirst(true),
      4 => $this->_dataSetLast(),
      5 => $this->_unlockStop(1),
      default => $this->_done()
    };
  }
  # }}}
  function next(string $data): object # {{{
  {
    if ($this->stage !== 1)
    {
      throw ErrorEx::fatal(
        static::class, $this->base->id,
        "unable to continue writing\n".
        "incorrect stage=".$this->stage
      );
    }
    $this->value = $data;
    return new Promise($this);
  }
  # }}}
}
# }}}
# }}}
class SyncAggregateMaster extends SyncReaderWriter # {{{
{
  # Aggregate: one reader, many writers
  # TODO: chunks/separation, array mode
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
      return new self(
        $id, $flag,
        ErrorEx::peep(SyncLock::new($id.'-lock')),
        ErrorEx::peep(SyncBuffer::new($id, $size))
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
    public ?object $reader,
    public object  $lock,
    public object  $data,
    public string  $store = '',# own writes
    public string  $chunk = '' # overflown parts
  ) {}
  # }}}
  function &read(?object &$error=null): ?string # {{{
  {
    # prepare
    $error = null;
    $data  = null;
    # lock
    if ($this->lock->set($error) <= 0) {
      return $data;
    }
    # get buffer state
    switch ($n = $this->data->size($error)) {
    case -2:# error
      break;
    case  0:# empty
      # check and drain own writes
      if ($this->store !== '')
      {
        $data = $this->store;
        $this->store = '';
      }
      break;
    default:# pending data
      # read the data
      $data = $this->data->read($error);
      if ($data === null) {
        break;
      }
      # resolve overflow
      if ($n === -1)
      {
        # accumulate chunks
        $this->chunk .= $data;
        $data = null;
      }
      elseif ($this->chunk !== '')
      {
        # assemble chunks into result
        $data = $this->chunk.$data;
        $this->chunk = '';
      }
      # append own writes
      if ($this->chunk === '' &&
          $this->store !== '')
      {
        $data .= $this->store;
        $this->store = '';
      }
      break;
    }
    # complete
    $this->lock->clear($error);
    return $data;
  }
  # }}}
  function write(string $data, ?object &$error=null): bool # {{{
  {
    $this->store .= $data;
    return true;
  }
  # }}}
  function close(?object &$error=null): bool # {{{
  {
    if (!$this->reader) {
      return true;
    }
    $this->reader = null;
    return true;
  }
  # }}}
}
# }}}
class SyncAggregate extends SyncReaderWriter # {{{
{
  # constructor {{{
  static function new(array $o): object
  {
    try
    {
      $id = self::o_id($o);
      $sz = self::o_size($o);
      return new static(
        $id, self::o_timeout($o),
        ErrorEx::peep(SyncFlag::new($id.'-master')),
        ErrorEx::peep(SyncLock::new($id.'-lock')),
        ErrorEx::peep(SyncBuffer::new($id, $sz))
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
    public object  $lock,
    public object  $data,
    public string  $store = '',
    public string  $chunk = ''
  ) {}
  # }}}
  # hlp {{{
  protected function dataWrite(# {{{
    string &$data, ?object &$error
  ):bool
  {
    # append and check failed
    $n = $this->data->append($data, $error);
    if ($n === -1) {
      return false;
    }
    # check fully written
    if ($n === strlen($data)) {
      return true;
    }
    # store leftover chunk
    $this->chunk = substr($data, $n);
    $this->time  = hrtime(true);
    return false;
  }
  # }}}
  protected function postpone(string &$data): void # {{{
  {
    if ($this->time === 0) {
      $this->time = hrtime(true);
    }
    $this->store .= $data;
  }
  # }}}
  protected function clear(?object &$error): bool # {{{
  {
    # when this instance has chunks,
    # the buffer is in overflow mode,
    # invalidate all the data
    if ($this->chunk !== '')
    {
      $this->lock->setWait(
        self::MIN_TIMEWAIT, $error
      );
      $this->data->clear($error);
      $this->lock->clear($error);
    }
    # reset properties
    $this->time  = 0;
    $this->store = $this->chunk = '';
    return true;# always positive
  }
  # }}}
  # }}}
  function isPending(): bool # {{{
  {
    return (
      $this->store !== '' ||
      $this->chunk !== ''
    );
  }
  # }}}
  function write(string $data, ?object &$error=null): bool # {{{
  {
    # check empty
    if ($data === '') {
      return true;
    }
    # postpone when either this instance has chunks,
    # reader is offline or unable to lock
    $error = null;
    if ($this->chunk !== '' ||
        !$this->reader->getShared() ||
        $this->lock->set($error) <= 0)
    {
      $this->postpone($data);
      return $error === null;
    }
    # check buffer state
    switch ($this->data->size($error)) {
    case -2:# error
      break;
    case -1:# overflow
      $this->postpone($data);
      break;
    default:# ok
      # check and drain previous writes
      if ($this->store)
      {
        $data = $this->store.$data;
        $this->store = '';
      }
      # write
      $this->dataWrite($data, $error);
      break;
    }
    $this->lock->clear($error);
    return $error === null;
  }
  # }}}
  function flush(?object &$error=null): bool # {{{
  {
    # check nothing to do
    if (!$this->isPending()) {
      return true;
    }
    # check timeout
    if ($this->timeout())
    {
      $error = ErrorEx::info('timeout');
      return $this->clear($error);
    }
    # check reader is offline or unable to lock
    if (!$this->reader->getShared() ||
        $this->lock->set($error) <= 0)
    {
      return false;
    }
    # check buffer state
    switch ($this->data->size($error)) {
    case -2:# error
    case -1:# overflow
      $this->lock->clear($error);
      return false;
    }
    # first, flush-drain overflow,
    # next, flush-drain previous writes
    if ($this->chunk !== '')
    {
      if ($this->dataWrite($this->chunk, $error)) {
        $this->chunk = '';
      }
    }
    else
    {
      $this->dataWrite($this->store, $error);
      $this->store = '';
    }
    # unlock
    $this->lock->clear($error);
    # check still pending
    if ($this->isPending()) {
      return false;
    }
    # reset timer and complete
    if ($this->time) {
      $this->time = 0;
    }
    return true;
  }
  # }}}
  function close(?object &$error=null): bool # {{{
  {
    $this->clear($error);
    return $error === null;
  }
  # }}}
}
# }}}
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
