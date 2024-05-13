<?php declare(strict_types=1);
# defs {{{
namespace SM;
use FFI,SplDoublyLinkedList,Throwable;
use function
  class_exists,hrtime,chr,ord,bin2hex,hex2bin,hexdec,
  str_repeat,strlen,substr,strpos,
  preg_match,preg_match_all,count,explode,
  getenv,ob_start,ob_get_level,ob_end_flush;
###
use const DIRECTORY_SEPARATOR;
require_once __DIR__.DIRECTORY_SEPARATOR.'mustache.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'promise.php';
require_once __DIR__.DIRECTORY_SEPARATOR.(
  (\PHP_OS_FAMILY === 'Windows')
  ? 'conio-win.php'
  : 'conio-nix.php'
);
# }}}
class Conio # {{{
{
  # TODO: default color management/info api
  const # {{{
    # event types {{{
    EV_GROUP  = 0x00,# timeout,count
    EV_KEY    = 0x01,# state,keycode,modifier,char
    EV_MOUSE  = 0x02,# button-state,modifier,x,y
    EV_RESIZE = 0x03,# new-size,old-size
    EV_SCROLL = 0x04,# new-scroll,old-scroll
    EV_FOCUS  = 0x05,# state
    EV_ERROR  = 0xFF,# ErrorEx
    # }}}
    # virtual key codes {{{
    K_LBUTTON    = 0x01,
    K_RBUTTON    = 0x02,
    K_CANCEL     = 0x03,
    # NOT contiguous with L & RBUTTON
    K_MBUTTON    = 0x04,
    K_XBUTTON1   = 0x05,
    K_XBUTTON2   = 0x06,
    # 0x07 : unassigned
    K_BACK       = 0x08,
    K_BACKSPACE  = 0x08,
    K_TAB        = 0x09,
    # 0x0A - 0x0B : reserved
    K_CLEAR      = 0x0C,
    K_RETURN     = 0x0D,
    # 0x0E - 0x0F : unassigned
    K_SHIFT      = 0x10,
    K_CONTROL    = 0x11,
    K_MENU       = 0x12,
    K_PAUSE      = 0x13,
    K_CAPITAL    = 0x14,
    K_KANA       = 0x15,
    K_HANGUL     = 0x15,
    K_JUNJA      = 0x17,
    K_FINAL      = 0x18,
    K_HANJA      = 0x19,
    K_KANJI      = 0x19,
    K_ESCAPE     = 0x1B,
    K_CONVERT    = 0x1C,
    K_NONCONVERT = 0x1D,
    K_ACCEPT     = 0x1E,
    K_MODECHANGE = 0x1F,
    K_SPACE      = 0x20,
    K_PRIOR      = 0x21,
    K_PAGEUP     = 0x21,
    K_NEXT       = 0x22,
    K_PAGEDOWN   = 0x22,
    K_END        = 0x23,
    K_HOME       = 0x24,
    K_LEFT       = 0x25,
    K_UP         = 0x26,
    K_RIGHT      = 0x27,
    K_DOWN       = 0x28,
    K_SELECT     = 0x29,
    K_PRINT      = 0x2A,
    K_EXECUTE    = 0x2B,
    K_SNAPSHOT   = 0x2C,
    K_INSERT     = 0x2D,
    K_DELETE     = 0x2E,
    K_HELP       = 0x2F,
    # ASCII '0'-'9' (0x30-0x39)
    K_0=0x30, K_1=0x31, K_2=0x32, K_3=0x33, K_4=0x34,
    K_5=0x35, K_6=0x36, K_7=0x37, K_8=0x38, K_9=0x39,
    # 0x40 : unassigned
    # ASCII 'A'-'Z' (0x41-0x5A)
    K_A=0x41, K_B=0x42, K_C=0x43, K_D=0x44, K_E=0x45,
    K_F=0x46, K_G=0x47, K_H=0x48, K_I=0x49, K_J=0x4A,
    K_K=0x4B, K_L=0x4C, K_M=0x4D, K_N=0x4E, K_O=0x4F,
    K_P=0x50, K_Q=0x51, K_R=0x52, K_S=0x53, K_T=0x54,
    K_U=0x55, K_V=0x56, K_W=0x57, K_X=0x58, K_Y=0x59,
    K_Z=0x5A,
    ###
    K_LWIN       = 0x5B,
    K_RWIN       = 0x5C,
    K_APPS       = 0x5D,
    # 0x5E : reserved
    K_SLEEP      = 0x5F,
    K_NUMPAD0    = 0x60,
    K_NUMPAD1    = 0x61,
    K_NUMPAD2    = 0x62,
    K_NUMPAD3    = 0x63,
    K_NUMPAD4    = 0x64,
    K_NUMPAD5    = 0x65,
    K_NUMPAD6    = 0x66,
    K_NUMPAD7    = 0x67,
    K_NUMPAD8    = 0x68,
    K_NUMPAD9    = 0x69,
    K_MULTIPLY   = 0x6A,
    K_ADD        = 0x6B,
    K_SEPARATOR  = 0x6C,
    K_SUBTRACT   = 0x6D,
    K_DECIMAL    = 0x6E,
    K_DIVIDE     = 0x6F,
    K_F1         = 0x70,
    K_F2         = 0x71,
    K_F3         = 0x72,
    K_F4         = 0x73,
    K_F5         = 0x74,
    K_F6         = 0x75,
    K_F7         = 0x76,
    K_F8         = 0x77,
    K_F9         = 0x78,
    K_F10        = 0x79,
    K_F11        = 0x7A,
    K_F12        = 0x7B,
    K_F13        = 0x7C,
    K_F14        = 0x7D,
    K_F15        = 0x7E,
    K_F16        = 0x7F,
    K_F17        = 0x80,
    K_F18        = 0x81,
    K_F19        = 0x82,
    K_F20        = 0x83,
    K_F21        = 0x84,
    K_F22        = 0x85,
    K_F23        = 0x86,
    K_F24        = 0x87,
    # 0x88 - 0x8F : unassigned
    K_NUMLOCK    = 0x90,
    K_SCROLL     = 0x91,
    # NEC PC-9800 kbd definitions
    K_OEM_NEC_EQUAL  = 0x92,# '=' key on numpad
    # Fujitsu/OASYS kbd definitions
    K_OEM_FJ_JISHO   = 0x92,# 'Dictionary' key
    K_OEM_FJ_MASSHOU = 0x93,# 'Unregister word' key
    K_OEM_FJ_TOUROKU = 0x94,# 'Register word' key
    K_OEM_FJ_LOYA    = 0x95,# 'Left OYAYUBI' key
    K_OEM_FJ_ROYA    = 0x96,# 'Right OYAYUBI' key
    # 0x97 - 0x9F : unassigned
    # VK_L* & VK_R* - left and right Alt, Ctrl and Shift virtual keys.
    # Used only as parameters to GetAsyncKeyState() and GetKeyState().
    # No other API or message will distinguish left and right keys in this way.
    K_LSHIFT     = 0xA0,
    K_RSHIFT     = 0xA1,
    K_LCONTROL   = 0xA2,
    K_RCONTROL   = 0xA3,
    K_LMENU      = 0xA4,
    K_RMENU      = 0xA5,
    K_BROWSER_BACK        = 0xA6,
    K_BROWSER_FORWARD     = 0xA7,
    K_BROWSER_REFRESH     = 0xA8,
    K_BROWSER_STOP        = 0xA9,
    K_BROWSER_SEARCH      = 0xAA,
    K_BROWSER_FAVORITES   = 0xAB,
    K_BROWSER_HOME        = 0xAC,
    K_VOLUME_MUTE         = 0xAD,
    K_VOLUME_DOWN         = 0xAE,
    K_VOLUME_UP           = 0xAF,
    K_MEDIA_NEXT_TRACK    = 0xB0,
    K_MEDIA_PREV_TRACK    = 0xB1,
    K_MEDIA_STOP          = 0xB2,
    K_MEDIA_PLAY_PAUSE    = 0xB3,
    K_LAUNCH_MAIL         = 0xB4,
    K_LAUNCH_MEDIA_SELECT = 0xB5,
    K_LAUNCH_APP1         = 0xB6,
    K_LAUNCH_APP2         = 0xB7,
    # 0xB8 - 0xB9 : reserved
    K_OEM_1      = 0xBA,# ';:' for US
    K_SEMICOLON  = 0xBA,
    K_OEM_PLUS   = 0xBB,# '=+' any country
    K_EQUAL      = 0xBB,
    K_OEM_COMMA  = 0xBC,# ',' any country
    K_COMMA      = 0xBC,
    K_OEM_MINUS  = 0xBD,# '-' any country
    K_MINUS      = 0xBD,
    K_OEM_PERIOD = 0xBE,# '.' any country
    K_PERIOD     = 0xBE,
    K_OEM_2      = 0xBF,# '/?' for US
    K_SLASH      = 0xBF,
    K_OEM_3      = 0xC0,# '`~' for US
    K_TILDE      = 0xC0,
    # 0xC1 - 0xD7 : reserved
    # 0xD8 - 0xDA : unassigned
    K_OEM_4      = 0xDB,#  '[{' for US
    K_LBRACKET   = 0xDB,
    K_OEM_5      = 0xDC,#  '\|' for US
    K_BACKSLASH  = 0xDC,
    K_OEM_6      = 0xDD,#  ']}' for US
    K_RBRACKET   = 0xDD,
    K_OEM_7      = 0xDE,#  ''"' for US
    K_QUOTE      = 0xDE,
    K_OEM_8      = 0xDF,
    # 0xE0 : reserved
    K_OEM_AX     = 0xE1,#  'AX' key on Japanese AX kbd
    K_OEM_102    = 0xE2,#  "<>" or "\|" on RT 102-key kbd.
    K_ICO_HELP   = 0xE3,#  Help key on ICO
    K_ICO_00     = 0xE4,#  00 key on ICO
    K_PROCESSKEY = 0xE5,
    K_ICO_CLEAR  = 0xE6,
    K_PACKET     = 0xE7,
    # 0xE8 : unassigned
    K_OEM_RESET  = 0xE9,
    K_OEM_JUMP   = 0xEA,
    K_OEM_PA1    = 0xEB,
    K_OEM_PA2    = 0xEC,
    K_OEM_PA3    = 0xED,
    K_OEM_WSCTRL = 0xEE,
    K_OEM_CUSEL  = 0xEF,
    K_OEM_ATTN   = 0xF0,
    K_OEM_FINISH = 0xF1,
    K_OEM_COPY   = 0xF2,
    K_OEM_AUTO   = 0xF3,
    K_OEM_ENLW   = 0xF4,
    K_OEM_BACKTAB= 0xF5,
    K_ATTN       = 0xF6,
    K_CRSEL      = 0xF7,
    K_EXSEL      = 0xF8,
    K_EREOF      = 0xF9,
    K_PLAY       = 0xFA,
    K_ZOOM       = 0xFB,
    K_NONAME     = 0xFC,
    K_PA1        = 0xFD,
    K_OEM_CLEAR  = 0xFE,
    # }}}
    # key modifiers (bitmask) {{{
    # windows compatible
    KM_RALT     = 0x0001,
    KM_LALT     = 0x0002,
    KM_RCTRL    = 0x0004,
    KM_LCTRL    = 0x0008,
    KM_SHIFT    = 0x0010,
    KM_NUM      = 0x0020,
    KM_SCROLL   = 0x0040,
    KM_CAPS     = 0x0080,
    KM_ENHANCED = 0x0100,
    # extras
    KM_META = 0x0200,
    KM_ALT  = 0x0001|0x0002,
    KM_CTRL = 0x0004|0x0008,
    # }}}
    # mouse {{{
    M_BUTTON1 = 0x0001,
    M_BUTTON2 = 0x0002,
    M_BUTTON3 = 0x0004,
    M_BUTTON4 = 0x0008,
    M_BUTTON5 = 0x0010,
    M_BUTTON6 = 0x0020,
    M_BUTTON7 = 0x0040,
    M_BUTTON8 = 0x0080,# unreachable?
    # states/modifiers
    M_RELEASE = 0x0100,
    M_WHEEL   = 0x0200,
    M_MOVE    = 0x0400,
    M = [# names
      0x0001 => 'BUTTON-1',
      0x0002 => 'BUTTON-2',
      0x0004 => 'BUTTON-3',
      0x0008 => 'BUTTON-4',
      0x0010 => 'BUTTON-5',
      0x0020 => 'BUTTON-6',
      0x0040 => 'BUTTON-7',
      0x0080 => 'BUTTON-8',
      0x0100 => 'RELEASE',
      0x0101 => 'RELEASE-1',
      0x0102 => 'RELEASE-2',
      0x0104 => 'RELEASE-3',
      0x0108 => 'RELEASE-4',
      0x0110 => 'RELEASE-5',
      0x0120 => 'RELEASE-6',
      0x0140 => 'RELEASE-7',
      0x0180 => 'RELEASE-8',
      0x0201 => 'WHEEL-UP',
      0x0202 => 'WHEEL-DOWN',
      0x0204 => 'WHEEL-LEFT',
      0x0208 => 'WHEEL-RIGHT',
      0x0400 => 'MOVE',
      0x0401 => 'DRAG-1',
      0x0402 => 'DRAG-2',
      0x0404 => 'DRAG-3',
      0x0408 => 'DRAG-4',
      0x0410 => 'DRAG-5',
      0x0420 => 'DRAG-6',
      0x0440 => 'DRAG-7',
      0x0480 => 'DRAG-8',
    ],
    # }}}
    K = [# keycode => name {{{
      0x00 => '?',
      0x01 => 'LBUTTON',
      0x02 => 'RBUTTON',
      0x03 => 'CANCEL',
      0x04 => 'MBUTTON',
      0x05 => 'XBUTTON1',
      0x06 => 'XBUTTON2',
      0x07 => '',
      0x08 => 'BACKSPACE',
      0x09 => 'TAB',
      0x0A => '',0x0B => '',
      0x0C => 'CLEAR',
      0x0D => 'RETURN',
      0x0E => '',0x0F => '',
      ###
      0x10 => 'SHIFT',
      0x11 => 'CONTROL',
      0x12 => 'MENU',
      0x13 => 'PAUSE',
      0x14 => 'CAPITAL',
      0x15 => 'KANA/HANGUL',
      0x16 => 'IME_ON',
      0x17 => 'JUNJA',
      0x18 => 'FINAL',
      0x19 => 'HANJA/KANJI',
      0x1A => 'IME_OFF',
      0x1B => 'ESCAPE',
      0x1C => 'CONVERT',
      0x1D => 'NONCONVERT',
      0x1E => 'ACCEPT',
      0x1F => 'MODECHANGE',
      ###
      0x20 => 'SPACE',
      0x21 => 'PAGEUP',
      0x22 => 'PAGEDOWN',
      0x23 => 'END',
      0x24 => 'HOME',
      0x25 => 'LEFT',
      0x26 => 'UP',
      0x27 => 'RIGHT',
      0x28 => 'DOWN',
      0x29 => 'SELECT',
      0x2A => 'PRINT',
      0x2B => 'EXECUTE',
      0x2C => 'SNAPSHOT',
      0x2D => 'INSERT',
      0x2E => 'DELETE',
      0x2F => 'HELP',
      ###
      0x30=>'0', 0x31=>'1', 0x32=>'2', 0x33=>'3',
      0x34=>'4', 0x35=>'5', 0x36=>'6', 0x37=>'7',
      0x38=>'8', 0x39=>'9', 0x3A=>'',  0x3B=>'',
      0x3C=>'',  0x3D=>'',  0x3E=>'',  0x3F=>'',
      0x40=>'',
      0x41=>'A', 0x42=>'B', 0x43=>'C', 0x44=>'D',
      0x45=>'E', 0x46=>'F', 0x47=>'G', 0x48=>'H',
      0x49=>'I', 0x4A=>'J', 0x4B=>'K', 0x4C=>'L',
      0x4D=>'M', 0x4E=>'N', 0x4F=>'O', 0x50=>'P',
      0x51=>'Q', 0x52=>'R', 0x53=>'S', 0x54=>'T',
      0x55=>'U', 0x56=>'V', 0x57=>'W', 0x58=>'X',
      0x59=>'Y', 0x5A=>'Z',
      ###
      0x5B => 'LWIN',
      0x5C => 'RWIN',
      0x5D => 'APPS',
      0x5E => '',
      0x5F => 'SLEEP',
      ###
      0x60 => 'NUMPAD0',
      0x61 => 'NUMPAD1',
      0x62 => 'NUMPAD2',
      0x63 => 'NUMPAD3',
      0x64 => 'NUMPAD4',
      0x65 => 'NUMPAD5',
      0x66 => 'NUMPAD6',
      0x67 => 'NUMPAD7',
      0x68 => 'NUMPAD8',
      0x69 => 'NUMPAD9',
      0x6A => 'MULTIPLY',
      0x6B => 'ADD',
      0x6C => 'SEPARATOR',
      0x6D => 'SUBTRACT',
      0x6E => 'DECIMAL',
      0x6F => 'DIVIDE',
      ###
      0x70=>'F1', 0x71=>'F2', 0x72=>'F3', 0x73=>'F4',
      0x74=>'F5', 0x75=>'F6', 0x76=>'F7', 0x77=>'F8',
      0x78=>'F9', 0x79=>'F10', 0x7A=>'F11', 0x7B=>'F12',
      0x7C=>'F13', 0x7D=>'F14', 0x7E=>'F15', 0x7F=>'F16',
      0x80=>'F17', 0x81=>'F18', 0x82=>'F19', 0x83=>'F20',
      0x84=>'F21', 0x85=>'F22', 0x86=>'F23', 0x87=>'F24',
      0x88=>'', 0x89=>'', 0x8A=>'', 0x8B=>'',
      0x8C=>'', 0x8D=>'', 0x8E=>'', 0x8F=>'',
      ###
      0x90 => 'NUMLOCK',
      0x91 => 'SCROLL',
      0x92 => 'OEM_NEC_EQUAL/OEM_FJ_JISHO',
      0x93 => 'OEM_FJ_MASSHOU',
      0x94 => 'OEM_FJ_TOUROKU',
      0x95 => 'OEM_FJ_LOYA',
      0x96 => 'OEM_FJ_ROYA',
      0x97 => '', 0x98 => '', 0x99 => '', 0x9A => '',
      0x9B => '', 0x9C => '', 0x9D => '', 0x9E => '',
      0x9F => '',
      ###
      0xA0 => 'LSHIFT',
      0xA1 => 'RSHIFT',
      0xA2 => 'LCONTROL',
      0xA3 => 'RCONTROL',
      0xA4 => 'LMENU',
      0xA5 => 'RMENU',
      0xA6 => 'BROWSER_BACK',
      0xA7 => 'BROWSER_FORWARD',
      0xA8 => 'BROWSER_REFRESH',
      0xA9 => 'BROWSER_STOP',
      0xAA => 'BROWSER_SEARCH',
      0xAB => 'BROWSER_FAVORITES',
      0xAC => 'BROWSER_HOME',
      0xAD => 'VOLUME_MUTE',
      0xAE => 'VOLUME_DOWN',
      0xAF => 'VOLUME_UP',
      ###
      0xB0 => 'MEDIA_NEXT_TRACK',
      0xB1 => 'MEDIA_PREV_TRACK',
      0xB2 => 'MEDIA_STOP',
      0xB3 => 'MEDIA_PLAY_PAUSE',
      0xB4 => 'LAUNCH_MAIL',
      0xB5 => 'LAUNCH_MEDIA_SELECT',
      0xB6 => 'LAUNCH_APP1',
      0xB7 => 'LAUNCH_APP2',
      0xB8 => '', 0xB9 => '',
      0xBA => 'SEMICOLON',
      0xBB => 'EQUAL',
      0xBC => 'COMMA',
      0xBD => 'MINUS',
      0xBE => 'PERIOD',
      0xBF => 'SLASH',
      ###
      0xC0 => 'OEM_3',
      0xC1 => '', 0xC2 => '', 0xC3 => '', 0xC4 => '',
      0xC5 => '', 0xC6 => '', 0xC7 => '', 0xC8 => '',
      0xC9 => '', 0xCA => '', 0xCB => '', 0xCC => '',
      0xCD => '', 0xCE => '', 0xCF => '',
      0xD0 => '', 0xD1 => '', 0xD2 => '', 0xD3 => '',
      0xD4 => '', 0xD5 => '', 0xD6 => '', 0xD6 => '',
      0xD7 => '', 0xD8 => '', 0xD9 => '', 0xDA => '',
      0xDB => 'LBRACKET',
      0xDC => 'BACKSLASH',
      0xDD => 'RBRACKET',
      0xDE => 'QUOTE',
      0xDF => 'OEM_8',
      ###
      0xE0 => '',
      0xE1 => 'OEM_AX',
      0xE2 => 'OEM_102',
      0xE3 => 'ICO_HELP',
      0xE4 => 'ICO_00',
      0xE5 => 'PROCESSKEY',
      0xE6 => 'ICO_CLEAR',
      0xE7 => 'PACKET',
      0xE8 => '',
      0xE9 => 'OEM_RESET',
      0xEA => 'OEM_JUMP',
      0xEB => 'OEM_PA1',
      0xEC => 'OEM_PA2',
      0xED => 'OEM_PA3',
      0xEE => 'OEM_WSCTRL',
      0xEF => 'OEM_CUSEL',
      ###
      0xF0 => 'OEM_ATTN',
      0xF1 => 'OEM_FINISH',
      0xF2 => 'OEM_COPY',
      0xF3 => 'OEM_AUTO',
      0xF4 => 'OEM_ENLW',
      0xF5 => 'OEM_BACKTAB',
      0xF6 => 'ATTN',
      0xF7 => 'CRSEL',
      0xF8 => 'EXSEL',
      0xF9 => 'EREOF',
      0xFA => 'PLAY',
      0xFB => 'ZOOM',
      0xFC => 'NONAME',
      0xFD => 'PA1',
      0xFE => 'OEM_CLEAR',
      0xFF => ''
    ],
    # }}}
    C1N = [# 8-bit control info {{{
      0x84 => ['IND','index'],
      0x85 => ['NEL','next line'],
      0x88 => ['HTS','horizontal tab set'],
      0x8D => ['RI','reverse index'],
      0x8E => ['SS2','single shift 2'],
      0x8F => ['SS3','single shift 3'],
      0x90 => ['DCS','device control string'],
      0x96 => ['SPA','start of guarded area'],
      0x97 => ['EPA','end of guarded area'],
      0x9C => ['ST','string terminator'],
      0x9D => ['OSC','operating system command'],
      0x9E => ['PM','privacy message'],
      0x9F => ['APC','application program command'],
    ];
    # }}}
  # }}}
  # initializer {{{
  static ?object $BASE=null;
  private function __construct()
  {}
  static function init(array $o=[]): ?object
  {
    # check already constructed
    if (self::$BASE) {
      return null;
    }
    # check requirements
    if (!class_exists('FFI'))
    {
      return ErrorEx::fail(__CLASS__,
        'FFI extension is required'
      );
    }
    # construct OS-specific instance and
    # add the gear of asynchronicity
    try
    {
      self::$BASE = ConioBase::new();
      Loop::gear(new ConioGear(self::$BASE));
      $e = null;
    }
    catch (Throwable $e)
    {
      $e = ErrorEx::from($e);
      self::$BASE = null;
    }
    return $e;
  }
  # }}}
  # informational {{{
  static function id(): string {
    return self::$BASE->id;
  }
  static function is_ansi(): bool {
    return !!self::$BASE->ansi;
  }
  static function has_colors(): int {
    return self::$BASE->colors;
  }
  static function has_8bits(): bool {
    return !!self::$BASE->s8c1t;
  }
  static function is_unicode(): bool {
    return !!self::$BASE->unicode;
  }
  static function is_mouse(): int {
    return self::$BASE->mouse;
  }
  static function is_keyboard(): int {
    return self::$BASE->keyboard;
  }
  static function is_async(): bool {
    return !!self::$BASE->async;
  }
  static function dev_src(): string {
    return self::$BASE->devPath;
  }
  static function dev_attr(): string {
    return self::$BASE->devAttr;
  }
  static function dev_id(): string {
    return self::$BASE->devId;
  }
  static function is_focused(): bool {
    return !!self::$BASE->focused;
  }
  static function get_size(): array {
    return self::$BASE->size;
  }
  # }}}
  # api {{{
  static function read(): object
  {
    return Promise::Context(
      new ConioReadEvents(self::$BASE)
    );
  }
  static function readch(int $n=1): object
  {
    return new Promise(
      new ConioReadChars(self::$BASE, $n)
    );
  }
  static function drain(): object
  {
    return new Promise(
      new ConioWriteComplete(self::$BASE)
    );
  }
  # }}}
}
# }}}
# gears {{{
class ConioGear extends Completable # {{{
{
  const RESIZE_INTERVAL=333*1000000;
  public int $lastActive,$nextResize;
  function __construct(
    public ?object &$base
  ) {
    $this->lastActive = $t = self::$HRTIME;
    $this->nextResize = $t + self::RESIZE_INTERVAL;
  }
  function _cancel(): void
  {
    $this->base->__destruct();
    $this->base = null;
  }
  function _complete(): ?object
  {
    static $i=3,$WAIT=[# ms
      96*1000000,
      32*1000000,
      16*1000000,
      8*1000000
    ];
    static $SPAN=[# sec
      0, 90*1000000000,
      30*1000000000,
      10*1000000000
    ];
    # prepare
    $time = self::$HRTIME;
    $base = $this->base;
    # decompose event groups
    if (($n = $base->pending) &&
        $time >= $base->events[0][1])
    {
      # decompose until fresh
      $q = $base->events;
      do
      {
        $k = $q->shift()[2];# group size
        $n = $n - $k - 1;# substract events + group
        do {
          $q->shift();
        }
        while (--$k);
      }
      while ($n && $time >= $q[0][1]);
      # update counter
      $base->pending = $n;
    }
    # write output
    if ($n = $base->writeLen1) {
      $base->write();
    }
    # poll resize/scroll change
    if ($time >= $this->nextResize)
    {
      $base->resize($time);
      $this->nextResize =
        $time + self::RESIZE_INTERVAL;
    }
    # read new input and check active
    if ($base->read($time) || $n)
    {
      $this->lastActive = $time;
      return self::$THEN->nanowait(
        $base->delay = $WAIT[$i = 3]
      );
    }
    # unfocused?
    if (!$base->focused)
    {
      return self::$THEN->nanowait(
        $base->delay = self::RESIZE_INTERVAL
      );
    }
    # active waiting?
    if ($i)
    {
      $time -= $this->lastActive;
      do
      {
        if ($time < $SPAN[$i])
        {
          return self::$THEN->nanowait(
            $base->delay = $WAIT[$i]
          );
        }
      }
      while (--$i);
    }
    # relaxed waiting
    return self::$THEN->nanowait(
      $base->delay = $WAIT[0]
    );
  }
}
# }}}
abstract class ConioReader extends Contextable # {{{
{
  public int $_stage=0;
  function _wait(): object # {{{
  {
    # wait in sync with the gear,
    # the gear executes and sets pending state,
    # then other readers may consume the input
    return self::$THEN->nanowait(
      $this->_base->delay
    );
  }
  # }}}
  function _lock(): object # {{{
  {
    # locking prevents other readers
    # from consuming input events
    return $this->_base->setRead($this)
      ? $this->_enter()
      : $this->_wait();
  }
  # }}}
  function _done(): void # {{{
  {
    if ($this->_stage)
    {
      $this->_base->setRead();
      $this->_stage = 0;
    }
  }
  # }}}
  function _cancel(): void # {{{
  {
    $this->_done();
  }
  # }}}
  function _complete(): ?object # {{{
  {
    return match ($this->_stage) {
      0 => $this->_lock(),
      1 => $this->_dispatch(),
      default => null
    };
  }
  # }}}
  function read(): object # {{{
  {
    if ($this->_stage !== 2)
    {
      throw ErrorEx::warn(static::class,
        "unable to continue reading\n".
        "incorrect stage=".$this->_stage
      );
    }
    # the reader is already aquired the lock,
    # start dispathing
    $this->_stage = 1;
    return Promise::Context($this);
  }
  # }}}
  abstract function _enter(): object;
  abstract function _dispatch(): ?object;
}
# }}}
class ConioReadEvents extends ConioReader # {{{
{
  # constructor {{{
  public array $events;
  public int   $count;
  function __construct(
    public object $_base
  ) {}
  # }}}
  function _enter(): object # {{{
  {
    # initialize and move to the next stage
    $this->events = [];
    $this->count  = 0;
    $this->_stage++;
    return $this;
  }
  # }}}
  function _dispatch(): ?object # {{{
  {
    # prepare
    $base = $this->_base;
    $q = $base->events;
    $a = &$this->events;
    $k = &$this->count;
    # collect code/key/mouse events
    if ($n = $base->pending)
    {
      # collect
      do
      {
        if (($e = $q->shift())[0])
        {
          $a[] = $e;
          $k++;
        }
      }
      while (--$n);
      # reset counter
      $base->pending = 0;
    }
    # check resized or scrolled
    if ($b = $base->lastSize)
    {
      # add resize event
      if ($b !== $base->size)
      {
        $a[] = [Conio::EV_RESIZE, $base->size, $b];
        $k++;
      }
      # invalidate previous size and scroll
      $base->lastSize = $base->lastScroll = null;
    }
    elseif ($b = $base->lastScroll)
    {
      # add scroll event
      if ($b !== $base->lastScroll)
      {
        $a[] = [Conio::EV_SCROLL, $base->scroll, $b];
        $k++;
      }
      # invalidate previous scroll
      $base->lastScroll = null;
    }
    # check focused
    if (($c = $base->lastFocused) >= 0)
    {
      # add focus event
      if ($c !== $base->focused)
      {
        $a[] = [Conio::EV_FOCUS, $base->focused];
        $k++;
      }
      # invalidate previous focus state
      $base->lastFocused = -1;
    }
    # complete
    if ($k)
    {
      $this->result->value = $this;
      $this->_stage++;
      return null;
    }
    # retry
    return $this->_wait();
  }
  # }}}
}
# }}}
class ConioReadChars extends ConioReader # {{{
{
  # constructor {{{
  public string $chars;
  public int    $count;
  function __construct(
    public object $_base,
    public int    $_count
  ) {}
  # }}}
  function _enter(): object # {{{
  {
    $this->chars = '';
    $this->count = 0;
    $this->_stage++;
    return $this;
  }
  # }}}
  function _dispatch(): ?object # {{{
  {
    # check pending events
    $base = $this->_base;
    if (!($n = $base->pending)) {
      return $this->_wait();
    }
    # collect characters
    $q = $base->events;
    $s = &$this->chars;
    $c = &$this->count;
    $d = $this->_count;
    do
    {
      # shift one
      $e = $q->shift();
      $n--;
      # skip non-characters
      if ($e[0] !== Conio::EV_KEY ||
          ($a = $e[1]) === 0 ||
          ($b = $e[4]) === '')
      {
        continue;
      }
      # check repeat
      if ($a > 1)
      {
        # correct overflow
        if ($c + $a > $d) {
          $a = $d - $c;
        }
        # grow
        $b = str_repeat($b, $a);
      }
      # accumulate
      $s .= $b;
      $c += $a;
      # check complete
      if ($c === $d)
      {
        # cut remaining events til the next group
        while ($n && $q[0][0] !== Conio::EV_GROUP)
        {
          $q->shift();
          $n--;
        }
        # complete
        $base->pending = $n;
        $base->setRead();
        $this->result->value = $s;
        $this->_stage++;
        return null;
      }
    }
    while ($n);
    # incomplete
    $base->pending = 0;
    return $this->_wait();
  }
  # }}}
}
# }}}
class ConioWriteComplete extends Completable # {{{
{
  function __construct(
    public object $_base
  ) {}
  function _complete(): ?object
  {
    $base = $this->_base;
    return ($base->writeLen1 || $base->writing > 0)
      ? self::$THEN->nanowait($base->delay)
      : null;
  }
}
# }}}
class ConioHlp implements Mustachable # {{{
{
  # TODO
  const STASH = [# {{{
    'title' => 7+1,
  ];
  # }}}
  function title(object $m, string $a): string # {{{
  {
    return "\x1B]2;".$a."\x1B\\";
  }
  # }}}
}
# }}}
# }}}
abstract class ConioPseudo
{
  const # {{{
    # memory for FFI calls
    MEM_SIZE  = 0x10000,# 64k
    MAX_WRITE = 0x10000,
    # C1 controls {{{
    ASK = "".    # The 1st query to the terminal
      "\x1B<".   # ANSI/VT52 doesnt support DA1
      "\x1B[0c". # DA1 is a basic sequence
    "",
    # anchored patterns
    # single shift 3
    SS3 = "/". # <SS3><P><F>
      "([0-9]{0,3})".# P - parameter bytes
      "([A-Za-z])".# F - final byte
    "/A",
    # control sequence introducer (body parser)
    CSI = "/". # <CSI><P><I + F>
      "([\x30-\x3F]{0,30})".# [P]arameter bytes
      "([\x20-\x2E\\/]{0,1}".# [I]ntermediate bytes
      "[\x40-\x7E])".# [F]inal byte
    "/A",
    # control string
    CST7 = "/".
      "([\x20-\x7E]{0,255})".# content
      "\x1B\\\\".# string terminator
    "/A",
    CST8 = "/".
      "([\x20-\x7E]{0,255})".# content
      "\x9C".# string terminator
    "/A",
    # immediate patterns
    S7E = [
      'DA1' => "/\x1B\\[(\\?.+c)/",
      'CPR' => "/\x1B\\[(\\d+);(\\d+)R/",
      'XTGET' => "/\x1BP1\\+[rR].+=(.+)\x1B\\\\/",
      'DECRPTUI' => "/\x1BP!\\|(.+)\x1B\\\\/",
      'DECRPM' => "/\x1B\\[\\?(\\d+);(\\d+)\\\$y/",
      'RGB' =>
        "~\x1B\\]\\d+;".
        "rgb[ai]?:([0-9A-F]+)/([0-9A-F]+)/([0-9A-F]+)".
        "\x1B\\\\~i"
    ],
    S8E = [# 8-bit
      'DA1' => "/\x9B(\\?.+c)/",
      'CPR' => "/\x9B(\\d+);(\\d+)R/",
      'XTGET' => "/\x901\\+[rR].+=(.+)\x9C/",
      'DECRPTUI' => "/\x90!\\|(.+)\x9C/",
      'DECRPM' => "/\x9B\\?(\\d+);(\\d+)\\\$y/",
      'RGB' =>
        "~\x9D\\d+;".
        "rgb[ai]?:([0-9A-F]+)/([0-9A-F]+)/([0-9A-F]+)".
        "\x9C~i"
    ],
    # }}}
    DECRQM = [# requested private modes {{{
      ###
      # VT100 mode (otherwise DECANM ANSI/VT52)
      # this is usually on for a nice terminal,
      # the meaning could be:
      # 0 - probably 3, but could be DECANM
      # 2 - supports switching into legacy
      # 3 - doesnt support switching
      2   ,# enabled=VT100, disabled=VT52
      # keys application mode (DECCKM) -
      # SS3 sequence on keypress allows to get
      # more precise information of the pressed key
      1   ,# cursor keys
      66  ,# keypad keys
      ###
      ### normal mouse tracking
      ###
      # the next two are basically the same,
      # the later supplies key modifiers
      9   ,# SET_X10_MOUSE
      1000,# SET_VT200_MOUSE
      # highlighting is software-layer oriented
      # protocol, here, is the abstraction-layer,
      # so this mode should be off
      1001,# SET_VT200_HIGHLIGHT_MOUSE
      # enables reporting of motion
      # with the mouse button is pressed down
      1002,# SET_BTN_EVENT_MOUSE
      # enables reporting of motion
      # without button pressed, plus
      # the previous modes: 9,1000,1002
      1003,# SET_ANY_EVENT_MOUSE
      # focus in and out events: <CSI>I and <CSI>O
      1004,# SET_FOCUS_EVENT_MOUSE
      # what a heck is this one? i did not get,
      # a mouse wheel that induces additional
      # events when alternate screen buffer
      # is active? it is poorly designed and
      # documented - should be off
      1007,# SET_ALTERNATE_SCROLL
      ###
      ### extended mouse tracking
      ###
      # these protocols are designed for
      # terminals are wider(taller) than
      # 255-32=223 columns/rows
      # the first one, 1005 is a king of
      # the poor design, should be off,
      # take your crown man!
      1005,# SET_EXT_MODE_MOUSE
      # this one is better but
      # only in comparison to the previous,
      # it looses greatly to the next one
      # - final "m" is excessive, serves no good
      #   except having an additional check
      # - first parameter is prefixed with "<"
      #   that obligates parser to remove it
      1006,# SET_SGR_EXT_MODE_MOUSE
      # this is a good extension of
      # the normal tracking, encoded as
      # a whole sequence. xterm docs state
      # very stupid cons that something can
      # be mistaken with terminal manipulation -
      # it is a response parser man, wake up!
      1015,# SET_URXVT_EXT_MODE_MOUSE
      # like 1006, but pixels not chars,
      # should be off
      1016,# SET_PIXEL_POSITION_MOUSE
      /***
      # click1 emit Esc seq to move point
      2001,# SET_BUTTON1_MOVE_POINT
      # press2 emit Esc seq to move point
      2002,# SET_BUTTON2_MOVE_POINT
      # Double click-3 deletes
      2003,# SET_DBUTTON3_DELETE
      # Surround paste by escapes
      2004,# SET_PASTE_IN_BRACKET
      # Quote each char during paste
      2005,# SET_PASTE_QUOTE
      # Paste "\n" as C-j
      2006,# SET_PASTE_LITERAL_NL
      /***/
      # Alternate/Normal screen buffer
      1047,
      1048,# cursor save/restore
      1049,# 1047+1048
    ],
    # }}}
    M_TRACKING = [# {{{
      # deactivate (mouse only)
      [
        9=>2,1000=>2,1001=>2,1002=>2,1003=>2,
        1015=>2
      ],
      # activate (initial)
      [
        9=>1,1000=>1,1001=>1,1002=>1,1003=>1,# normal
        1004=>1,# focus
        1005=>2,1006=>2,1016=>2,1015=>1 # ext
      ],
      # activate (mouse only)
      [
        9=>1,1000=>1,1001=>1,1002=>1,1003=>1,
        1015=>1
      ]
    ],
    # }}}
    KEY_SPECIAL = [# {{{
      # cursor keys (SS3/CSI)
      'A' => [Conio::K_UP,0],
      'B' => [Conio::K_DOWN,0],
      'C' => [Conio::K_RIGHT,0],
      'D' => [Conio::K_LEFT,0],
      'E' => [Conio::K_CLEAR,],
      'H' => [Conio::K_HOME,0],
      'F' => [Conio::K_END,0],
      'Z' => [Conio::K_TAB,Conio::KM_SHIFT],
      # editing keypad (CSI)
      '1~' => [Conio::K_HOME, 0],
      '2~' => [Conio::K_INSERT, 0],
      '3~' => [Conio::K_DELETE, 0],
      '4~' => [Conio::K_END, 0],
      '5~' => [Conio::K_PAGEUP, 0],
      '6~' => [Conio::K_PAGEDOWN, 0],
      # keypad (SS3)
      'j' => [Conio::K_MULTIPLY, 0],
      'k' => [Conio::K_ADD, 0],
      'l' => [Conio::K_SEPARATOR, 0],
      'm' => [Conio::K_SUBTRACT, 0],
      'n' => [Conio::K_DECIMAL, 0],
      'o' => [Conio::K_DIVIDE, 0],
      # function keys (SS3/CSI)
      'P' => [Conio::K_F1, 0],
      'Q' => [Conio::K_F2, 0],
      'R' => [Conio::K_F3, 0],
      'S' => [Conio::K_F4, 0],
      # old function keys (CSI)
      '11~' => [Conio::K_F1, 0],
      '12~' => [Conio::K_F2, 0],
      '13~' => [Conio::K_F3, 0],
      '14~' => [Conio::K_F4, 0],
      # function keys (CSI)
      '15~' => [Conio::K_F5, 0],
      '17~' => [Conio::K_F6, 0],
      '18~' => [Conio::K_F7, 0],
      '19~' => [Conio::K_F8, 0],
      '20~' => [Conio::K_F9, 0],
      '21~' => [Conio::K_F10, 0],
      '23~' => [Conio::K_F11, 0],
      '24~' => [Conio::K_F12, 0],
      '25~' => [Conio::K_F13, 0],
      '26~' => [Conio::K_F14, 0],
      '28~' => [Conio::K_F15, 0],
      '29~' => [Conio::K_F16, 0],
      '31~' => [Conio::K_F17, 0],
      '32~' => [Conio::K_F18, 0],
      '33~' => [Conio::K_F19, 0],
      '34~' => [Conio::K_F20, 0],
      '42~' => [Conio::K_F21, 0],
      '43~' => [Conio::K_F22, 0],
      '44~' => [Conio::K_F23, 0],
      '45~' => [Conio::K_F24, 0],
    ],
    # }}}
    KEY_ASCII = [# {{{
      # CTRL + ...
      #0x00 => [Conio::K_TILDE, Conio::KM_CTRL],
      0x00 => [Conio::K_SPACE,Conio::KM_CTRL],
      # CTRL + [A-Z], H/I/M - overlap
      0x01=>[0x41,Conio::KM_CTRL], 0x02=>[0x42,Conio::KM_CTRL],
      0x03=>[0x43,Conio::KM_CTRL], 0x04=>[0x44,Conio::KM_CTRL],
      0x05=>[0x45,Conio::KM_CTRL], 0x06=>[0x46,Conio::KM_CTRL],
      0x07=>[0x47,Conio::KM_CTRL], 0x08=>[0x48,Conio::KM_CTRL],
      0x09=>[0x49,Conio::KM_CTRL], 0x0A=>[0x4A,Conio::KM_CTRL],
      0x0B=>[0x4B,Conio::KM_CTRL], 0x0C=>[0x4C,Conio::KM_CTRL],
      0x0D=>[0x4D,Conio::KM_CTRL], 0x0E=>[0x4E,Conio::KM_CTRL],
      0x0F=>[0x4F,Conio::KM_CTRL], 0x10=>[0x50,Conio::KM_CTRL],
      0x11=>[0x51,Conio::KM_CTRL], 0x12=>[0x52,Conio::KM_CTRL],
      0x13=>[0x53,Conio::KM_CTRL], 0x14=>[0x54,Conio::KM_CTRL],
      0x15=>[0x55,Conio::KM_CTRL], 0x16=>[0x56,Conio::KM_CTRL],
      0x17=>[0x57,Conio::KM_CTRL], 0x18=>[0x58,Conio::KM_CTRL],
      0x19=>[0x59,Conio::KM_CTRL], 0x1A=>[0x5A,Conio::KM_CTRL],
      # CTRL + [\]67
      0x1B=>[Conio::K_LBRACKET,Conio::KM_CTRL],# ESC overlap
      0x1C=>[Conio::K_BACKSLASH,Conio::KM_CTRL],
      0x1D=>[Conio::K_RBRACKET,Conio::KM_CTRL],
      0x1E=>[0x36,Conio::KM_CTRL],
      0x1F=>[Conio::K_SLASH,Conio::KM_CTRL],
      ####
      0x20=>[Conio::K_SPACE,0],
      0x21=>[Conio::K_1,Conio::KM_SHIFT],
      0x22=>[Conio::K_QUOTE, Conio::KM_SHIFT],
      0x23=>[Conio::K_3,Conio::KM_SHIFT],
      0x24=>[Conio::K_4,Conio::KM_SHIFT],
      0x25=>[Conio::K_5,Conio::KM_SHIFT],
      0x26=>[Conio::K_7,Conio::KM_SHIFT],
      0x27=>[Conio::K_QUOTE,0],
      0x28=>[Conio::K_9,Conio::KM_SHIFT],
      0x29=>[Conio::K_0,Conio::KM_SHIFT],
      0x2A=>[Conio::K_8,Conio::KM_SHIFT],
      0x2B=>[Conio::K_EQUAL,Conio::KM_SHIFT],
      0x2C=>[Conio::K_COMMA,0],
      0x2D=>[Conio::K_MINUS,0],
      0x2E=>[Conio::K_PERIOD,0],
      0x2F=>[Conio::K_SLASH,0],
      #### 0-9
      0x30=>[Conio::K_0,0], 0x31=>[Conio::K_1,0],
      0x32=>[Conio::K_2,0], 0x33=>[Conio::K_3,0],
      0x34=>[Conio::K_4,0], 0x35=>[Conio::K_5,0],
      0x36=>[Conio::K_6,0], 0x37=>[Conio::K_7,0],
      0x38=>[Conio::K_8,0], 0x39=>[Conio::K_9,0],
      ####
      0x3A=>[Conio::K_SEMICOLON,Conio::KM_SHIFT],
      0x3B=>[Conio::K_SEMICOLON,0],
      0x3C=>[Conio::K_COMMA,Conio::KM_SHIFT],
      0x3D=>[Conio::K_EQUAL,0],
      0x3E=>[Conio::K_PERIOD,Conio::KM_SHIFT],
      0x3F=>[Conio::K_SLASH,Conio::KM_SHIFT],
      0x40=>[Conio::K_2,Conio::KM_SHIFT],
      #### SHIFT + [A-Z]
      0x41=>[0x41,Conio::KM_SHIFT], 0x42=>[0x42,Conio::KM_SHIFT],
      0x43=>[0x43,Conio::KM_SHIFT], 0x44=>[0x44,Conio::KM_SHIFT],
      0x45=>[0x45,Conio::KM_SHIFT], 0x46=>[0x46,Conio::KM_SHIFT],
      0x47=>[0x47,Conio::KM_SHIFT], 0x48=>[0x48,Conio::KM_SHIFT],
      0x49=>[0x49,Conio::KM_SHIFT], 0x4A=>[0x4A,Conio::KM_SHIFT],
      0x4B=>[0x4B,Conio::KM_SHIFT], 0x4C=>[0x4C,Conio::KM_SHIFT],
      0x4D=>[0x4D,Conio::KM_SHIFT], 0x4E=>[0x4E,Conio::KM_SHIFT],
      0x4F=>[0x4F,Conio::KM_SHIFT], 0x50=>[0x50,Conio::KM_SHIFT],
      0x51=>[0x51,Conio::KM_SHIFT], 0x52=>[0x52,Conio::KM_SHIFT],
      0x53=>[0x53,Conio::KM_SHIFT], 0x54=>[0x54,Conio::KM_SHIFT],
      0x55=>[0x55,Conio::KM_SHIFT], 0x56=>[0x56,Conio::KM_SHIFT],
      0x57=>[0x57,Conio::KM_SHIFT], 0x58=>[0x58,Conio::KM_SHIFT],
      0x59=>[0x59,Conio::KM_SHIFT], 0x5A=>[0x5A,Conio::KM_SHIFT],
      ####
      0x5B=>[Conio::K_LBRACKET,0],
      0x5C=>[Conio::K_BACKSLASH,0],
      0x5D=>[Conio::K_RBRACKET,0],
      0x5E=>[Conio::K_6,Conio::KM_SHIFT],
      0x5F=>[Conio::K_MINUS,Conio::KM_SHIFT],
      0x60=>[Conio::K_TILDE,0],
      #### A-Z
      0x61=>[0x41,0], 0x62=>[0x42,0],
      0x63=>[0x43,0], 0x64=>[0x44,0],
      0x65=>[0x45,0], 0x66=>[0x46,0],
      0x67=>[0x47,0], 0x68=>[0x48,0],
      0x69=>[0x49,0], 0x6A=>[0x4A,0],
      0x6B=>[0x4B,0], 0x6C=>[0x4C,0],
      0x6D=>[0x4D,0], 0x6E=>[0x4E,0],
      0x6F=>[0x4F,0], 0x70=>[0x50,0],
      0x71=>[0x51,0], 0x72=>[0x52,0],
      0x73=>[0x53,0], 0x74=>[0x54,0],
      0x75=>[0x55,0], 0x76=>[0x56,0],
      0x77=>[0x57,0], 0x78=>[0x58,0],
      0x79=>[0x59,0], 0x7A=>[0x5A,0],
      ####
      0x7B=>[Conio::K_LBRACKET,Conio::KM_SHIFT],
      0x7C=>[Conio::K_BACKSLASH,Conio::KM_SHIFT],
      0x7D=>[Conio::K_RBRACKET,Conio::KM_SHIFT],
      0x7E=>[Conio::K_TILDE,Conio::KM_SHIFT],
      0x7F=>[Conio::K_BACKSPACE,0],
    ];
    # }}}
  # }}}
  # basis {{{
  public object
    $events;
  public ?object
    $error=null,$reading=null;
  public array
    $re,# patterns for sync parser
    $env,# environment variables
    $sio,# system modes of the terminal
    $size,# of the visible screen: columns,rows
    $scroll,# columns,rows,left,top
    $cursor,# x,y
    $pio;# private modes of the terminal
  public ?array
    $lastSize=null,$lastScroll=null;
  public string
    $devAttr='',$devId='',$id='',
    $partBuf='',$writeBuf1='',$writeBuf2='';
  public int
    $eventTimeout=3*1000000000,# seconds in nano
    $timeout=500,# ms
    $delay=0,$async=0,$ansi=0,$s8c1t=0,
    $xcolor=0,$colors=0,$unicode=1,$keyboard=0,
    $mouse=0,$mouseBtn=0,$mouseX=0,$mouseY=0,
    $buffering=0,$writing=0,$writeLen1=0,$writeLen2=0,
    $pending=0,$focused=1,$lastFocused=1;
  ###
  protected function __construct(
    public object  $api,    # FFI library bindings
    public ?object $varmem, # some malloc'ated memory
    public int     $f0,     # input descriptor
    public int     $f1,     # output descriptor
    public string  $devPath,# terminal device path
    public array   $mode    # initial configuration
  ) {
    $this->events = new SplDoublyLinkedList();
    $this->env    = getenv(null, true) ?: [];
    $this->sio    = $mode['sio'];
    $this->size   = $mode['size'];
    $this->scroll = $mode['scroll'];
    $this->cursor = $mode['cursor'];
  }
  function __destruct()
  {
    # check already deconstructed
    if (!$this->varmem) {
      return;
    }
    # deactivate output buffering
    $this->setBuffering(false);
    # invoke instance-specific finalizer
    $this->finit();
    # restore s7c1t/s8c1t
    if (~($i = $this->mode['s8c1t']) &&
        $i !== $this->s8c1t)
    {
      $this->puts("\x1B ".($i?'G':'F'));
    }
    # restore initial mode
    try {$this->setMode($this->mode);}
    catch (Throwable) {}
    # close handles/file descriptors
    $this->close();
    # release allocated memory
    FFI::free($this->varmem);
    $this->varmem = null;
  }
  function __debugInfo(): array
  {
    return [];
  }
  # }}}
  # stasis {{{
  static function malloc(# {{{
    object $api, int $size, string $type='char'
  ):object
  {
    $type = $type.'['.$size.']';
    $mem  = $api->new($type, false, true);
    if (!$mem)
    {
      throw ErrorEx::fatal('FFI::new',
        'unable to allocate '.$type
      );
    }
    FFI::memset($mem, 0, $size);
    return $mem;
  }
  # }}}
  static function u8chr(int $cp): string # {{{
  {
    static $map = [# {{{
      "\x00","\x01","\x02","\x03","\x04","\x05","\x06","\x07",
      "\x08","\x09","\x0A","\x0B","\x0C","\x0D","\x0E","\x0F",
      "\x10","\x11","\x12","\x13","\x14","\x15","\x16","\x17",
      "\x18","\x19","\x1A","\x1B","\x1C","\x1D","\x1E","\x1F",
      "\x20","\x21","\x22","\x23","\x24","\x25","\x26","\x27",
      "\x28","\x29","\x2A","\x2B","\x2C","\x2D","\x2E","\x2F",
      "\x30","\x31","\x32","\x33","\x34","\x35","\x36","\x37",
      "\x38","\x39","\x3A","\x3B","\x3C","\x3D","\x3E","\x3F",
      "\x40","\x41","\x42","\x43","\x44","\x45","\x46","\x47",
      "\x48","\x49","\x4A","\x4B","\x4C","\x4D","\x4E","\x4F",
      "\x50","\x51","\x52","\x53","\x54","\x55","\x56","\x57",
      "\x58","\x59","\x5A","\x5B","\x5C","\x5D","\x5E","\x5F",
      "\x60","\x61","\x62","\x63","\x64","\x65","\x66","\x67",
      "\x68","\x69","\x6A","\x6B","\x6C","\x6D","\x6E","\x6F",
      "\x70","\x71","\x72","\x73","\x74","\x75","\x76","\x77",
      "\x78","\x79","\x7A","\x7B","\x7C","\x7D","\x7E","\x7F",
      "\x80","\x81","\x82","\x83","\x84","\x85","\x86","\x87",
      "\x88","\x89","\x8A","\x8B","\x8C","\x8D","\x8E","\x8F",
      "\x90","\x91","\x92","\x93","\x94","\x95","\x96","\x97",
      "\x98","\x99","\x9A","\x9B","\x9C","\x9D","\x9E","\x9F",
      "\xA0","\xA1","\xA2","\xA3","\xA4","\xA5","\xA6","\xA7",
      "\xA8","\xA9","\xAA","\xAB","\xAC","\xAD","\xAE","\xAF",
      "\xB0","\xB1","\xB2","\xB3","\xB4","\xB5","\xB6","\xB7",
      "\xB8","\xB9","\xBA","\xBB","\xBC","\xBD","\xBE","\xBF",
      "\xC0","\xC1","\xC2","\xC3","\xC4","\xC5","\xC6","\xC7",
      "\xC8","\xC9","\xCA","\xCB","\xCC","\xCD","\xCE","\xCF",
      "\xD0","\xD1","\xD2","\xD3","\xD4","\xD5","\xD6","\xD7",
      "\xD8","\xD9","\xDA","\xDB","\xDC","\xDD","\xDE","\xDF",
      "\xE0","\xE1","\xE2","\xE3","\xE4","\xE5","\xE6","\xE7",
      "\xE8","\xE9","\xEA","\xEB","\xEC","\xED","\xEE","\xEF",
      "\xF0","\xF1","\xF2","\xF3","\xF4","\xF5","\xF6","\xF7",
      "\xF8","\xF9","\xFA","\xFB","\xFC","\xFD","\xFE","\xFF"
    ];
    # }}}
    if ($cp < 0x80) {
      return $map[$cp];# ASCII
    }
    if ($cp < 0x800)
    {
      return
        $map[0xC0 | ($cp >> 6)].
        $map[0x80 | ($cp & 0x3F)];
    }
    if ($cp > 0xD7FF && $cp < 0xE000) {
      return '';# incorrect code point
    }
    if ($cp < 0x10000)
    {
      return
        $map[0xE0 | ($cp >> 12)].
        $map[0x80 | (($cp >> 6) & 0x3F)].
        $map[0x80 | ($cp & 0x3f)];
    }
    return
      $map[0xF0 | ($cp >> 18)].
      $map[0x80 | (($cp >> 12) & 0x3f)].
      $map[0x80 | (($cp >> 6) & 0x3f)].
      $map[0x80 | ($cp & 0x3f)];
  }
  # }}}
  static function flagnames(int $flag, int $type): array # {{{
  {
    $a = [];
    foreach (static::FLAGS[$type] as $name => $i)
    {
      if (($flag & $i) === $i) {
        $a[] = $name;
      }
    }
    return $a;
  }
  # }}}
  static function dev_id(string $a): string # {{{
  {
    if ($a === '') {
      return '';
    }
    $a = substr($a, 1, -1);
    $vt100 = [
      '1;2' => 'VT100',
      '1;0' => 'VT101',
      '4;6' => 'VT132',
      '6'   => 'VT102',
      '7'   => 'VT131'
    ];
    if (isset($vt100[$a])) {
      return $vt100[$a];
    }
    $vt220 = [
      '12' => 'VT125',
      '60' => 'VT100-VT520',
      '62' => 'VT220',
      '63' => 'VT320',
      '64' => 'VT420',
      '65' => 'VT510/VT525'
    ];
    if (isset($vt220[$b = substr($a, 0, 2)])) {
      return $vt220[$b];
    }
    return $a;
  }
  # }}}
  static function key_modifier(int $k): int # {{{
  {
    # this routine maps wacky xterm modifiers
    # to windows compatible modifier bitmask
    static $MAP=[0,0,
      Conio::KM_SHIFT,
      Conio::KM_ALT,
      Conio::KM_ALT|Conio::KM_SHIFT,
      Conio::KM_CTRL,
      Conio::KM_CTRL|Conio::KM_SHIFT,
      Conio::KM_CTRL|Conio::KM_ALT,
      Conio::KM_CTRL|Conio::KM_ALT|Conio::KM_SHIFT,
      Conio::KM_META,
      Conio::KM_META|Conio::KM_SHIFT,
      Conio::KM_META|Conio::KM_ALT,
      Conio::KM_META|Conio::KM_ALT|Conio::KM_SHIFT,
      Conio::KM_META|Conio::KM_CTRL,
      Conio::KM_META|Conio::KM_CTRL|Conio::KM_SHIFT,
      Conio::KM_META|Conio::KM_CTRL|Conio::KM_ALT,
      Conio::KM_META|Conio::KM_CTRL|Conio::KM_ALT|Conio::KM_SHIFT
    ];
    return isset($MAP[$k]) ? $MAP[$k] : 0;
    /*
    *  None                  1
    *  Shift                 2 = 1(None)+1(Shift)
    *  Alt                   3 = 1(None)+2(Alt)
    *  Alt+Shift             4 = 1(None)+1(Shift)+2(Alt)
    *  Ctrl                  5 = 1(None)+4(Ctrl)
    *  Ctrl+Shift            6 = 1(None)+1(Shift)+4(Ctrl)
    *  Ctrl+Alt              7 = 1(None)+2(Alt)+4(Ctrl)
    *  Ctrl+Alt+Shift        8 = 1(None)+1(Shift)+2(Alt)+4(Ctrl)
    *  Meta                  9 = 1(None)+8(Meta)
    *  Meta+Shift           10 = 1(None)+8(Meta)+1(Shift)
    *  Meta+Alt             11 = 1(None)+8(Meta)+2(Alt)
    *  Meta+Alt+Shift       12 = 1(None)+8(Meta)+1(Shift)+2(Alt)
    *  Meta+Ctrl            13 = 1(None)+8(Meta)+4(Ctrl)
    *  Meta+Ctrl+Shift      14 = 1(None)+8(Meta)+1(Shift)+4(Ctrl)
    *  Meta+Ctrl+Alt        15 = 1(None)+8(Meta)+2(Alt)+4(Ctrl)
    *  Meta+Ctrl+Alt+Shift  16 = 1(None)+8(Meta)+1(Shift)+2(Alt)+4(Ctrl)
    */
  }
  # }}}
  static function btn_modifier(int $k): int # {{{
  {
    # determine key modifier (1000)
    $m = 0;
    if ($k & 28)
    {
      if ($k &  4) {$m |= Conio::KM_SHIFT;}
      if ($k &  8) {$m |= Conio::KM_ALT;}
      if ($k & 16) {$m |= Conio::KM_CTRL;}
    }
    return $m;
  }
  # }}}
  # }}}
  # abstrasis {{{
  abstract function puts(string $s): void;
  abstract function setMode(array $m): void;
  abstract function getId(): string;
  abstract function gets(): string;
  abstract function write(): void;
  abstract function resize(): bool;
  abstract function read(int $time): bool;
  abstract function finit(): void;
  abstract function close(): void;
  # }}}
  # control functions (sync) {{{
  function _DECRQM(array $m): array # {{{
  {
    # compose the request
    for ($s='',$i=0,$j=count($m); $i < $j; ++$i) {
      $s .= "\x1B[?".$m[$i].'$p';
    }
    # when multiple items requested,
    # add the DA1 marker
    if ($j > 1) {
      $s .= "\x1B[0c";
    }
    # send it and recieve the answer
    $this->puts($s);
    if ($j > 1)
    {
      # making several attempts
      # for multiple elements
      for ($s='',$i=3; $i; --$i)
      {
        $s .= $this->gets();
        if (strpos($s, $this->devAttr)) {
          break;
        }
      }
      if (!$i)
      {
        throw ErrorEx::fatal(
          'DECRPM','timed out ('.$j.')'
        );
      }
    }
    elseif (!$s = $this->gets())
    {
      # for a single item without an answer,
      # assume unsupported
      return [$m[0] => 0];
    }
    # parse the answer and compose the reply
    # 0=unsupported 1=set 2=reset
    # 3=set 4=reset permanently
    $k = preg_match_all($this->re['DECRPM'], $s, $a);
    for ($r=[],$i=0; $i < $j; ++$i) {
      $r[$m[$i]] = 0;
    }
    for ($i=0; $i < $k; ++$i) {
      $r[$a[1][$i]] = (int)$a[2][$i];
    }
    return $r;
  }
  # }}}
  function _DECSET(array $m, bool $force=false): int # {{{
  {
    # compose the request
    $s = '';
    $c = [];
    $n = 0;
    foreach ($m as $i => $v)
    {
      # skip unknown
      if (!isset($this->pio[$i])) {
        continue;
      }
      $j = $this->pio[$i];
      switch ($v) {
      case 1:
        # check already set
        if ($j === 1 || $j === 3)
        {
          $n++;
          break;
        }
        # check cant be set -
        # cleared permanently or not supported
        if ($j === 4 || $j === 0)
        {
          if ($force) {
            break;
          }
          else {
            return -1;
          }
        }
        # append set control
        $s .= "\x1B[?".$i."h";
        $c[$i] = 1;
        $n++;
        break;
      case 2:
        # check already cleared or not supported
        if ($j === 2 || $j === 4 || $j === 0)
        {
          $n++;
          break;
        }
        # check cant be cleared (set permanently)
        if ($j === 3)
        {
          if ($force) {
            break;
          }
          else {
            return -1;
          }
        }
        # append clear control
        $s .= "\x1B[?".$i."l";
        $c[$i] = 2;
        $n++;
        break;
      }
    }
    # apply when necessary, storing changes
    if ($c)
    {
      $this->puts($s);
      foreach ($c as $i => $v) {
        $this->pio[$i] = $v;
      }
    }
    # complete with the count of items configured
    return $n;
  }
  # }}}
  function _DA3(): string # {{{
  {
    $this->puts("\x1B[=0c");
    if (($s = $this->gets()) === '' ||
        !preg_match($this->re['DECRPTUI'], $s, $a))
    {
      return '';
    }
    return hex2bin($a[1]) ?: '';
  }
  # }}}
  function _XTGET(string $what, bool $res=false): string # {{{
  {
    $this->puts(
      "\x1BP+".($res ? 'Q' : 'q').
      bin2hex($what).
      "\x1B\\"
    );
    if (($s = $this->gets()) === '' ||
        !preg_match($this->re['XTGET'], $s, $a))
    {
      return '';
    }
    return hex2bin($a[1]) ?: '';
  }
  # }}}
  function _XCOLOR_GET(int $what): array # {{{
  {
    # this function should only be called
    # when the xcolor property is nonzero
    $this->puts("\x1B]".(10 + $what).";?\x1B\\");
    if (($s = $this->gets()) === '' ||
        !preg_match($this->re['RGB'], $s, $a))
    {
      return [];
    }
    # trim leading bytes,
    # which either non-meaningful or repeated
    if (($n = $this->xcolor) > 2)
    {
      $n   -= 2;
      $a[1] = substr($a[1], $n);
      $a[2] = substr($a[2], $n);
      $a[3] = substr($a[3], $n);
    }
    # convert to integers and complete
    return [hexdec($a[1]), hexdec($a[2]), hexdec($a[3])];
  }
  # }}}
  function _CPR(bool $put=false): array # {{{
  {
    $put && $this->puts("\x1B[6n");
    if (($s = $this->gets()) === '')
    {
      throw ErrorEx::fatal(
        'CPR','response timed out'
      );
    }
    if (!preg_match($this->re['CPR'], $s, $a))
    {
      throw ErrorEx::fatal(
        'CPR','incorrect: '.Fx::strhex($s, ':')
      );
    }
    return [(int)$a[2], (int)$a[1]];
  }
  # }}}
  # }}}
  # setters {{{
  function setRead(?object $o=null): bool # {{{
  {
    if ($o)
    {
      if ($this->reading) {
        return false;
      }
      $this->reading = $o;
    }
    else {
      $this->reading = null;
    }
    return true;
  }
  # }}}
  function setMouse(int $n): void # {{{
  {
    $this->mouse &&
    $this->_DECSET(self::M_TRACKING[$n], true);
  }
  # }}}
  function setBuffering(bool $on): void # {{{
  {
    if ($on)
    {
      if (ob_start($this->setWrite(...), 1)) {
        $this->buffering = ob_get_level();
      }
    }
    elseif ($n = $this->buffering)
    {
      # flush and stop buffering
      if (ob_get_level() === $n) {
        ob_end_flush();
      }
      $this->buffering = 0;
      $this->flushOutput();
    }
  }
  # }}}
  function setWrite(string $s, int $n): string # {{{
  {
    # here, the ob_* phase is not needed,
    # determine the record length instead
    $n = strlen($s);
    # check current state
    if ($this->writing === 0)
    {
      if ($this->writeLen1 + $n <= self::MAX_WRITE)
      {
        # write chunk size is not exceeded,
        # accumulate to the primary buffer
        $this->writeBuf1 .= $s;
        $this->writeLen1 += $n;
      }
      else
      {
        # set the state of overflow
        $this->writing = -2;
        # determine the size of the first piece and
        # distribute the record to both buffers
        $m = self::MAX_WRITE - $this->writeLen1;
        $this->writeBuf1 .= substr($s, 0, $m);
        $this->writeBuf2  = substr($s, $m);
        $this->writeLen1  = self::MAX_WRITE;
        $this->writeLen2  = $n - $m;
      }
    }
    elseif (~$this->writing)
    {
      # upon overflow or writing,
      # accumulate to the secondary buffer
      $this->writeBuf2 .= $s;
      $this->writeLen2 += $n;
    }
    return '';
  }
  # }}}
  function setWriteComplete(): void # {{{
  {
    # reset the state
    $this->writing = 0;
    # check more output is pending
    if ($n = $this->writeLen2)
    {
      if ($n <= self::MAX_WRITE)
      {
        # move all
        $this->writeBuf1 = $this->writeBuf2;
        $this->writeBuf2 = '';
        $this->writeLen1 = $n;
        $this->writeLen2 = 0;
      }
      else
      {
        # move chunk
        $this->writeBuf1 = substr(
          $this->writeBuf2, 0, self::MAX_WRITE
        );
        $this->writeBuf2 = substr(
          $this->writeBuf2, self::MAX_WRITE
        );
        $this->writeLen1 = self::MAX_WRITE;
        $this->writeLen2 = $n - self::MAX_WRITE;
      }
    }
    else {# cleanup
      $this->writeBuf1 = '';
    }
  }
  # }}}
  function setFocused(int $i): void # {{{
  {
    if ($this->lastFocused < 0) {
      $this->lastFocused = $this->focused;
    }
    $this->focused = $i ? 1 : 0;
  }
  # }}}
  function setPending(int $n, int $time): int # {{{
  {
    # by comparing current and initial counts,
    # check no event was added (except the dummy)
    $q = $this->events;
    if (($k = $q->count()) === $n + 1)
    {
      # remove dummy
      $q->pop();
      return 0;
    }
    # replace dummy with events group
    $q[$n] = [
      Conio::EV_GROUP,
      $time + $this->eventTimeout,# expiration
      $k - $n - 1 # size of the group
    ];
    # update counter and complete
    return $this->pending = $k;
  }
  # }}}
  function clearInput(): void # {{{
  {
    $this->partBuf = '';
    if ($this->pending)
    {
      $this->events  = new SplDoublyLinkedList();
      $this->pending = 0;
    }
  }
  # }}}
  function clearOutput(): void # {{{
  {
    $this->writeBuf1 = $this->writeBuf2 = '';
    $this->writeLen1 = $this->writeLen2 = 0;
  }
  # }}}
  function flushOutput(): void # {{{
  {
    # flush primary
    if ($this->writeLen1) {
      $this->puts($this->writeBuf1);
    }
    # flush secondary
    if ($n = $this->writeLen2)
    {
      while ($n > self::MAX_WRITE)
      {
        $this->puts(substr(
          $this->writeBuf2, 0, self::MAX_WRITE
        ));
        $this->writeBuf2 = substr(
          $this->writeBuf2, self::MAX_WRITE
        );
        $n -= self::MAX_WRITE;
      }
      $this->puts($this->writeBuf2);
    }
    # cleanup
    $this->writeBuf1 = $this->writeBuf2 = '';
    $this->writeLen1 = $this->writeLen2 = 0;
  }
  # }}}
  # }}}
  # probes {{{
  function probeMouse(): int # {{{
  {
    # check if one of the normal tracking modes
    # is supported and available
    $x = $this->mode['pio'];
    $z = 0;
    foreach ([1003,1002,1001,1000,9] as $m)
    {
      $y = $x[$m];
      if ($y > 0 && $y < 4)
      {
        $z++;
        break;# 1,2,3
      }
    }
    # check extended mode (URXVT) is available
    if ($z && ($y = $x[1015]) > 0 && $y < 4) {
      $z++;
    }
    return $z;
  }
  # }}}
  function probeColors(): int # {{{
  {
    # probe xterm color support (OSC 10-19)
    $this->puts("\x1B]10;?\x1B\\");
    if (($s = $this->gets()) === '' ||
        !preg_match($this->re['RGB'], $s, $a))
    {
      echo "[NOT SUPPORTED]";
      return 0;
    }
    # supported!
    ###
    # <r>,<g>,<b> := h | hh | hhh | hhhh
    # h := single hexadecimal digit
    # h indicates the value scaled in 4 bits,
    # hh - 8 bits, hhh - 12 bits and hhhh - 16 bits
    # total is 3-components 4-bits in h-positions
    ###
    switch ($this->xcolor = $x = strlen($a[1])) {
    case 1:
    case 2:
      return 3*4*$x;
    case 3:
    case 4:
      return 24;# clamp/limit to 24-bit color (truecolor)
    }
    # incorrect length, not supported
    return $this->xcolor = 0;
  }
  # }}}
  function probeUnicode(): bool # {{{
  {
    # The idea is to ask the terminal to tell
    # you its cursor position, output a test
    # character, ask the terminal again
    # to tell you its position, and compare
    # the two positions to see how far
    # the terminal's cursor moved.
    # - Celada
    ###
    # probe latin 2-byte letter [É][C3:89]
    $this->puts("\xC3\x89\x1B[6n");
    $a = $this->_CPR();
    $c = $this->cursor;
    $n = $a[0] - $c[0];
    # restore cursor position and
    # erase printed character(s)
    $this->puts(
      "\x1B[".$c[1].";".$c[0]."H".
      "\x1B[".$n."X"
    );
    # complete
    return $n === 1;
  }
  # }}}
  function probeWinSize(): array # {{{
  {
    ############
    ### TODO ###
    ############
    # moving cursor to the bottom right
    # so it doesnt pass the edge of the window
    $this->puts(
      "\x1B[999B".  # CUD down
      "\x1B[999C".  # CUF forward
      "\x1B[6n"     # CPR position report
    );
    return $this->_CPR();
  }
  # }}}
  # }}}
  # parsers {{{
  function parse8(# 8-bit response parser {{{
    object $q,# the queue to push events to
    string $s,# the string to parse
    int $n,   # the length of the string
    int $eop  # end of the previous partial
  ):int       # bytes consumed or -1=error
  {
    $a = null;
    $i = 0;
    do
    {
      # checkout the next byte
      $k = ord($c = $s[$i]);
      if ($k < 0x20)
      {
        # 7-bit control (C0) {{{
        switch ($k) {
        case 0x08:# K_BACKSPACE
        case 0x09:# K_TAB
        case 0x0D:# K_RETURN
          $q->push([
            Conio::EV_KEY, 1,
            $k, 0, $c
          ]);
          break;
        case 0x1B:# K_ESCAPE
          $q->push([
            Conio::EV_KEY, 1,
            $k, 0, ''
          ]);
          break;
        case 0x7F:# K_BACKSPACE
          $q->push([
            Conio::EV_KEY, 1,
            Conio::K_BACKSPACE, 0, ''
          ]);
          break;
        default:# CTRL combo
          $q->push([
            Conio::EV_KEY, 1,
            ...self::KEY_ASCII[$k], ''
          ]);
          break;
        }
        # }}}
      }
      elseif ($k < 0x80)
      {
        # ASCII: printable character
        $q->push([
          Conio::EV_KEY, 1,
          ...self::KEY_ASCII[$k], $c
        ]);
      }
      elseif ($k < 0xA0)
      {
        # 8-bit control (C1) {{{
        # at least 2 bytes required,
        # check partial
        if ($n - $i < 2)
        {
          if ($eop && $i < $eop)
          {
            $q->push([
              Conio::EV_ERROR,
              ErrorEx::fail(
                "incomplete 8-bit control".
                " 0x".Fx::inthex($k).
                " at ".$i."\n".
                Fx::strhex($s, ':')
              )
            ]);
            return -1;
          }
          return $i;
        }
        # start from the next byte
        $j = $i + 1;
        switch ($k) {
        case 0x8F:# SS3 {{{
          # try parsing the body at the given offset
          if (!preg_match(self::SS3, $s, $a, 0, $j))
          {
            # validate
            # skip [P]arameter bytes
            for ($m=$j; $m < $n; ++$m)
            {
              $k = ord($s[$m]);
              if ($k < 0x30 || $k > 0x39) {
                break;
              }
            }
            # check oversized parameter
            if ($m - $j > 3)
            {
              $q->push([
                Conio::EV_ERROR,
                ErrorEx::fail(
                  "incorrect SS3 control".
                  " at ".$i."\n".
                  "oversized parameter (".
                  ($m - $j)." > 3)\n".
                  Fx::strhex($s, ':')
                )
              ]);
              return -1;
            }
            # check partial
            if ($m === $n)
            {
              if ($eop && $i < $eop)
              {
                $q->push([
                  Conio::EV_ERROR,
                  ErrorEx::fail(
                    "incomplete SS3 control".
                    " at ".$i."\n".
                    Fx::strhex($s, ':')
                  )
                ]);
                return -1;
              }
              return $i;
            }
            # otherwise, its obvious that
            # the [F]inal byte is wrong
            $q->push([
              Conio::EV_ERROR,
              ErrorEx::fail(
                "incorrect SS3 control".
                " at ".$i."\n".
                "incorrect final byte".
                " 0x".Fx::strhex($s[$m])."\n".
                Fx::strhex($s, ':')
              )
            ]);
            return -1;
          }
          # parse ahead
          $i += $this->parseSS3($q, $a);
          break;
          # }}}
        case 0x9B:# CSI {{{
          # try parsing the body at the given offset
          if (!preg_match(self::CSI, $s, $a, 0, $j))
          {
            # validate
            # skip [P]arameter bytes
            for ($m=$j; $m < $n; ++$m)
            {
              $k = ord($s[$m]);
              if ($k < 0x30 || $k > 0x3F) {
                break;# the valid exit
              }
            }
            # check oversized parameter
            if ($m - $j > 30)
            {
              $q->push([
                Conio::EV_ERROR,
                ErrorEx::fail(
                  "incorrect CSI control".
                  " at ".$i."\n".
                  "oversized parameter (".
                  ($m - $j)." > 30)\n".
                  Fx::strhex($s, ':')
                )
              ]);
              return -1;
            }
            # check partial, either current or
            # [I]ntermediate byte
            if ($m === $n ||
                ($k >= 0x20 &&
                 $k <= 0x2F &&
                 ++$m === $n))
            {
              if ($eop && $i < $eop)
              {
                $q->push([
                  Conio::EV_ERROR,
                  ErrorEx::fail(
                    "incomplete CSI control".
                    " at ".$i."\n".
                    Fx::strhex($s, ':')
                  )
                ]);
                return -1;
              }
              return $i;
            }
            # something must be wrong with either
            # [I]ntermediate or the [F]inal byte
            $q->push([
              Conio::EV_ERROR,
              ErrorEx::fail(
                "incorrect CSI control".
                " at ".$i."\n".
                "incorrect byte".
                " 0x".Fx::strhex($s[$m]).
                " at ".$m."\n".
                Fx::strhex($s, ':')
              )
            ]);
            return -1;
          }
          $j = $this->parseCSI(
            $q, $a, $s, $j, $n, $eop
          );
          # check failed or partial
          if ($j <= 0) {
            return $j ? -1 : $i;
          }
          # continue
          $i += $j;
          break;
          # }}}
        case 0x90:# DCS {{{
        case 0x9D:# OSC
          # try parsing the body at the given offset
          if (!preg_match(self::CST8, $s, $a, 0, $j))
          {
            # validate
            # check no string terminator
            if (strpos($s, "\x9C", $j) === false)
            {
              if ($eop && $i < $eop)
              {
                $q->push([
                  Conio::EV_ERROR,
                  ErrorEx::fail(
                    "incomplete control string".
                    " (".Conio::C1N[$k][0].")".
                    " at ".$i."\n".
                    "missing terminator (ST)\n".
                    Fx::strhex($s, ':')
                  )
                ]);
                return -1;
              }
              return $i;
            }
            $q->push([
              Conio::EV_ERROR,
              ErrorEx::fail(
                "incorrect control string".
                " (".Conio::C1N[$k][0].")".
                " at ".$i."\n".
                Fx::strhex($s, ':')
              )
            ]);
            return -1;
          }
          # add control string
          $q->push([$k, $a[1]]);
          $i += strlen($a[0]);
          break;
          # }}}
        default:# {{{
          # 8-bit control functions are
          # in the range of [0x80..0x9F]
          # but there is only few variants
          # that are either a response or
          # an event from the terminal..
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "unknown 8-bit control".
              " 0x".Fx::inthex($k).
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
          # }}}
        }
        # }}}
      }
      else
      {
        # unicode character
        $k = $this->parseUTF8(
          $q, $k, $c, $s, $i, $n, $eop
        );
        if ($k <= 0) {
          return $k ? -1 : $i;
        }
        $i += $k;
      }
    }
    while (++$i < $n);
    return $i;
  }
  # }}}
  function parse7(# 7-bit response parser {{{
    object $q, string $s, int $n, int $eop
  ):int
  {
    $a = null;
    $i = 0;
    do
    {
      # checkout the next byte
      $k = ord($c = $s[$i]);
      if ($k < 0x20)
      {
        # 7-bit control (C0) {{{
        switch ($k) {
        case 0x08:# K_BACKSPACE
        case 0x09:# K_TAB
        case 0x0D:# K_RETURN
          $q->push([
            Conio::EV_KEY, 1,
            $k, 0, $c
          ]);
          break;
        case 0x7F:# K_BACKSPACE
          $q->push([
            Conio::EV_KEY, 1,
            Conio::K_BACKSPACE, 0, ''
          ]);
          break;
        case 0x1B:
          # ESC sequence requires at least 2 bytes,
          # check partial
          if ($n - $i < 2)
          {
            # uncertainty of the last byte that
            # may be either ESC key or sequence
            # is resolved with the help of the
            # eop (end-of-partial) marker
            if ($eop && $i < $eop)
            {
              $q->push([
                Conio::EV_KEY, 1,
                Conio::K_ESCAPE, 0, ''
              ]);
              break;
            }
            # request eop
            return $i;
          }
          # 7bit control function lies in
          # the range of [0x40..0x5F] but
          # there is only few response variants
          $c = $s[$i + 1];
          $j = $i + 2;
          switch ($k = ord($c)) {
          case 0x4F:# SS3 {{{
            # try parsing the body at the given offset
            if (!preg_match(self::SS3, $s, $a, 0, $j))
            {
              # the 7bit parser cannot reliably
              # validate the incorrect sequence,
              # it may only skip to simplier case
              if ($eop && $i < $eop) {
                break;
              }
              # ask for more data
              return $i;
            }
            $i += 1 + $this->parseSS3($q, $a);
            break 2;
            # }}}
          case 0x5B:# CSI {{{
            # try parsing the body at the given offset
            if (!preg_match(self::CSI, $s, $a, 0, $j))
            {
              # the 7bit parser cannot reliably
              # validate the incorrect sequence,
              # it may only skip to simplier case
              if ($eop && $i < $eop) {
                break;
              }
              # ask for more data
              return $i;
            }
            # parse ahead
            $j = $this->parseCSI(
              $q, $a, $s, $j, $n, $eop
            );
            # check failed or partial
            if ($j <= 0) {
              return $j ? -1 : $i;
            }
            # continue
            $i += 1 + $j;
            break 2;
            # }}}
          case 0x50:# DCS {{{
          case 0x5D:# OSC
            # try parsing the body at the given offset
            if (!preg_match(self::CST7, $s, $a, 0, $j))
            {
              if ($eop && $i < $eop) {
                break;# skip
              }
              return $i;# ask for more data
            }
            # add control string
            $q->push([0x40 + $k, $a[1]]);
            $i += 1 + strlen($a[0]);
            break 2;
            # }}}
          }
          # this is not a control function,
          # check legacy ALT combo
          if (!$this->keyboard)
          {
            $a = self::KEY_ASCII[$k];
            $k = $a[0];
            $m = $a[1] | Conio::KM_ALT;
            $q->push([
              Conio::EV_KEY, 1, $k, $m, ''
            ]);
            $i++;
            break;
          }
          # non-legacy key combination
          # is encoded with the full ESC sequence,
          # so this must be a standalone ESC key
          $q->push([
            Conio::EV_KEY, 1,
            Conio::K_ESCAPE, 0, ''
          ]);
          break;
        default:# legacy CTRL combo
          $q->push([
            Conio::EV_KEY, 1,
            ...self::KEY_ASCII[$k], ''
          ]);
          break;
        }
        # }}}
      }
      elseif ($k < 0x80)
      {
        # ASCII printable character
        $q->push([
          Conio::EV_KEY, 1,
          ...self::KEY_ASCII[$k], $c
        ]);
      }
      else
      {
        # unicode character
        $k = $this->parseUTF8(
          $q, $k, $c, $s, $i, $n, $eop
        );
        if ($k <= 0) {
          return $k ? -1 : $i;
        }
        $i += $k;
      }
    }
    while (++$i < $n);
    return $i;
  }
  # }}}
  function parseSS3(# {{{
    object $q, array $a
  ):int # bytes consumed
  {
    # advance to the end of the sequence
    $j = strlen($a[0]);
    $b = $a[1];
    $c = $a[2];
    # check a special key
    if (isset(self::KEY_SPECIAL[$c]))
    {
      # get keycode and modifier
      $a = self::KEY_SPECIAL[$c];
      $k = $a[0];
      $m = ($b === '')
        ? $a[1]
        : $a[1] | self::key_modifier((int)$b);
      # add key
      $q->push([Conio::EV_KEY, 1, $k, $m, '']);
    }
    else
    {
      # add SS3 code
      $q->push([0x8F, $b, $c]);
    }
    return $j;
  }
  # }}}
  function parseCSI(# {{{
    object $q, array $a,
    string $s, int $i, int $n, int $eop
  ):int # bytes consumed
  {
    # prepare parameters
    $b = $a[0];
    $c = $a[2];
    $j = strlen($b);
    $a = ($a[1] !== '')
      ? explode(';', $a[1])
      : [];
    # operate
    switch ($m = count($a)) {
    case 0:# {{{
      # handle mouse tracking
      switch ($c) {
      case 'M':# X10 mouse protocol
        # parse
        $k = $this->parseMouseX10(
          $q, $s, $i, $n, $eop
        );
        # complete
        return ($k > 0)
          ? ($j + $k) # successful
          : ($k ? -1 : 0);# failed or partial
        ###
      case 'O':# focus out (1004)
        $this->setFocused(0);
        return $j;
      case 'I':# focus in (1004)
        $this->setFocused(1);
        return $j;
      }
      # handle unmodified special key
      if (isset(self::KEY_SPECIAL[$c]))
      {
        $q->push([
          Conio::EV_KEY, 1,
          ...self::KEY_SPECIAL[$c], ''
        ]);
        return $j;
      }
      break;
      # }}}
    case 1:# {{{
      # handle unmodified special key
      if ($c === '~' &&
          isset(self::KEY_SPECIAL[$b]))
      {
        $q->push([
          Conio::EV_KEY, 1,
          ...self::KEY_SPECIAL[$b], ''
        ]);
        return $j;
      }
      break;
      # }}}
    case 2:# {{{
      # handle modified special key variants
      if ($c === '~')
      {
        $d = $a[0].'~';
        if (isset(self::KEY_SPECIAL[$d]))
        {
          $q->push([
            Conio::EV_KEY, 1,
            self::KEY_SPECIAL[$d][0],
            self::key_modifier((int)$a[1]), ''
          ]);
          return $j;
        }
      }
      elseif ($a[0] === '1')
      {
        if (isset(self::KEY_SPECIAL[$c]))
        {
          $q->push([
            Conio::EV_KEY, 1,
            self::KEY_SPECIAL[$c][0],
            self::key_modifier((int)$a[1]), ''
          ]);
          return $j;
        }
      }
      break;
      # }}}
    case 3:# {{{
      # handle URXVT mouse protocol (1015)
      if ($c === 'M')
      {
        $n = (int)$a[0];
        $x = (int)$a[1];
        $y = (int)$a[2];
        if ($k = $this->getMouseState($n, $x, $y))
        {
          $q->push([
            Conio::EV_MOUSE, $k,
            self::btn_modifier($n),
            $x, $y
          ]);
        }
        return $j;
      }
      # handle modified other key
      if ($a[0] === '27')
      {
        $k = (int)$a[2];
        $m = self::key_modifier((int)$a[1]);
        $c = ($m === Conio::KM_SHIFT)
          ? chr($k)
          : '';
        ###
        $q->push([
          Conio::EV_KEY, 1,
          self::KEY_ASCII[$k][0], $m, $c
        ]);
        return $j;
      }
      break;
      # }}}
    }
    # add CSI code
    $q->push([0x9B, $a, $c]);
    return $j;
  }
  # }}}
  function parseUTF8(# {{{
    object $q, int $k, string $c,
    string $s, int $i, int $n, int $eop
  ):int
  {
    # UTF-8 scheme {{{
    # Char. number range  |  UTF-8 octet sequence
    # (hexadecimal)       |  (binary)
    # --------------------+------------------------------------
    # 0000 0000-0000 007F | 0xxxxxxx
    # 0000 0080-0000 07FF | 110xxxxx 10xxxxxx
    # 0000 0800-0000 FFFF | 1110xxxx 10xxxxxx 10xxxxxx
    # 0001 0000-0010 FFFF | 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
    # }}}
    # handle 2-byte character {{{
    if (($k & 0xE0) === 0xC0)
    {
      # check partial
      if ($n - $i < 2)
      {
        if ($eop && $i < $eop)
        {
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "incomplete UTF-8[2] character".
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
        }
        return 0;
      }
      # checkout the next byte
      $j = ord($d = $s[++$i]);
      if ($j < 0x80 || $j > 0xBF)
      {
        $q->push([
          Conio::EV_ERROR,
          ErrorEx::fail(
            "incorrect UTF-8[2] byte".
            " 0x".Fx::inthex($j).
            " at ".$i."\n".
            Fx::strhex($s, ':')
          )
        ]);
        return -1;
      }
      # check legacy 8-bit ALT combo (xterm)
      # ASCII rangemaps are:
      # xC280 - xC2BF => x00 - x3F
      # xC380 - xC3BF => x40 - x7F
      if (($this->keyboard === 0) &&
          ($k === 0xC2 || $k === 0xC3))
      {
        $k = $j - 0x40 - 0x40*(0xC3 - $k);
        $c = ($k < 0x20) ? '' : $c.$d;
        $a = self::KEY_ASCII[$k];
        $k = $a[0];
        $m = $a[1] | Conio::KM_ALT;
      }
      else
      {
        $c = $c.$d;
        $k = $m = 0;
      }
      # add
      $q->push([
        Conio::EV_KEY, 1,
        $k, $m, $c
      ]);
      return 1;
    }
    # }}}
    # handle 3-byte character {{{
    if (($k & 0xF0) === 0xE0)
    {
      # check partial
      if ($n - $i < 3)
      {
        if ($eop && $i < $eop)
        {
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "incomplete UTF-8[3] character".
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
        }
        return 0;
      }
      # check incorrect
      for ($j=2; $j; --$j)
      {
        $k = ord($d = $s[++$i]);
        $c = $c.$d;
        if ($k < 0x80 || $k > 0xBF)
        {
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "incorrect UTF-8[3] byte".
              " 0x".Fx::inthex($k).
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
        }
      }
      # add
      $q->push([
        Conio::EV_KEY, 1, 0, 0, $c
      ]);
      return 2;
    }
    # }}}
    # handle 4-byte character {{{
    if (($k & 0xF8) === 0xF0)
    {
      # check partial
      if ($n - $i < 4)
      {
        if ($eop && $i < $eop)
        {
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "incomplete UTF-8[4] character".
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
        }
        return 0;
      }
      # check incorrect
      for ($j=3; $j; --$j)
      {
        $k = ord($d = $s[++$i]);
        $c = $c.$d;
        if ($k < 0x80 || $k > 0xBF)
        {
          $q->push([
            Conio::EV_ERROR,
            ErrorEx::fail(
              "incorrect UTF-8[4] byte".
              " 0x".Fx::inthex($k).
              " at ".$i."\n".
              Fx::strhex($s, ':')
            )
          ]);
          return -1;
        }
      }
      # add
      $q->push([
        Conio::EV_KEY, 1, 0, 0, $c
      ]);
      return 3;
    }
    # }}}
    # incorrect
    $q->push([
      Conio::EV_ERROR,
      ErrorEx::fail(
        "incorrect input byte".
        " 0x".Fx::inthex($k).
        " at ".$i."\n".
        Fx::strhex($s, ':')
      )
    ]);
    return -1;
  }
  # }}}
  function parseMouseX10(# {{{
    object $q, string $s, int $i, int $n, int $eop
  ):int # number of bytes consumed
  {
    # the current offset (i) is at the end
    # of <CSI>M sequence - 3 more bytes required
    # check partial
    if ($n - $i < 4)
    {
      if ($eop && $i < $eop)
      {
        $q->push([
          Conio::EV_ERROR,
          ErrorEx::fail(
            "incomplete X10 mouse event".
            " at ".$i."\n".
            Fx::strhex($s, ':')
          )
        ]);
        return -1;
      }
      return 0;
    }
    # extract coordinate values,
    # zero is allowed to short-circuit a value
    # that is outside of the [1..223] range maximum
    if (($x = ord($s[$i + 2])) === 0) {
      $x = 0xFF;
    }
    if (($y = ord($s[$i + 3])) === 0) {
      $y = 0xFF;
    }
    # check incorrect
    if ($x <= 0x20 || $y <= 0x20)
    {
      $q->push([
        Conio::EV_ERROR,
        ErrorEx::fail(
          "incorrect X10 mouse coordinate".
          " at ".(($x <= 0x20)
            ? $i + 2
            : $i + 3
          )."\n".
          Fx::strhex($s, ':')
        )
      ]);
      return -1;
    }
    # determine coordinates
    $x -= 0x20;
    $y -= 0x20;
    # get button state
    $j = ord($s[$i + 1]);
    if (!($k = $this->getMouseState($j, $x, $y))) {
      return 3;
    }
    # add event and complete
    $q->push([
      Conio::EV_MOUSE, $k,
      self::btn_modifier($j),
      $x, $y
    ]);
    return 3;
  }
  # }}}
  function getMouseState(int $k, int $x, int $y): int # {{{
  {
    static $BTN=[# buttons map
      Conio::M_BUTTON1, Conio::M_BUTTON2,
      Conio::M_BUTTON3, Conio::M_BUTTON4,
      Conio::M_BUTTON5, Conio::M_BUTTON6,
      Conio::M_BUTTON7, Conio::M_BUTTON8
    ];
    $i = $k & 3;
    if ($k & 32)
    {
      # standalone button action or
      # a motion without button pressed
      # aka mouse move
      if ($k & 128)
      {
        # button press,
        # extended bit maps to buttons 4..7
        $k = $this->mouseBtn = $BTN[$i + 3];
      }
      elseif ($k & 64)
      {
        # button wheel,
        # 1/2 for vertical up/down action,
        # 3/4 for horizontal
        $k = Conio::M_WHEEL | $BTN[$i];
      }
      elseif ($i < 3)
      {
        # button press, 1..3
        $this->mouseBtn = $k = $BTN[$i];
      }
      elseif ($this->mouseBtn)
      {
        # previously, a button was pressed,
        # so it must be a release action
        $k = Conio::M_RELEASE | $this->mouseBtn;
        $this->mouseBtn = 0;
      }
      elseif ($x === $this->mouseX &&
              $y === $this->mouseY)
      {
        # mouse move with the same coordinates
        # must be filtered
        return 0;
      }
      else
      {
        # no button was pressed previously,
        # so it must be a mouse move
        $k = Conio::M_MOVE;
      }
      return $k;
    }
    elseif ($x === $this->mouseX &&
            $y === $this->mouseY)
    {
      # mouse move/drage with the same coordinates
      # must be filtered
      return 0;
    }
    else
    {
      # its perfectly fine to assume mouse move/drag
      $k = Conio::M_MOVE | $this->mouseBtn;
    }
    # update coordinates and complete
    $this->mouseX = $x;
    $this->mouseY = $y;
    return $k;
    /*** INCORRECTLY IMPLEMENTED BY SOME TERMINALS ***
    elseif ($k & 64)
    {
      # motion with button pressed down (1002)
      # aka mouse drag
      # it is better to rely on own state
      return Conio::M_MOVE | (($k & 128)
        ? $BTN[$i + 3]
        : (($i < 3) ? $BTN[$i] : 0)
      );
    }
    return 0;# incorrect
    /***/
  }
  # }}}
  # }}}
  function init(): bool # {{{
  {
    ### probe ANSI {{{
    # query primary device attributes and
    # start response time measurement
    $this->puts(static::ASK);
    $t = hrtime(true);
    if (($s = $this->gets()) === '') {
      return false;
    }
    $T = hrtime(true) - $t;
    # parse the response
    if (preg_match(self::S7E['DA1'], $s, $a)) {
      $i = 0; goto a1;
    }
    if (preg_match(self::S8E['DA1'], $s, $a)) {
      $i = 1; goto a1;
    }
    throw ErrorEx::fatal(
      'DA1','incorrect: '.Fx::strhex($s, ':')
    );
  a1:
    # ANSI ESC codes are supported,
    # store device information
    $this->ansi = 1;
    $this->devAttr = $s = $a[1];
    $this->devId = self::dev_id($s);
    # store initial S8C1T flag
    $this->s8c1t = $i;
    $this->re = $i ? self::S8E : self::S7E;
    $this->mode['s8c1t'] = $i;
    # }}}
    ### probe S8C1T {{{
    # query cursor position (CPR)
    $this->puts("\x1B[6n");
    $t = hrtime(true);
    $a = $this->_CPR();
    if (($t = hrtime(true) - $t) > $T) {
      $T = $t;
    }
    # check mode is already established
    if ($i) {
      goto a2;
    }
    # try switching into 8-bits and
    # request another CPR
    $this->puts("\x1B G\x1B[6n");
    $t = hrtime(true);
    $s = $this->gets();
    if (($t = hrtime(true) - $t) > $T) {
      $T = $t;
    }
    # parse 8-bit response
    if (preg_match(self::S8E['CPR'], $s, $b))
    {
      # switched successfully!
      $this->s8c1t = 1;
      $this->re = self::S8E;
      goto a2;
    }
    # parse 7-bit response
    if (!preg_match(self::S7E['CPR'], $s, $b)) {
      throw ErrorEx::fatal('unexpected');
    }
    # some terminals do not correctly
    # parse this sequence, leaving garbage symbols,
    # that should be cleared from the screen
    $b = [(int)$b[2], (int)$b[1]];
    if ($i = $b[0] - $a[0])
    {
      # restore cursor position and
      # erase in line to the right
      $this->puts(
        "\x1B[".$a[1].";".$a[0]."H".
        "\x1B[0K"
      );
    }
  a2:
    # update cursor position
    $this->cursor[0] = $a[0];
    $this->cursor[1] = $a[1];
    # update maximal response timeout
    $t = (int)($T / 1000000);# ns => ms
    $this->timeout = ($t < 10) ? 30 : 3*$t;
    # }}}
    # initialize the rest
    $this->mode['pio'] = $this->pio =
      $this->_DECRQM(self::DECRQM);
    ###
    $this->mouse  = $this->probeMouse();
    $this->colors = $this->probeColors();
    $this->id     = $this->getId();
    $this->setBuffering(true);
    # complete
    return true;
  }
  # }}}
}
###
