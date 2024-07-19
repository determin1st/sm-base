<?php declare(strict_types=1);
namespace SM;
use FFI,Throwable;
use function
  function_exists,str_repeat,strlen,substr,strpos,
  posix_strerror,pcntl_async_signals,pcntl_signal;
###
abstract class Conio_Base extends Conio_PseudoBase
{
  # constants {{{
  const # termios {{{
    # c_iflag (input)
    IGNBRK  = (1 << 0),# Ignore break condition
    BRKINT  = (1 << 1),# Signal interrupt on break
    IGNPAR  = (1 << 2),# Ignore characters with parity errors
    PARMRK  = (1 << 3),# Mark parity and framing errors
    INPCK   = (1 << 4),# Enable input parity check
    ISTRIP  = (1 << 5),# Strip 8th bit off characters
    INLCR   = (1 << 6),# Map NL to CR on input
    IGNCR   = (1 << 7),# Ignore CR
    ICRNL   = (1 << 8),# Map CR to NL on input
    IXON    = (1 << 9),# Enable start/stop output control
    IXOFF   = (1 << 10),# Enable start/stop input control
    IXANY   = (1 << 11),# Any character will restart after stop
    IMAXBEL = (1 << 13),# Ring bell when input queue is full
    IUCLC   = (1 << 14),# Translate upper case input to lower case
    # c_oflag(output)
    OPOST   = (1 << 0),# Perform output processing
    ONLCR   = (1 << 1),# Map NL to CR-NL on output
    OXTABS  = (1 << 2),# Expand tabs to spaces
    ONOEOT  = (1 << 3),# Discard EOT (^D) on output
    OCRNL   = (1 << 4),# Map CR to NL
    ONOCR   = (1 << 5),# Discard CR's when on column 0
    ONLRET  = (1 << 6),# Move to column 0 on NL
    NLDLY   = (3 << 8),# NL delay
    NL1     = (1 << 8),# NL type 1
    TABDLY  = ((3 << 10)|(1 << 2)),# TAB delay
    TAB1    = (1 << 10),# TAB delay type 1
    TAB2    = (2 << 10),# TAB delay type 2
    CRDLY   = (3 << 12),# CR delay
    CR1     = (1 << 12),# CR delay type 1
    CR2     = (2 << 12),# CR delay type 2
    CR3     = (3 << 12),# CR delay type 3
    FFDLY   = (1 << 14),# FF delay
    FF1     = (1 << 14),# FF delay type 1
    BSDLY   = (1 << 15),# BS delay
    BS1     = (1 << 15),# BS delay type 1
    VTDLY   = (1 << 16),# VT delay
    VT1     = (1 << 16),# VT delay type 1
    OLCUC   = (1 << 17),# Translate lower case output to upper case
    OFILL   = (1 << 18),# Send fill characters for delays
    OFDEL   = (1 << 19),# Fill is DEL
    # c_cflag (control)
    CIGNORE = (1 << 0),# Ignore these control flags
    CS8     = ((1 << 8)|(1 << 9)),# 8 bits per byte
    CS7     = (1 << 9),# 7 bits per byte
    CS6     = (1 << 8),# 6 bits per byte
    CS5     = 0,# 5 bits per byte
    CSTOPB  = (1 << 10),# Two stop bits instead of one
    CREAD   = (1 << 11),# Enable receiver
    PARENB  = (1 << 12),# Parity enable
    PARODD  = (1 << 13),# Odd parity instead of even
    HUPCL   = (1 << 14),# Hang up on last close
    CLOCAL  = (1 << 15),# Ignore modem status lines
    CRTSCTS = (1 << 16),# RTS/CTS flow control
    CDTRCTS = (1 << 17),# DTR/CTS flow control
    MDMBUF  = (1 << 20),# DTR/DCD flow control
    CHWFLOW = ((1 << 16)|(1 << 17)|(1 << 20)),# All types
    # c_lflag (local)
    ECHOKE  = (1 << 0),# Visual erase for KILL
    ECHOE   = (1 << 1),# Visual erase for ERASE
    ECHOK   = (1 << 2),# Echo NL after KILL
    ECHO    = (1 << 3),# Enable echo
    ECHONL  = (1 << 4),# Echo NL even if ECHO is off
    ECHOPRT = (1 << 5),# Hardcopy visual erase
    ECHOCTL = (1 << 6),# Echo control characters as ^X
    ISIG    = (1 << 7),# Enable signals
    ICANON  = (1 << 8),# Do erase and kill processing
    ALTWERASE = (1 << 9),# Alternate WERASE algorithm
    IEXTEN  = (1 << 10),# Enable DISCARD and LNEXT
    EXTPROC = (1 << 11),# External processing
    TOSTOP  = (1 << 22),# Send SIGTTOU for background output
    FLUSHO  = (1 << 23),# Output being flushed (state)
    XCASE   = (1 << 24),# Canonical upper/lower case
    NOKERNINFO = (1 << 25),# Disable VSTATUS
    PENDIN  = (1 << 29),# Retype pending input (state)
    NOFLSH  = (1 << 31),# Disable flush after interrupt
    # c_cc[20] (control characters)
    VEOF    = 0,# End-of-file character [ICANON]
    VEOL    = 1,# End-of-line character [ICANON]
    VEOL2   = 2,# Second EOL character [ICANON]
    VERASE  = 3,# Erase character [ICANON]
    VWERASE = 4,# Word-erase character [ICANON]
    VKILL   = 5,# Kill-line character [ICANON]
    VREPRINT= 6,# Reprint-line character [ICANON]
    VINTR   = 8,# Interrupt character [ISIG]
    VQUIT   = 9,# Quit character [ISIG]
    VSUSP   = 10,# Suspend character [ISIG]
    VDSUSP  = 11,# Delayed suspend character [ISIG]
    VSTART  = 12,# Start (X-ON) character [IXON, IXOFF]
    VSTOP   = 13,# Stop (X-OFF) character [IXON, IXOFF]
    VLNEXT  = 14,# Literal-next character [IEXTEN]
    VDISCARD= 15,# Discard character [IEXTEN]
    VMIN    = 16,# Minimum number of bytes read at once [!ICANON]
    VTIME   = 17,# Time-out value (tenths of a second) [!ICANON]
    VSTATUS = 18,# Status character [ICANON]
    NCCS    = 20;# size, duplicated in <hurd/tioctl.defs>
  # }}}
  const # tcsetattr {{{
    TCSANOW   = 0,# Change immediately
    TCSADRAIN = 1,# Change when pending output is written
    TCSAFLUSH = 2,# Flush pending input before changing
    TCSASOFT  = 0x10;# Flag: Don't alter hardware state
  # }}}
  const # pollfd {{{
    # Event types that can be polled for.
    # These bits may be set in `events' to
    # indicate the interesting event types;
    # they will appear in `revents' to
    # indicate the status of the file descriptor
    POLLIN  = 01,# There is data to read
    POLLPRI = 02,# There is urgent data to read
    POLLOUT = 04,# Writing now will not block
    # Event types always implicitly polled for.
    # These bits need not be set in `events',
    # but they will appear in `revents' to
    # indicate the status of the file descriptor
    POLLERR = 010,# Error condition
    POLLHUP = 020,# Hung up
    POLLNVAL= 040;# Invalid polling request
  # }}}
  const # ioctl {{{
    KDSETMODE    = 0x4B3A,# set text/graphics mode
    KDGETMODE    = 0x4B3B,# get current mode
    KD_TEXT      = 0x00,
    KD_GRAPHICS  = 0x01,
    KD_TEXT0     = 0x02,# obsolete
    KD_TEXT1     = 0x03,# obsolete
    ###
    KDGKBMODE    = 0x4B44,# gets keyboard mode
    KDSKBMODE    = 0x4B45,# sets keyboard mode
    K_RAW        = 0x00,# Raw (scancode) mode
    K_XLATE      = 0x01,# Translate keycodes using keymap
    K_MEDIUMRAW  = 0x02,# Medium raw (scancode) mode
    K_UNICODE    = 0x03,# Unicode mode
    K_OFF        = 0x04,# Disabled mode; since Linux 2.6.39
    # ...
    KDGKBMETA    = 0x4B62,# gets meta key handling mode
    KDSKBMETA    = 0x4B63,# sets meta key handling mode
    K_METABIT    = 0x03,# set high order bit
    K_ESCPREFIX  = 0x04,# escape prefix
    # kernel keycode table entry
    KDGETKEYCODE = 0x4B4C,# read
    KDSETKEYCODE = 0x4B4D,# write
    ### ioctl_tty
    # pending bytes
    FIONREAD = 0x541B,# bytes in the input
    TIOCOUTQ = 0x5411,# output queue size
    # window size
    TIOCGWINSZ = 0x5413,# get
    TIOCSWINSZ = 0x5414,# set
    # line discipline
    TIOCGETD = 0x5424,# get
    TIOCSETD = 0x5423;# set
  # }}}
  const FLAGS = [
    [# 0:i {{{
      'IGNBRK'  => self::IGNBRK,
      'BRKINT'  => self::BRKINT,
      'IGNPAR'  => self::IGNPAR,
      'PARMRK'  => self::PARMRK,
      'INPCK'   => self::INPCK,
      'ISTRIP'  => self::ISTRIP,
      'INLCR'   => self::INLCR,
      'IGNCR'   => self::IGNCR,
      'ICRNL'   => self::ICRNL,
      'IXON'    => self::IXON,
      'IXOFF'   => self::IXOFF,
      'IXANY'   => self::IXANY,
      'IMAXBEL' => self::IMAXBEL,
      'IUCLC'   => self::IUCLC,
    ],
    # }}}
    [# 1:o {{{
      'OPOST'   => self::OPOST,
      'ONLCR'   => self::ONLCR,
      'OXTABS'  => self::OXTABS,
      'ONOEOT'  => self::ONOEOT,
      'OCRNL'   => self::OCRNL,
      'ONOCR'   => self::ONOCR,
      'ONLRET'  => self::ONLRET,
      'NLDLY'   => self::NLDLY,
      'NL1'     => self::NL1,
      'TABDLY'  => self::TABDLY,
      'TAB1'    => self::TAB1,
      'TAB2'    => self::TAB2,
      'CRDLY'   => self::CRDLY,
      'CR1'     => self::CR1,
      'CR2'     => self::CR2,
      'CR3'     => self::CR3,
      'FFDLY'   => self::FFDLY,
      'FF1'     => self::FF1,
      'BSDLY'   => self::BSDLY,
      'BS1'     => self::BS1,
      'VTDLY'   => self::VTDLY,
      'VT1'     => self::VT1,
      'OLCUC'   => self::OLCUC,
      'OFILL'   => self::OFILL,
      'OFDEL'   => self::OFDEL,
    ],
    # }}}
    [# 2:c {{{
      'CIGNORE' => self::CIGNORE,
      'CS8'     => self::CS8,
      'CS7'     => self::CS7,
      'CS6'     => self::CS6,
      'CSTOPB'  => self::CSTOPB,
      'CREAD'   => self::CREAD,
      'PARENB'  => self::PARENB,
      'PARODD'  => self::PARODD,
      'HUPCL'   => self::HUPCL,
      'CLOCAL'  => self::CLOCAL,
      'CRTSCTS' => self::CRTSCTS,
      'CDTRCTS' => self::CDTRCTS,
      'MDMBUF'  => self::MDMBUF,
    ],
    # }}}
    [# 3:l {{{
      'ECHOKE'  => self::ECHOKE,
      'ECHOE'   => self::ECHOE,
      'ECHOK'   => self::ECHOK,
      'ECHO'    => self::ECHO,
      'ECHONL'  => self::ECHONL,
      'ECHOPRT' => self::ECHOPRT,
      'ECHOCTL' => self::ECHOCTL,
      'ISIG'    => self::ISIG,
      'ICANON'  => self::ICANON,
      'ALTWERASE' => self::ALTWERASE,
      'IEXTEN'  => self::IEXTEN,
      'EXTPROC' => self::EXTPROC,
      'TOSTOP'  => self::TOSTOP,
      'FLUSHO'  => self::FLUSHO,
      'XCASE'   => self::XCASE,
      'NOKERNINFO' => self::NOKERNINFO,
      'PENDIN'  => self::PENDIN,
      'NOFLSH'  => self::NOFLSH,
    ],
    # }}}
  ];
  # }}}
  # constructor {{{
  static function new(): object
  {
    # check requirements
    if (!function_exists('posix_strerror'))
    {
      throw ErrorEx::fail(__CLASS__,
        'POSIX extension is required'
      );
    }
    # prepare
    $libc = FFI::load(__DIR__.'/conio-libc.h');
    $mem  = self::malloc($libc, self::MEM_SIZE);
    $base = null;
    # to be able to restore the terminal,
    # proceed into guarded section
    try
    {
      # get I/O descriptors
      [$f0, $f1, $ds] =
        self::get_descriptors($libc, $mem);
      # get current/initial mode of the terminal
      $mode = self::get_mode($libc, $mem, $f0);
      # create specific instance
      $base = self::kd_type($libc, $mem, $f0)
        ? new Conio_BaseKD($libc,$mem,$f0,$f1,$ds,$mode)
        : new Conio_BasePT($libc,$mem,$f0,$f1,$ds,$mode);
      ###
      # initialize
      $base->setMode($base::get_applied_mode($mode));
      $base->init();
    }
    catch (Throwable $e)
    {
      # restore/cleanup
      if ($base)
      {
        # a little pause may help
        # cleaning any input dirt
        Loop::cooldown(100);
        $base->__destruct();
        $base = null;
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
  static function error(# {{{
    object $libc, string $func, string $more=''
  ):object
  {
    $f = 'libc::'.$func;
    $e = posix_strerror($libc->errno);
    return ($more !== '')
      ? ErrorEx::fatal($f, $more, $e)
      : ErrorEx::fatal($f, $e);
  }
  # }}}
  static function tty_first(): int # {{{
  {
    # get first standard descriptor
    # that is not redirected
    return match(true)
    {
      posix_isatty(0) => 0,
      posix_isatty(1) => 1,
      posix_isatty(2) => 2,
      default => -1
    };
  }
  # }}}
  static function tty_name(# {{{
    object $libc, object $mem, int $fd
  ):string
  {
    $e = $libc->ttyname_r($fd, FFI::addr($mem), 100);
    if ($e)
    {
      throw ErrorEx::fatal(
        'libc::ttyname_r', posix_strerror($e)
      );
    }
    $s = FFI::string($mem, 100);
    return substr($s, 0, strpos($s, "\x00"));
  }
  # }}}
  static function get_descriptors(# {{{
    object $libc, object $mem
  ):array
  {
    # get standard descriptor of the real tty
    if (($tty = self::tty_first()) < 0)
    {
      throw ErrorEx::fail(__CLASS__,
        'terminal device is not available'
      );
    }
    # get device path
    $path = self::tty_name($libc, $mem, $tty);
    # open file descriptors
    # using flags:
    # O_RDONLY=0 O_WRONLY=1 O_RDWR=2 O_NONBLOCK=4
    ###
    if (($f0 = $libc->open($path, 0|4)) < 0 ||
        ($f1 = $libc->open($path, 1|4)) < 0)
    {
      throw self::error($libc, 'open', $path);
    }
    return [$f0, $f1, $path];
  }
  # }}}
  static function kd_type(# {{{
    object $libc, object $mem, int $fd
  ):int
  {
    $n = $libc->cast('uint8_t', $mem);
    $e = $libc->ioctl(# KDGKBTYPE
      $fd, 0x4B33, FFI::addr($n)
    );
    if ($e)
    {
      if (($e = $libc->errno) === 0x19) {# ENOTTY
        return 0;# pseudo-terminal
      }
      throw ErrorEx::fatal(
        'libc::ioctl', 'KDGKBTYPE', posix_strerror($e)
      );
    }
    return $n->cdata;# virtual terminal (tty)
  }
  # }}}
  static function termios_get(# {{{
    object $libc, object $mem, int $fd
  ):array
  {
    # initialize memory
    $t = $libc->cast('struct termios', $mem);
    FFI::memset($t, 0, FFI::sizeof($t));
    # invoke
    if ($libc->tcgetattr($fd, FFI::addr($t))) {
      throw self::error($libc, 'tcgetattr');
    }
    # move control chars
    for ($cc=[],$i=0; $i < 20; ++$i) {
      $cc[$i] = $t->c_cc[$i];
    }
    # complete
    return [
      $t->c_iflag, $t->c_oflag,
      $t->c_cflag, $t->c_lflag, $cc
    ];
  }
  # }}}
  static function termios_set(# {{{
    object $libc, object $mem, int $fd,
    array $a, int $how=self::TCSAFLUSH
  ):void
  {
    # initialize structure
    $t = $libc->cast('struct termios', $mem);
    FFI::memset($t, 0, FFI::sizeof($t));
    $t->c_iflag = $a[0];
    $t->c_oflag = $a[1];
    $t->c_cflag = $a[2];
    $t->c_lflag = $a[3];
    for ($b=$a[4],$i=0; $i < 20; ++$i) {
      $t->c_cc[$i] = $b[$i];
    }
    # apply it
    if ($libc->tcsetattr($fd, $how, FFI::addr($t))) {
      throw self::error($libc, 'tcsetattr');
    }
  }
  # }}}
  static function winsize_get(# {{{
    object $libc, object $mem, int $fd
  ):array
  {
    $w = $libc->cast('struct winsize', $mem);
    $e = $libc->ioctl(
      $fd, self::TIOCGWINSZ, FFI::addr($w)
    );
    if ($e) {
      throw self::error($libc, 'ioctl');
    }
    return [$w->ws_col, $w->ws_row];
  }
  # }}}
  static function winsize_set(# {{{
    object $libc, object $mem, int $fd, array $sz
  ):void
  {
    # initialize structure
    $w = $libc->cast('struct winsize', $mem);
    FFI::memset($w, 0, FFI::sizeof($w));
    $w->ws_col = $sz[0];# x/width
    $w->ws_row = $sz[1];# y/height
    # apply it
    $e = $libc->ioctl(
      $fd, self::TIOCSWINSZ, FFI::addr($w)
    );
    if ($e) {
      throw self::error($libc, 'ioctl', 'TIOCSWINSZ');
    }
  }
  # }}}
  static function get_mode(# {{{
    object $libc, object $mem, int $f0
  ):array
  {
    $w = self::winsize_get($libc, $mem, $f0);
    return [
      'sio'    => self::termios_get($libc, $mem, $f0),
      'size'   => $w,
      'scroll' => [$w[0], $w[1], 0, 0],
      'cursor' => [0, 0],
      's8c1t'  => -1
    ];
  }
  # }}}
  static function get_applied_mode(array $m): array # {{{
  {
    # POSIX does not specify whether the setting of
    # the O_NONBLOCK file status flag takes
    # precedence over the MIN and TIME settings.
    # If O_NONBLOCK is set, a read() in noncanonical
    # mode may return immediately, regardless of
    # the setting of MIN or TIME. Furthermore,
    # if no data is available, POSIX permits a read()
    # in noncanonical mode to return either 0,
    # or -1 with errno set to EAGAIN.
    $cc = $m['sio'][4];
    $cc[self::VMIN]  = 0;
    $cc[self::VTIME] = 0;
    return [
      'sio' => [
        0,# c_iflag
        self::OPOST|self::OXTABS,# c_oflag
        self::CIGNORE,# c_cflag
        0,# c_lflag
        $cc # c_cc
      ]
    ];
  }
  # }}}
  static function kbhit(# {{{
    object $api, object $mem, int $fd
  ):int
  {
    $o = $api->cast('uint32_t', $mem);
    $i = $api->ioctl(
      $fd, self::FIONREAD, FFI::addr($o)
    );
    if ($i === 0) {
      return $o->cdata;
    }
    throw self::error(
      $api, 'ioctl', 'FIONREAD'
    );
  }
  # }}}
  static function get_input(# {{{
    object $api, object $mem, int $fd, int $n
  ):int
  {
    # read exact number of bytes
    $n = $api->read($fd, $mem, $n);
    if ($n < 0)
    {
      # EAGAIN is acceptable
      if ($api->errno === 11) {
        return 0;
      }
      # a terrible failure otherwise
      throw self::error($api, 'read');
    }
    # complete
    return $n;
  }
  # }}}
  # }}}
  # setters {{{
  function puts(string $s): void # {{{
  {
    $x = $this->api->write(
      $this->f1, $s, strlen($s)
    );
    if ($x < 0) {
      throw self::error($this->api, 'write');
    }
  }
  # }}}
  function setMode(array $m): void # {{{
  {
    # set private modes
    isset($m['pio']) &&
    $this->_DECSET($m['pio'], true);
    # set system-related modes
    if (isset($m['sio']) &&
        ($a = $m['sio']) !== $this->sio)
    {
      self::termios_set(
        $this->api, $this->varmem, $this->f1, $a
      );
      $this->sio = $a;
    }
  }
  # }}}
  function setKeyboard(): void # {{{
  {
    # activate application mode for special keys
    $this->_DECSET([1=>1, 66=>1]);
    # check and activate xterm key modifiers
    #$this->puts("\x1B[?0m\x1B[?1m\x1B[?2m\x1B[?4m");
    $this->puts("\x1B[?0m");
    if (($s = $this->gets()) !== '')
    {
      $this->puts(
        "\x1B[>0;15m".  # allow all = 1|2|4|8
        "\x1B[>1;2m".   # cursor keys (default)
        "\x1B[>2;2m".   # function keys (default)
        "\x1B[>4;2m"    # other keys
      );
      $this->keyboard = 1;# better
    }
  }
  # }}}
  # }}}
  # getters {{{
  function probeColors(): int # {{{
  {
    # try pseudoterminal
    if ($n = parent::probeColors()) {
      return $n;
    }
    # check environment variables
    if (isset($this->env['COLORTERM']))
    {
      # when this variable is set,
      # 256 colors are always supported
      return match ($this->env['COLORTERM']) {
        'truecolor','24bit' => 24,
        default => 8
      };
    }
    if (isset($this->env['TERM']) &&
        strpos($this->env['TERM'], '-256color'))
    {
      return 8;
    }
    # assume 16-colors are supported
    return 4;
  }
  # }}}
  function getWinSize(): array # {{{
  {
    # it is better to rely on systems api
    return self::winsize_get(
      $this->api, $this->varmem, $this->f0
    );
  }
  # }}}
  function getScroll(): array # {{{
  {
    # generally nix terminals dont have a concept
    # of a scroll, so horizontal is taken from window size,
    # and vertical is queried as xterm resource,
    # offsets are unknown
    $w = $this->size[0];
    $h = (($s = $this->_XTGET('saveLines', true)) === '')
      ? $this->size[1]
      : (int)$s;
    ###
    return [$w, $h, 0, 0];
  }
  # }}}
  function getId(): string # {{{
  {
    # check hardcoded device attribute
    # with undocumented value (60)
    if ($this->devAttr === '?60;1;6;9;15c') {
      return 'libtsm';
    }
    # try DA3 (terminal identification)
    switch ($this->_DA3()) {
    case '~VTE':
      return 'libvte';
    case "\x00\x00\x00\x00":
      return 'xterm';
    }
    # try terminal capability
    switch ($this->_XTGET('name')) {
    case 'xterm-kitty':
      # kitty support of 8bit responses
      # is incomplete and unreliable
      if ($this->s8c1t)
      {
        $this->puts("\x1B F");
        $this->s8c1t = 0;
        $this->re = self::S7E;
      }
      return 'kitty';
    }
    # rely on environment
    return isset($this->env['TERM'])
      ? $this->env['TERM'] : '?';
  }
  # }}}
  function gets(int $timeout=0): string # {{{
  {
    # prepare
    $timeout || $timeout = $this->timeout;
    $api = $this->api;
    $mem = $this->varmem;
    $fd  = $this->f0;
    # wait for the input
    while (!($n = self::kbhit($api, $mem, $fd)))
    {
      if (($timeout -= 5) < 0) {
        return '';# timed out
      }
      Loop::cooldown(5);
    }
    # read everything
    $s = '';
    while ($n)
    {
      if (!($m = self::get_input($api, $mem, $fd, $n)))
      {
        if (($timeout -= 5) < 0) {
          return '';# timed out
        }
        Loop::cooldown(5);
        continue;
      }
      $s .= FFI::string($mem, $m);
      $n -= $m;
    }
    # complete
    return $s;
  }
  # }}}
  # }}}
  # essentials {{{
  function init(): bool # {{{
  {
    # invoke common initializer
    if (!parent::init())
    {
      # assume the cursor is in the first column,
      # try to cleanup after unseccessful request
      $this->puts("\r          \r");
      throw ErrorEx::fail(
        "ANSI ESC codes are not supported\n".
        "cannot properly operate"
      );
    }
    if (!$this->probeUnicode()) {
      $this->unicode = 0;
    }
    $this->setKeyboard();
    $this->setMouse(1);
    /*** UNRELIABLE! ***
    ---
    The SIGWINCH signal is sent to a process
    when its controlling terminal changes its
    size - a window change
    ---
    APPLICATION USAGE
    Applications should take care to avoid race
    conditions and other undefined behavior when
    calling *tcgetsize()* from signal handlers. A
    common idiom is to establish a signal handler
    for *SIGWINCH* from which *tcgetsize()* is
    called to update a global struct winsize.  This
    usage is incorrect as writing to a struct
    winsize is not guaranteed to be an atomic
    operation.  Instead, applications should have
    *tcgetsize()* write to a local structure and
    copy each member the application is interested
    in to a global variable of type *volatile
    sig_atomic_t*.  Furthermore, *SIGWINCH* should
    be blocked from delivery while the terminal size
    is read from these global variables to further
    avoid race conditions.
    ---
    pcntl_signal(28, function():void {
      echo "<<<  SIGWINCH  >>>";
    });
    /***/
    return true;
  }
  # }}}
  function write(): void # {{{
  {
    # write
    $x = $this->api->write(
      $this->f1, $this->writeBuf1,
      $this->writeLen1
    );
    # cleanup
    $this->writeBuf1 = '';
    $this->writeLen1 = 0;
    # complete
    if ($x < 0)
    {
      $this->writing = -1;
      $this->error = self::error(
        $this->api, 'write'
      );
    }
    else {
      $this->setWriteComplete();
    }
  }
  # }}}
  function resize(): bool # {{{
  {
    # TODO: what about the EV_SCROLL?
    # get window size and
    # check it did not change
    $size = $this->getWinSize();
    if ($size === $this->size) {
      return false;
    }
    # initialize last record
    if (!$this->lastSize) {
      $this->lastSize = $this->size;
    }
    # complete
    $this->size = $size;
    return true;
  }
  # }}}
  function read(): bool # {{{
  {
    # prepare
    $api = $this->api;
    $mem = $this->varmem;
    $i   = $this->f0;
    # check nothing is pending
    if (!($n = self::kbhit($api, $mem, $i))) {
      return false;
    }
    # check for the rupture/overflow
    if ($n > self::MEM_SIZE)
    {
      # the routine is designed periodic,
      # overflows are not tolerated,
      # not even for a copy-paste scenario
      $this->clearInput();
      $this->error = ErrorEx::warn(
        'input overflow (cleared)'
      );
      return true;
    }
    # read pending data
    $n = self::get_input($api, $mem, $i, $n);
    if (!$n) {# unlikely, but acceptable
      return false;
    }
    # check pending partial
    if ($i = strlen($this->inputPart))
    {
      # join previous and current data
      $s = $this->inputPart.FFI::string($mem, $n);
      $n = $n + $i;
      # seek the DA1 response that
      # marks the end of the partial
      $r = $this->s8c1t
        ? "\x9B".$this->devAttr
        : "\x1B[".$this->devAttr;
      ###
      if ($j = strpos($s, $r, $i))
      {
        # cut the DA1 response from the data
        # and clear the partial buffer
        $k = strlen($r);
        $s = substr($s, 0, $j).substr($s, $k);
        $n = $n - $k;
        $this->inputPart = '';
      }
      else
      {
        # the DA1 is not arrived yet,
        # accumulate partial data and bail out
        $this->inputPart = $s;
        return true;
      }
    }
    else
    {
      # a nice, whole block of bytes
      $s = FFI::string($mem, $n);
      $j = 0;
    }
    # parse and accumulate events
    $i = $this->s8c1t
      ? $this->parse8($s, $n, $j)
      : $this->parse7($s, $n, $j);
    # update indexes
    if (!$this->setPending())
    {
      throw ErrorEx::fatal(
        'incorrect parse behaviour'
      );
    }
    # check the result
    if ($i < 0)
    {
      # set last error
      $j = $this->inputCount - 1;
      $this->error = $this->input[$j][1];
    }
    elseif ($i < $n)
    {
      # upon incomplete/partial response,
      # store remaining bytes and request DA1
      $this->inputPart = substr($s, $i);
      $this->puts("\x1B[0c");
    }
    return true;
  }
  # }}}
  function clearInput(): void # {{{
  {
    parent::clearInput();
    if ($this->api->tcflush($this->f0, 1))
    {
      throw self::error(
        $this->api, 'tcflush', 'input'
      );
    }
    # TCIFLUSH=1 / input
    # TCOFLUSH=2 / output
  }
  # }}}
  function finit(): void # {{{
  {
    # ignore SIGWINCH
    pcntl_signal(28, \SIG_IGN);
    # restore key modifiers
    $this->keyboard &&
    $this->puts("\x1B[>0m\x1B[>1m\x1B[>2m\x1B[>4m");
  }
  # }}}
  function close(): void # {{{
  {
    $api = $this->api;
    $api->tcflush($this->f0, 1);# TCIFLUSH
    #$api->tcflush($this->f1, 2);# TCOFLUSH
    $api->close($this->f0);
    $api->close($this->f1);
  }
  # }}}
  ############
  ### TODO ###
  ############
  function enableJobControl(): void # {{{
  {
    # create handler
    $h = (function(int $n, $info): void {
    });
    # The SIGCONT signal instructs the OS
    # to continue (restart) a process previously
    # paused by the SIGSTOP or SIGTSTP signal.
    # One important use of this signal
    # is in job control in the Unix shell.
    pcntl_signal(18, $h);# SIGCONT
    # The SIGTSTP signal is sent to a process
    # by its controlling terminal to request it
    # to stop (terminal stop).
    # It is commonly initiated by the user
    # pressing Ctrl+Z. Unlike SIGSTOP,
    # the process can register a signal handler for,
    # or ignore, the signal.
    #pcntl_signal(19, $h);# SIGSTOP: cant be handled!
    pcntl_signal(20, $h);# SIGTSTP: stop (Ctrl+Z)
    # The SIGTTIN and SIGTTOU signals
    # are sent to a process when it attempts
    # to read in or write out respectively
    # from the tty while in the background.
    # Typically, these signals are received
    # only by processes under job control;
    # daemons do not have controlling terminals and,
    # therefore, should never receive these signals
    #pcntl_signal(21, $h);# SIGTTIN
    #pcntl_signal(22, $h);# SIGTTOU
    ###
    pcntl_async_signals(true);
  }
  # }}}
  # }}}
}
class Conio_BasePT extends Conio_Base {
  # Pseudo Terminal (PT)
}
class Conio_BaseKD extends Conio_Base
{
  # TODO
  # Virtual Terminal (VT) / Keyboard Display (KD)
  /* key modes {{{
  * The keyboard driver is made up several levels:
  * [1] the keyboard hardware, which turns the user's
  * finger moves into so-called scancodes
  * (Disclaimer: this is not really part of
  * the software driver itself).
  * An event (key pressed or released) generates
  * a sequence composed of 1 to 6 scancodes.
  * [2] a mechanism turning scancodes into
  * one of 127 possible keycodes using a
  * translation-table which you can access with
  * the getkeycodes(8) and setkeycodes(8) utilities.
  * You will only need to look at that
  * if you have some sort of non-standard
  * (or programmable) keys on your keyboard.
  * [3] a mechanism turning keycodes into characters
  * using a keymap. You can access this keymap
  * using the loadkeys(1) and dumpkeys(1) utilities.
  * ---
  * The keyboard driver can be in one of 4 modes
  * (which you can access using kbd_mode(1)),
  * which will influence what type of data applications
  * will get as keyboard input:
  * [1] the scancode (K_RAW) mode,
  * in which the application gets scancodes for input.
  * It is used by applications that implement their
  * own keyboard driver. For example, X11 does that.
  * [2] the keycode (K_MEDIUMRAW) mode,
  * in which the application gets information
  * on which keys (identified by their keycodes)
  * get pressed and released. AFAIK, no real-life
  * application uses this mode, but it is useful
  * to helper programs like showkey(1) to assist
  * keymap designers.
  * [3] the ASCII (K_XLATE) mode,
  * in which the application effectively gets
  * the characters as defined by the keymap,
  * using an 8-bit encoding. In this mode,
  * the Ascii_0 to Ascii_9 keymap symbols allow
  * to compose characters by giving their decimal
  * 8bit-code, and Hex_0 to Hex_F do the same
  * with (2-digit) hexadecimal codes.
  * [4] the Unicode (K_UNICODE) mode,
  * which at this time only differs from the ASCII
  * mode by allowing the user to compose UTF8
  * unicode characters by their decimal value,
  * using Ascii_0 to Ascii_9 (who needs that ?),
  * or their hexadecimal (4-digit) value,
  * using Hex_0 to Hex_9. A keymap can be set up
  * to produce UTF8 sequences (with a U+XXXX
  * pseudo-symbol, where each X is an hexadecimal
  * digit), but be warned that these UTF8
  * sequences will also be produced even
  * in ASCII mode. I think this is a bug in the kernel.
  * ---
  * putting the keyboard in RAW or MEDIUMRAW mode
  * will make it unusable for most applications.
  * Use showkey(1) to get a demo of these
  * special modes, or to find out what
  * scancodes/keycodes are produced by a specific key.
  * }}} */
  static function kd_kmode(# {{{
    object $libc, object $mem, int $fd, int $mode=-1
  ):int
  {
    $n = $libc->cast('long', $mem);
    if (~$mode)
    {
      $n->cdata = $mode;
      $e = $libc->ioctl(
        $fd, self::KDSKBMODE, FFI::addr($n)
      );
      $e && throw self::error(
        $libc, 'ioctl', 'KDSKBMODE'
      );
      return $mode;
    }
    $e = $libc->ioctl(
      $fd, self::KDGKBMODE, FFI::addr($n)
    );
    $e && throw self::error(
      $libc, 'ioctl', 'KDGKBMODE'
    );
    return $n->cdata;
  }
  # }}}
  static function kd_dmode(# {{{
    object $libc, object $mem, int $fd, int $mode=-1
  ):int
  {
    $n = $libc->cast('int', $mem);
    if (~$mode)
    {
      $n->cdata = $mode;
      $e = $libc->ioctl(
        $fd, self::KDSETMODE, FFI::addr($n)
      );
      $e && throw self::error(
        $libc, 'ioctl', 'KDSETMODE'
      );
      return $mode;
    }
    $e = $libc->ioctl(
      $fd, self::KDGETMODE, FFI::addr($n)
    );
    $e && throw self::error(
      $libc, 'ioctl', 'KDGETMODE'
    );
    return $n->cdata;
  }
  # }}}
  function getColors(): int # {{{
  {
    # 4-bits foreground 3-bits background
    return 3;
  }
  # }}}
}
###
