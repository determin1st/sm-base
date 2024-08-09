<?php declare(strict_types=1);
### defs {{{
namespace SM;
use FFI;
use function class_exists,strlen,unpack;
use const PHP_BINARY;
### }}}
abstract class Sys_Base # {{{
{
  const MEM_SIZE = 0x1000;# 4k
  static ?object $API=null,$MEM=null;
  private function __construct()
  {}
  static function _init(): void
  {
    if (self::$API) {
      return;
    }
    if (!class_exists('FFI'))
    {
      throw ErrorEx::fail(__CLASS__,
        'FFI extension is required'
      );
    }
    self::$API = FFI::load(static::API_FILE);
  }
  static function _mem(): object
  {
    if (self::$MEM) {
      return self::$MEM;
    }
    return self::$MEM = self::$API->new(
      'char['.self::MEM_SIZE.']', false
    );
  }
}
# }}}
if (PHP_OS_FAMILY === 'Windows')
{
  class Sys extends Sys_Base
  {
    const API_FILE = __DIR__.'\\sysapi-kernel32.h';
    static function open_process(# {{{
      int $pid, int $access=0x1000|0x0001
      # PROCESS_QUERY_LIMITED_INFORMATION (0x1000)
      # PROCESS_TERMINATE (0x0001)
    ):int
    {
      return self::$API->OpenProcess($access, 0, $pid);
    }
    # }}}
    static function terminate_process(# {{{
      int $handle
    ):int
    {
      return self::$API->TerminateProcess($handle, 0);
    }
    # }}}
    static function close_handle(# {{{
      int $handle
    ):int
    {
      return self::$API->CloseHandle($handle);
    }
    # }}}
    static function is_process_active(# {{{
      int $handle
    ):bool
    {
      # get exit code
      static $CODE = "\x00\x00\x00\x00";
      $i = self::$API->GetExitCodeProcess(
        $handle, $CODE
      );
      # check STILL_ACTIVE (259)
      return ($i && $CODE === "\x03\x01\x00\x00");
    }
    # }}}
    static function last_error(# {{{
      int $e=0
    ):array # [code,description]
    {
      # prepare
      $api = self::$API;
      $mem = self::_mem();
      # get last error number
      if (!$e && !($e = $api->GetLastError())) {
        return [0, ''];
      }
      # get error description in utf16 (wide chars)
      $i = $api->FormatMessageW(0
        |0x00001000 # FORMAT_MESSAGE_FROM_SYSTEM
        |0x00000200 # FORMAT_MESSAGE_IGNORE_INSERTS
        |0,
        null, $e, 0, $mem, 1000, null
      );
      # failed, no description
      if (!$i) {
        return [$e, ''];
      }
      # convert into utf8
      $s = FFI::string($mem, 2 * $i);
      $i = $api->WideCharToMultiByte(
        65001, 0, $s, $i,
        $mem, self::MEM_SIZE,
        null, null
      );
      # failed, no description
      if (!$i) {
        return [$e, ''];
      }
      # errors may contain whitespace,
      # trim the result and complete
      return [$e, trim(FFI::string($mem, $i))];
    }
    # }}}
  }
}
else
{
  class Sys extends Sys_Base
  {
    const API_FILE = __DIR__.'/sysapi-libc.h';
    static int $ERRNO=0;
    static function posix_spawn(string $file): int # {{{
    {
      # prepare
      static $PID="\x00\x00\x00\x00";
      $api = self::$API;
      $sz0 = 1 + strlen(PHP_BINARY);
      $sz1 = 1 + strlen($file);
      $mem = $api->cast("
      struct {
        char bin[$sz0];
        char a0[3];
        char a1[$sz1];
        char *argv[3];
      }
      ", self::_mem());
      # initialize
      FFI::memcpy($mem->bin, PHP_BINARY."\x00", $sz0);
      FFI::memcpy($mem->a0, "-f\x00", 3);
      FFI::memcpy($mem->a1, $file."\x00", $sz1);
      $mem->argv[0] = $mem->a0;
      $mem->argv[1] = $mem->a1;
      $mem->argv[2] = null;
      # invoke
      $i = $api->posix_spawn(
        $PID, $mem->bin, null, null,
        $mem->argv, $api->environ
      );
      # complete
      if ($i === 0) {
        return unpack('L', $PID)[1];
      }
      self::$ERRNO = $i;
      return 0;
    }
    # }}}
  }
}
Sys::_init();
###
