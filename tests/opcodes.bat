@echo off
:: opcache.opt_debug_level
:: 0x10000: Output OPCodes prior to optimizations.
:: 0x20000: Output OPCodes After optimizations.
:: 0x40000: Output OPCodes with Context-Free Grammar
:: 0x200000: Output OPCodes with Static Single Assignments forms.

:: watch out for (get rid of):
:: FETCH_CONSTANT ~ use constants
:: INIT_NS_FCALL_BY_NAME ~ use functions

set PHP=php -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x20000 -f
set PHPASM=php -d opcache.enable_cli=1 -d opcache.opt_debug_level=0x20000 -d opcache.jit_debug=0xFFFFFFFF -f
set DST=%CD%\__opcode
cd "%CD%\..\src"
mkdir "%DST%" 2> nul

%PHP% conio.php 2> "%DST%\conio.opcode"
%PHP% error.php 2> "%DST%\error.opcode"
%PHP% functions.php 2> "%DST%\functions.opcode"
%PHP% hurl.php 2> "%DST%\hurl.opcode"
%PHP% mustache.php 2> "%DST%\mustache.opcode"
%PHP% process.php 2> "%DST%\process.opcode"
%PHP% promise.php 2> "%DST%\promise.opcode"
%PHP% sync.php 2> "%DST%\sync.opcode"
%PHP% sysapi.php 2> "%DST%\sysapi.opcode"

::%PHPASM% sync.php 2> "%DST%\sync.asm"

exit
