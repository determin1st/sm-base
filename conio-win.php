<?php declare(strict_types=1);
namespace SM;
use FFI,Throwable;
use function
  str_repeat,strlen,substr,strrpos,trim,dechex,
  pack,unpack;
###
abstract class Conio_Base extends Conio_PseudoBase
{
  # constants {{{
  const ASK = "\x1B[0c";# DA1
  const FLAGS = [
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
  ];
  const TERMID = [
    0    => 'dumb conhost',
    1920 => 'conhost',
    1264 => 'ansicon',
    1176 => 'ConEmu',
    1056 => 'Windows Terminal',
  ];
  # }}}
  # constructor {{{
  public int $recSize;# sizeof(INPUT_RECORD)
  public int $keyboard=2;
  static function new(): object
  {
    # prepare
    $api  = FFI::load(__DIR__.'\\conio-kernel32.h');
    $mem  = self::malloc($api, self::MEM_SIZE);
    $base = null;
    # to be able to restore the terminal,
    # proceed into guarded section
    try
    {
      # open I/O handles
      [$h0, $h1, $ds] =
        self::get_handles($api, $mem);
      # get current/initial mode of the terminal,
      # this is made before construction to
      # stress the system stability (fail fast)
      $mode = self::get_mode($api, $mem, $h0, $h1);
      $isVt = self::is_virtual(
        $api, $mem, $h0, $mode['sio'][0]
      );
      # construct specific instance
      $base = self::is_async($api, $mem, $h1)
        ? ($isVt
          ? new Conio_BaseAP($api,$mem,$h0,$h1,$ds,$mode)
          : new Conio_BaseAD($api,$mem,$h0,$h1,$ds,$mode))
        : ($isVt
          ? new Conio_BaseSP($api,$mem,$h0,$h1,$ds,$mode)
          : new Conio_BaseSD($api,$mem,$h0,$h1,$ds,$mode));
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
      # restore/cleanup
      if ($base)
      {
        $base->__destruct();
        $base = null;# destruct
      }
      else {
        FFI::free($mem);
      }
      throw $e;
    }
    return $base;
  }
  # }}}
  # stasis {{{
  static function strerror(# {{{
    object $api, object $mem, int $e=0
  ):string
  {
    # get last error number
    if (!$e && !($e = $api->GetLastError())) {
      return '';
    }
    # allocate buffer (wide chars, utf16) and
    # get error transcription
    $i = $api->FormatMessageW(0
      |0x00001000 # FORMAT_MESSAGE_FROM_SYSTEM
      |0x00000200 # FORMAT_MESSAGE_IGNORE_INSERTS
      |0,
      null, $e, 0, $mem, 1000, null
    );
    # when failed, return hex code
    if (!$i) {
      return 'ERROR=0x'.dechex($e);
    }
    # extract the result and
    # convert it into utf8
    $s = FFI::string($mem, 2 * $i);
    $i = $api->WideCharToMultiByte(
      65001, 0, $s, $i,
      $mem, self::MEM_SIZE,
      null, null
    );
    # errors returned by the WinOS
    # may contain undesireable whitespace..
    return $i
      ? trim(FFI::string($mem, $i))
      : 'ERROR=0x'.dechex($e);
    ###
  }
  # }}}
  static function error(# {{{
    object $api, object $mem,
    string $func, string $more=''
  ):object
  {
    $f = 'kernel32::'.$func;
    $e = self::strerror($api, $mem);
    return ($e !== '')
      ? (($more !== '')
        ? ErrorEx::fatal($f, $more, $e)
        : ErrorEx::fatal($f, $e))
      : (($more !== '')
        ? ErrorEx::fatal($f, $more)
        : ErrorEx::fatal($f));
  }
  # }}}
  static function con_handle(# {{{
    object $api, object $mem, int $i
  ):int
  {
    /*** STD HANDLES ***
    static $hstd=[0xFFFFFFF6, 0xFFFFFFF5];
    if ($i === 0) {
      return $api->GetStdHandle($hstd[$i]);
    }
    /***/
    # prepare
    static $hname=['CONIN$','CONOUT$'];
    static $flags=[
      # input
      0x20000000, # FILE_FLAG_NO_BUFFERING
      # output
      0x20000000  # FILE_FLAG_NO_BUFFERING
      |0x40000000 # FILE_FLAG_OVERLAPPED
      |0x80000000 # FILE_FLAG_WRITE_THROUGH
    ];
    # open new handle
    $h = $api->CreateFileA($hname[$i],
      0x80000000|0x40000000,# read/write access
      0x00000001|0x00000002,# shared read/write
      null,# cannot be inherited by child
      3,# OPEN_EXISTING
      $flags[$i], 0
    );
    # check failed
    if ($h < 1 || $h > 2147483647)
    {
      throw self::error(
        $api, $mem, 'CreateFileA', $hname[$i]
      );
    }
    return $h;
  }
  # }}}
  static function con_info(# {{{
    object $api, object $mem, int $h
  ):object
  {
    # prepare structure
    $a = $api->cast(
      'CONSOLE_SCREEN_BUFFER_INFO', $mem
    );
    FFI::memset($a, 0, FFI::sizeof($a));
    # invoke
    $b = $api->GetConsoleScreenBufferInfo(
      $h, FFI::addr($a)
    );
    # check failed
    if (!$b)
    {
      throw self::error(
        $api, $mem, 'GetConsoleScreenBufferInfo'
      );
    }
    # complete
    return $a;
  }
  # }}}
  static function con_mode_get(# {{{
    object $api, object $mem, int $h
  ):int
  {
    $n = $api->cast('uint32_t', $mem);
    if ($api->GetConsoleMode($h, FFI::addr($n))) {
      return $n->cdata;
    }
    throw self::error(
      $api, $mem, 'GetConsoleMode'
    );
  }
  # }}}
  static function con_mode_set(# {{{
    object $api, object $mem, int $h, int $x
  ):int
  {
    if ($api->SetConsoleMode($h, $x)) {
      return $x;
    }
    throw self::error(
      $api, $mem, 'SetConsoleMode'
    );
  }
  # }}}
  static function con_i_cp(# {{{
    object $api, object $mem, int $cp=0
  ):int
  {
    # setter?
    if ($cp)
    {
      if ($api->SetConsoleCP($cp)) {
        return $cp;
      }
      throw self::error(
        $api, $mem, 'SetConsoleCP'
      );
    }
    # getter!
    if ($cp = $api->GetConsoleCP()) {
      return $cp;
    }
    throw self::error(
      $api, $mem, 'GetConsoleCP'
    );
  }
  # }}}
  static function con_o_cp(# {{{
    object $api, object $mem, int $cp=0
  ):int
  {
    # setter?
    if ($cp)
    {
      if ($api->SetConsoleOutputCP($cp)) {
        return $cp;
      }
      throw self::error(
        $api, $mem, 'SetConsoleOutputCP'
      );
    }
    # getter!
    if ($cp = $api->GetConsoleCP()) {
      return $cp;
    }
    throw self::error(
      $api, $mem, 'GetConsoleOutputCP'
    );
  }
  # }}}
  static function get_handles(# {{{
    object $api, object $mem
  ):array
  {
    # lets get bound console handles
    # that are not affected by redirection
    return [
      self::con_handle($api, $mem, 0),
      self::con_handle($api, $mem, 1),
      'CON'
    ];
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
    object $api, object $mem, int $h0, int $h1
  ):array
  {
    $info   = self::con_info($api, $mem, $h1);
    $size   = self::get_size($info);
    $scroll = self::get_scroll($info);
    $cursor = self::get_cursor($info);
    return [
      'sio' => [
        self::con_mode_get($api, $mem, $h0),
        self::con_mode_get($api, $mem, $h1),
        self::con_i_cp($api, $mem),
        self::con_o_cp($api, $mem)
      ],
      'size'   => $size,
      'scroll' => $scroll,
      'cursor' => $cursor,
      's8c1t'  => -1
    ];
  }
  # }}}
  static function is_virtual(# {{{
    object $api, object $mem, int $h0, int $m0
  ):bool
  {
    # check terminal is already in virtual mode
    $x = self::FLAGS[0]['VIRTUAL_TERMINAL_INPUT'];
    if ($x & $m0) {
      return true;# already set - supported
    }
    # make a probe
    $api->SetConsoleMode($h0, $m0|$x);
    $x = $api->GetLastError();
    # supported or not, the mode value
    # could be spoiled with this bit,
    # set it back
    $api->SetConsoleMode($h0, $m0);
    # check the probe result
    switch ($x) {
    case 0:# ERROR_SUCCESS
      return true;# supported
    case 87:# ERROR_INVALID_PARAMETER
      return false;# supported not
    }
    # unexpected
    throw ErrorEx::fail(
      'kernel32::SetConsoleMode',
      self::strerror($api, $mem, $x)
    );
  }
  # }}}
  static function is_async(# {{{
    object $api, object $mem, int $h
  ):bool
  {
    # prepare
    $w = 100;# attempts
    $f = (function() use (&$w): void
    {
      $w = -1;# success
    });
    $o = $api->cast('OVERLAPPED', $mem);
    FFI::memset($o, 0, FFI::sizeof($o));
    # invoke (null-write operation)
    $api->WriteFileEx(
      $h, null, 0, FFI::addr($o), $f
    );
    # handle failure
    switch ($e = $api->GetLastError()) {
    case 0:# ERROR_SUCCESS
      break;
    case 6:# ERROR_INVALID_HANDLE
      # asynchronous write is not supported
      return false;
    default:# unexpected error
      throw ErrorEx::fatal(
        'kernel32::WriteFileEx',
        self::strerror($api, $mem, $e)
      );
    }
    # wait for completion
    do {Loop::cooldown();}
    while (--$w > 0);
    # complete
    return $w < 0;
  }
  # }}}
  static function kbhit(# {{{
    object $api, object $mem, int $handle
  ):int
  {
    $o = $api->cast('uint32_t', $mem);
    $i = $api->GetNumberOfConsoleInputEvents(
      $handle, FFI::addr($o)
    );
    if ($i) {
      return $o->cdata;
    }
    throw self::error(
      $api, $mem, 'GetNumberOfConsoleInputEvents'
    );
  }
  # }}}
  static function get_input(# {{{
    object $api, object $mem, int $h, int $n
  ):object
  {
    static $k = "\x00\x00\x00\x00";
    $o = $api->cast('INPUT_RECORD['.$n.']', $mem);
    FFI::memset($o, 0, FFI::sizeof($o));
    if (!$api->ReadConsoleInputW($h, $o, $n, $k))
    {
      throw self::error(
        $api, $mem, 'ReadConsoleInputW'
      );
    }
    /*** REDUNDANT? ***
    $i = unpack('L', $k)[1];
    if ($i !== $n)
    {
      throw ErrorEx::fatal(
        'kernel32::ReadConsoleInputW',
        $i.' records read but '.
        $n.' records were pending'
      );
    }
    /***/
    return $o;
  }
  # }}}
  # }}}
  # getters {{{
  function probeColors(): int # {{{
  {
    return $this->ansi ? 24 : 0;
  }
  # }}}
  function getId(): string # {{{
  {
    # prepare
    static $NumberOfAttrsRead="\x00\x00\x00\x00";
    $api  = $this->api;
    $mem  = $this->varmem;
    $hOut = $this->f1;
    $info = self::con_info($api, $mem, $hOut);
    $attr = $api->cast('uint16_t', $mem);
    $ptr  = FFI::addr($attr);
    $mask = 0
      |0x0001   # FOREGROUND_BLUE
      |0x0002   # FOREGROUND_GREEN
      |0x0004   # FOREGROUND_RED
      |0x0008;  # FOREGROUND_INTENSITY
    ###
    # calculate checksum
    for ($x=0,$i=0; $i < 256; ++$i)
    {
      # print and read color attribute
      $this->puts("\x1B[38;2;".$i.";0;0m \x1B[0m");
      $k = $api->ReadConsoleOutputAttribute(
        $hOut, $ptr, 1, $info->dwCursorPosition,
        $NumberOfAttrsRead
      );
      if (!$k)
      {
        throw self::error(
          $api, $mem, 'ReadConsoleOutputAttribute'
        );
      }
      # restore cursor position
      $k = $api->SetConsoleCursorPosition(
        $hOut, $info->dwCursorPosition
      );
      if (!$k)
      {
        throw self::error(
          $api, $mem, 'SetConsoleCursorPosition'
        );
      }
      # sum the color part of the attribute
      $x += ($attr->cdata & $mask);
    }
    # complete
    return isset(self::TERMID[$x])
      ? self::TERMID[$x]
      : 'unknown ('.$x.')';
  }
  # }}}
  function getProcessList(int $max=10): array # {{{
  {
    ############
    ### TODO ###
    ############
    # get the list of process identifiers
    $api = $this->api;
    $lst = str_repeat("\x00\x00\x00\x00", $max);
    $n = $api->GetConsoleProcessList($lst, $max);
    if (!$n)
    {
      throw ErrorEx::fail(
        'kernel32::GetConsoleProcessList',
        ($this->lastError)()
      );
    }
    elseif ($n > $max) {# bigger buffer needed
      return $this->getProcessList($n);
    }
    # iterate the list and get each process details,
    # skip the first process as it refers to myself
    $path = str_repeat("\x00", 500);
    for ($a=[],$i=1; $i < $n; ++$i)
    {
      # get process handle from identifier
      $pid = unpack('L', substr($lst, 4*$i, 4))[1];
      $h = $api->OpenProcess(0x1000, false, $pid);
      if (!$h)
      {
        throw ErrorEx::fail(
          'kernel32::OpenProcess',
          ($this->lastError)()
        );
      }
      # get path to executable (dont fail here)
      $j = $api->K32GetProcessImageFileNameA(
        $h, $path, 500
      );
      $s = $j ? substr($path, 0, $j) : '';
      # cleanup
      if (!$api->CloseHandle($h))
      {
        throw ErrorEx::fail(
          'kernel32::CloseHandle',
          ($this->lastError)()
        );
      }
      # extract executable name
      $name = ($j = strrpos($s, '\\'))
        ? substr($s, $j + 1)
        : $s;
      # add process info
      $a[] = [$pid, $name, $s];
    }
    return $a;
  }
  # }}}
  function gets(int $timeout=0): string # {{{
  {
    # prepare
    $timeout || $timeout = $this->timeout;
    $api = $this->api;
    $mem = $this->varmem;
    $h   = $this->f0;
    # wait for the input
    while (!($n = self::kbhit($api, $mem, $h)))
    {
      if (($timeout -= 5) < 0) {
        return '';# timed out
      }
      Loop::cooldown(5);
    }
    # get input records and
    # filter them into a string
    $r = self::get_input($api, $mem, $h, $n);
    for ($s='',$i=0; $i < $n; ++$i)
    {
      if (($e = $r[$i])->EventType !== 0x0001) {
        continue;
      }
      $e = $e->Event->KeyEvent;
      if (!$e->bKeyDown || $e->wVirtualKeyCode) {
        continue;
      }
      $s .= self::u8chr($e->uChar);
    }
    return $s;
  }
  # }}}
  # }}}
  # setters {{{
  function setConstructed(): void # {{{
  {
    # set record size constant
    $this->recSize =
      $this->api->type('INPUT_RECORD')->getSize();
    # set applied mode
    $this->setMode(
      static::get_applied_mode()
    );
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
      $api = $this->api;
      $mem = $this->varmem;
      $a = $m['sio'];
      $b = &$this->sio;
      if ($a[0] !== $b[0])
      {
        self::con_mode_set(
          $api, $mem, $this->f0, $b[0] = $a[0]
        );
      }
      if ($a[1] !== $b[1])
      {
        self::con_mode_set(
          $api, $mem, $this->f1, $b[1] = $a[1]
        );
      }
      if ($a[2] && $a[2] !== $b[2]) {
        self::con_i_cp($api, $mem, $b[2] = $a[2]);
      }
      if ($a[3] && $a[3] !== $b[3]) {
        self::con_o_cp($api, $mem, $b[3] = $a[3]);
      }
    }
  }
  # }}}
  function setCursorPos(array $xy): void # {{{
  {
    $api  = $this->api;
    $mem  = $this->varmem;
    $h    = $this->f1;
    $o    = $api->cast('COORD', $mem);
    $o->X = $xy[0];
    $o->Y = $xy[1];
    if (!$api->SetConsoleCursorPosition($h, $o))
    {
      throw self::error(
        $api, $mem, 'SetConsoleCursorPosition'
      );
    }
  }
  # }}}
  function puts(string $s): void # {{{
  {
    $x = $this->api->WriteConsoleA(
      $this->f1, $s, strlen($s),
      $this->varmem, null
    );
    if (!$x)
    {
      throw self::error(
        $this->api, $this->varmem, 'WriteConsoleA'
      );
    }
  }
  # }}}
  # }}}
  # essentials {{{
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
      $this->setCursorPos($a);
      $this->puts('          ');
      $this->setCursorPos($a);
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
    # prepare
    $api = $this->api;
    $mem = $this->varmem;
    $i   = $this->f0;
    # check no pending input
    if (!($n = self::kbhit($api, $mem, $i))) {
      return false;
    }
    # determine space required for event records and
    # check for the rupture/overflow
    if ($n * $this->recSize > self::MEM_SIZE)
    {
      $this->clearInput();
      $this->error = ErrorEx::warn(
        'input overflow (cleared)'
      );
      return true;
    }
    # prepare for parsing
    $r = self::get_input($api, $mem, $i, $n);
    $c = $this->pending;
    # parse records
    for ($s='',$i=0; $i < $n; ++$i)
    {
      $e = $r[$i];
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
          ' of the INPUT_RECORD#'.$i.'/'.$n
        );
        break 2;# skip the rest
        # }}}
      }
    }
    # complete
    return $this->setPending($c) > $c;
  }
  # }}}
  function resize(): bool # {{{
  {
    # get console information
    $o = self::con_info(
      $this->api, $this->varmem, $this->f1
    );
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
    # write
    $res = $this->api->WriteConsoleA(
      $this->f1, $this->writeBuf1,
      $this->writeLen1, $this->varmem, null
    );
    # cleanup
    $this->writeBuf1 = '';
    $this->writeLen1 = 0;
    # complete
    if ($res) {
      $this->setWriteComplete();
    }
    else
    {
      $this->writing = -1;
      $this->error = self::error(
        $this->api, $this->varmem, 'WriteConsoleA'
      );
    }
  }
  # }}}
  function clearInput(): void # {{{
  {
    parent::clearInput();
    $x = $this->api
      ->FlushConsoleInputBuffer($this->f0);
    ###
    if (!$x)
    {
      throw self::error(
        $this->api, $this->varmem,
        'FlushConsoleInputBuffer'
      );
    }
  }
  # }}}
  function finit(): void # {{{
  {
  }
  # }}}
  function close(): void # {{{
  {
    $this->api->CloseHandle($this->f0);
    $this->api->CloseHandle($this->f1);
  }
  # }}}
  # }}}
}
trait Conio_BaseA # Async {{{
{
  # base {{{
  public object $writeCallback,$overlapped;
  function setConstructed(): void
  {
    parent::setConstructed();
    ###
    $this->overlapped = self::malloc(
      $this->api, 1, 'OVERLAPPED'
    );
    $this->writeCallback =
      $this->writeCallback(...);
  }
  function finit(): void
  {
    isset($this->overlapped) &&
    FFI::free($this->overlapped);
  }
  # }}}
  function puts(string $s): void # {{{
  {
    $o = $this->overlapped;
    FFI::memset($o, 0, FFI::sizeof($o));
    $x = $this->api->WriteFileEx(
      $this->f1, $s,
      $this->writing = strlen($s), $o,
      $this->writeCallback
    );
    if ($x)
    {
      # wait for completion
      do {Loop::cooldown();}
      while ($this->writing > 0);
    }
    else
    {
      throw self::error(
        $this->api, $this->varmem, 'WriteFileEx'
      );
    }
  }
  # }}}
  function write(): void # {{{
  {
    $o = $this->overlapped;
    FFI::memset($o, 0, FFI::sizeof($o));
    $n = $this->writeLen1;
    $x = $this->api->WriteFileEx(
      $this->f1, $this->writeBuf1, $n, $o,
      $this->writeCallback
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
      $this->error = self::error(
        $this->api, $this->varmem, 'WriteFileEx'
      );
    }
  }
  # }}}
  function writeCallback(# {{{
    int $e, int $n
  ):void
  {
    if ($e === 0)
    {
      $this->writing &&
      $this->setWriteComplete();
    }
    else
    {
      $s = self::strerror(
        $this->api, $this->varmem, $e
      );
      $this->writing = -1;
      $this->error = ErrorEx::fatal(
        'kernel32::WriteFileEx', $s
      );
    }
  }
  # }}}
  function clearOutput(): void # {{{
  {
    # cancel pending write operation
    if ($this->writing > 0)
    {
      if ($this->api->CancelIo($this->f1)) {
        $this->writing = 0;
      }
      else
      {
        $this->writing = -1;
        $this->error = self::error(
          $this->api, $this->varmem, 'CancelIo'
        );
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
