<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
$t = hrtime(true);
if ($e = Conio::init())
{
  echo "\n".ErrorLog::render($e);
  exit(1);
}
$t = Fx::hrtime_delta_ms($t);
echo <<<TEXT
 ▄▄▄▄▄▄▄▄▄▄▄  ▄▄        ▄  ▄▄▄▄▄▄▄▄▄▄▄  ▄▄▄▄▄▄▄▄▄▄▄ 
▐░░░░░░░░░░░▌▐░░▌      ▐░▌▐░░░░░░░░░░░▌▐░░░░░░░░░░░▌
 ▀▀▀▀█░█▀▀▀▀ ▐░▌░▌     ▐░▌▐░█▀▀▀▀▀▀▀▀▀ ▐░█▀▀▀▀▀▀▀█░▌
     ▐░▌     ▐░▌▐░▌    ▐░▌▐░▌          ▐░▌       ▐░▌
     ▐░▌     ▐░▌ ▐░▌   ▐░▌▐░█▄▄▄▄▄▄▄▄▄ ▐░▌       ▐░▌
     ▐░▌     ▐░▌  ▐░▌  ▐░▌▐░░░░░░░░░░░▌▐░▌       ▐░▌
     ▐░▌     ▐░▌   ▐░▌ ▐░▌▐░█▀▀▀▀▀▀▀▀▀ ▐░▌       ▐░▌
     ▐░▌     ▐░▌    ▐░▌▐░▌▐░▌          ▐░▌       ▐░▌
 ▄▄▄▄█░█▄▄▄▄ ▐░▌     ▐░▐░▌▐░▌          ▐░█▄▄▄▄▄▄▄█░▌
▐░░░░░░░░░░░▌▐░▌      ▐░░▌▐░▌          ▐░░░░░░░░░░░▌
 ▀▀▀▀▀▀▀▀▀▀▀  ▀        ▀▀  ▀            ▀▀▀▀▀▀▀▀▀▀▀ 
