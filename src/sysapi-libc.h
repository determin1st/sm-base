#define FFI_LIB "libc.so.6"

char **environ;
extern int errno;
// structs {{{
struct pollfd // {{{
{
  int fd;/* File descriptor to poll.  */
  short int events;/* Types of events poller cares about.  */
  short int revents;/* Types of events that actually occurred.  */
};
// }}}
struct termios // {{{
{
  uint32_t c_iflag,c_oflag,c_cflag,c_lflag;
  uint8_t c_cc[20];
  uint32_t __ispeed,__ospeed;
};
// }}}
struct winsize // TIOCGWINSZ,TIOCSWINSZ {{{
{
  unsigned short int ws_row;/* Rows, in characters.  */
  unsigned short int ws_col;/* Columns, in characters.  */

  /* These are not actually used.  */
  unsigned short int ws_xpixel;/* Horizontal pixels.  */
  unsigned short int ws_ypixel;/* Vertical pixels.  */
};
// }}}
struct kbkeycode // KDGETKEYCODE,KDSETKEYCODE  {{{
{
  unsigned int scancode,keycode;
};
// }}}
// }}}

//int *__errno_location();
uintptr_t strerror_r(int,void*,int);// GNU?
int ptsname_r(int,void*,int);
int ttyname_r(int,void*,size_t);
int open(void*,int);
int ioctl(int,uint32_t,void*);
ssize_t read(int,void*,size_t);
ssize_t write(int,void*,size_t);
int fcntl(int,int,...);
int poll(struct pollfd*,uint32_t,int);
int close(int);
int tcgetattr(int,struct termios*);
void cfmakeraw(struct termios*);
int tcsetattr(int,int,struct termios*);
int tcflush(int,int);

int sched_yield();
int posix_spawn(void*,char*, void*,void*, char**,char**);


/* {{{
}}} */
// vim: fdm=marker ts=2 sw=2 sts=2 nu:
