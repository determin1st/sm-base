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
    self::$API = FFI::load(static::API_HEADER);
    static::_init_ex();
  }
  static function mem(int $zap=0): object
  {
    self::$MEM || self::$MEM = self::$API->new(
      'char['.self::MEM_SIZE.']', false, true
    );
    $zap && FFI::memset(self::$MEM, 0, $zap);
    return self::$MEM;
  }
  function _finit()
  {
    if (self::$MEM)
    {
      FFI::free(self::$MEM);
      self::$MEM = null;
    }
    self::$API &&
    self::$API = null;
  }
}
# }}}
if (PHP_OS_FAMILY === 'Windows')
{
  class Sys extends Sys_Base
  {
    const API_HEADER = __DIR__.'\\sysapi-kernel32.h';
    # extras {{{
    static int
      $INPUT_RECORD_SZ,$OVERLAPPED_SZ;
    static object
      $OVERLAPPED,$CONSOLE_SCREEN_BUFFER_INFO;
    ###
    static function _init_ex(): void
    {
      $api = self::$API;
      self::$INPUT_RECORD_SZ =
        $api->type('INPUT_RECORD')->getSize();
      self::$OVERLAPPED =
        $api->type('OVERLAPPED');
      self::$OVERLAPPED_SZ =
        self::$OVERLAPPED->getSize();
      self::$CONSOLE_SCREEN_BUFFER_INFO =
        $api->type('CONSOLE_SCREEN_BUFFER_INFO');
    }
    static function _OVERLAPPED(): object
    {
      return self::$API->cast(
        self::$OVERLAPPED,
        self::mem(self::$OVERLAPPED_SZ)
      );
    }
    # }}}
    # stasis {{{
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
    static function close_handle(int $handle): bool # {{{
    {
      return self::$API->CloseHandle($handle) !== 0;
    }
    # }}}
    static function get_last_error(): int # {{{
    {
      return self::$API->GetLastError();
    }
    # }}}
    static function last_error(# {{{
      int $e=0
    ):array # [code,description]
    {
      # prepare
      $api = self::$API;
      $mem = self::mem();
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
    static function get_console_mode(int $handle): int # {{{
    {
      static $N="\x00\x00\x00\x00";
      return self::$API->GetConsoleMode($handle, $N)
        ? unpack('L', $N)[1]
        : -1;
    }
    # }}}
    static function set_console_mode(int $handle, int $mode): int # {{{
    {
      return self::$API->SetConsoleMode(
        $handle, $mode
      );
    }
    # }}}
    static function write_file(# {{{
      int $handle, string $data, int $size
    ):int
    {
      # prepare
      $api = self::$API;
      $mem = $api->cast(
        $typ = $api->type(
        'struct {'.
          'OVERLAPPED op;'.
          'uint32_t n;'.
        '}'),
        self::mem(self::$OVERLAPPED_SZ + 4)
      );
      # invoke
      $res = $api->WriteFile(
        $handle, $data, $size,
        FFI::addr($mem->n), FFI::addr($mem->op)
      );
      # check done
      if ($res) {
        return 0;
      }
      # check not async (ERROR_IO_PENDING)
      if (($res = $api->GetLastError()) !== 997) {
        return $res;
      }
      # wait for completion
      $res = $api->GetOverlappedResult(
        $handle, FFI::addr($mem->op),
        FFI::addr($mem->n), 1
      );
      return $res ? 0 : $api->GetLastError();
      /*** NOPE
      $res = $api->WaitForSingleObject($handle, 9000);
      return match ($res) {
        0x00000080 => 735,# ABANDONED
        0x00000102 => 1460,# TIMEOUT
        0xFFFFFFFF => $api->GetLastError(),# FAILED
        default => 0
      };
      /***/
    }
    # }}}
    static function console_info(int $handle): ?object # {{{
    {
      # prepare
      $api = self::$API;
      $typ = self::$CONSOLE_SCREEN_BUFFER_INFO;
      $mem = $api->cast(
        $typ, self::mem($typ->getSize())
      );
      # invoke
      $i = $api->GetConsoleScreenBufferInfo(
        $handle, FFI::addr($mem)
      );
      # complete
      return $i ? $mem : null;
    }
    # }}}
    static function get_number_of_console_input_events(int $handle): int # {{{
    {
      $api = self::$API;
      $mem = $api->cast('uint32_t', self::mem(4));
      $res = $api->GetNumberOfConsoleInputEvents(
        $handle, FFI::addr($mem)
      );
      return $res ? $mem->cdata : -1;
    }
    # }}}
    static function read_console_input(# {{{
      int $handle, int $count
    ):?object
    {
      # prepare
      $api = self::$API;
      $sz1 = self::$INPUT_RECORD_SZ;
      $szN = $sz1 * $count;
      $max = self::MEM_SIZE - $sz1;
      if ($szN > $max)
      {
        # overflow, reduce count
        $count = (int)($max / $sz1);
        $szN   = $sz1 * $count;
      }
      $mem = $api->cast(
        /*** SOMETIMES FAILS ***
        'struct {'.
          'INPUT_RECORD rec['.$count.'];'.
          'uint32_t cnt;'.
        '}',
        /***/
        $typ = $api->type(
        'struct {'.
          'INPUT_RECORD rec['.$count.'];'.
          'uint32_t cnt;'.
        '}'),
        self::mem($szN + 4)
      );
      # invoke
      $x = $api->ReadConsoleInputW(
        $handle, $mem->rec, $count,
        FFI::addr($mem->cnt)
      );
      return $x ? $mem : null;
    }
    # }}}
    static function read_console_output_attribute(# {{{
      int $handle, int $count, int $x, int $y
    ):?object
    {
      $api = self::$API;
      $mem = $api->cast(
        $typ = $api->type(
        'struct {'.
          'uint16_t attr['.$count.'];'.
          'uint32_t cnt;'.
          'COORD pos;'.
        '}'),
        self::mem(2*$count + 4 + 4)
      );
      $mem->pos->X = $x;
      $mem->pos->Y = $y;
      $res = $api->ReadConsoleOutputAttribute(
        $handle, $mem->attr, $count,
        $mem->pos, FFI::addr($mem->cnt)
      );
      return $res ? $mem : null;
    }
    # }}}
    static function set_console_cursor_position(# {{{
      int $handle, int $x, int $y
    ):bool
    {
      $api = self::$API;
      $mem = $api->cast('COORD', self::mem(4));
      $mem->X = $x;
      $mem->Y = $y;
      $x = $api->SetConsoleCursorPosition(
        $handle, $mem
      );
      return $x !== 0;
    }
    # }}}
    static function write_console(# {{{
      int $handle, string $data, int $len=0
    ):int
    {
      $api = self::$API;
      $mem = $api->cast('uint32_t', self::mem());
      $res = $api->WriteConsoleA(
        $handle, $data, $len ?: strlen($data),
        FFI::addr($mem), null
      );
      return ($res !== 0)
        ? $mem->cdata
        : -1;
    }
    # }}}
    static function flush_console_input_buffer(int $handle): bool # {{{
    {
      $x = self::$API->FlushConsoleInputBuffer(
        $handle
      );
      return $x !== 0;
    }
    # }}}
    static function cancel_io(int $handle): bool # {{{
    {
      return self::$API->CancelIo($handle) !== 0;
    }
    # }}}
    static function get_stdin_handle(): int # {{{
    {
      return Sys::$API->GetStdHandle(-10);
    }
    # }}}
    static function get_stdout_handle(): int # {{{
    {
      return Sys::$API->GetStdHandle(-11);
    }
    # }}}
    # }}}
  }
}
else
{
  class Sys extends Sys_Base
  {
    const API_HEADER = __DIR__.'/sysapi-libc.h';
    # prep/data {{{
    static int $ERRNO=0;
    static function _init_ex(): void
    {
    }
    # }}}
    static function posix_spawn(string $file): int # {{{
    {
      # prepare
      static $PID="\x00\x00\x00\x00";
      $api = self::$API;
      $sz0 = 1 + strlen(PHP_BINARY);
      $sz1 = 1 + strlen($file);
      $mem = $api->cast(
        $typ = $api->type(
        'struct {'.
          'char bin['.$sz0.'];'.
          'char a0[3];'.
          'char a1['.$sz1.'];'.
          'char *argv[3];'.
        '}'),
        self::mem()
      );
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
