#define FFI_LIB "kernel32.dll"

// defs {{{
// basic {{{
typedef uintptr_t HANDLE;
typedef unsigned int UINT;
typedef struct _COORD {
  uint16_t X;
  uint16_t Y;
} COORD;
typedef struct _SMALL_RECT {
  uint16_t Left;
  uint16_t Top;
  uint16_t Right;
  uint16_t Bottom;
} SMALL_RECT;
// }}}
// CONSOLE_SCREEN_BUFFER_INFO {{{
typedef struct _CONSOLE_SCREEN_BUFFER_INFO {
  COORD dwSize;/* {{{
  A COORD structure that contains the size of the
  console screen buffer, in character columns and rows.
  }}} */
  COORD dwCursorPosition;/* {{{
  A COORD structure that contains the column and
  row coordinates of the cursor in the console
  screen buffer.
  }}} */
  uint16_t wAttributes;/* {{{
  The attributes of the characters written
  to a screen buffer by the WriteFile and
  WriteConsole functions, or echoed to a screen
  buffer by the ReadFile and ReadConsole functions.
  For more information, see Character Attributes.
  ---
  FOREGROUND_BLUE            0x0001
  FOREGROUND_GREEN           0x0002
  FOREGROUND_RED             0x0004
  FOREGROUND_INTENSITY       0x0008
  BACKGROUND_BLUE            0x0010
  BACKGROUND_GREEN           0x0020
  BACKGROUND_RED             0x0040
  BACKGROUND_INTENSITY       0x0080
  COMMON_LVB_LEADING_BYTE    0x0100
  COMMON_LVB_TRAILING_BYTE   0x0200
  COMMON_LVB_GRID_HORIZONTAL 0x0400
  COMMON_LVB_GRID_LVERTICAL  0x0800
  COMMON_LVB_GRID_RVERTICAL  0x1000
  COMMON_LVB_REVERSE_VIDEO   0x4000
  COMMON_LVB_UNDERSCORE      0x8000
  }}} */
  SMALL_RECT srWindow;/* {{{
  A SMALL_RECT structure that contains the console
  screen buffer coordinates of the upper-left and
  lower-right corners of the display window.
  }}} */
  COORD dwMaximumWindowSize;/* {{{
  A COORD structure that contains the maximum size
  of the console window, in character columns
  and rows, given the current screen buffer size
  and font and the screen size.
  }}} */
} CONSOLE_SCREEN_BUFFER_INFO;
// }}}
// INPUT_RECORD {{{
typedef struct _FOCUS_EVENT_RECORD {
  uint32_t bSetFocus;
} FOCUS_EVENT_RECORD;
typedef struct _MENU_EVENT_RECORD {
  uint32_t dwCommandId;
} MENU_EVENT_RECORD;
typedef struct _WINDOW_BUFFER_SIZE_RECORD {
  /*
  Buffer size events are placed in
  the input buffer when the console is
  in window-aware mode (ENABLE_WINDOW_INPUT)
  */
  COORD dwSize;
} WINDOW_BUFFER_SIZE_RECORD;
typedef struct _MOUSE_EVENT_RECORD
{
  COORD dwMousePosition;/* {{{
  A COORD structure that contains the location
  of the cursor, in terms of the console screen
  buffer's character-cell coordinates.
  }}} */
  uint32_t dwButtonState;/* {{{
  The status of the mouse buttons.
  The least significant bit corresponds to the
  leftmost mouse button. The next least
  significant bit corresponds to the rightmost
  mouse button. The next bit indicates the
  next-to-leftmost mouse button. The bits then
  correspond left to right to the mouse buttons.
  A bit is 1 if the button was pressed.
  The following constants are defined for the
  first five mouse buttons:
  ---
  0x0001: FROM_LEFT_1ST_BUTTON_PRESSED
    The leftmost mouse button.
  0x0004: FROM_LEFT_2ND_BUTTON_PRESSED
    The second button fom the left.
  0x0008: FROM_LEFT_3RD_BUTTON_PRESSED
    The third button from the left.
  0x0010: FROM_LEFT_4TH_BUTTON_PRESSED
    The fourth button from the left.
  0x0002: RIGHTMOST_BUTTON_PRESSED
    The rightmost mouse button.
  }}} */
  uint32_t dwControlKeyState;/* {{{
  The state of the control keys. This member can
  be one or more of the following values:
  ---
  0x0001: RIGHT_ALT_PRESSED
    The right ALT key is pressed.
  0x0002: LEFT_ALT_PRESSED
    The left ALT key is pressed.
  0x0004: RIGHT_CTRL_PRESSED
    The right CTRL key is pressed.
  0x0008: LEFT_CTRL_PRESSED
    The left CTRL key is pressed.
  0x0010: SHIFT_PRESSED
    The SHIFT key is pressed.
  0x0020: NUMLOCK_ON
    The NUM LOCK light is on.
  0x0040: SCROLLLOCK_ON
    The SCROLL LOCK light is on.
  0x0080: CAPSLOCK_ON
    The CAPS LOCK light is on.
  0x0100: ENHANCED_KEY
    The key is enhanced. See remarks.
  }}} */
  uint32_t dwEventFlags; /* {{{
  The type of mouse event. If this value is zero,
  it indicates a mouse button being pressed or
  released. Otherwise, this member is one of
  the following values:
  ---
  0x0002: DOUBLE_CLICK
    The second click (button press) of a
    double-click occurred. The first click is
    returned as a regular button-press event.
  0x0008: MOUSE_HWHEELED
    The horizontal mouse wheel was moved.
  ---
  If the high word of the dwButtonState member
  contains a positive value, the wheel was
  rotated to the right. Otherwise, the wheel was
  rotated to the left.
  ---
  0x0001: MOUSE_MOVED
    A change in mouse position occurred.
  0x0004: MOUSE_WHEELED
    The vertical mouse wheel was moved.
  ---
  If the high word of the dwButtonState member
  contains a positive value, the wheel was
  rotated forward, away from the user. Otherwise,
  the wheel was rotated backward, toward the user.
  }}} */
}
MOUSE_EVENT_RECORD;
typedef struct _KEY_EVENT_RECORD
{
  uint32_t bKeyDown;
  uint16_t wRepeatCount;
  uint16_t wVirtualKeyCode;
  uint16_t wVirtualScanCode;
  uint16_t uChar;
  uint32_t dwControlKeyState;/* {{{
  The state of the control keys
  ---
  This member can be one or more of the following values.
  CAPSLOCK_ON 0x0080 The CAPS LOCK light is on.
  ENHANCED_KEY 0x0100 The key is enhanced. See remarks.
  LEFT_ALT_PRESSED 0x0002 The left ALT key is pressed.
  LEFT_CTRL_PRESSED 0x0008 The left CTRL key is pressed.
  NUMLOCK_ON 0x0020 The NUM LOCK light is on.
  RIGHT_ALT_PRESSED 0x0001 The right ALT key is pressed.
  RIGHT_CTRL_PRESSED 0x0004 The right CTRL key is pressed.
  SCROLLLOCK_ON 0x0040 The SCROLL LOCK light is on.
  SHIFT_PRESSED 0x0010 The SHIFT key is pressed.
  ---
  Enhanced keys for the IBM® 101- and 102-key
  keyboards are the INS, DEL, HOME, END, PAGE UP,
  PAGE DOWN, and direction keys in the clusters
  to the left of the keypad; and the divide (/)
  and ENTER keys in the keypad.
  ---
  Keyboard input events are generated when any key,
  including control keys, is pressed or released.
  However, the ALT key when pressed and released
  without combining with another character,
  has special meaning to the system and
  is not passed through to the application.
  Also, the CTRL+C key combination is not passed
  through if the input handle is in processed mode
  (ENABLE_PROCESSED_INPUT).
  }}} */
}
KEY_EVENT_RECORD;
typedef struct _INPUT_RECORD
{
  uint16_t EventType;
  union {
    KEY_EVENT_RECORD          KeyEvent;
    MOUSE_EVENT_RECORD        MouseEvent;
    WINDOW_BUFFER_SIZE_RECORD WindowBufferSizeEvent;
    MENU_EVENT_RECORD         MenuEvent;
    FOCUS_EVENT_RECORD        FocusEvent;
  } Event;
}
INPUT_RECORD;
// }}}
// SECURITY_ATTRIBUTES {{{
typedef struct _SECURITY_ATTRIBUTES {
  uint32_t nLength;/*  {{{
  The size, in bytes, of this structure.
  Set this value to the size of
  the SECURITY_ATTRIBUTES structure.

For information about creating a security descriptor, see Creating a Security Descriptor.

bInheritHandle
A Boolean value that specifies whether the returned handle is inherited when a new process is created. If this member is TRUE, the new process inherits the handle.
  }}} */
  void* lpSecurityDescriptor;/*  {{{
  A pointer to a SECURITY_DESCRIPTOR structure
  that controls access to the object.
  If the value of this member is NULL,
  the object is assigned the default security
  descriptor associated with the access token of
  the calling process.
  This is not the same as granting access to
  everyone by assigning a NULL discretionary
  access control list (DACL).
  By default, the default DACL in the access token of
  a process allows access only to the user
  represented by the access token.
  }}} */
  int bInheritHandle;/*  {{{
  A Boolean value that specifies whether
  the returned handle is inherited when
  a new process is created.
  If this member is TRUE, the new process
  inherits the handle.
  }}} */
} SECURITY_ATTRIBUTES;
// }}}
// OVERLAPPED {{{
typedef struct _OVERLAPPED {
  uintptr_t Internal; /*  {{{
  The status code for the I/O request.
  When the request is issued,
  the system sets this member to STATUS_PENDING
  to indicate that the operation has not yet started.
  When the request is completed,
  the system sets this member to the status code
  for the completed request.
  The Internal member was originally reserved
  for system use and its behavior may change.
  }}} */
  uintptr_t InternalHigh; /*  {{{
  The number of bytes transferred for the I/O request.
  The system sets this member if the request
  is completed without errors.
  The InternalHigh member was originally reserved
  for system use and its behavior may change.
  }}} */
  union {
    struct {
      uint32_t Offset; /*  {{{
      The low-order portion of the file position
      at which to start the I/O request,
      as specified by the user.
      This member is nonzero only when performing I/O
      requests on a seeking device that supports
      the concept of an offset (also referred to
      as a file pointer mechanism), such as a file.
      Otherwise, this member must be zero.
      }}} */
      uint32_t OffsetHigh; /*  {{{
      The high-order portion of the file position
      at which to start the I/O request,
      as specified by the user.
      This member is nonzero only
      when performing I/O requests on a seeking device
      that supports the concept of an offset
      (also referred to as a file pointer mechanism),
      such as a file. Otherwise,
      this member must be zero.
      }}} */
    } DUMMYSTRUCTNAME;
    void* Pointer;/* {{{
    Reserved for system use;
    do not use after initialization to zero.
    }}} */
  } DUMMYUNIONNAME;
  HANDLE hEvent;/*  {{{
  A handle to the event that will be set
  to a signaled state by the system when
  the operation has completed.
  The user must initialize this member
  either to zero or a valid event handle using
  the CreateEvent function before passing
  this structure to any overlapped functions.
  This event can then be used to synchronize
  simultaneous I/O requests for a device.
  For additional information, see Remarks.

  Functions such as ReadFile and WriteFile
  set this handle to the nonsignaled state
  before they begin an I/O operation.
  When the operation has completed,
  the handle is set to the signaled state.

  Functions such as GetOverlappedResult and
  the synchronization wait functions reset
  auto-reset events to the nonsignaled state.
  Therefore, you should use a manual reset event;
  if you use an auto-reset event,
  your application can stop responding if you wait
  for the operation to complete and then
  call GetOverlappedResult with the
  bWait parameter set to TRUE.
  }}} */
  /* REMARKS {{{
  Any unused members of this structure
  should always be initialized to zero before
  the structure is used in a function call.
  Otherwise, the function may fail and
  return ERROR_INVALID_PARAMETER.
  ---
  The Offset and OffsetHigh members together
  represent a 64-bit file position.
  It is a byte offset from the start of the file or
  file-like device, and it is specified by the user;
  the system will not modify these values.
  The calling process must set this member
  before passing the OVERLAPPED structure
  to functions that use an offset,
  such as the ReadFile or WriteFile
  (and related) functions.
  ---
  You can use the HasOverlappedIoCompleted macro
  to check whether an asynchronous I/O operation
  has completed if GetOverlappedResult is too cumbersome
  for your application.
  You can use the CancelIo function to cancel
  an asynchronous I/O operation.
  A common mistake is to reuse an OVERLAPPED structure
  before the previous asynchronous operation
  has been completed.
  You should use a separate structure for each request.
  You should also create an event object
  for each thread that processes data.
  If you store the event handles in an array,
  you could easily wait for all events to be signaled
  using the WaitForMultipleObjects function.
  ---
  For additional information and potential pitfalls
  of asynchronous I/O usage, see CreateFile, ReadFile,
  WriteFile, and related functions.
  For a general synchronization overview and
  conceptual OVERLAPPED usage information,
  see Synchronization and Overlapped Input and
  Output and related topics.
  For a file I/O–oriented overview of synchronous and
  asynchronous I/O, see Synchronous and Asynchronous I/O.
  }}} */
} OVERLAPPED, *LPOVERLAPPED;
// }}}
// }}}
/// console {{{
HANDLE GetStdHandle(// {{{
  uint32_t /* nStdHandle [in] {{{
  The standard device. This parameter can be one
  of the following values.

  STD_INPUT_HANDLE ((DWORD)-10)
  The standard input device.
  Initially, this is the console input buffer, CONIN$.

  STD_OUTPUT_HANDLE ((DWORD)-11)
  The standard output device.
  Initially, this is the active console screen buffer, CONOUT$.

  STD_ERROR_HANDLE ((DWORD)-12)
  The standard error device. Initially,
  this is the active console screen buffer, CONOUT$.

  The values for these constants are unsigned numbers,
  but are defined in the header files as a cast from
  a signed number and take advantage of the C compiler
  rolling them over to just under the maximum 32-bit value.
  When interfacing with these handles in a language
  that does not parse the headers and
  is re-defining the constants,
  please be aware of this constraint.
  As an example, ((DWORD)-10) is actually
  the unsigned number 4294967286.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is a handle
  to the specified device, or a redirected handle
  set by a previous call to SetStdHandle.
  The handle has GENERIC_READ and GENERIC_WRITE access rights,
  unless the application has used SetStdHandle
  to set a standard handle with lesser access.
  ---
  Tip
  It is not required to dispose of this handle with
  CloseHandle when done. See Remarks for more information.
  If the function fails,
  the return value is INVALID_HANDLE_VALUE.
  To get extended error information, call GetLastError.
  If an application does not have associated standard handles,
  such as a service running on an interactive desktop,
  and has not redirected them, the return value is NULL.
  }}} */
  /* REMARKS {{{
  Handles returned by GetStdHandle can be used by applications
  that need to read from or write to the console.
  When a console is created, the standard input handle is a handle
  to the console's input buffer, and the standard output and
  standard error handles are handles of the console's
  active screen buffer. These handles can be used by the ReadFile
  and WriteFile functions, or by any of the console functions that
  access the console input buffer or a screen buffer
  (for example, the ReadConsoleInput, WriteConsole,
  or GetConsoleScreenBufferInfo functions).

  The standard handles of a process may be redirected
  by a call to SetStdHandle, in which case GetStdHandle returns
  the redirected handle. If the standard handles have been redirected,
  you can specify the CONIN$ value in a call to the CreateFile
  function to get a handle to a console's input buffer.
  Similarly, you can specify the CONOUT$ value to get a handle to
  a console's active screen buffer.

  The standard handles of a process on entry of the main method
  are dictated by the configuration of the /SUBSYSTEM flag passed
  to the linker when the application was built.
  Specifying /SUBSYSTEM:CONSOLE requests that the operating system
  fill the handles with a console session on startup,
  if the parent didn't already fill the standard handle
  table by inheritance. On the contrary, /SUBSYSTEM:WINDOWS
  implies that the application does not need a console and
  will likely not be making use of the standard handles.
  More information on handle inheritance can be found
  in the documentation for STARTF_USESTDHANDLES.

  Some applications operate outside the boundaries of
  their declared subsystem; for instance, a /SUBSYSTEM:WINDOWS
  application might check/use standard handles for logging or
  debugging purposes but operate normally with a
  graphical user interface. These applications will need to
  carefully probe the state of standard handles on startup
  and make use of AttachConsole, AllocConsole,
  and FreeConsole to add/remove a console if desired.

  Some applications may also vary their behavior on
  the type of inherited handle. Disambiguating the type
  between console, pipe, file, and others can be performed
  with GetFileType.

  Handle disposal
  It is not required to CloseHandle when done with
  the handle retrieved from GetStdHandle.
  The returned value is simply a copy of the value
  stored in the process table.
  The process itself is generally considered the owner
  of these handles and their lifetime.
  Each handle is placed in the table on creation
  depending on the inheritance and launch specifics
  of the CreateProcess call and will be freed
  when the process is destroyed.

  Manual manipulation of the lifetime of these handles
  may be desirable for an application intentionally
  trying to replace them or block other parts of
  the process from using them.
  As a HANDLE can be cached by running code,
  that code will not necessarily pick up changes made via SetStdHandle.
  Closing the handle explicitly via CloseHandle will close
  it process-wide and the next usage of any cached reference
  will encounter an error.

  Guidance for replacing a standard handle in the process table
  would be to get the existing HANDLE from the table with GetStdHandle,
  use SetStdHandle to place a new HANDLE in that is opened
  with CreateFile (or a similar function),
  then to close the retrieved handle.

  There is no validation of the values stored as handles in the process
  table by either the GetStdHandle or SetStdHandle functions.
  Validation is performed at the time of the actual read/write
  operation such as ReadFile or WriteFile.

  Attach/detach behavior
  When attaching to a new console,
  standard handles are always replaced with
  console handles unless STARTF_USESTDHANDLES
  was specified during process creation.

  If the existing value of the standard handle is NULL,
  or the existing value of the standard handle looks
  like a console pseudohandle,
  the handle is replaced with a console handle.

  When a parent uses both CREATE_NEW_CONSOLE and
  STARTF_USESTDHANDLES to create a console process,
  standard handles will not be replaced unless
  the existing value of the standard handle is NULL or
  a console pseudohandle.

  Note
  Console processes must start with the standard handles
  filled or they will be filled automatically with
  appropriate handles to a new console.
  Graphical user interface (GUI) applications can be started
  without the standard handles and
  they will not be automatically filled.
  }}} */
);
// }}}
HANDLE GetConsoleWindow();
int GetConsoleMode(// {{{
  HANDLE, /* hConsoleHandle [in] {{{

    A handle to the console input buffer or
    the console screen buffer. The handle must
    have the GENERIC_READ access right. For more information,
    see Console Buffer Security and Access Rights.

  }}} */
  uint32_t* /* lpMode [out] {{{
  A pointer to a variable that receives the current mode
  of the specified buffer.
  If the hConsoleHandle parameter is an input handle,
  the mode can be one or more of the following values.
  When a console is created, all input modes except
  ENABLE_WINDOW_INPUT and ENABLE_VIRTUAL_TERMINAL_INPUT
  are enabled by default.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  A console consists of an input buffer and one or more
  screen buffers. The mode of a console buffer determines
  how the console behaves during input or output (I/O)
  operations. One set of flag constants is used with
  input handles, and another set is used with screen
  buffer (output) handles. Setting the output modes
  of one screen buffer does not affect the output modes
  of other screen buffers.

  The ENABLE_LINE_INPUT and ENABLE_ECHO_INPUT modes only
  affect processes that use ReadFile or ReadConsole to
  read from the console's input buffer. Similarly,
  the ENABLE_PROCESSED_INPUT mode primarily affects ReadFile
  and ReadConsole users, except that it also determines
  whether CTRL+C input is reported in the input buffer
  (to be read by the ReadConsoleInput function) or
  is passed to a function defined by the application.

  The ENABLE_WINDOW_INPUT and ENABLE_MOUSE_INPUT modes
  determine whether user interactions involving window
  resizing and mouse actions are reported in the input
  buffer or discarded. These events can be read by
  ReadConsoleInput, but they are always filtered
  by ReadFile and ReadConsole.

  The ENABLE_PROCESSED_OUTPUT and ENABLE_WRAP_AT_EOL_OUTPUT
  modes only affect processes using ReadFile or ReadConsole
  and WriteFile or WriteConsole.

  To change a console's I/O modes,
  call SetConsoleMode function.
  }}} */
);
// }}}
int SetConsoleMode(// {{{
  HANDLE, /* hConsoleHandle [in] {{{
  A handle to the console input buffer or
  a console screen buffer. The handle must have
  the GENERIC_READ access right.
  For more information,
  see Console Buffer Security and Access Rights.
  }}} */
  uint32_t /* dwMode [in] {{{
  The input or output mode to be set.
  If the hConsoleHandle parameter is an input handle,
  the mode can be one or more of the following values.
  When a console is created, all input modes except
  ENABLE_WINDOW_INPUT and ENABLE_VIRTUAL_TERMINAL_INPUT
  are enabled by default.
  ---
  ENABLE_PROCESSED_INPUT 0x0001
  ---
  CTRL+C is processed by the system and is not placed
  in the input buffer. If the input buffer is being read
  by ReadFile or ReadConsole, other control keys are
  processed by the system and are not returned in the
  ReadFile or ReadConsole buffer. If the ENABLE_LINE_INPUT
  mode is also enabled, backspace, carriage return, and
  line feed characters are handled by the system.
  ---
  ENABLE_LINE_INPUT 0x0002
  ---
  The ReadFile or ReadConsole function returns only
  when a carriage return character is read.
  If this mode is disabled, the functions return when
  one or more characters are available.
  ---
  ENABLE_ECHO_INPUT 0x0004
  ---
  Characters read by the ReadFile or ReadConsole
  function are written to the active screen buffer
  as they are typed into the console.
  This mode can be used only if the ENABLE_LINE_INPUT
  mode is also enabled.
  ---
  ENABLE_WINDOW_INPUT 0x0008
  ---
  User interactions that change the size of the console
  screen buffer are reported in the console's input buffer.
  Information about these events can be read from
  the input buffer by applications using the
  ReadConsoleInput function, but not by those
  using ReadFile or ReadConsole.
  ---
  ENABLE_MOUSE_INPUT 0x0010
  ---
  If the mouse pointer is within the borders of the
  console window and the window has the keyboard focus,
  mouse events generated by mouse movement and button
  presses are placed in the input buffer.
  These events are discarded by ReadFile or ReadConsole,
  even when this mode is enabled. The ReadConsoleInput
  function can be used to read MOUSE_EVENT input records
  from the input buffer.
  ---
  ENABLE_INSERT_MODE 0x0020
  ---
  When enabled, text entered in a console window
  will be inserted at the current cursor location and
  all text following that location will not be
  overwritten. When disabled, all following text
  will be overwritten.
  ---
  ENABLE_QUICK_EDIT_MODE 0x0040
  ---
  This flag enables the user to use the mouse to select
  and edit text. To enable this mode, use
  ENABLE_QUICK_EDIT_MODE | ENABLE_EXTENDED_FLAGS.
  To disable this mode, use ENABLE_EXTENDED_FLAGS
  without this flag.
  ---
  ENABLE_EXTENDED_FLAGS 0x0080
  ---
  Required to enable or disable extended flags.
  See ENABLE_INSERT_MODE and ENABLE_QUICK_EDIT_MODE.
  ---
  ENABLE_AUTO_POSITION 0x100
  ---
  Though defined in WinCon.h, this flag is otherwise undocumented.
  My initial best guess is that it is related to the
  "Let system position window" check box on the property
  sheet of a CMD.EXE window,
  though I have yet to test this theory.
  ---
  ENABLE_VIRTUAL_TERMINAL_INPUT 0x0200
  ---
  Setting this flag directs the Virtual Terminal
  processing engine to convert user input received
  by the console window into Console Virtual
  Terminal Sequences that can be retrieved by
  a supporting application through ReadFile or
  ReadConsole functions.
  The typical usage of this flag is intended in conjunction
  with ENABLE_VIRTUAL_TERMINAL_PROCESSING on the output
  handle to connect to an application that communicates
  exclusively via virtual terminal sequences.
  ***
  If the hConsoleHandle parameter is a screen bufferhandle,
  the mode can be one or more of the following values.
  When a screen buffer is created, both output modes
  are enabled by default.
  ---
  ENABLE_PROCESSED_OUTPUT 0x0001
  ---
  Characters written by the WriteFile or WriteConsole
  function or echoed by the ReadFile or ReadConsole
  function are parsed for ASCII control sequences,
  and the correct action is performed. Backspace, tab,
  bell, carriage return, and line feed characters
  are processed. It should be enabled when using control
  sequences or when ENABLE_VIRTUAL_TERMINAL_PROCESSING is set.
  ---
  ENABLE_WRAP_AT_EOL_OUTPUT 0x0002
  ---
  When writing with WriteFile or WriteConsole or echoing
  with ReadFile or ReadConsole, the cursor moves
  to the beginning of the next row when it reaches
  the end of the current row. This causes the rows displayed
  in the console window to scroll up automatically when
  the cursor advances beyond the last row in the window.
  It also causes the contents of the console screen buffer
  to scroll up (../discarding the top row of the console
  screen buffer) when the cursor advances beyond the
  last row in the console screen buffer. If this mode
  is disabled, the last character in the row
  is overwritten with any subsequent characters.
  ---
  ENABLE_VIRTUAL_TERMINAL_PROCESSING 0x0004
  ---
  When writing with WriteFile or WriteConsole,
  characters are parsed for VT100 and similar control
  character sequences that control cursor movement,
  color/font mode, and other operations that can also
  be performed via the existing Console APIs.
  For more information, see Console Virtual Terminal Sequences.
  Ensure ENABLE_PROCESSED_OUTPUT is set when using this flag.
  ---
  DISABLE_NEWLINE_AUTO_RETURN 0x0008
  ---
  When writing with WriteFile or WriteConsole,
  this adds an additional state to end-of-line wrapping
  that can delay the cursor move and buffer scroll operations.
  Normally when ENABLE_WRAP_AT_EOL_OUTPUT is set and
  text reaches the end of the line, the cursor will
  immediately move to the next line and the contents
  of the buffer will scroll up by one line.
  In contrast with this flag set, the cursor does not
  move to the next line, and the scroll operation is
  not performed. The written character will be printed
  in the final position on the line and the cursor will
  remain above this character as if
  ENABLE_WRAP_AT_EOL_OUTPUT was off, but the next
  printable character will be printed as if
  ENABLE_WRAP_AT_EOL_OUTPUT is on.
  No overwrite will occur. Specifically, the cursor
  quickly advances down to the following line,
  a scroll is performed if necessary, the character
  is printed, and the cursor advances one more position.
  The typical usage of this flag is intended in
  conjunction with setting ENABLE_VIRTUAL_TERMINAL_PROCESSING
  to better emulate a terminal emulator where writing
  the final character on the screen
  (../in the bottom right corner) without triggering
  an immediate scroll is the desired behavior.
  ---
  ENABLE_LVB_GRID_WORLDWIDE 0x0010
  ---
  The APIs for writing character attributes including
  WriteConsoleOutput and WriteConsoleOutputAttribute
  allow the usage of flags from character attributes
  to adjust the color of the foreground and background
  of text. Additionally, a range of DBCS flags was
  specified with the COMMON_LVB prefix. Historically,
  these flags only functioned in DBCS code pages for
  Chinese, Japanese, and Korean languages.
  With exception of the leading byte and trailing byte
  flags, the remaining flags describing line drawing and
  reverse video (../swap foreground and background colors)
  can be useful for other languages to emphasize
  portions of output.
  Setting this console mode flag will allow these attributes
  to be used in every code page on every language.
  It is off by default to maintain compatibility with
  known applications that have historically taken advantage
  of the console ignoring these flags on non-CJK
  machines to store bits in these fields for their
  own purposes or by accident.
  Note that using the ENABLE_VIRTUAL_TERMINAL_PROCESSING
  mode can result in LVB grid and reverse video flags
  being set while this flag is still off if the
  attached application requests underlining or inverse
  video via Console Virtual Terminal Sequences.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  A console consists of an input buffer and
  one or more screen buffers. The mode of a console buffer
  determines how the console behaves during input or
  output (I/O) operations. One set of flag constants
  is used with input handles, and another set is used
  with screen buffer (output) handles. Setting the output
  modes of one screen buffer does not affect the output
  modes of other screen buffers.

  The ENABLE_LINE_INPUT and ENABLE_ECHO_INPUT modes
  only affect processes that use ReadFile or ReadConsole
  to read from the console's input buffer. Similarly,
  the ENABLE_PROCESSED_INPUT mode primarily affects
  ReadFile and ReadConsole users, except that it also
  determines whether CTRL+C input is reported in the
  input buffer (to be read by the ReadConsoleInput function)
  or is passed to a function defined by the application.

  The ENABLE_WINDOW_INPUT and ENABLE_MOUSE_INPUT modes
  determine whether user interactions involving window
  resizing and mouse actions are reported in the input
  buffer or discarded. These events can be read by
  ReadConsoleInput, but they are always filtered by
  ReadFile and ReadConsole.

  The ENABLE_PROCESSED_OUTPUT and ENABLE_WRAP_AT_EOL_OUTPUT
  modes only affect processes using ReadFile or ReadConsole
  and WriteFile or WriteConsole.

  To determine the current mode of a console input buffer
  or a screen buffer, use the GetConsoleMode function.
  }}} */
);
// }}}
int GetConsoleCP();
int SetConsoleCP(int);
int GetConsoleOutputCP();/* {{{
RETURN VALUE
  The return value is a code that identifies
  the code page. For a list of identifiers,
  see Code Page Identifiers.
  If the return value is zero, the function has failed.
  To get extended error information, call GetLastError.
REMARKS
  A code page maps 256 character codes to individual
  characters. Different code pages include different
  special characters, typically customized for a
  language or a group of languages. To retrieve
  more information about a code page, including
  it's name, see the GetCPInfoEx function.
  To set a console's output code page,
  use the SetConsoleOutputCP function. To set and
  query a console's input code page,
  use the SetConsoleCP and GetConsoleCP functions.
}}} */
int SetConsoleOutputCP(int);/* {{{
wCodePageID [in]
  The identifier of the code page to set.
  For more information, see Remarks.
RETURN VALUE
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
REMARKS
  A code page maps 256 character codes to individual characters.
  Different code pages include different special
  characters, typically customized for a language or
  a group of languages.
  If the current font is a fixed-pitch Unicode font,
  SetConsoleOutputCP changes the mapping of the
  character values into the glyph set of the font,
  rather than loading a separate font each time it
  is called. This affects how extended characters
  (ASCII value greater than 127) are displayed in
  a console window. However, if the current font
  is a raster font, SetConsoleOutputCP does not
  affect how extended characters are displayed.
  ---
  To find the code pages that are installed or
  supported by the operating system, use the
  EnumSystemCodePages function. The identifiers of
  the code pages available on the local computer
  are also stored in the registry under
  the following key:
  HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\Control\Nls\CodePage
  However, it is better to use EnumSystemCodePages
  to enumerate code pages because the registry
  can differ in different versions of Windows.
  To determine whether a particular code page is valid,
  use the IsValidCodePage function.
  To retrieve more information about a code page,
  including its name, use the GetCPInfoEx function.
  For a list of available code page identifiers,
  see Code Page Identifiers.
  To determine a console's current output code page,
  use the GetConsoleOutputCP function.
  To set and retrieve a console's input code page,
  use the SetConsoleCP and GetConsoleCP functions.
}}} */
int GetConsoleScreenBufferInfo(// {{{
  HANDLE, /* hConsoleOutput [in] {{{
  A handle to the console screen buffer.
  The handle must have the GENERIC_READ access right.
  For more information, see Console Buffer Security
  and Access Rights.
  }}} */
  CONSOLE_SCREEN_BUFFER_INFO* /* [out] {{{
  A pointer to a CONSOLE_SCREEN_BUFFER_INFO structure
  that receives the console screen buffer information.
  }}} */
  /* REMARKS {{{
  The rectangle returned in the srWindow member of
  the CONSOLE_SCREEN_BUFFER_INFO structure can be
  modified and then passed to the SetConsoleWindowInfo
  function to scroll the console screen buffer in
  the window, to change the size of the window, or both.

  All coordinates returned in the CONSOLE_SCREEN_BUFFER_INFO
  structure are in character-cell coordinates,
  where the origin (0, 0) is at the upper-left corner
  of the console screen buffer.

  This API does not have a virtual terminal equivalent.
  Its use may still be required for applications that
  are attempting to draw columns, grids, or fill the
  display to retrieve the window size.
  This window state is managed by the TTY/PTY/Pseudoconsole
  outside of the normal stream flow and is generally
  considered a user privilege not adjustable by the
  client application.
  Updates can be received on ReadConsoleInput.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
);
// }}}
int SetConsoleCursorPosition(// {{{
  int, /* hConsoleOutput [in] {{{
  A handle to the console screen buffer.
  The handle must have the GENERIC_READ access right.
  For more information, see Console Buffer
  Security and Access Rights.
  }}} */
  COORD /* dwCursorPosition [in] {{{
  A COORD structure that specifies the
  new cursor position, in characters.
  The coordinates are the column and row of
  a screen buffer character cell.
  The coordinates must be within the boundaries
  of the console screen buffer.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  The cursor position determines where
  characters written by the WriteFile or WriteConsole
  function, or echoed by the ReadFile or ReadConsole
  function, are displayed. To determine the
  current position of the cursor,
  use the GetConsoleScreenBufferInfo function.

  If the new cursor position is not within the
  boundaries of the console screen buffer's window,
  the window origin changes to make the cursor visible.

  This API has a virtual terminal equivalent
  in the simple cursor positioning and
  cursor positioning sections. Use of the newline,
  carriage return, backspace, and tab control
  sequences can also assist with cursor positioning.
  }}} */
);
// }}}
int GetNumberOfConsoleInputEvents(// {{{
  HANDLE, /* hConsoleInput [in] {{{
  A handle to the console input buffer.
  The handle must have the GENERIC_READ access right.
  For more information, see Console Buffer Security
  and Access Rights.
  }}} */
  uint32_t* /* lpcNumberOfEvents [out] {{{
  A pointer to a variable that receives the number
  of unread input records in the console's input buffer.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  The GetNumberOfConsoleInputEvents function reports
  the total number of unread input records
  in the input buffer, including keyboard, mouse,
  and window-resizing input records.
  Processes using the ReadFile or ReadConsole function
  can only read keyboard input.
  Processes using the ReadConsoleInput function
  can read all types of input records.

  A process can specify a console input buffer handle
  in one of the wait functions to determine
  when there is unread console input
  When the input buffer is not empty
  the state of a console input buffer handle is signaled.

  To read input records from a console input buffe
  without affecting the number of unread records
  use the PeekConsoleInput function
  To discard all unread records in a console's input buffer
  use the FlushConsoleInputBuffer function.
  }}} */
);
// }}}
int ReadConsoleInputW(// {{{
  HANDLE, /* hConsoleInput [in] {{{
  A handle to the console input buffer.
  The handle must have the GENERIC_READ access right.
  }}} */
  INPUT_RECORD*, /* lpBuffer [out] {{{
  A pointer to an array of INPUT_RECORD structures
  that receives the input buffer data.
  }}} */
  uint32_t, /* nLength [in] {{{
  The size of the array pointed to by the lpBuffer
  parameter, in array elements.
  }}} */
  void* /* uint32_t* lpNumberOfEventsRead [out] {{{
  A pointer to a variable that receives the number
  of input records read.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  If the number of records requested in the nLength
  parameter exceeds the number of records available
  in the buffer, the number available is read.
  The function does not return until at least one
  input record has been read.

  A process can specify a console input buffer handle
  in one of the wait functions to determine
  when there is unread console input.
  When the input buffer is not empty,
  the state of a console input buffer handle is signaled.

  To determine the number of unread input records
  in a console's input buffer, use the
  GetNumberOfConsoleInputEvents function.
  To read input records from a console input buffer
  without affecting the number of unread records,
  use the PeekConsoleInput function.
  To discard all unread records in a console's
  input buffer, use the FlushConsoleInputBuffer function.

  This function uses either Unicode characters or
  8-bit characters from the console's current code page.
  The console's code page defaults initially to
  the system's OEM code page.
  To change the console's code page,
  use the SetConsoleCP or SetConsoleOutputCP functions.
  Legacy consumers may also use the chcp or
  mode con cp select= commands,
  but it is not recommended for new development
  }}} */
);
// }}}
int ReadConsoleOutputAttribute(// {{{
  HANDLE, /* hConsoleOutput [in] {{{
  A handle to the console screen buffer.
  The handle must have the GENERIC_READ access
  }}} */
  uint16_t*, /* lpAttribute [out] {{{
  A pointer to a buffer that receives
  the attributes being used by the console
  screen buffer.
  }}} */
  uint32_t, /* nLength [in] {{{
  The number of screen buffer character
  cells from which to read.
  The size of the buffer pointed to by the
  lpAttribute parameter should be
  nLength * sizeof(WORD) .
  }}} */
  COORD, /* dwReadCoord [in] {{{
  The coordinates of the first cell in the
  console screen buffer from which to read,
  in characters. The X member of the COORD
  structure is the column,
  and the Y member is the row.
  }}} */
  void* /* uint32_t* lpNumberOfAttrsRead [out] {{{
  A pointer to a variable that receives the number
  of attributes actually read.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  If the number of attributes to be read
  from extends beyond the end of the specified
  screen buffer row, attributes are read from
  the next row. If the number of attributes to
  be read from extends beyond the end of the
  console screen buffer, attributes up to the
  end of the console screen buffer are read.

  Tip
  This API is not recommended and does not
  have a virtual terminal equivalent. This
  decision intentionally aligns the Windows
  platform with other operating systems
  where the individual client application is
  expected to remember its own drawn state
  for further manipulation. Applications
  remoting via cross-platform utilities and
  transports like SSH may not work as expected
  if using this API.
  }}} */
);
// }}}
int WriteConsoleA(// {{{
  HANDLE,/* hConsoleOutput [in] {{{
  A handle to the console screen buffer.
  The handle must have the GENERIC_WRITE access right.
  }}} */
  char*,/* lpBuffer [in] {{{
  A pointer to a buffer that contains characters
  to be written to the console screen buffer.
  This is expected to be an array of either char
  for WriteConsoleA or wchar_t for WriteConsoleW.
  }}} */
  uint32_t,/* nNumberOfCharsToWrite [in] {{{
  The number of characters to be written.
  If the total size of the specified number of
  characters exceeds the available heap,
  the function fails with ERROR_NOT_ENOUGH_MEMORY.
  }}} */
  void*,/* uint32_t* lpNumberOfCharsWritten [out, optional] {{{
  A pointer to a variable that receives the number
  of characters actually written.
  }}} */
  void* /* lpReserved  {{{
  Reserved; must be NULL.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  The WriteConsole function writes characters
  to the console screen buffer at the current
  cursor position. The cursor position advances
  as characters are written.
  The SetConsoleCursorPosition function sets
  the current cursor position.
  ---
  Characters are written using the foreground and
  background color attributes associated
  with the console screen buffer.
  The SetConsoleTextAttribute function
  changes these colors. To determine the current
  color attributes and the current cursor position,
  use GetConsoleScreenBufferInfo.
  ---
  All of the input modes that affect the behavior
  of the WriteFile function have the same effect
  on WriteConsole. To retrieve and set
  the output modes of a console screen buffer,
  use the GetConsoleMode and SetConsoleMode functions.
  ---
  This function uses either Unicode characters or 8-bit
  characters from the console's current code page.
  The console's code page defaults initially to
  the system's OEM code page. To change the console's
  code page, use the SetConsoleCP or SetConsoleOutputCP
  functions. Legacy consumers may also use the chcp or
  mode con cp select= commands, but it is not recommended
  for new development.
  ---
  WriteConsole fails if it is used with a standard handle
  that is redirected to a file. If an application processes
  multilingual output that can be redirected,
  determine whether the output handle is a console handle
  (one method is to call the GetConsoleMode function and
  check whether it succeeds).
  If the handle is a console handle, call WriteConsole.
  If the handle is not a console handle,
  the output is redirected and you should call WriteFile
  to perform the I/O. Be sure to prefix a Unicode plain
  text file with a byte order mark.
  For more information, see Using Byte Order Marks.
  ---
  Although an application can use WriteConsole in ANSI mode
  to write ANSI characters, consoles do not support
  "ANSI escape" or "virtual terminal" sequences
  unless enabled. See Console Virtual Terminal Sequences
  for more information and
  for operating system version applicability.
  When virtual terminal escape sequences are not enabled,
  console functions can provide equivalent functionality.
  For more information, see SetCursorPos,
  SetConsoleTextAttribute, and GetConsoleCursorInfo.
  }}} */
);
// }}}
int FlushConsoleInputBuffer(// {{{
  /* INFO {{{
  Flushes the console input buffer.
  All input records currently in the input buffer are discarded.
  }}} */
  HANDLE /* hConsoleInput [in] {{{
  A handle to the console input buffer.
  The handle must have the GENERIC_WRITE access right.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
);
// }}}
int GetConsoleProcessList(// {{{
  uint32_t*, /* lpdwProcessList [out] {{{
  A pointer to a buffer that receives an array
  of process identifiers upon success.
  This must be a valid buffer and cannot be NULL.
  The buffer must have space to receive
  at least 1 returned process id.
  }}} */
  uint32_t /* dwProcessCount [in] {{{
  The maximum number of process identifiers that
  can be stored in the lpdwProcessList buffer.
  This must be greater than 0.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value
  is less than or equal to dwProcessCount and
  represents the number of process identifiers
  stored in the lpdwProcessList buffer.
  If the buffer is too small to hold all
  the valid process identifiers, the return value
  is the required number of array elements.
  The function will have stored no identifiers
  in the buffer. In this situation,
  use the return value to allocate a buffer
  that is large enough to store the entire list and
  call the function again.
  If the return value is zero, the function has failed,
  because every console has at least one process
  associated with it.
  To get extended error information,
  call GetLastError.
  If a NULL process list was provided or
  the process count was 0, the call will return 0 and
  GetLastError will return ERROR_INVALID_PARAMETER.
  Please provide a buffer of at least one element
  to call this function. Allocate a larger buffer and
  call again if the return code is larger than
  the length of the provided buffer.
  }}} */
);
// }}}
/// }}}
/// files {{{
HANDLE CreateFileA(// {{{
  /* INFO {{{
  Creates or opens a file or I/O device.
  The most commonly used I/O devices are as follows:
  file, file stream, directory, physical disk,
  volume, console buffer, tape drive, communications
  resource, mailslot, and pipe. The function returns
  a handle that can be used to access the file or
  device for various types of I/O depending
  on the file or device and the flags and
  attributes specified.
  To perform this operation as a transacted operation,
  which results in a handle that can be used for
  transacted I/O, use the CreateFileTransacted function.
  }}} */
  char*,/* [in] lpFileName {{{
  The name of the file or device to be created or
  opened. You may use either forward slashes (/) or
  backslashes (\) in this name.
  By default, the name is limited to MAX_PATH
  characters. To extend this limit to 32,767 wide
  characters, prepend "\\?\" to the path.
  For more information, see Naming Files,
  Paths, and Namespaces.
  Tip
  ---
  Starting with Windows 10, Version 1607, you can opt-in
  to remove the MAX_PATH limitation without
  prepending "\\?\". See the "Maximum Path Length
  Limitation" section of Naming Files, Paths,
  and Namespaces for details.
  For information on special device names,
  see Defining an MS-DOS Device Name.
  To create a file stream, specify the name of the file,
  a colon, and then the name of the stream.
  For more information, see File Streams.
  }}} */
  uint32_t,/* [in] dwDesiredAccess {{{
  The requested access to the file or device,
  which can be summarized as read, write,
  both or 0 to indicate neither).
  The most commonly used values are GENERIC_READ,
  GENERIC_WRITE, or both (GENERIC_READ | GENERIC_WRITE).
  For more information, see Generic Access Rights,
  File Security and Access Rights, File Access Rights
  Constants, and ACCESS_MASK.
  If this parameter is zero, the application can query
  certain metadata such as file, directory,
  or device attributes without accessing that
  file or device, even if GENERIC_READ access would
  have been denied.
  You cannot request an access mode that conflicts
  with the sharing mode that is specified by the
  dwShareMode parameter in an open request that
  already has an open handle.
  For more information, see the Remarks section of
  this topic and Creating and Opening Files.
  ---
  GENERIC_READ    = 0x80000000,
  GENERIC_WRITE   = 0x40000000,
  GENERIC_EXECUTE = 0x20000000,
  GENERIC_ALL     = 0x10000000,
  }}} */
  uint32_t,/* [in] dwShareMode {{{
  The requested sharing mode of the file or device,
  which can be read, write, both, delete, all of these,
  or none (refer to the following table).
  Access requests to attributes or extended attributes
  are not affected by this flag.
  If this parameter is zero and CreateFile succeeds,
  the file or device cannot be shared and cannot be
  opened again until the handle to the file or
  device is closed. For more information,
  see the Remarks section.
  You cannot request a sharing mode that conflicts
  with the access mode that is specified in an existing
  request that has an open handle. CreateFile would
  fail and the GetLastError function would
  return ERROR_SHARING_VIOLATION.
  To enable a process to share a file or device
  while another process has the file or device open,
  use a compatible combination of one or more of the
  following values. For more information about valid
  combinations of this parameter with the
  dwDesiredAccess parameter, see Creating and Opening Files.

  Note
  ---
  The sharing options for each open handle remain
  in effect until that handle is closed, regardless of
  process context.

  Value/Meaning
  ---
  0 0x00000000
  ---
  Prevents other processes from opening a file or device
  if they request delete, read, or write access.
  ---
  FILE_SHARE_READ 0x00000001
  ---
  Enables subsequent open operations on a file or
  device to request read access.
  Otherwise, other processes cannot open the file or
  device if they request read access.
  If this flag is not specified, but the file or
  device has been opened for read access,
  the function fails.
  ---
  FILE_SHARE_WRITE 0x00000002
  ---
  Enables subsequent open operations on a file or
  device to request write access.
  Otherwise, other processes cannot open the file or
  device if they request write access.
  If this flag is not specified, but the file or
  device has been opened for write access or has a file
  mapping with write access, the function fails.
  ---
  FILE_SHARE_DELETE 0x00000004
  ---
  Enables subsequent open operations on a file or
  device to request delete access.
  Otherwise, other processes cannot open the file or
  device if they request delete access.
  If this flag is not specified, but the file or
  device has been opened for delete access,
  the function fails.
  Delete access allows both delete and
  rename operations.
  }}} */
  SECURITY_ATTRIBUTES*,/* [in, optional] lpSecurityAttributes {{{
  A pointer to a SECURITY_ATTRIBUTES structure that
  contains two separate but related data members:
  an optional security descriptor, and a Boolean value
  that determines whether the returned handle can be
  inherited by child processes.
  This parameter can be NULL.
  If this parameter is NULL, the handle returned by
  CreateFile cannot be inherited by any child processes
  the application may create and the file or device
  associated with the returned handle gets a default
  security descriptor.
  The lpSecurityDescriptor member of the structure
  specifies a SECURITY_DESCRIPTOR for a file or device.
  If this member is NULL, the file or device
  associated with the returned handle is assigned
  a default security descriptor.
  CreateFile ignores the lpSecurityDescriptor member
  when opening an existing file or device,
  but continues to use the bInheritHandle member.
  The bInheritHandle member of the structure specifies
  whether the returned handle can be inherited.
  For more information, see the Remarks section.
  }}} */
  uint32_t,/* [in] dwCreationDisposition {{{
  An action to take on a file or device that exists or
  does not exist.  For devices other than files, this parameter
  is usually set to OPEN_EXISTING. For more information,
  see the Remarks section. This parameter must be one of
  the following values, which cannot be combined:

  Value/Meaning
  ---
  CREATE_ALWAYS 2
  ---
  Creates a new file, always.
  If the specified file exists and is writable,
  the function truncates the file, the function succeeds,
  and last-error code is set to ERROR_ALREADY_EXISTS (183).
  If the specified file does not exist and is
  a valid path, a new file is created, the function succeeds,
  and the last-error code is set to zero.
  For more information, see the Remarks section
  of this topic.
  ---
  CREATE_NEW 1
  ---
  Creates a new file, only if it does not already exist.
  If the specified file exists, the function fails and
  the last-error code is set to ERROR_FILE_EXISTS (80).
  If the specified file does not exist and is a valid
  path to a writable location, a new file is created.
  ---
  OPEN_ALWAYS 4
  ---
  Opens a file, always.
  If the specified file exists, the function succeeds
  and the last-error code is set to ERROR_ALREADY_EXISTS (183).
  If the specified file does not exist and is
  a valid path to a writable location, the function
  creates a file and the last-error code is set to zero.
  ---
  OPEN_EXISTING 3
  ---
  Opens a file or device, only if it exists.
  If the specified file or device does not exist,
  the function fails and the last-error code
  is set to ERROR_FILE_NOT_FOUND (2).
  For more information about devices, see the Remarks section.
  ---
  TRUNCATE_EXISTING 5
  ---
  Opens a file and truncates it so that its size
  is zero bytes, only if it exists.
  If the specified file does not exist,
  the function fails and the last-error code is
  set to ERROR_FILE_NOT_FOUND (2).
  The calling process must open the file with
  the GENERIC_WRITE bit set as part of
  the dwDesiredAccess parameter.
  }}} */
  uint32_t,/* [in] dwFlagsAndAttributes {{{
  The file or device attributes and flags,
  FILE_ATTRIBUTE_NORMAL being the most common
  default value for files.
  This parameter can include any combination of
  the available file attributes (FILE_ATTRIBUTE_*).
  All other file attributes override FILE_ATTRIBUTE_NORMAL.
  This parameter can also contain combinations of
  flags (FILE_FLAG_*) for control of file or
  device caching behavior, access modes,
  and other special-purpose flags.
  These combine with any FILE_ATTRIBUTE_* values.
  This parameter can also contain Security Quality
  of Service (SQOS) information by specifying the
  SECURITY_SQOS_PRESENT flag. Additional SQOS-related
  flags information is presented in the table following
  the attributes and flags tables.
  Note
  ---
  When CreateFile opens an existing file,
  it generally combines the file flags with
  the file attributes of the existing file,
  and ignores any file attributes supplied as part
  of dwFlagsAndAttributes. Special cases are detailed
  in Creating and Opening Files.
  ---
  Some of the following file attributes and flags
  may only apply to files and not necessarily all
  other types of devices that CreateFile can open.
  For additional information, see the Remarks section
  of this topic and Creating and Opening Files.
  For more advanced access to file attributes,
  see SetFileAttributes. For a complete list of
  all file attributes with their values and descriptions,
  see File Attribute Constants.

  Attribute/Meaning
  ---
  FILE_ATTRIBUTE_ARCHIVE 32 (0x20)
  ---
  The file should be archived. Applications use this
  attribute to mark files for backup or removal.
  ---
  FILE_ATTRIBUTE_ENCRYPTED 16384 (0x4000)
  ---
  The file or directory is encrypted. For a file,
  this means that all data in the file is encrypted.
  For a directory, this means that encryption is
  the default for newly created files and subdirectories.
  For more information, see File Encryption.
  This flag has no effect if FILE_ATTRIBUTE_SYSTEM
  is also specified.
  This flag is not supported on Home, Home Premium,
  Starter, or ARM editions of Windows.
  ---
  FILE_ATTRIBUTE_HIDDEN 2 (0x2)
  ---
  The file is hidden. Do not include it in an
  ordinary directory listing.
  ---
  FILE_ATTRIBUTE_NORMAL 128 (0x80)
  ---
  The file does not have other attributes set.
  This attribute is valid only if used alone.
  ---
  FILE_ATTRIBUTE_OFFLINE 4096 (0x1000)
  ---
  The data of a file is not immediately available.
  This attribute indicates that file data is physically
  moved to offline storage. This attribute is used by
  Remote Storage, the hierarchical storage management
  software. Applications should not arbitrarily
  change this attribute.
  ---
  FILE_ATTRIBUTE_READONLY 1 (0x1)
  ---
  The file is read only. Applications can read the file,
  but cannot write to or delete it.
  ---
  FILE_ATTRIBUTE_SYSTEM 4 (0x4)
  ---
  The file is part of or used exclusively
  by an operating system.
  ---
  FILE_ATTRIBUTE_TEMPORARY 256 (0x100)
  ---
  The file is being used for temporary storage.
  For more information, see the Caching Behavior
  section of this topic.

  Flag/Meaning
  ---
  FILE_FLAG_BACKUP_SEMANTICS 0x02000000
  ---
  The file is being opened or created for a backup or
  restore operation. The system ensures that
  the calling process overrides file security
  checks when the process has SE_BACKUP_NAME and
  SE_RESTORE_NAME privileges. For more information,
  see Changing Privileges in a Token.
  You must set this flag to obtain a handle
  to a directory. A directory handle can be passed
  to some functions instead of a file handle.
  For more information, see the Remarks section.
  ---
  FILE_FLAG_DELETE_ON_CLOSE 0x04000000
  ---
  The file is to be deleted immediately after all of
  its handles are closed, which includes the specified
  handle and any other open or duplicated handles.
  If there are existing open handles to a file,
  the call fails unless they were all opened with
  the FILE_SHARE_DELETE share mode.
  Subsequent open requests for the file fail,
  unless the FILE_SHARE_DELETE share mode is specified.
  ---
  FILE_FLAG_NO_BUFFERING 0x20000000
  ---
  The file or device is being opened with no system
  caching for data reads and writes. This flag does not
  affect hard disk caching or memory mapped files.
  There are strict requirements for successfully working
  with files opened with CreateFile using the
  FILE_FLAG_NO_BUFFERING flag, for details see File Buffering.
  ---
  FILE_FLAG_OPEN_NO_RECALL 0x00100000
  ---
  The file data is requested, but it should continue
  to be located in remote storage. It should not be
  transported back to local storage. This flag is
  for use by remote storage systems.
  ---
  FILE_FLAG_OPEN_REPARSE_POINT 0x00200000
  ---
  Normal reparse point processing will not occur;
  CreateFile will attempt to open the reparse point.
  When a file is opened, a file handle is returned,
  whether or not the filter that controls the reparse
  point is operational.
  This flag cannot be used with the CREATE_ALWAYS flag.
  If the file is not a reparse point,
  then this flag is ignored.
  For more information, see the Remarks section.
  ---
  FILE_FLAG_OVERLAPPED 0x40000000
  ---
  The file or device is being opened or
  created for asynchronous I/O.
  When subsequent I/O operations are
  completed on this handle, the event specified in
  the OVERLAPPED structure will be set to the signaled state.
  If this flag is specified, the file can be used
  for simultaneous read and write operations.
  If this flag is not specified, then I/O operations
  are serialized, even if the calls to the read and
  write functions specify an OVERLAPPED structure.
  For information about considerations when using
  a file handle created with this flag,
  see the Synchronous and Asynchronous I/O Handles
  section of this topic.
  ---
  FILE_FLAG_POSIX_SEMANTICS 0x01000000
  ---
  Access will occur according to POSIX rules.
  This includes allowing multiple files with names,
  differing only in case, for file systems
  that support that naming. Use care when using this option,
  because files created with this flag may not
  be accessible by applications that are written for
  MS-DOS or 16-bit Windows.
  ---
  FILE_FLAG_RANDOM_ACCESS 0x10000000
  ---
  Access is intended to be random. The system can use
  this as a hint to optimize file caching.
  This flag has no effect if the file system does not
  support cached I/O and FILE_FLAG_NO_BUFFERING.
  For more information, see the Caching Behavior
  section of this topic.
  ---
  FILE_FLAG_SESSION_AWARE 0x00800000
  ---
  The file or device is being opened with session
  awareness. If this flag is not specified,
  then per-session devices (such as a device using
  RemoteFX USB Redirection) cannot be opened by
  processes running in session 0. This flag has
  no effect for callers not in session 0.
  This flag is supported only on server editions of Windows.
  Windows Server 2008 R2 and Windows Server 2008:
  This flag is not supported before Windows Server 2012.
  ---
  FILE_FLAG_SEQUENTIAL_SCAN 0x08000000
  ---
  Access is intended to be sequential from beginning to end.
  The system can use this as a hint to optimize file caching.
  This flag should not be used if read-behind
  (that is, reverse scans) will be used.
  This flag has no effect if the file system does not
  support cached I/O and FILE_FLAG_NO_BUFFERING.
  For more information, see the Caching Behavior section
  of this topic.
  ---
  FILE_FLAG_WRITE_THROUGH 0x80000000
  ---
  Write operations will not go through any
  intermediate cache, they will go directly to disk.
  For additional information, see the Caching Behavior
  section of this topic.

  The dwFlagsAndAttributes parameter can also specify
  SQOS information. For more information,
  see Impersonation Levels. When the calling application
  specifies the SECURITY_SQOS_PRESENT flag as part of
  dwFlagsAndAttributes, it can also contain one or
  more of the following values.

  Security flag / Meaning
  ---
  SECURITY_ANONYMOUS
  Impersonates a client at the Anonymous impersonation level.
  ---
  SECURITY_CONTEXT_TRACKING
  The security tracking mode is dynamic.
  If this flag is not specified,
  the security tracking mode is static.
  ---
  SECURITY_DELEGATION
  Impersonates a client at the Delegation impersonation level.
  ---
  SECURITY_EFFECTIVE_ONLY
  Only the enabled aspects of the client's security
  context are available to the server. If you do not
  specify this flag, all aspects of the client's
  security context are available.
  This allows the client to limit the groups and privileges
  that a server can use while impersonating the client.
  ---
  SECURITY_IDENTIFICATION
  Impersonates a client at the Identification
  impersonation level.
  ---
  SECURITY_IMPERSONATION
  Impersonate a client at the impersonation level.
  This is the default behavior if no other flags are
  specified along with the SECURITY_SQOS_PRESENT flag.
  }}} */
  HANDLE /* [in, optional] hTemplateFile {{{
  A valid handle to a template file with the
  GENERIC_READ access right. The template file supplies
  file attributes and extended attributes for
  the file that is being created.
  This parameter can be NULL.
  When opening an existing file,
  CreateFile ignores this parameter.
  When opening a new encrypted file,
  the file inherits the discretionary access control list
  from its parent directory.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value
  is an open handle to the specified file, device,
  named pipe, or mail slot. If the function fails,
  the return value is INVALID_HANDLE_VALUE.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  CreateFile was originally developed specifically
  for file interaction but has since been expanded and
  enhanced to include most other types of I/O devices
  and mechanisms available to Windows developers.
  This section attempts to cover the varied issues
  developers may experience when using CreateFile
  in different contexts and with different I/O types.
  The text attempts to use the word file only
  when referring specifically to data stored in
  an actual file on a file system. However,
  some uses of file may be referring more generally
  to an I/O object that supports file-like mechanisms.
  This liberal use of the term file is particularly
  prevalent in constant names and parameter names
  because of the previously mentioned historical reasons.
  When an application is finished using the
  object handle returned by CreateFile,
  use the CloseHandle function to close the handle.
  This not only frees up system resources,
  but can have wider influence on things like
  sharing the file or device and committing
  data to disk. Specifics are noted within this
  topic as appropriate.
  Windows Server 2003 and Windows XP:
  A sharing violation occurs if an attempt is
  made to open a file or directory for deletion on
  a remote computer when the value of the dwDesiredAccess
  parameter is the DELETE access flag (0x00010000)
  OR'ed with any other access flag, and the remote file or
  directory has not been opened with FILE_SHARE_DELETE.
  To avoid the sharing violation in this scenario,
  open the remote file or directory with the DELETE
  access right only, or call DeleteFile without
  first opening the file or directory for deletion.
  Some file systems, such as the NTFS file system,
  support compression or encryption for individual
  files and directories. On volumes that have a mounted
  file system with this support, a new file inherits
  the compression and encryption attributes of its directory.
  You cannot use CreateFile to control compression,
  decompression, or decryption on a file or directory.
  For more information, see Creating and Opening Files,
  File Compression and Decompression, and File Encryption.
  Windows Server 2003 and Windows XP:
  For backward compatibility purposes, CreateFile does not
  apply inheritance rules when you specify a security
  descriptor in lpSecurityAttributes. To support inheritance,
  functions that later query the security descriptor
  of this file may heuristically determine and
  report that inheritance is in effect. For more information,
  see Automatic Propagation of Inheritable ACEs.
  As stated previously, if the lpSecurityAttributes
  parameter is NULL, the handle returned by CreateFile
  cannot be inherited by any child processes your
  application may create. The following information
  regarding this parameter also applies:
  If the bInheritHandle member variable is not FALSE,
  which is any nonzero value, then the handle
  can be inherited. Therefore it is critical this
  structure member be properly initialized to FALSE
  if you do not intend the handle to be inheritable.
  The access control lists (ACL) in the default
  security descriptor for a file or directory are inherited
  from its parent directory.
  The target file system must support security
  on files and directories for the lpSecurityDescriptor
  member to have an effect on them,
  which can be determined by using GetVolumeInformation.
  }}} */
);
// }}}
uint32_t GetFileType(// {{{
  HANDLE /* [in] hFile {{{
  A handle to the file.
  }}} */
  /* RETURN VALUE {{{
  The function returns one of the following values.
  ---
  0x0000 FILE_TYPE_UNKNOWN
  Either the type of the specified file
  is unknown, or the function failed.
  ---
  0x0001 FILE_TYPE_DISK
  The specified file is a disk file.
  ---
  0x0002 FILE_TYPE_CHAR
  The specified file is a character file,
  typically an LPT device or a console.
  ---
  0x0003 FILE_TYPE_PIPE
  The specified file is a socket, a named pipe,
  or an anonymous pipe.
  ---
  0x8000 FILE_TYPE_REMOTE
  Unused.
  ---
  You can distinguish between a "valid" return
  of FILE_TYPE_UNKNOWN and its return due to
  a calling error (for example, passing
  an invalid handle to GetFileType)
  by calling GetLastError.
  If the function worked properly and
  FILE_TYPE_UNKNOWN was returned,
  a call to GetLastError will return NO_ERROR.
  If the function returned FILE_TYPE_UNKNOWN
  due to an error in calling GetFileType,
  GetLastError will return the error code.
  }}} */
);
// }}}
int WriteFileEx(// {{{
  /* INFO {{{
  Writes data to the specified file or input/output
  (I/O) device. It reports its completion status
  asynchronously, calling the specified completion
  routine when writing is completed or canceled
  and the calling thread is in an alertable wait state.
  To write data to a file or device synchronously,
  use the WriteFile function.
  }}} */
  HANDLE, /* [in] hFile {{{
  A handle to the file or I/O device
  (for example, a file, file stream, physical disk,
  volume, console buffer, tape drive, socket,
  communications resource, mailslot, or pipe).
  This parameter can be any handle opened with
  the FILE_FLAG_OVERLAPPED flag by the CreateFile
  function, or a socket handle returned
  by the socket or accept function.
  Do not associate an I/O completion port
  with this handle. For more information,
  see the Remarks section.
  This handle also must have the GENERIC_WRITE
  access right. For more information on access rights,
  see File Security and Access Rights.
  }}} */
  void*, /* [in, optional] lpBuffer {{{
  A pointer to the buffer containing the data
  to be written to the file or device.
  This buffer must remain valid for the duration
  of the write operation. The caller must not
  use this buffer until the write operation
  is completed.
  }}} */
  uint32_t, /* [in] nNumberOfBytesToWrite {{{
  The number of bytes to be written
  to the file or device.
  A value of zero specifies a null write operation.
  The behavior of a null write operation
  depends on the underlying file system.
  Pipe write operations across a network
  are limited to 65,535 bytes per write.
  For more information regarding pipes,
  see the Remarks section.
  }}} */
  OVERLAPPED*, /* [in, out] lpOverlapped {{{
  A pointer to an OVERLAPPED data structure
  that supplies data to be used during
  the overlapped (asynchronous) write operation.
  For files that support byte offsets,
  you must specify a byte offset at which to start
  writing to the file. You specify this offset
  by setting the Offset and OffsetHigh members
  of the OVERLAPPED structure. For files or devices
  that do not support byte offsets,
  Offset and OffsetHigh are ignored.
  To write to the end of file, specify both
  the Offset and OffsetHigh members of the
  OVERLAPPED structure as 0xFFFFFFFF.
  This is functionally equivalent to
  previously calling the CreateFile function
  to open hFile using FILE_APPEND_DATA access.
  The WriteFileEx function ignores the OVERLAPPED
  structure's hEvent member.
  An application is free to use that member
  for its own purposes in the context of a
  WriteFileEx call. WriteFileEx signals completion
  of its writing operation by calling,
  or queuing a call to, the completion routine
  pointed to by lpCompletionRoutine,
  so it does not need an event handle.
  The WriteFileEx function does use
  the Internal and InternalHigh members of the
  OVERLAPPED structure. You should not change
  the value of these members.
  The OVERLAPPED data structure must remain valid
  for the duration of the write operation.
  It should not be a variable that can go out of scope
  while the write operation is pending completion.
  }}} */
  void(*f)(uint32_t,uint32_t,OVERLAPPED) /*  {{{
  A pointer to a completion routine to be called
  when the write operation has been completed and
  the calling thread is in an alertable wait state.
  ---
  void LpoverlappedCompletionRoutine(
    [in]      DWORD dwErrorCode,
    [in]      DWORD dwNumberOfBytesTransfered,
    [in, out] LPOVERLAPPED lpOverlapped
  )
  {...}
  ---
  The return value for an asynchronous operation
  is 0 (ERROR_SUCCESS) if the operation completed
  successfully or if the operation completed
  with a warning. To determine whether an I/O
  operation was completed successfully,
  check that dwErrorCode is 0, call
  GetOverlappedResult, then call GetLastError.
  For example, if the buffer was not large
  enough to receive all of the data from a call
  to ReadFileEx, dwErrorCode is set to 0,
  GetOverlappedResult fails, and GetLastError
  returns ERROR_MORE_DATA.
  ---
  Returning from this function allows another
  pending I/O completion routine to be called.
  All waiting completion routines are called
  before the alertable thread's wait is
  completed with a return code of WAIT_IO_COMPLETION.
  The system may call the waiting completion
  routines in any order. They may or may not
  be called in the order the I/O
  functions are completed.
  ---
  Each time the system calls a completion routine,
  it uses some of the application's stack.
  If the completion routine does additional
  asynchronous I/O and alertable waits,
  the stack may grow.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  If the WriteFileEx function succeeds,
  the calling thread has an asynchronous I/O
  operation pending: the overlapped write operation
  to the file. When this I/O operation finishes,
  and the calling thread is blocked
  in an alertable wait state,
  the operating system calls the function pointed to
  by lpCompletionRoutine, and the wait completes
  with a return code of WAIT_IO_COMPLETION.
  If the function succeeds and the file-writing
  operation finishes, but the calling thread
  is not in an alertable wait state,
  the system queues the call to *lpCompletionRoutine,
  holding the call until the calling thread enters
  an alertable wait state.
  For more information about alertable wait states
  and overlapped input/output operations,
  see About Synchronization.
  }}} */
  /* REMARKS {{{
  When using WriteFileEx you should check GetLastError
  even when the function returns "success"
  to check for conditions that are successes but
  have some outcome you might want to know about.
  For example, a buffer overflow when calling
  WriteFileEx will return TRUE,
  but GetLastError will report the overflow
  with ERROR_MORE_DATA.
  If the function call is successful and
  there are no warning conditions,
  GetLastError will return ERROR_SUCCESS.
  The WriteFileEx function will fail if the hFile
  parameter is associated with an I/O completion port.
  To perform writes using this type of handle,
  use the WriteFile function.
  The WriteFileEx function may fail if there are
  too many outstanding asynchronous I/O requests.
  In the event of such a failure,
  GetLastError can return ERROR_INVALID_USER_BUFFER
  or ERROR_NOT_ENOUGH_MEMORY.
  To cancel all pending asynchronous I/O
  operations, use either:

  * CancelIo: This function only cancels operations
  issued by the calling thread for the
  specified file handle.
  * CancelIoEx: This function cancels all operations
  issued by the threads for the
  specified file handle.

  Use CancelSynchronousIo to cancel pending
  synchronous I/O operations.
  I/O operations that are canceled complete
  with the error ERROR_OPERATION_ABORTED.

  If part of the file specified by hFile
  is locked by another process,
  and the specified write operation overlaps
  the locked portion, WriteFileEx fails.
  When writing to a file, the last write time
  is not fully updated until all handles used
  for writing have been closed.
  Therefore, to ensure an accurate last write time,
  close the file handle immediately
  after writing to the file.
  Accessing the output buffer while a write operation
  is using the buffer may lead to corruption
  of the data written from that buffer.
  Applications must not write to, reallocate,
  or free the output buffer that a write operation
  is using until the write operation completes.
  Note that the time stamps may not
  be updated correctly for a remote file.
  To ensure consistent results, use unbuffered I/O.
  The system interprets zero bytes to write
  as specifying a null write operation and
  WriteFile does not truncate or extend the file.
  To truncate or extend a file, use
  the SetEndOfFile function.
  An application uses the WaitForSingleObjectEx,
  WaitForMultipleObjectsEx, MsgWaitForMultipleObjectsEx,
  SignalObjectAndWait, and SleepEx functions
  to enter an alertable wait state.
  For more information about alertable wait states and
  overlapped I/O operations, see About Synchronization.
  If you write directly to a volume that has
  a mounted file system, you must first obtain
  exclusive access to the volume.
  Otherwise, you risk causing data corruption or
  system instability, because your application's
  writes may conflict with other changes coming
  from the file system and leave the contents of
  the volume in an inconsistent state.
  To prevent these problems,
  the following changes have been made in
  Windows Vista and later:

  A write on a volume handle will succeed
  if the volume does not have a mounted file system,
  or if one of the following conditions is true:

  - The sectors to be written to are boot sectors.
  - The sectors to be written to reside outside
  of file system space.
  - You have explicitly locked or dismounted
  the volume by using FSCTL_LOCK_VOLUME or
  FSCTL_DISMOUNT_VOLUME.
  - The volume has no actual file system.
  In other words, it has a RAW file system mounted.

  A write on a disk handle will succeed
  if one of the following conditions is true:

  - The sectors to be written to do not
  fall within a volume's extents.
  - The sectors to be written to fall within
  a mounted volume, but you have explicitly locked
  or dismounted the volume by using
  FSCTL_LOCK_VOLUME or FSCTL_DISMOUNT_VOLUME.
  - The sectors to be written to fall within
  a volume that has no mounted file system
  other than RAW.

  There are strict requirements for successfully
  working with files opened with CreateFile
  using FILE_FLAG_NO_BUFFERING.
  For details see File Buffering.
  }}} */
);
// }}}
int CancelIo(// {{{
  /* INFO {{{
  Cancels all pending input and output (I/O)
  operations that are issued by the calling
  thread for the specified file. The function
  does not cancel I/O operations that other
  threads issue for a file handle.
  To cancel I/O operations from another thread,
  use the CancelIoEx function.
  }}} */
  HANDLE /* hFile [in] {{{
  A handle to the file.
  The function cancels all pending I/O operations
  for this file handle.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  The cancel operation for all pending I/O operations
  issued by the calling thread for the specified
  file handle was successfully requested.
  The thread can use the GetOverlappedResult function
  to determine when the I/O operations themselves
  have been completed.
  If the function fails, the return value is zero (0).
  To get extended error information,
  call the GetLastError function.
  }}} */
  /* REMARKS {{{
  If there are any pending I/O operations
  in progress for the specified file handle,
  and they are issued by the calling thread,
  the CancelIo function cancels them.
  CancelIo cancels only outstanding I/O on the handle,
  it does not change the state of the handle;
  this means that you cannot rely on the state
  of the handle because you cannot know whether
  the operation was completed successfully or canceled.

  The I/O operations must be issued as overlapped I/O.
  If they are not, the I/O operations do not return
  to allow the thread to call the CancelIo function.
  Calling the CancelIo function with a file handle
  that is not opened with FILE_FLAG_OVERLAPPED
  does nothing.

  All I/O operations that are canceled
  complete with the error ERROR_OPERATION_ABORTED,
  and all completion notifications for
  the I/O operations occur normally.
  }}} */
);
// }}}
int CloseHandle(// {{{
  HANDLE /* [in] hObject {{{
  A valid handle to an open object.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  If the application is running under a debugger,
  the function will throw an exception if
  it receives either a handle value that is
  not valid or a pseudo-handle value.
  This can happen if you close a handle twice,
  or if you call CloseHandle on a handle returned
  by the FindFirstFile function instead of
  calling the FindClose function.
  }}} */
  /* REMARKS {{{
  The CloseHandle function closes handles
  to the following objects:
    Access token
    Communications device
    Console input
    Console screen buffer
    Event
    File
    File mapping
    I/O completion port
    Job
    Mailslot
    Memory resource notification
    Mutex
    Named pipe
    Pipe
    Process
    Semaphore
    Thread
    Transaction
    Waitable timer
  ---
  The documentation for the functions that create
  these objects indicates that CloseHandle should
  be used when you are finished with the object,
  and what happens to pending operations on the object
  after the handle is closed.
  In general, CloseHandle invalidates the specified
  object handle, decrements the object's handle count,
  and performs object retention checks.
  After the last handle to an object is closed,
  the object is removed from the system.
  For a summary of the creator functions
  for these objects, see Kernel Objects.
  Generally, an application should call CloseHandle once
  for each handle it opens. It is usually not necessary
  to call CloseHandle if a function that
  uses a handle fails with ERROR_INVALID_HANDLE,
  because this error usually indicates
  that the handle is already invalidated.
  However, some functions use ERROR_INVALID_HANDLE
  to indicate that the object itself is no longer valid.
  For example, a function that attempts to use a handle
  to a file on a network might fail with
  ERROR_INVALID_HANDLE if the network connection
  is severed, because the file object
  is no longer available.
  In this case, the application should close the handle.

  If a handle is transacted, all handles bound
  to a transaction should be closed before the transaction
  is committed. If a transacted handle was opened by
  calling CreateFileTransacted with the FILE_FLAG_DELETE_ON_CLOSE
  flag, the file is not deleted until the application
  closes the handle and calls CommitTransaction.
  For more information about transacted objects,
  see Working With Transactions.

  Closing a thread handle does not terminate the
  associated thread or remove the thread object.
  Closing a process handle does not terminate the
  associated process or remove the process object.
  To remove a thread object, you must terminate the thread,
  then close all handles to the thread.
  For more information, see Terminating a Thread.
  To remove a process object, you must terminate
  the process, then close all handles to the process.
  For more information, see Terminating a Process.

  Closing a handle to a file mapping can succeed
  even when there are file views that are still open.
  For more information, see Closing a File Mapping Object.

  Do not use the CloseHandle function to close a socket.
  Instead, use the closesocket function,
  which releases all resources associated with
  the socket including the handle to the socket object.
  For more information, see Socket Closure.

  Do not use the CloseHandle function to close a handle
  to an open registry key.
  Instead, use the RegCloseKey function.
  CloseHandle does not close the handle
  to the registry key, but does not return an error
  to indicate this failure.
  }}} */
);
// }}}
/// }}}
/// misc {{{
uint32_t GetLastError(// {{{
  /* RETURN VALUE {{{
  The return value is the calling thread's last-error code.
  The Return Value section of the documentation
  for each function that sets the last-error
  code notes the conditions under which
  the function sets the last-error code.
  Most functions that set the thread's last-error
  code set it when they fail. However,
  some functions also set the last-error code
  when they succeed. If the function is not documented
  to set the last-error code, the value returned
  by this function is simply the most recent
  last-error code to have been set;
  some functions set the last-error code to 0
  on success and others do not.
  Remarks
  Functions executed by the calling thread
  set this value by calling the SetLastError function.
  You should call the GetLastError function immediately
  when a function's return value indicates
  that such a call will return useful data.
  That is because some functions call SetLastError
  with a zero when they succeed,
  wiping out the error code set by
  the most recently failed function.

  To obtain an error string for system error codes,
  use the FormatMessage function.
  For a complete list of error codes provided
  by the operating system, see System Error Codes.

  The error codes returned by a function are not
  part of the Windows API specification and
  can vary by operating system or device driver.
  For this reason, we cannot provide the complete
  list of error codes that can be returned
  by each function. There are also many functions
  whose documentation does not include even
  a partial list of error codes that can be returned.

  Error codes are 32-bit values
  (bit 31 is the most significant bit).
  Bit 29 is reserved for application-defined error codes;
  no system error code has this bit set.
  If you are defining an error code for your application,
  set this bit to one.
  That indicates that the error code has been defined
  by an application, and ensures that your error code
  does not conflict with any error codes
  defined by the system.

  To convert a system error into an HRESULT value,
  use the HRESULT_FROM_WIN32 macro.
  }}} */
  /* REMARKS {{{
  Functions executed by the calling thread
  set this value by calling the SetLastError function.
  You should call the GetLastError function immediately
  when a function's return value indicates
  that such a call will return useful data.
  That is because some functions call SetLastError
  with a zero when they succeed,
  wiping out the error code set by
  the most recently failed function.

  To obtain an error string for system error codes,
  use the FormatMessage function.
  For a complete list of error codes provided
  by the operating system, see System Error Codes.

  The error codes returned by a function are not
  part of the Windows API specification and
  can vary by operating system or device driver.
  For this reason, we cannot provide the complete
  list of error codes that can be returned
  by each function. There are also many functions
  whose documentation does not include even
  a partial list of error codes that can be returned.

  Error codes are 32-bit values
  (bit 31 is the most significant bit).
  Bit 29 is reserved for application-defined error codes;
  no system error code has this bit set.
  If you are defining an error code for your application,
  set this bit to one.
  That indicates that the error code has been defined
  by an application, and ensures that your error code
  does not conflict with any error codes
  defined by the system.

  To convert a system error into an HRESULT value,
  use the HRESULT_FROM_WIN32 macro.
  }}} */
);
// }}}
void SetLastError(uint32_t);/* {{{
The last-error code is kept in thread local
storage so that multiple threads do not overwrite
each other's values.

Most functions call SetLastError or SetLastErrorEx
only when they fail. However, some system functions
call SetLastError or SetLastErrorEx under
conditions of success; those cases are noted in
each function's documentation.

Applications can optionally retrieve the value
set by this function by using the GetLastError
function immediately after a function fails.

Error codes are 32-bit values (bit 31 is the most
significant bit). Bit 29 is reserved for
application-defined error codes;
no system error code has this bit set.
If you are defining an error code for your application,
set this bit to indicate that the error code
has been defined by your application and
to ensure that your error code does not conflict
with any system-defined error codes.
}}} */
uint32_t FormatMessageW(// {{{
  /* INFO {{{
  Formats a message string.
  The function requires a message definition as input.
  The message definition can come from a buffer
  passed into the function. It can come from a message
  table resource in an already-loaded module.
  Or the caller can ask the function to search
  the system's message table resource(s) for
  the message definition. The function finds the message
  definition in a message table resource based on
  a message identifier and a language identifier.
  The function copies the formatted message text
  to an output buffer, processing any embedded insert
  sequences if requested.
  }}} */
  uint32_t,/* [in] dwFlags {{{
  The formatting options, and how to interpret
  the lpSource parameter. The low-order byte of dwFlags
  specifies how the function handles line breaks in the
  output buffer. The low-order byte can also specify
  the maximum width of a formatted output line.

  This parameter can be one or more of the following values.
  ---
  FORMAT_MESSAGE_ALLOCATE_BUFFER 0x00000100
  ---
  The function allocates a buffer large enough to hold the
  formatted message, and places a pointer to the allocated
  buffer at the address specified by lpBuffer.
  The lpBuffer parameter is a pointer to an LPTSTR;
  you must cast the pointer to an LPTSTR (for example,
  (LPTSTR)&lpBuffer). The nSize parameter specifies the
  minimum number of TCHARs to allocate for an output
  message buffer. The caller should use the LocalFree
  function to free the buffer when it is no longer needed.
  If the length of the formatted message exceeds 128K bytes,
  then FormatMessage will fail and a subsequent call to
  GetLastError will return ERROR_MORE_DATA.
  In previous versions of Windows, this value was not
  available for use when compiling Windows Store apps.
  As of Windows 10 this value can be used.
  Windows Server 2003 and Windows XP:
  If the length of the formatted message exceeds 128K bytes,
  then FormatMessage will not automatically fail with an
  error of ERROR_MORE_DATA.
  ---
  FORMAT_MESSAGE_ARGUMENT_ARRAY 0x00002000
  ---
  The Arguments parameter is not a va_list structure,
  but is a pointer to an array of values that represent
  the arguments.
  This flag cannot be used with 64-bit integer values.
  If you are using a 64-bit integer,
  you must use the va_list structure.
  ---
  FORMAT_MESSAGE_FROM_HMODULE 0x00000800
  ---
  The lpSource parameter is a module handle containing
  the message-table resource(s) to search. If this
  lpSource handle is NULL, the current process's
  application image file will be searched. This flag
  cannot be used with FORMAT_MESSAGE_FROM_STRING.
  If the module has no message table resource,
  the function fails with ERROR_RESOURCE_TYPE_NOT_FOUND.
  ---
  FORMAT_MESSAGE_FROM_STRING 0x00000400
  ----
  The lpSource parameter is a pointer to a null-terminated
  string that contains a message definition.
  The message definition may contain insert sequences,
  just as the message text in a message table resource may.
  This flag cannot be used with FORMAT_MESSAGE_FROM_HMODULE
  or FORMAT_MESSAGE_FROM_SYSTEM.
  ---
  FORMAT_MESSAGE_FROM_SYSTEM 0x00001000
  ---
  The function should search the system message-table
  resource(s) for the requested message. If this flag is
  specified with FORMAT_MESSAGE_FROM_HMODULE, the function
  searches the system message table if the message is not
  found in the module specified by lpSource.
  This flag cannot be used with FORMAT_MESSAGE_FROM_STRING.
  If this flag is specified, an application can pass the
  result of the GetLastError function to retrieve the
  message text for a system-defined error.
  ---
  FORMAT_MESSAGE_IGNORE_INSERTS 0x00000200
  ---
  Insert sequences in the message definition such as %1
  are to be ignored and passed through to the output buffer
  unchanged. This flag is useful for fetching a message
  for later formatting. If this flag is set, the Arguments
  parameter is ignored.

  The low-order byte of dwFlags can specify the maximum width
  of a formatted output line. The following are possible
  values of the low-order byte.
  ---
  0
  ---
  There are no output line width restrictions.
  The function stores line breaks that are in the message
  definition text into the output buffer.
  ---
  FORMAT_MESSAGE_MAX_WIDTH_MASK 0x000000FF
  ---
  The function ignores regular line breaks in the message
  definition text. The function stores hard-coded line
  breaks in the message definition text into the output
  buffer. The function generates no new line breaks.

  If the low-order byte is a nonzero value other than
  FORMAT_MESSAGE_MAX_WIDTH_MASK, it specifies the maximum
  number of characters in an output line. The function
  ignores regular line breaks in the message definition text.
  The function never splits a string delimited by white space
  across a line break. The function stores hard-coded
  line breaks in the message definition text into
  the output buffer. Hard-coded line breaks are coded with
  the %n escape sequence.
  }}} */
  void*,/* [in, optional] lpSource {{{
  The location of the message definition. The type of this
  parameter depends upon the settings in the dwFlags parameter.
  ---
  FORMAT_MESSAGE_FROM_HMODULE 0x00000800
  ---
  A handle to the module that contains
  the message table to search.
  ---
  FORMAT_MESSAGE_FROM_STRING 0x00000400
  ---
  Pointer to a string that consists of unformatted
  message text. It will be scanned for inserts and
  formatted accordingly.

  If neither of these flags is set in dwFlags,
  then lpSource is ignored.
  }}} */
  uint32_t,/* [in] dwMessageId {{{
  The message identifier for the requested message.
  This parameter is ignored if dwFlags includes
  FORMAT_MESSAGE_FROM_STRING.
  }}} */
  uint32_t,/* [in] dwLanguageId {{{
  The language identifier for the requested message.
  This parameter is ignored if dwFlags includes
  FORMAT_MESSAGE_FROM_STRING.
  If you pass a specific LANGID in this parameter,
  FormatMessage will return a message for that LANGID only.
  If the function cannot find a message for that LANGID,
  it sets Last-Error to ERROR_RESOURCE_LANG_NOT_FOUND.
  If you pass in zero, FormatMessage looks for a message
  for LANGIDs in the following order:
  - Language neutral
  - Thread LANGID, based on the thread's locale value
  - User default LANGID, based on the user's default locale value
  - System default LANGID, based on the system default locale value
  - US English
  If FormatMessage does not locate a message for any
  of the preceding LANGIDs, it returns any language message
  string that is present. If that fails,
  it returns ERROR_RESOURCE_LANG_NOT_FOUND.
  }}} */
  char*,/* [out] lpBuffer {{{
  A pointer to a buffer that receives the null-terminated
  string that specifies the formatted message. If dwFlags
  includes FORMAT_MESSAGE_ALLOCATE_BUFFER, the function
  allocates a buffer using the LocalAlloc function,
  and places the pointer to the buffer at the address
  specified in lpBuffer.
  This buffer cannot be larger than 64K bytes.
  }}} */
  uint32_t, /* [in] nSize {{{
  If the FORMAT_MESSAGE_ALLOCATE_BUFFER flag is not set,
  this parameter specifies the size of the output buffer,
  in TCHARs. If FORMAT_MESSAGE_ALLOCATE_BUFFER is set,
  this parameter specifies the minimum number of TCHARs
  to allocate for an output buffer.
  The output buffer cannot be larger than 64K bytes.
  }}} */
  va_list* /* [in, optional] Arguments {{{
  An array of values that are used as insert values in
  the formatted message. A %1 in the format string indicates
  the first value in the Arguments array;
  a %2 indicates the second argument; and so on.
  The interpretation of each value depends on the formatting
  information associated with the insert in
  the message definition. The default is to treat
  each value as a pointer to a null-terminated string.
  By default, the Arguments parameter is of type va_list*,
  which is a language- and implementation-specific data
  type for describing a variable number of arguments.
  The state of the va_list argument is undefined upon
  return from the function. To use the va_list again,
  destroy the variable argument list pointer using
  va_end and reinitialize it with va_start.

  If you do not have a pointer of type va_list*,
  then specify the FORMAT_MESSAGE_ARGUMENT_ARRAY flag
  and pass a pointer to an array of DWORD_PTR values;
  those values are input to the message formatted as
  the insert values. Each insert must have a
  corresponding element in the array.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is the
  number of TCHARs stored in the output buffer,
  excluding the terminating null character.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  Within the message text, several escape sequences are
  supported for dynamically formatting the message.
  These escape sequences and their meanings are shown
  in the following tables. All escape sequences start
  with the percent character (%).
  ---
  Security Remarks
  If this function is called without FORMAT_MESSAGE_IGNORE_INSERTS,
  the Arguments parameter must contain enough parameters
  to satisfy all insertion sequences in the message string,
  and they must be of the correct type. Therefore,
  do not use untrusted or unknown message strings with inserts
  enabled because they can contain more insertion sequences
  than Arguments provides, or those that may be of
  the wrong type. In particular, it is unsafe to take an
  arbitrary system error code returned from an API and
  use FORMAT_MESSAGE_FROM_SYSTEM without
  FORMAT_MESSAGE_IGNORE_INSERTS.
  }}} */
);
// }}}
int WideCharToMultiByte(// {{{
  /* INFO {{{
  Maps a UTF-16 (wide character) string to a new character string.
  The new character string is not necessarily
  from a multibyte character set.

  Caution
  ---
  Using the WideCharToMultiByte function incorrectly
  can compromise the security of your application.
  Calling this function can easily cause a buffer overrun
  because the size of the input buffer indicated by lpWideCharStr
  equals the number of characters in the Unicode string,
  while the size of the output buffer indicated by
  lpMultiByteStr equals the number of bytes.
  To avoid a buffer overrun, your application must specify
  a buffer size appropriate for the data type
  the buffer receives.

  Data converted from UTF-16 to non-Unicode encodings
  is subject to data loss, because a code page might not
  be able to represent every character used in the specific
  Unicode data. For more information,
  see Security Considerations: International Features.

  Note
  ---
  The ANSI code pages can be different on different
  computers, or can be changed for a single computer,
  leading to data corruption. For the most consistent
  results, applications should use Unicode, such as
  UTF-8 or UTF-16, instead of a specific code page,
  unless legacy standards or data formats prevent
  the use of Unicode. If using Unicode is not possible,
  applications should tag the data stream with the
  appropriate encoding name when protocols allow it.
  HTML and XML files allow tagging, but text files do not.
  }}} */
  unsigned int,/* [in] CodePage {{{
  Code page to use in performing the conversion.
  This parameter can be set to the value of any code page
  that is installed or available in the operating system.
  For a list of code pages, see Code Page Identifiers.
  Your application can also specify one of the values
  shown in the following table.
  ---
  CP_ACP
  ---
  The system default Windows ANSI code page.
  Note  This value can be different on different computers,
  even on the same network. It can be changed on the same
  computer, leading to stored data becoming irrecoverably
  corrupted. This value is only intended for temporary
  use and permanent storage should use UTF-16 or UTF-8 if possible.
  ---
  CP_MACCP 2
  ---
  The current system Macintosh code page.
  This value can be different on different computers,
  even on the same network. It can be changed on the
  same computer, leading to stored data becoming irrecoverably
  corrupted. This value is only intended for temporary use
  and permanent storage should use UTF-16 or UTF-8 if possible.
  This value is used primarily in legacy code and
  should not generally be needed since modern Macintosh computers
  use Unicode for encoding.
  ---
  CP_OEMCP 1
  ---
  The current system OEM code page.
  This value can be different on different computers,
  even on the same network. It can be changed on the same computer,
  leading to stored data becoming irrecoverably corrupted.
  This value is only intended for temporary use and
  permanent storage should use UTF-16 or UTF-8 if possible.
  ---
  CP_SYMBOL 42
  ---
  Windows 2000: Symbol code page (42).
  ---
  CP_THREAD_ACP 3
  ---
  Windows 2000: The Windows ANSI code page for the current thread.
  This value can be different on different computers,
  even on the same network. It can be changed on the same computer,
  leading to stored data becoming irrecoverably corrupted.
  This value is only intended for temporary use and
  permanent storage should use UTF-16 or UTF-8 if possible.
  ---
  CP_UTF7 65000
  ---
  UTF-7. Use this value only when forced by a 7-bit
  transport mechanism. Use of UTF-8 is preferred.
  With this value set, lpDefaultChar and lpUsedDefaultChar
  must be set to NULL.
  ---
  CP_UTF8 65001
  ---
  UTF-8. With this value set, lpDefaultChar and
  lpUsedDefaultChar must be set to NULL.
  }}} */
  uint32_t,/* [in] dwFlags {{{
  Flags indicating the conversion type. The application can
  specify a combination of the following values.
  The function performs more quickly when none of
  these flags is set. The application should specify
  WC_NO_BEST_FIT_CHARS and WC_COMPOSITECHECK with
  the specific value WC_DEFAULTCHAR to retrieve all
  possible conversion results. If all three values are
  not provided, some results will be missing.
  ---
  WC_COMPOSITECHECK
  ---
  Convert composite characters, consisting of a base
  character and a nonspacing character, each with
  different character values. Translate these
  characters to precomposed characters, which have a
  single character value for a base-nonspacing character
  combination. For example, in the character è, the e
  is the base character and the accent grave mark is
  the nonspacing character.
  Windows normally represents Unicode strings with
  precomposed data, making the use of the
  WC_COMPOSITECHECK flag unnecessary.
  Your application can combine WC_COMPOSITECHECK
  with any one of the following flags, with the
  default being WC_SEPCHARS. These flags determine
  the behavior of the function when no precomposed
  mapping for a base-nonspacing character combination
  in a Unicode string is available. If none of these
  flags is supplied, the function behaves as if the
  WC_SEPCHARS flag is set. For more information,
  see WC_COMPOSITECHECK and related flags in the
  Remarks section.
  ---
  WC_DEFAULTCHAR
  ---
  Replace exceptions with the default character
  during conversion.
  ---
  WC_DISCARDNS
  ---
  Discard nonspacing characters during conversion.
  ---
  WC_SEPCHARS
  ---
  Default. Generate separate characters during conversion.
  ---
  WC_ERR_INVALID_CHARS
  ---
  Windows Vista and later:
  Fail (by returning 0 and setting the last-error
  code to ERROR_NO_UNICODE_TRANSLATION) if an invalid
  input character is encountered. You can retrieve
  the last-error code with a call to GetLastError.
  If this flag is not set, the function replaces
  illegal sequences with U+FFFD (encoded as
  appropriate for the specified codepage) and
  succeeds by returning the length of the
  converted string. Note that this flag only
  applies when CodePage is specified as CP_UTF8 or
  54936. It cannot be used with other code
  page values.
  ---
  WC_NO_BEST_FIT_CHARS
  ---
  Translate any Unicode characters that do not
  translate directly to multibyte equivalents to
  the default character specified by lpDefaultChar.
  In other words, if translating from Unicode to
  multibyte and back to Unicode again does not
  yield the same Unicode character, the function
  uses the default character. This flag can be
  used by itself or in combination with the other
  defined flags.
  For strings that require validation, such as file,
  resource, and user names, the application should
  always use the WC_NO_BEST_FIT_CHARS flag.
  This flag prevents the function from mapping
  characters to characters that appear similar but
  have very different semantics. In some cases,
  the semantic change can be extreme. For example,
  the symbol for "∞" (infinity) maps to 8 (eight)
  in some code pages.

  For the code pages listed below, dwFlags must be 0.
  Otherwise, the function fails with ERROR_INVALID_FLAGS.

    50220 50221 50222 50225 50227 50229
    57002 through 57011
    65000 (UTF-7) 42 (Symbol)

  For the code page 65001 (UTF-8) or the code page 54936
  (GB18030, Windows Vista and later), dwFlags must be
  set to either 0 or WC_ERR_INVALID_CHARS. Otherwise,
  the function fails with ERROR_INVALID_FLAGS.
  }}} */
  char*,/* [in] lpWideCharStr {{{

    Pointer to the Unicode string to convert.

  }}} */
  int,/* [in] cchWideChar {{{
  Size, in characters, of the string indicated by
  lpWideCharStr. Alternatively, this parameter
  can be set to -1 if the string is null-terminated.
  If cchWideChar is set to 0, the function fails.
  If this parameter is -1, the function processes
  the entire input string, including the terminating
  null character. Therefore, the resulting character
  string has a terminating null character,
  and the length returned by the function includes
  this character.
  If this parameter is set to a positive integer,
  the function processes exactly the specified number
  of characters. If the provided size does not include
  a terminating null character, the resulting character
  string is not null-terminated, and the returned
  length does not include this character.
  }}} */
  char*,/* [out, optional] lpMultiByteStr {{{
  Pointer to a buffer that receives the converted string.
  }}} */
  int,/* [in] cbMultiByte {{{
  Size, in bytes, of the buffer indicated by lpMultiByteStr.
  If this value is 0, the function returns the required
  buffer size, in bytes, including any terminating
  null character, and makes no use of the
  lpMultiByteStr buffer.
  }}} */
  char*,/* [in, optional] lpDefaultChar {{{
  Pointer to the character to use if a character cannot be
  represented in the specified code page.
  The application sets this parameter to NULL
  if the function is to use a system default value.
  To obtain the system default character,
  the application can call the GetCPInfo or
  GetCPInfoEx function.
  For the CP_UTF7 and CP_UTF8 settings for CodePage,
  this parameter must be set to NULL. Otherwise,
  the function fails with ERROR_INVALID_PARAMETER.
  }}} */
  int* /* [out, optional] lpUsedDefaultChar {{{
  Pointer to a flag that indicates if the function
  has used a default character in the conversion.
  The flag is set to TRUE if one or more characters
  in the source string cannot be represented in
  the specified code page. Otherwise, the flag is
  set to FALSE. This parameter can be set to NULL.
  For the CP_UTF7 and CP_UTF8 settings for CodePage,
  this parameter must be set to NULL. Otherwise,
  the function fails with ERROR_INVALID_PARAMETER.
  }}} */
  /* RETURN VALUE {{{
  If successful, returns the number of bytes written
  to the buffer pointed to by lpMultiByteStr.
  If the function succeeds and cbMultiByte is 0,
  the return value is the required size,
  in bytes, for the buffer indicated by lpMultiByteStr.
  Also see dwFlags for info about how the
  WC_ERR_INVALID_CHARS flag affects the return value
  when invalid sequences are input.
  The function returns 0 if it does not succeed.
  To get extended error information,
  the application can call GetLastError,
  which can return one of the following error codes:
  - ERROR_INSUFFICIENT_BUFFER.
  A supplied buffer size was not large enough,
  or it was incorrectly set to NULL.
  - ERROR_INVALID_FLAGS.
  The values supplied for flags were not valid.
  - ERROR_INVALID_PARAMETER.
  Any of the parameter values was invalid.
  - ERROR_NO_UNICODE_TRANSLATION.
  Invalid Unicode was found in a string.
  }}} */
  /* REMARKS {{{
  The lpMultiByteStr and lpWideCharStr pointers
  must not be the same. If they are the same,
  the function fails, and GetLastError returns
  ERROR_INVALID_PARAMETER.
  WideCharToMultiByte does not null-terminate
  an output string if the input string length is
  explicitly specified without a terminating null
  character. To null-terminate an output string
  for this function, the application should pass
  in -1 or explicitly count the terminating null
  character for the input string.
  If cbMultiByte is less than cchWideChar,
  this function writes the number of characters
  specified by cbMultiByte to the buffer indicated
  by lpMultiByteStr. However, if CodePage is set
  to CP_SYMBOL and cbMultiByte is less than
  cchWideChar, the function writes no characters
  to lpMultiByteStr.
  The WideCharToMultiByte function operates most
  efficiently when both lpDefaultChar and
  lpUsedDefaultChar are set to NULL.
  The following table shows the behavior of the
  function for the four possible combinations of
  these parameters.
  ===
  lpDefaultChar/lpUsedDefaultChar/Result
  ---
  NULL/NULL/
  No default checking. These parameter settings are
  the most efficient ones for use with this function.
  ---
  Non-null character/NULL/
  Uses the specified default character,
  but does not set lpUsedDefaultChar.
  ---
  NULL/Non-null character/
  Uses the system default character and sets
  lpUsedDefaultChar if necessary.
  ---
  Non-null character/Non-null character/
  Uses the specified default character and
  sets lpUsedDefaultChar if necessary.
  ===
  Starting with Windows Vista, this function fully
  conforms with the Unicode 4.1 specification for UTF-8 and
  UTF-16. The function used on earlier operating systems
  encodes or decodes lone surrogate halves or mismatched
  surrogate pairs. Code written in earlier versions of
  Windows that rely on this behavior to encode random
  non-text binary data might run into problems.
  However, code that uses this function to produce valid
  UTF-8 strings will behave the same way as on earlier
  Windows operating systems.

  Starting with Windows 8: WideCharToMultiByte is declared
  in Stringapiset.h. Before Windows 8, it was declared
  in Winnls.h.

  WC_COMPOSITECHECK and related flags
  ---
  As discussed in Using Unicode Normalization to Represent Strings,
  Unicode allows multiple representations of the same
  string (interpreted linguistically). For example,
  Capital A with dieresis (umlaut) can be represented either
  precomposed as a single Unicode code point "Ä" (U+00C4)
  or decomposed as the combination of Capital A and the
  combining dieresis character ("A" + "¨", that is U+0041 U+0308).
  However, most code pages provide only composed characters.
  The WC_COMPOSITECHECK flag causes the WideCharToMultiByte
  function to test for decomposed Unicode characters and
  attempts to compose them before converting them to
  the requested code page. This flag is only available
  for conversion to single byte (SBCS) or double byte
  (DBCS) code pages (code pages < 50000, excluding code page 42).
  If your application needs to convert decomposed Unicode
  data to single byte or double byte code pages,
  this flag might be useful. However, not all characters
  can be converted this way and it is more reliable to
  save and store such data as Unicode.

  When an application is using WC_COMPOSITECHECK,
  some character combinations might remain incomplete or
  might have additional nonspacing characters left over.
  For example, A + ¨ + ¨ combines to Ä + ¨.
  Using the WC_DISCARDNS flag causes the function to
  discard additional nonspacing characters.
  Using the WC_DEFAULTCHAR flag causes the function to
  use the default replacement character (typically "?") instead.
  Using the WC_SEPCHARS flag causes the function to
  attempt to convert each additional
  nonspacing character to the target code page.
  Usually this flag also causes the use of the replacement
  character ("?"). However, for code page 1258 (Vietnamese)
  and 20269, nonspacing characters exist and can be used.
  The conversions for these code pages are not perfect.
  Some combinations do not convert correctly to code page 1258,
  and WC_COMPOSITECHECK corrupts data in code page 20269.
  As mentioned earlier, it is more reliable to design
  your application to save and store such data as Unicode.
  }}} */
);
// }}}
HANDLE OpenProcess(// {{{
  uint32_t,/* [in] dwDesiredAccess {{{
  The access to the process object. This access right
  is checked against the security descriptor for the process.
  This parameter can be one or more
  of the process access rights.
  If the caller has enabled the SeDebugPrivilege privilege,
  the requested access is granted regardless
  of the contents of the security descriptor.
  ---
  The Microsoft Windows security model enables
  you to control access to process objects.
  When a user logs in, the system collects
  a set of data that uniquely identifies the user
  during the authentication process,
  and stores it in an access token.
  This access token describes the security
  context of all processes associated with
  the user. The security context of a process
  is the set of credentials given to the process
  or the user account that created the process.
  You can use a token to specify the current
  security context for a process using the
  CreateProcessWithTokenW function.
  You can specify a security descriptor for
  a process when you call the CreateProcess,
  CreateProcessAsUser, or CreateProcessWithLogonW function.
  If you specify NULL, the process gets a default
  security descriptor. The ACLs in the default
  security descriptor for a process come
  from the primary or impersonation token of
  the creator.To retrieve a process’s security
  descriptor, call the GetSecurityInfo function.
  To change a process’s security descriptor,
  call the SetSecurityInfo function.
  The valid access rights for process objects
  include the standard access rights and some
  process-specific access rights.
  ---
  PROCESS_ALL_ACCESS (0x1fffff)
  All possible access rights for a process object.
  ---
  PROCESS_CREATE_PROCESS (0x0080)
  Required to create a process.
  ---
  PROCESS_CREATE_THREAD (0x0002)
  Required to create a thread.
  ---
  PROCESS_DUP_HANDLE (0x0040)
  Required to duplicate a handle using DuplicateHandle.
  ---
  PROCESS_QUERY_INFORMATION (0x0400)
  Required to retrieve certain information
  about a process, such as its token,
  exit code, and priority class (see OpenProcessToken).
  ---
  PROCESS_QUERY_LIMITED_INFORMATION (0x1000)
  Required to retrieve certain information
  about a process. A handle that has the
  PROCESS_QUERY_INFORMATION access right is
  automatically granted PROCESS_QUERY_LIMITED_INFORMATION.
  ---
  PROCESS_SET_INFORMATION (0x0200)
  Required to set certain information
  about a process, such as its priority class
  (see SetPriorityClass).
  ---
  PROCESS_SET_QUOTA (0x0100)
  Required to set memory limits using
  SetProcessWorkingSetSize.
  ---
  PROCESS_SUSPEND_RESUME (0x0800)
  Required to suspend or resume a process.
  ---
  PROCESS_TERMINATE (0x0001)
  Required to terminate a process using
  TerminateProcess.
  ---
  PROCESS_VM_OPERATION (0x0008)
  Required to perform an operation on the
  address space of a process
  ---
  PROCESS_VM_READ (0x0010)
  Required to read memory in a process
  using ReadProcessMemory.
  ---
  PROCESS_VM_WRITE (0x0020)
  Required to write to memory in a process
  using WriteProcessMemory.
  ---
  SYNCHRONIZE (0x00100000L)
  Required to wait for the process to
  terminate using the wait functions.
  }}} */
  int,/* [in] bInheritHandle {{{
  If this value is TRUE, processes created by this process
  will inherit the handle. Otherwise,
  the processes do not inherit this handle.
  }}} */
  uint32_t /* [in] dwProcessId {{{
  The identifier of the local process to be opened.
  If the specified process is the System Idle Process
  (0x00000000), the function fails and
  the last error code is ERROR_INVALID_PARAMETER.
  If the specified process is the System process or
  one of the Client Server Run-Time Subsystem
  (CSRSS) processes, this function fails and
  the last error code is ERROR_ACCESS_DENIED
  because their access restrictions prevent
  user-level code from opening them.
  If you are using GetCurrentProcessId as an argument
  to this function, consider using GetCurrentProcess
  instead of OpenProcess, for improved performance.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value
  is an open handle to the specified process.
  If the function fails, the return value is NULL.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  To open a handle to another local process and
  obtain full access rights, you must enable
  the SeDebugPrivilege privilege. For more information,
  see Changing Privileges in a Token.
  The handle returned by the OpenProcess function
  can be used in any function that requires a handle
  to a process, such as the wait functions,
  provided the appropriate access rights were requested.
  When you are finished with the handle,
  be sure to close it using the CloseHandle function.
  }}} */
);
// }}}
int TerminateProcess(// {{{
  HANDLE, /* [in] hProcess {{{
  A handle to the process to be terminated.
  The handle must have the PROCESS_TERMINATE
  access right.
  }}} */
  UINT /* [in] uExitCode {{{
  The exit code to be used by the process and
  threads terminated as a result of this call.
  Use the GetExitCodeProcess function to retrieve
  a process's exit value. Use the GetExitCodeThread
  function to retrieve a thread's exit value.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  The TerminateProcess function is used to
  unconditionally cause a process to exit.
  The state of global data maintained by
  dynamic-link libraries (DLLs) may be compromised
  if TerminateProcess is used rather than ExitProcess.

  This function stops execution of all threads
  within the process and requests cancellation
  of all pending I/O. The terminated process cannot
  exit until all pending I/O has been
  completed or canceled. When a process terminates,
  its kernel object is not destroyed until
  all processes that have open handles to the process
  have released those handles.

  When a process terminates itself,
  TerminateProcess stops execution of the calling
  thread and does not return. Otherwise,
  TerminateProcess is asynchronous;
  it initiates termination and returns immediately.
  If you need to be sure the process has terminated,
  call the WaitForSingleObject function
  with a handle to the process.

  A process cannot prevent itself from being terminated.
  After a process has terminated,
  call to TerminateProcess with open handles
  to the process fails with ERROR_ACCESS_DENIED (5)
  error code.
  }}} */
);
// }}}
int GetExitCodeProcess(// {{{
  HANDLE, /* [in]  hProcess {{{
  A handle to the process.
  The handle must have the PROCESS_QUERY_INFORMATION
  or PROCESS_QUERY_LIMITED_INFORMATION access right.
  }}}*/
  void* /* [out] lpExitCode {{{
  A pointer to a variable to receive the process
  termination status. For more information, see Remarks.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds,
  the return value is nonzero.
  If the function fails, the return value is zero.
  To get extended error information,
  call GetLastError.
  }}} */
  /* REMARKS {{{
  This function returns immediately.
  If the process has not terminated and
  the function succeeds, the status returned is
  STILL_ACTIVE (a macro for STATUS_PENDING
  (minwinbase.h)). If the process has terminated
  and the function succeeds, the status returned
  is one of the following values:
  - The exit value specified in the ExitProcess or
  TerminateProcess function.
  - The return value from the main or WinMain
  function of the process.
  - The exception value for an unhandled exception
  that caused the process to terminate.
  ===
  The GetExitCodeProcess function returns
  a valid error code defined by the application
  only after the thread terminates. Therefore,
  an application should not use STILL_ACTIVE (259)
  as an error code (STILL_ACTIVE is a macro for
  STATUS_PENDING (minwinbase.h)). If a thread returns
  STILL_ACTIVE (259) as an error code,
  then applications that test for that value could
  interpret it to mean that the thread is still running,
  and continue to test for the completion
  of the thread after the thread has terminated,
  which could put the application into
  an infinite loop.
  }}} */
);
// }}}
uint32_t K32GetProcessImageFileNameA(// {{{
  HANDLE,/* [in] hProcess {{{
  A handle to the process. The handle must have
  the PROCESS_QUERY_INFORMATION or
  PROCESS_QUERY_LIMITED_INFORMATION access right.
  For more information, see Process Security and
  Access Rights.
  Windows Server 2003 and Windows XP:
  The handle must have the
  PROCESS_QUERY_INFORMATION access right.
  }}} */
  char*,/* [out] lpImageFileName {{{
  A pointer to a buffer that receives the full path
  to the executable file.
  }}} */
  uint32_t /* [in] nSize {{{
  The size of the lpImageFileName buffer,
  in characters.
  }}} */
  /* RETURN VALUE {{{
  If the function succeeds, the return value specifies
  the length of the string copied to the buffer.
  If the function fails, the return value is zero.
  To get extended error information, call GetLastError.
  }}} */
  /* REMARKS {{{
  The file Psapi.dll is installed in the %windir%\System32
  directory. If there is another copy of this DLL
  on your computer, it can lead to the following error
  when running applications on your system:
  "The procedure entry point GetProcessImageFileName
  could not be located in the dynamic link library
  PSAPI.DLL." To work around this problem,
  locate any versions that are not in the
  %windir%\System32 directory and delete or
  rename them, then restart.
  The GetProcessImageFileName function returns
  the path in device form, rather than drive letters.
  For example, the file name
  C:\Windows\System32\Ctype.nls would look as
  follows in device form:
  \Device\Harddisk0\Partition1\Windows\System32\Ctype.nls
  ---
  To retrieve the module name of the current process,
  use the GetModuleFileName function with a NULL
  module handle. This is more efficient than calling
  the GetProcessImageFileName function with a handle
  to the current process.
  To retrieve the name of the main executable module
  for a remote process in win32 path format,
  use the QueryFullProcessImageName function.
  Starting with Windows 7 and Windows Server 2008 R2,
  Psapi.h establishes version numbers for the PSAPI
  functions. The PSAPI version number affects
  the name used to call the function and the library
  that a program must load.
  If PSAPI_VERSION is 2 or greater, this function
  is defined as K32GetProcessImageFileName in Psapi.h
  and exported in Kernel32.lib and Kernel32.dll.
  If PSAPI_VERSION is 1, this function is defined
  as GetProcessImageFileName in Psapi.h and exported
  in Psapi.lib and Psapi.dll as a wrapper that calls
  K32GetProcessImageFileName.
  Programs that must run on earlier versions of Windows
  as well as Windows 7 and later versions should
  always call this function as GetProcessImageFileName.
  To ensure correct resolution of symbols,
  add Psapi.lib to the TARGETLIBS macro and compile
  the program with -DPSAPI_VERSION=1.
  To use run-time dynamic linking, load Psapi.dll.
  }}} */
);
// }}}
uint32_t SleepEx(// {{{
  uint32_t, /* [in] dwMilliseconds {{{
  The time interval for which execution is to be
  suspended, in milliseconds.
  A value of zero causes the thread to relinquish
  the remainder of its time slice to any other
  thread that is ready to run. If there are
  no other threads ready to run, the function
  returns immediately, and the thread continues
  execution. Windows XP: A value of zero causes
  the thread to relinquish the remainder of its time
  slice to any other thread of equal priority that is
  ready to run. If there are no other threads of equal
  priority ready to run, the function returns immediately,
  and the thread continues execution.
  This behavior changed starting with Windows Server 2003.
  A value of INFINITE indicates that the suspension
  should not time out.
  }}} */
  int /* [in] bAlertable {{{
  If this parameter is FALSE, the function does not
  return until the time-out period has elapsed.
  If an I/O completion callback occurs,
  the function does not immediately return and the I/O
  completion function is not executed.
  If an APC is queued to the thread,
  the function does not immediately return and
  the APC function is not executed.
  ---
  If the parameter is TRUE and the thread that
  called this function is the same thread that
  called the extended I/O function (ReadFileEx or
  WriteFileEx), the function returns when either
  the time-out period has elapsed or when an I/O
  completion callback function occurs.
  If an I/O completion callback occurs, the I/O
  completion function is called.
  If an APC is queued to the thread (QueueUserAPC),
  the function returns when either the time-out
  period has elapsed or when the APC function is called.
  }}} */
  /* RETURN VALUE {{{
  The return value is zero if the specified
  time interval expired.
  The return value is WAIT_IO_COMPLETION
  if the function returned due to one or more I/O
  completion callback functions.
  This can happen only if bAlertable is TRUE,
  and if the thread that called the SleepEx
  function is the same thread that called
  the extended I/O function.
  }}} */
  /* REMARKS {{{
  This function causes a thread to relinquish
  the remainder of its time slice and become unrunnable
  for an interval based on the value of dwMilliseconds.
  After the sleep interval has passed,
  the thread is ready to run. Note that a ready thread
  is not guaranteed to run immediately.
  Consequently, the thread will not run until
  some arbitrary time after the sleep interval elapses,
  based upon the system "tick" frequency and
  the load factor from other processes.
  The system clock "ticks" at a constant rate.
  To increase the accuracy of the sleep interval,
  call the timeGetDevCaps function to determine
  the supported minimum timer resolution and
  the timeBeginPeriod function to set the timer
  resolution to its minimum.
  Use caution when calling timeBeginPeriod,
  as frequent calls can significantly affect
  the system clock, system power usage, and the scheduler.
  If you call timeBeginPeriod, call it one time early
  in the application and be sure to call
  the timeEndPeriod function at the very end
  of the application. If you specify 0 milliseconds,
  the thread will relinquish the remainder of its
  time slice but remain ready. For more information,
  see Scheduling Priorities.
  ---
  This function can be used with the ReadFileEx or
  WriteFileEx functions to suspend a thread until
  an I/O operation has been completed.
  These functions specify a completion routine that is
  to be executed when the I/O operation has been completed.
  For the completion routine to be executed,
  the thread that called the I/O function must be
  in an alertable wait state when
  the completion callback function occurs.
  A thread goes into an alertable wait state
  by calling either SleepEx, MsgWaitForMultipleObjectsEx,
  WaitForSingleObjectEx, or WaitForMultipleObjectsEx,
  with the function's bAlertable parameter set to TRUE.
  ---
  Be careful when using SleepEx in the following scenarios:
  - Code that directly or indirectly creates windows
  (for example, DDE and COM CoInitialize).
  If a thread creates any windows, it must process messages.
  Message broadcasts are sent to all windows in the system.
  If you have a thread that uses SleepEx with infinite delay,
  the system will deadlock.
  - Threads that are under concurrency control.
  For example, an I/O completion port or thread pool limits
  the number of associated threads that can run.
  If the maximum number of threads is already running,
  no additional associated thread can run until
  a running thread finishes. If a thread uses SleepEx
  with an interval of zero to wait for one of the
  additional associated threads to accomplish some work,
  the process might deadlock.
  ---
  For these scenarios, use MsgWaitForMultipleObjects or
  MsgWaitForMultipleObjectsEx, rather than SleepEx.
  ---
  Windows Phone 8.1: This function is supported for
  Windows Phone Store apps on Windows Phone 8.1 and later.
  Windows 8.1 and Windows Server 2012 R2:
  This function is supported for Windows Store apps
  on Windows 8.1, Windows Server 2012 R2, and later.
  }}} */
);
// }}}
/// }}}

// vim: fdm=marker ts=2 sw=2 sts=2 nu:
