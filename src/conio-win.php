<?php declare(strict_types=1);
namespace SM;
use FFI,Throwable;
use function
  str_repeat,strlen,substr,strrpos,trim,dechex,
  pack,unpack;
###
abstract class Conio_Base extends Conio_PseudoBase
{
  const # {{{
    ASK = "\x1B[0c",# DA1
    FLAGS = [
      [# 0:input {{{
      'PROCESSED_INPUT' => 0x0001,
      'LINE_INPUT'      => 0x0002,
      'ECHO_INPUT'      => 0x0004,
      'WINDOW_INPUT'    => 0x0008,# buggy!
      'MOUSE_INPUT'     => 0x0010,
      'INSERT_MODE'     => 0x0020,
      'QUICK_EDIT_MODE' => 0x0040,
      'EXTENDED_FLAGS'  => 0x0080,
      'AUTO_POSITION'   => 0x0100,
      'VIRTUAL_TERMINAL_INPUT' => 0x0200 # buggy!
      ],
      # }}}
      [# 1:output {{{
      'PROCESSED_OUTPUT'   => 0x0001,
      'WRAP_AT_EOL_OUTPUT' => 0x0002,
      'VIRTUAL_TERMINAL_PROCESSING' => 0x0004,
      'DISABLE_NEWLINE_AUTO_RETURN' => 0x0008,
      'LVB_GRID_WORLDWIDE' => 0x0010
      ]
      # }}}
    ],
    TERMID = [
      0    => 'dumb conhost',
      1920 => 'conhost',
      1264 => 'ansicon',
      1176 => 'ConEmu',
      1056 => 'Windows Terminal',
    ];
  ###
  # }}}
  # basis {{{
  public int $keyboard=2;
  static function new(): object
  {
    # to be able to restore the terminal,
    # operate in a guarded section
    $base = null;
    try
    {
      # open I/O handles
      $h0 = self::con_handle(0);
      $h1 = self::con_handle(1);
      $ds = 'CON';
      # get current/initial mode of the terminal,
      # this is made before construction to
      # stress the system stability (fail fast)
      $mode = self::get_mode($h0, $h1);
      $isVt = self::is_virtual($h0, $mode['sio'][0]);
      # construct specific instance
      $base = self::is_async($h1)
        ? ($isVt
          ? new Conio_BaseAP($h0, $h1, $ds, $mode)
          : new Conio_BaseAD($h0, $h1, $ds, $mode))
        : ($isVt
          ? new Conio_BaseSP($h0, $h1, $ds, $mode)
          : new Conio_BaseSD($h0, $h1, $ds, $mode));
      #####
      # initialize
      $base->setConstructed();
      if (!$base->init() && $isVt)
      {
        throw ErrorEx::fatal(
          "virtual terminal mode is available,\n".
          "but ANSI ESC codes are not supported\n".
          "cannot bypass this contradiction"
        );
      }
    }
    catch (Throwable $e)
    {
      echo ErrorLog::render(ErrorEx::from($e));
      echo "[ERROR";
      $base && $base->deconstruct();
      echo "]";
      throw $e;
    }
    return $base;
  }
  # }}}
  # stasis {{{
  static function error(# {{{
    string $func, string $more='', int $x=0
  ):object
  {
    $e = Sys::last_error($x);
    $s = $e[1] ? $e[1] : 'ERROR='.$x;
    return ($more !== '')
      ? ErrorEx::fatal_up(1, $func, $more, $s)
      : ErrorEx::fatal_up(1, $func, $s);
  }
  # }}}
  static function con_handle(int $i): int # {{{
  {
    # prepare
    static $hname=['CONIN$','CONOUT$'];
    static $flags=[
      # read/input
      0x20000000, # FILE_FLAG_NO_BUFFERING
      # write/output
      0x20000000  # FILE_FLAG_NO_BUFFERING
      |0x40000000 # FILE_FLAG_OVERLAPPED
      |0x80000000 # FILE_FLAG_WRITE_THROUGH
    ];
    # open new handle
    $h = Sys::$API->CreateFileA($hname[$i],
      0x80000000|0x40000000,# read/write access
      0x00000001|0x00000002,# shared read/write
      null,# cannot be inherited by child
      3,# OPEN_EXISTING
      $flags[$i], 0
    );
    # check failed
    if ($h === -1) {
      throw self::error('CreateFileA', $hname[$i]);
    }
    return $h;
  }
  # }}}
  static function con_info(int $handle): object # {{{
  {
    if ($info = Sys::console_info($handle)) {
      return $info;
    }
    throw self::error('GetConsoleScreenBufferInfo');
  }
  # }}}
  static function con_mode_get(int $handle): int # {{{
  {
    if (~($n = Sys::get_console_mode($handle))) {
      return $n;
    }
    throw self::error('GetConsoleMode');
  }
  # }}}
  static function con_mode_set(int $handle, int $mode): int # {{{
  {
    if (Sys::set_console_mode($handle, $mode)) {
      return $mode;
    }
    throw self::error('SetConsoleMode');
  }
  # }}}
  static function con_i_cp(int $cp=0): int # {{{
  {
    # setter?
    if ($cp)
    {
      if (Sys::$API->SetConsoleCP($cp)) {
        return $cp;
      }
      throw self::error('SetConsoleCP');
    }
    # getter!
    if ($cp = Sys::$API->GetConsoleCP()) {
      return $cp;
    }
    throw self::error('GetConsoleCP');
  }
  # }}}
  static function con_o_cp(int $cp=0): int # {{{
  {
    # setter?
    if ($cp)
    {
      if (Sys::$API->SetConsoleOutputCP($cp)) {
        return $cp;
      }
      throw self::error('SetConsoleOutputCP');
    }
    # getter!
    if ($cp = Sys::$API->GetConsoleCP()) {
      return $cp;
    }
    throw self::error('GetConsoleOutputCP');
  }
  # }}}
  static function get_size(object $info): array # {{{
  {
    $w = $info->srWindow;
    return [
      1 + $w->Right  - $w->Left,
      1 + $w->Bottom - $w->Top
    ];
  }
  # }}}
  static function get_scroll(object $info): array # {{{
  {
    $s = $info->dwSize;
    $w = $info->srWindow;
    return [
      $s->X, $s->Y,
      $w->Left, $w->Top
    ];
  }
  # }}}
  static function get_cursor(object $info): array # {{{
  {
    $p = $info->dwCursorPosition;
    $w = $info->srWindow;
    return [
      1 + $p->X - $w->Left,
      1 + $p->Y - $w->Top
    ];
  }
  # }}}
  static function get_mode(# {{{
    int $h0, int $h1
  ):array
  {
    $info   = self::con_info($h1);
    $size   = self::get_size($info);
    $scroll = self::get_scroll($info);
    $cursor = self::get_cursor($info);
    return [
      'sio' => [
        self::con_mode_get($h0),
        self::con_mode_get($h1),
        self::con_i_cp(),
        self::con_o_cp()
      ],
      'size'   => $size,
      'scroll' => $scroll,
      'cursor' => $cursor,
      's8c1t'  => -1
    ];
  }
  # }}}
  static function is_virtual(# {{{
    int $handle, int $mode
  ):bool
  {
    # check terminal is already in virtual mode
    $x = self::FLAGS[0]['VIRTUAL_TERMINAL_INPUT'];
    if ($x & $mode) {
      return true;# already set - supported
    }
    # make a probe
    Sys::set_console_mode($handle, $mode|$x);
    $x = Sys::get_last_error();
    # supported or not, the mode value
    # could be spoiled with this bit,
    # set it back
    Sys::set_console_mode($handle, $mode);
    # check the probe result
    switch ($x) {
    case 0:# ERROR_SUCCESS
      return true;# supported
    case 87:# ERROR_INVALID_PARAMETER
      return false;# supported not
    }
    # unexpected
    throw self::error('SetConsoleMode', '', $x);
  }
  # }}}
  static function is_async(int $handle): bool # {{{
  {
    # prepare
    $w = 100;# attempts
    $f = (static function() use (&$w): void
    {
      $w = -1;# success
    });
    # probe with null-write operation
    $api = Sys::$API;
    $mem = Sys::_OVERLAPPED();
    Sys::$API->WriteFileEx(
      $handle, null, 0, FFI::addr($mem), $f
    );
    # handle failure
    switch ($e = $api->GetLastError()) {
    case 0:# ERROR_SUCCESS
      break;
    case 6:# ERROR_INVALID_HANDLE
      # asynchronous write is not supported
      return false;
    default:# unexpected
      throw self::error('WriteFileEx', '', $e);
    }
    # wait for completion
    do {Loop::cooldown();}
    while (--$w > 0);
    # complete
    return $w < 0;
  }
  # }}}
  static function kbhit(int $handle): int # {{{
  {
    $n = Sys::get_number_of_console_input_events($handle);
    if ($n >= 0) {
      return $n;
    }
    throw self::error('GetNumberOfConsoleInputEvents');
  }
  # }}}
  static function con_input(int $handle, int $n): object # {{{
  {
    if ($o = Sys::read_console_input($handle, $n)) {
      return $o;
    }
    throw self::error('ReadConsoleInputW');
  }
  # }}}
  static function con_input_flush(int $handle): void # {{{
  {
    $x = Sys::flush_console_input_buffer($handle);
    if ($x) {return;}
    throw self::error('FlushConsoleInputBuffer');
  }
  # }}}
  static function con_output_attr(int $handle, int $n, int $x, int $y): object # {{{
  {
    $o = Sys::read_console_output_attribute(
      $handle, $n, $x, $y
    );
    if ($o) {
      return $o;
    }
    throw self::error('ReadConsoleOutputAttribute');
  }
  # }}}
  static function con_cursor_pos(int $handle, int $x, int $y): void # {{{
  {
    $res = Sys::set_console_cursor_position(
      $handle, $x, $y
    );
    if ($res) {return;}
    throw self::error('SetConsoleCursorPosition');
  }
  # }}}
  # }}}
  # dynamis {{{
  function probeColors(): int # {{{
  {
    return $this->ansi ? 24 : 0;
  }
  # }}}
  function getId(): string # {{{
  {
    # prepare
    $hndl = $this->f1;
    $res  = self::con_info($hndl);
    $x    = $res->dwCursorPosition->X;
    $y    = $res->dwCursorPosition->Y;
    $s0   = "\x1B[38;2;";
    $s1   = ";0;0m \x1B[0m";
    $mask = 0
      |0x0001   # FOREGROUND_BLUE
      |0x0002   # FOREGROUND_GREEN
      |0x0004   # FOREGROUND_RED
      |0x0008;  # FOREGROUND_INTENSITY
    ###
    # calculate checksum
    for ($z=0,$i=0; $i < 256; $i+=8)
    {
      # print space chars with background color
      $this->puts(
        $s0.($i + 0).$s1.$s0.($i + 1).$s1.
        $s0.($i + 2).$s1.$s0.($i + 3).$s1.
        $s0.($i + 4).$s1.$s0.($i + 5).$s1.
        $s0.($i + 6).$s1.$s0.($i + 7).$s1
      );
      # read printed attributes and
      # sum the color part of the attribute
      $res = self::con_output_attr(
        $hndl, 8, $x, $y
      );
      for ($j=0,$k=$res->cnt; $j < $k; ++$j) {
        $z += ($res->attr[$j] & $mask);
      }
      # restore cursor position
      self::con_cursor_pos($hndl, $x, $y);
    }
    # complete
    return isset(self::TERMID[$z])
      ? self::TERMID[$z]
      : 'unknown ('.$z.')';
  }
  # }}}
  function gets(int $timeout=0): string # {{{
  {
    # prepare
    $timeout || $timeout = $this->timeout;
    $handle = $this->f0;
    # wait for the input
    while (!($cnt = self::kbhit($handle)))
    {
      if (($timeout -= 5) < 0) {
        return '';# timed out
      }
      Loop::cooldown(5);
    }
    # get input records and
    # filter them into a string
    $input = self::con_input($handle, $cnt);
    $rec = $input->rec;
    $cnt = $input->cnt;
    for ($s='',$i=0; $i < $cnt; ++$i)
    {
      # skip non-keyboard
      $e = $rec[$i];
      if ($e->EventType !== 0x0001) {
        continue;
      }
      # skip keyups and physical input (keycodes)
      $e = $e->Event->KeyEvent;
      if (!$e->bKeyDown || $e->wVirtualKeyCode) {
        continue;
      }
      $s .= self::u8chr($e->uChar);
    }
    # cleanup and complete
    unset($e,$rec,$cnt,$input);
    return $s;
  }
  # }}}
  function setConstructed(): void # {{{
  {
    # set applied mode
    $this->setMode(static::get_applied_mode());
  }
  # }}}
  function setMode(array $m): void # {{{
  {
    # set private modes
    isset($m['pio']) &&
    $this->_DECSET($m['pio'], true);
    # set system-related modes
    if (isset($m['sio']))
    {
      $a = $m['sio'];
      $b = &$this->sio;
      if ($a[0] !== $b[0]) {
        self::con_mode_set($this->f0, $b[0] = $a[0]);
      }
      if ($a[1] !== $b[1]) {
        self::con_mode_set($this->f1, $b[1] = $a[1]);
      }
      if ($a[2] && $a[2] !== $b[2]) {
        self::con_i_cp($b[2] = $a[2]);
      }
      if ($a[3] && $a[3] !== $b[3]) {
        self::con_o_cp($b[3] = $a[3]);
      }
    }
  }
  # }}}
  function puts(string $s): void # {{{
  {
    if (Sys::write_console($this->f1, $s) < 0) {
      throw self::error('WriteConsoleA');
    }
  }
  # }}}
  # }}}
  # concretis {{{
  function init(): bool # {{{
  {
    # invoke common initializer
    if (!parent::init())
    {
      # windows api is always capable
      # of moving cursor around - move it and
      # cleanup the screen
      $a = $this->cursor;
      $a[0] += $this->scroll[2] - 1;
      $a[1] += $this->scroll[3] - 1;
      self::con_cursor_pos($this->f1, $a[0], $a[1]);
      $this->puts('          ');
      self::con_cursor_pos($this->f1, $a[0], $a[1]);
      # dumb terminal is acceptable,
      # set identity and complete
      $this->id = self::TERMID[0];
      return false;
    }
    # DECRQM is wildly ignored among
    # windows-based terminals, so
    # apply additional tuning based on identifier
    switch ($this->id) {
    case 'ConEmu':
      # simulate mouse tracking defaults
      #$a = self::M_TRACKING[1] + [1049=>1];
      $a = self::M_TRACKING[1];
      foreach ($this->mode['pio'] as $k => &$v) {
        if (isset($a[$k])) {$v = 2;}
      }
      # simulate mode support
      foreach ($this->pio as $k => &$v) {
        $v = 5;
      }
      # enable mouse tracking
      $this->_DECSET($a, true);
      $this->mouse = 1;
      break;
    }
    return true;
  }
  # }}}
  function read(): bool # {{{
  {
    # check no pending input
    $i = $this->f0;
    if (!($count = self::kbhit($i))) {
      return false;
    }
    # get input records
    $input = self::con_input($i, $count);
    $rec = $input->rec;
    $cnt = $input->cnt;
    # parse
    for ($i=0; $i < $cnt; ++$i)
    {
      $e = $rec[$i];
      switch ($j = $e->EventType) {
      case 0x0001:# KEY_EVENT {{{
        ###
        $e = $e->Event->KeyEvent;
        ###
        $this->input[] = [
          Conio::EV_KEY,
          ($e->bKeyDown
            ? $e->wRepeatCount # pressed (down)
            : 0 # depressed (up)
          ),
          $e->wVirtualKeyCode,
          $e->dwControlKeyState,
          (($j = $e->uChar)
            ? self::u8chr($j)
            : '')
        ];
        break;
        # }}}
      case 0x0002:# MOUSE_EVENT {{{
        ###
        $e = $e->Event->MouseEvent;
        ###
        # determine state
        switch ($e->dwEventFlags) {
        case 0x0001:# move
          $j = Conio::M_MOVE | $this->mouseBtn;
          break;
        case 0x0004:# wheel (vertical)
          # SCROLL_DELTA_FORWARD  = 0080 0000
          # SCROLL_DELTA_BACKWARD = FF80 0000
          # up = 0078 0000
          # dn = FF88 0000
          ###
          # determine direction
          $j = ($e->dwButtonState & 0x80000000)
            ? Conio::M_BUTTON2 # negative (down)
            : Conio::M_BUTTON1;# positive (up)
          ###
          $j = $j | Conio::M_WHEEL;
          break;
        case 0x0008:# wheel (horizontal)
          # determine direction
          $j = ($e->dwButtonState & 0x80000000)
            ? Conio::M_BUTTON4 # negative (right)
            : Conio::M_BUTTON3;# positive (left)
          ###
          $j = $j | Conio::M_WHEEL;
          break;
        case 0x0002:# double click
          # fallthrough..
        default:# click/release
          if (!($j = $e->dwButtonState))
          {
            $j = Conio::M_RELEASE | $this->mouseBtn;
            $this->mouseBtn = 0;
          }
          elseif ($j & 0x0001) {
            $this->mouseBtn = $j = Conio::M_BUTTON1;
          }
          elseif ($j & 0x0004) {
            $this->mouseBtn = $j = Conio::M_BUTTON2;
          }
          elseif ($j & 0x0008) {
            $this->mouseBtn = $j = Conio::M_BUTTON3;
          }
          elseif ($j & 0x0010) {
            $this->mouseBtn = $j = Conio::M_BUTTON4;
          }
          else {# neverland
            $this->mouseBtn = $j = Conio::M_BUTTON5;
          }
          break;
        }
        ###
        $k = $e->dwControlKeyState;
        $e = $e->dwMousePosition;
        $this->input[] = [
          Conio::EV_MOUSE, $j, $k,
          # convert windows-specific coordinates
          # into common format [1..cols,1..rows]
          $e->X + 1 - $this->scroll[2],
          $e->Y + 1 - $this->scroll[3]
        ];
        break;
        # }}}
      case 0x0004:# WINDOW_BUFFER_SIZE_EVENT {{{
        break;# IGNORE
        # }}}
      case 0x0008:# MENU_EVENT {{{
        break;# IGNORE
        # }}}
      case 0x0010:# FOCUS_EVENT {{{
        ###
        $this->setFocused(
          $e->Event->FocusEvent->bSetFocus
        );
        break;
        # }}}
      default:# {{{
        $this->error = ErrorEx::warn(
          'kernel32::ReadConsoleInputW',
          'unknown EventType='.$j.
          ' of the INPUT_RECORD#'.$i.'/'.$cnt
        );
        break 2;# skip the rest
        # }}}
      }
    }
    # update / recurse
    $x = $this->setPending();
    $y = ($cnt < $count) ? $this->read() : false;
    # cleanup
    unset($e,$rec,$cnt,$input);
    # complete
    return $x || $y;
  }
  # }}}
  function resize(): bool # {{{
  {
    # get console information
    $o  = self::con_info($this->f1);
    $a1 = self::get_size($o);
    $a2 = self::get_scroll($o);
    $b1 = $a1 !== $this->size;
    # check nothing changed
    if (!$b1 && $a2 === $this->scroll) {
      return false;
    }
    # update size records
    if ($b1)
    {
      if (!$this->lastSize) {
        $this->lastSize = $this->size;
      }
      $this->size = $a1;
    }
    # update scroll records
    if (!$this->lastScroll) {
      $this->lastScroll = $this->scroll;
    }
    $this->scroll = $a2;
    $this->cursor = self::get_cursor($o);
    return true;
  }
  # }}}
  function write(): void # {{{
  {
    $res = Sys::write_console(
      $this->f1, $this->writeBuf1, $this->writeLen1
    );
    $this->writeBuf1 = '';
    $this->writeLen1 = 0;
    if ($res < 0)
    {
      $this->writing = -1;
      $this->error = self::error('WriteConsoleA');
    }
    else {
      $this->setWriteComplete();
    }
  }
  # }}}
  function clearInput(): void # {{{
  {
    parent::clearInput();
    self::con_input_flush($this->f0);
  }
  # }}}
  function finit(): void # {{{
  {
  }
  # }}}
  function close(): void # {{{
  {
    Sys::close_handle($this->f0);
    Sys::close_handle($this->f1);
  }
  # }}}
  # }}}
}
trait Conio_BaseA # Async {{{
{
  # basis {{{
  public ?object
    $writeCallback=null,
    $overlapped=null;
  ###
  function setConstructed(): void
  {
    parent::setConstructed();
    $this->overlapped = Sys::$API->new(
      Sys::$OVERLAPPED, false, true
    );
    $this->writeCallback =
      $this->writeCallback(...);
  }
  function finit(): void
  {
    if ($this->overlapped)
    {
      FFI::free($this->overlapped);
      $this->overlapped = $this->writeCallback = null;
    }
  }
  # }}}
  function puts(string $s): void # {{{
  {
    $x = Sys::write_file($this->f1, $s, strlen($s));
    if ($x === 0) {return;}
    throw self::error('WriteFile', '', $x);
  }
  # }}}
  function write(): void # {{{
  {
    $o = $this->overlapped;
    FFI::memset($o, 0, FFI::sizeof($o));
    $n = $this->writeLen1;
    $x = Sys::$API->WriteFileEx(
      $this->f1, $this->writeBuf1, $n,
      FFI::addr($o), $this->writeCallback
    );
    if ($x)
    {
      # success
      $this->writing = $n;# buffering wont spoil
      $this->writeLen1 = 0;# gear wont call
    }
    else
    {
      # failure
      $this->writing = -1;# suspend forever
      $this->error = self::error('WriteFileEx');
    }
  }
  # }}}
  function writeCallback(int $e, int $n): void # {{{
  {
    if ($e)
    {
      $this->writing = -1;
      $this->error = self::error(
        'WriteFileEx', '', $e
      );
    }
    else
    {
      $this->writing &&
      $this->setWriteComplete();
    }
  }
  # }}}
  function clearOutput(): void # {{{
  {
    # cancel pending write operation
    if ($this->writing > 0)
    {
      if (Sys::cancel_io($this->f1)) {
        $this->writing = 0;
      }
      else
      {
        $this->writing = -1;
        $this->error = self::error('CancelIo');
      }
    }
    # clear output buffers
    parent::clearOutput();
  }
  # }}}
  function flushOutput(): void # {{{
  {
    # wait for completion
    $n = 1000;# number of attempts
    while ($this->writing > 0 && --$n) {
      Loop::cooldown();
    }
    if ($n)
    {
      # write remaining output synchronously
      parent::flushOutput();
    }
    else
    {
      # clear when all attempts exhausted
      $this->clearOutput();
    }
  }
  # }}}
}
# }}}
trait Conio_BaseP # Pseudo-Terminal {{{
{
  # Windows > 6.1
  static function get_applied_mode(): array
  {
    $i = self::FLAGS[0];
    $o = self::FLAGS[1];
    return [
      'sio' => [
        $i['MOUSE_INPUT'],
        ###
        $o['PROCESSED_OUTPUT']
        |$o['WRAP_AT_EOL_OUTPUT']
        |$o['VIRTUAL_TERMINAL_PROCESSING'],
        # codepages (UTF-8)
        65001,65001
      ]
    ];
  }
  function probeMouse(): int {
    return 1;
  }
}
# }}}
trait Conio_BaseD # Dumb-Terminal {{{
{
  # Windows <= 6.1
  static function get_applied_mode(): array
  {
    $i = self::FLAGS[0];
    $o = self::FLAGS[1];
    return [
      'sio' => [
        $i['MOUSE_INPUT'],
        ###
        $o['PROCESSED_OUTPUT']
        |$o['WRAP_AT_EOL_OUTPUT'],
        # codepages (UTF-8)
        65001,65001
      ]
    ];
  }
}
# }}}
# base variants {{{
class Conio_BaseAP extends Conio_Base {
  public int $async=1;
  use Conio_BaseA,Conio_BaseP;
}
class Conio_BaseAD extends Conio_Base {
  public int $async=1;
  use Conio_BaseA,Conio_BaseD;
}
class Conio_BaseSP extends Conio_Base {
  use Conio_BaseP;
}
class Conio_BaseSD extends Conio_Base {
  use Conio_BaseD;
}
# }}}
###