TEXT;
echo "\nInitialized in ".$t."ms";
echo "\nOS: ".php_uname("s")." ".php_uname("v")."\n";
###
show_info();
input_cycle();
###
echo "\n> exit\n\n";
exit(0);
###
function input_cycle() # {{{
{
  $prompt = true;
  while (1)
  {
    if ($prompt)
    {
      echo "\n> Conio::read()\n";
      echo ">> type [0] to show OS specific terminal attributes..\n";
      echo ">> type [1] to read characters..\n";
      echo ">> type [2] to test parser errors..\n";
      echo ">> type [3] to test event decomposition..\n";
      echo ">> type [CTRL+Q] to quit: ";
      $prompt = false;
    }
    if (!($r = await(Conio::read()))->ok)
    {
      echo "\n".ErrorLog::render($r);
      break;
    }
    foreach ($r->value as $e)
    {
      switch ($e[0]) {
      case Conio::EV_KEY:# {{{
        # display {{{
        $k = $e[2];
        $m = $e[3];
        if ($k)
        {
          $s = Conio::K[$k];
          if ($m)
          {
            if (($m & Conio::KM_SHIFT) &&
                $k !== Conio::K_SHIFT)
            {
              $s = 'SHIFT+'.$s;
            }
            if (($m & Conio::KM_ALT) &&
                $k !== Conio::K_MENU)
            {
              $s = 'ALT+'.$s;
            }
            if (($m & Conio::KM_CTRL) &&
                $k !== Conio::K_CONTROL)
            {
              $s = 'CTRL+'.$s;
            }
            if ($m & Conio::KM_META) {
              $s = 'META+'.$s;
            }
          }
          echo '['.$e[1].'*'.$s."]";
        }
        else
        {
          echo '{'.$e[1].'*'.$e[4].'}';
        }
        # }}}
        # handle
        if ($k === Conio::K_Q && ($m & Conio::KM_CTRL)) {
          break 3;
        }
        $e[1] && !$m && $prompt = match ($k) {
          Conio::K_0 => show_info_sio(),
          Conio::K_1 => test_readch(),
          Conio::K_2 => test_parser(),
          Conio::K_3 => test_event_timeout(),
          default => false
        };
        break;
        # }}}
      case Conio::EV_MOUSE:# {{{
        $k = $e[1];
        $s = isset(Conio::M[$k])
          ? Conio::M[$k]
          : '?';
        ###
        if ($m = $e[2])
        {
          if ($m & Conio::KM_SHIFT) {
            $s = 'SHIFT+'.$s;
          }
          if ($m & Conio::KM_ALT) {
            $s = 'ALT+'.$s;
          }
          if ($m & Conio::KM_CTRL) {
            $s = 'CTRL+'.$s;
          }
        }
        # add coordinates
        $s .= '@'.$e[3].':'.$e[4];
        echo '<'.$s.'>';
        break;
        # }}}
      case Conio::EV_FOCUS:# {{{
        $s = $e[1] ? 'IN' : 'OUT';
        echo "<FOCUS-".$s.">";
        break;
        # }}}
      case Conio::EV_RESIZE:# {{{
        $s = (
          '['.implode(',', $e[2]).']→'.
          '['.implode(',', $e[1]).']'
        );
        echo "<RESIZE:".$s.">";
        break;
        # }}}
      case Conio::EV_SCROLL:# {{{
        $s = (
          '['.implode(',', $e[2]).']→'.
          '['.implode(',', $e[1]).']'
        );
        echo "<SCROLL:".$s.">";
        break;
        # }}}
      default:
        echo '?';
        var_dump($e);
        break;
      }
    }
  }
}
# }}}
function show_info() # {{{
{
  $m = Mustache::new();
  echo $m->prepare("
Conio
::id()          => {{0}}
::is_ansi()     => {{1}} (ESC sequences)
::has_colors()  => {{2}} (bits, 0/3/4/8/24)
::has_8bits()   => {{3}} (8-bit responses)
::is_unicode()  => {{4}} (UTF-8 output)
::is_mouse()    => {{^5}}no{{|1}}limited{{|}}good{{/}} (mouse tracking)
::is_keyboard() => {{^6}}poor{{|1}}limited{{|}}good{{/}} (support level)
::is_async()    => {{7}} (asynchronous writing)
::dev_src()     => {{8}}
::dev_attr()    => {{9}} (DA1 response)
::dev_id()      => {{10}} (terminal emulator variant)
::is_focused()  => {{11}}
::get_size()    => {{12.0}}:{{12.1}} (columns:rows)
  ", [
    Conio::id(),
    Conio::is_ansi(),
    Conio::has_colors(),
    Conio::has_8bits(),
    Conio::is_unicode(),
    Conio::is_mouse(),
    Conio::is_keyboard(),
    Conio::is_async(),
    Conio::dev_src(),
    Conio::dev_attr(),
    Conio::dev_id(),
    Conio::is_focused(),
    Conio::get_size()
  ]);
}
# }}}
function show_info_sio():bool # {{{
{
  $B  = Conio::$GEAR->base;
  $m0 = $B->mode['sio'];
  $m1 = $B->sio;
  echo "\n\nOS specific terminal attributes/flags:";
  if (\PHP_OS_FAMILY === 'Windows')
  {
    echo "\n> GetConsoleMode";
    echo "\n>> initial input: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[0], 0));
    echo "\n>> applied input: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[0], 0));
    echo "\n>> ---";
    echo "\n>> initial output: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[1], 1));
    echo "\n>> applied output: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[1], 1));
    echo "\n>> ---";
  }
  else
  {
    echo "\n>> initial input: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[0], 0));
    echo "\n>> applied input: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[0], 0));
    echo "\n>> ---";
    echo "\n>> initial output: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[1], 1));
    echo "\n>> applied output: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[1], 1));
    echo "\n>> ---";
    echo "\n>> initial control: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[2], 2));
    echo "\n>> applied control: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[2], 2));
    echo "\n>> ---";
    echo "\n>> initial local: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m0[3], 3));
    echo "\n>> applied local: ";
    echo "\n>>> ".implode("\n>>> ", $B::flagnames($m1[3], 3));
    echo "\n>> ---";
    echo "\n>> initial control characters: ";
    echo "\n>>>";
    echo " VMIN=".$m0[4][$B::VMIN];
    echo " VTIME=".$m0[4][$B::VTIME];
    echo "\n>> applied control characters: ";
    echo "\n>>>";
    echo " VMIN=".$m1[4][$B::VMIN];
    echo " VTIME=".$m1[4][$B::VTIME];
    echo "\n>> ---";
  }
  echo "\n";
  return true;
}
# }}}
function test_readch():bool # {{{
{
  echo "\n\n> Conio::readch()";
  echo "\n>> type {q} to quit: ";
  while (1)
  {
    $r = await(Conio::readch());
    if (!$r->ok)
    {
      echo "\n".ErrorLog::render($r);
      break;
    }
    echo "{".$r->value."}";
    if ($r->value === 'q') {
      break;
    }
  }
  return true;
}
# }}}
function test_parser():bool # {{{
{
  echo "\n\n> testing parser errors..\n";
  try
  {
    parse8_error(
      "incomplete 8-bit control",
      "hello\x9B", 5
    );
    parse8_error(
      "incorrect SS3 control.+oversized parameter",
      "\x8F1234c"
    );
    parse8_error(
      "incomplete SS3 control",
      "\x8F123", 0
    );
    parse8_error(
      "incorrect SS3 control.+incorrect final byte",
      "\x8F123\x1B"
    );
    parse8_error(
      "incorrect CSI control.+oversized parameter",
      "\x9B1234567890;1234567890;1234567890~"
    );
    parse8_error(
      "incomplete CSI control",
      "12\x9B1;2;3", 2
    );
    parse8_error(
      "incorrect CSI control.+incorrect byte",
      "12\x9B1;2;3++"
    );
    parse8_error(
      "incomplete control string",
      "\x90controlstring", 0
    );
    parse8_error(
      "incorrect control string",
      "\x90control\x00string\x9C"
    );
    parse8_error(
      "unknown 8-bit control",
      "\x81abc"
    );
    parse8_error(
      "incomplete UTF-8\\[2\\] character",
      "12\xC3", 2
    );
    parse8_error(
      "incorrect UTF-8\\[2\\] byte",
      "12\xC3hello"
    );
    parse8_error(
      "incomplete UTF-8\\[3\\] character",
      "12\xE1\x89", 2
    );
    parse8_error(
      "incorrect UTF-8\\[3\\] byte",
      "12\xE1\x89hello"
    );
    parse8_error(
      "incomplete UTF-8\\[4\\] character",
      "12\xF1", 2
    );
    parse8_error(
      "incorrect UTF-8\\[4\\] byte",
      "12\xF1hello"
    );
    parse8_error(
      "incorrect input byte",
      "12\xF834"
    );
    parse8_error(
      "incomplete X10 mouse event",
      "\x9BM12", 0
    );
    parse8_error(
      "incorrect X10 mouse coordinate",
      "\x9BM\x00\x21\x20"
    );
  }
  catch (\Throwable) {
    echo "\n> test failed\n";
  }
  return true;
}
# }}}
function test_event_timeout():bool # {{{
{
  echo "\n\n> sleeping 10 seconds..";
  $n = Conio::$GEAR->base->inputTimeout;
  $n = (int)($n / 1000000000);
  echo <<<TEXT

>> INFO
>>> when application does not read input events
>>> for $n seconds, they are automatically decomposed.
>>> this does not happen for resize/scroll and focus events.
>> TEST 1
>>> generate any key/mouse input.
>>> it must not appear after this timeout expires.
>> TEST 2
>>> resize screen to new dimensions.
>>> new size must be reported.
>> TEST 3
>>> unfocus the screen but leave it visible.
>>> focus out event must appear after timeouts.
>> TEST 4
>>> resize/scroll/focus debouncing is based on
>>> the previous state, when it is not changed
>>> between application reads - event does not dispatch.
>>> focus out and in, resize to a new size and back,
>>> events wont appear after timeouts.

TEXT;
  ###
  await(sleep(10000));
  echo "> sleeping ".$n." seconds.. (these events will appear)";
  await(sleep($n*1000));
  echo "\n";
  return true;
}
# }}}
function parse8_error($e, $s, $end=-1): void # {{{
{
  echo '>> '.$e.'.. ';
  $B = Conio::$GEAR->base;
  $a = $B->input;
  $n = strlen($s);
  if (~$end)
  {
    if ($B->parse8($s, $n, 0)  !== $end ||
        $B->parse8($s, $n, $n) !== -1)
    {
      echo "fail (passed)\n";
      throw ErrorEx::skip();
    }
  }
  elseif ($B->parse8($s, $n, 0) !== -1)
  {
    echo "fail (passed)\n";
    throw ErrorEx::skip();
  }
  $err = array_pop($B->input)[1];
  if (!preg_match("/$e/s", $err->message()))
  {
    echo "fail (not matched)\n";
    echo ErrorLog::render($err)."\n";
    throw ErrorEx::skip();
  }
  $B->input = $a;
  echo "ok\n";
}
# }}}
function measure_write_time(int $n=1000*1000) # {{{
{
  echo "\n> writing ".$n." bytes.. ";
  await(Conio::drain());
  $s = str_repeat('*', $n);
  $t = hrtime(true);
  echo $s;
  $t0 = Fx::hrtime_delta_ms($t);
  $t  = hrtime(true);
  await(Conio::drain());
  $t1 = Fx::hrtime_delta_ms($t);
  $s = " complete in ".$t0."ms (echo) + ".$t1."ms (drain)\n\n";
  echo $s;
  await(Conio::drain());
}
# }}}
function kb_mode_info(int $mode): array # {{{
{
  return match($mode) {
    0 => ['K_RAW','Raw (scancode) mode'],
    1 => ['K_XLATE','Translate keycodes using keymap'],
    2 => ['K_MEDIUMRAW','Medium raw (scancode) mode'],
    3 => ['K_UNICODE','Unicode mode'],
    4 => ['K_OFF','Disabled mode; since Linux 2.6.39'],
    default => ['UNKNOWN='.$mode,'?']
  };
}
# }}}
function ansi_colors(int $bits=8): array # {{{
{
  $a = [];
  for ($i=0; $i < 256; ++$i)
  {
    $a[] = "\x1B[48;5;".$i."m[".$i."]\x1B[0m";
    #$a[] = "\x1B[48;2;".$i.";0;0m \x1B[0m";
  }
  return $a;
}
# }}}
###
