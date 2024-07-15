<?php declare(strict_types=1);
# defs {{{
namespace SM;
#extension_loaded('php_ds') || dl('php_ds.dll');
use
  Throwable,Ds\Vector,Ds\Deque,FFI,
  SplFixedArray,SplDoublyLinkedList,SplObjectStorage;
use function
  mb_ord,mb_chr;
###
require_once
__DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
###
# }}}
echo "&ref vs ->noref"; # {{{
$n = 100000000;
echo "\nn=$n\n";
$obj = (new class {public int $test=1;});
###
echo "> ref(1): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($z = &$obj->pending)
  {
    if ($j > $z) {
      $j = $z;
    }
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
unset($z);
###
echo "> noref(1): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($obj->test)
  {
    if ($j > $obj->test) {
      $j = $obj->test;
    }
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> noref+var(1): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($z = $obj->test)
  {
    if ($j > $z) {
      $j = $z;
    }
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "f(array) vs f(?array & !==)"; # {{{
$n = 100000000;
echo "\nn=$n\n";
$f1 = (function(int $i, array $cfg):int {
  if ($cfg) {$i++;}
  else {$i--;}
  return $i;
});
$f2 = (function(int $i, ?array $cfg):int {
  if ($cfg !== null) {$i++;}
  else {$i--;}
  return $i;
});
$a = [];
$b = null;
$c = [1,2,3];
###
echo "> -f(array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $f1($i, $a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> -f(?array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $f2($i, $b);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> +f(array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $f1($i, $c);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> +f(?array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $f2($i, $c);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "expansive replacement: array_splice vs Ds\Deque vs SplDoublyLinkedList"; # {{{
$n = 10000000;
echo "\nn=".$n."\n";
$m = 15;
$a = [1];
$b = [1,2,3];
$o1 = new Deque($a);
$o2 = new Deque($b);
$d1 = new SplDoublyLinkedList();
$d1->push(1);
$d2 = new SplDoublyLinkedList();
foreach ($b as $i) {
  $d2->push($i);
}
$d2->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO);
###
echo "> array_splice: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  array_splice($a, 0, 1, $b);
  if (++$j > $m) {$j = 0;$a = [1];}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Deque: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $o1->shift();
  $o1->unshift(...$o2);
  if (++$j > $m)
  {
    $j = 0;
    $o1 = new Deque([1]);
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> SplDoublyLinkedList(for): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $d1->shift();
  for ($k=$d2->count() - 1; $k >= 0; --$k) {
    $d1->unshift($d2->offsetGet($k));
  }
  if (++$j > $m)
  {
    $j = 0;
    $d1 = new SplDoublyLinkedList();
    $d1->push(1);
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> SplDoublyLinkedList(while): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $d1->shift();
  $k = $d2->count();
  while (--$k >= 0) {
    $d1->unshift($d2->offsetGet($k));
  }
  if (++$j > $m)
  {
    $j = 0;
    $d1 = new SplDoublyLinkedList();
    $d1->push(1);
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> SplDoublyLinkedList(foreach): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  foreach ($d2 as $value) {
    $d1->unshift($value);
  }
  if (++$j > $m)
  {
    $j = 0;
    $d1 = new SplDoublyLinkedList();
    $d1->push(1);
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "shift/unshift: array vs Ds\Deque vs SplDoublyLinkedList"; # {{{
$n = 10000000;
echo "\nn=$n\n";
$j = 10;
for ($a=[],$i=0; $i < $j; ++$i) {
  $a[] = '1234567890';
}
for ($o=new Deque(),$i=0; $i < $j; ++$i) {
  $o[] = '1234567890';
}
for ($d=new SplDoublyLinkedList(),$i=0; $i < $j; ++$i) {
  $d[] = '1234567890';
}
###
echo "> array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($j > 10) {array_shift($a);$j--;}
  else {array_unshift($a, '123');$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Deque: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($j > 10) {$o->shift();$j--;}
  else {$o->unshift('123');$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> SplDoublyLinkedList: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($j > 10) {$d->shift();$j--;}
  else {$d->unshift('123');$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "push: array vs Ds\Vector vs Ds\Deque vs SplDoublyLinkedList"; # {{{
$n = 10000000;
echo "\nn=$n\n";
###
echo "> array[]: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a = [];
  for ($j; $j < 1000; ++$j) {
    $a[] = '1234567890';
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Vector::push: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $o = new Vector();
  for ($j; $j < 1000; ++$j) {
    $o->push('1234567890');
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Vector[]: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $o = new Vector();
  for ($j; $j < 1000; ++$j) {
    $o[] = '1234567890';
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> array_push: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a = [];
  for ($j; $j < 1000; ++$j) {
    array_push($a, '1234567890');
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Deque::push: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $o = new Deque();
  for ($j; $j < 1000; ++$j) {
    $o->push('1234567890');
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> SplDoublyLinkedList::push: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $o = new SplDoublyLinkedList();
  for ($j; $j < 1000; ++$j) {
    $o->push('1234567890');
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "mb_chr VS u8chr"; # {{{
echo "\n\n";
$n = 1000000;
class CharTest
{
  static object $map;
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
    if ($cp > 0xD7FF && $cp < 0xE000) {
      return '';# incorrect code point
    }
    if ($cp < 0x80) {# ASCII
      return $map[$cp];
      #return chr($cp);
    }
    if ($cp < 0x800)
    {
      return
        $map[0xC0 | ($cp >> 6)].
        $map[0x80 | ($cp & 0x3F)];
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
  static function u8chr2(int $cp): string # {{{
  {
    static $map = # {{{
      "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F".
      "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F".
      "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2A\x2B\x2C\x2D\x2E\x2F".
      "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3A\x3B\x3C\x3D\x3E\x3F".
      "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4A\x4B\x4C\x4D\x4E\x4F".
      "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5A\x5B\x5C\x5D\x5E\x5F".
      "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6A\x6B\x6C\x6D\x6E\x6F".
      "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7A\x7B\x7C\x7D\x7E\x7F".
      "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F".
      "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F".
      "\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF".
      "\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF".
      "\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF".
      "\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF".
      "\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF".
      "\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";
    # }}}
    if ($cp > 0xD7FF && $cp < 0xE000) {
      return '';# incorrect code point
    }
    if ($cp < 0x80) {# ASCII
      return $map[$cp];
      #return chr($cp);
    }
    if ($cp < 0x800)
    {
      return
        $map[0xC0 | ($cp >> 6)].
        $map[0x80 | ($cp & 0x3F)];
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
  static function u8chr3(int $cp): string # {{{
  {
    if ($cp > 0xD7FF && $cp < 0xE000) {
      return '';# incorrect code point
    }
    if ($cp < 0x80) {# ASCII
      return chr($cp);
    }
    if ($cp < 0x800)
    {
      return
        chr(0xC0 | ($cp >> 6)).
        chr(0x80 | ($cp & 0x3F));
    }
    if ($cp < 0x10000)
    {
      return
        chr(0xE0 | ($cp >> 12)).
        chr(0x80 | (($cp >> 6) & 0x3F)).
        chr(0x80 | ($cp & 0x3f));
    }
    return
      chr(0xF0 | ($cp >> 18)).
      chr(0x80 | (($cp >> 12) & 0x3f)).
      chr(0x80 | (($cp >> 6) & 0x3f)).
      chr(0x80 | ($cp & 0x3f));
  }
  # }}}
  static function mbchr(int $cp): string # {{{
  {
    return mb_chr($cp);
  }
  # }}}
  # test methods {{{
  static function test1(array $a): string
  {
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= mb_chr($a[$i]);
    }
    return $s;
  }
  static function test1w(array $a): string
  {
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= self::mbchr($a[$i]);
    }
    return $s;
  }
  static function test2(array $a): string
  {
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= self::u8chr($a[$i]);
    }
    return $s;
  }
  static function test3(array $a): string
  {
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= self::u8chr3($a[$i]);
    }
    return $s;
  }
  static function test1x(array $a): string
  {
    $f = mb_chr(...);
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= $f($a[$i]);
    }
    return $s;
  }
  static function test2x(array $a): string
  {
    $f = self::u8chr(...);
    for ($s='',$i=0,$j=count($a); $i < $j; ++$i) {
      $s .= $f($a[$i]);
    }
    return $s;
  }
  # }}}
}
$a = [];
$b = "Салями алейкуми ╠╦╣ чебурексан!";
#$b = "Hello my friend ╠╦╣ Be like water!";
$b = mb_convert_encoding($b, 'UTF-16LE', 'UTF-8');
for ($i=0,$j=strlen($b); $i < $j; $i+=2) {
  $a[] = mb_ord($b[$i].$b[$i+1], 'UTF-16LE');
}
$a[] = 0x2603;
#var_dump(CharTest::test1($a));
#var_dump(CharTest::test2($a));
#exit;
###
###
echo "> mb_chr: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test1($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> mb_chr + wrap: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test1w($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> u8chr + array map: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test2($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> u8chr + chr(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test3($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> dynamic mb_chr: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test1x($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> dynamic u8chr + array map: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  CharTest::test2x($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
###
exit(0);
# }}}
echo "if VS ()()"; # {{{
echo "\n\n";
class BaseThing
{
  public object $func;
  function __construct(public int $async) {
    $this->func = ($async ? $this->exec0(...) : $this->exec1(...));
  }
  function checkCall(): int {
    return $this->async ? $this->exec0() : $this->exec1();
  }
  function exec0(): int {return 123;}
  function exec1(): int {return 321;}
}
$n = 100000000;
$a = new BaseThing(0);
$b = new BaseThing(1);
###
###
echo "> if(0): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a->checkCall();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> (0)(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  ($a->func)();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> 0(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a->exec0();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
echo "> if(1): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $b->checkCall();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> (1)(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  ($b->func)();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> 1(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $b->exec1();
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "f(5) VS f(9)"; # {{{
echo "\n\n";
$n = 100000000;
$a = (static function(
  int $i1,int $i2,int $i3,int $i4,int $i5
):int {
  return $i1+$i2+$i3+$i4+$i5;
});
$b = (static function(
  int $i1,int $i2,int $i3,int $i4,int $i5,
  int $i6,int $i7,int $i8,int $i9
):int {
  return $i1+$i2+$i3+$i4+$i5;
});
###
###
echo "> f(5): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a($i,2,3,4,5);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> f(7): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $b($i,2,3,4,5,6,7,8,9);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "[]= VS [last]="; # {{{
echo "\n\n";
$n = 100000000;
$a = [];
$b = [1,2,3];
###
###
echo "> []=: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a[] = $i;
  if (++$j > 100)
  {
    $a = [];
    $j = 0;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> [last]=: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $a[$j] = $i;
  if (++$j > 100)
  {
    $a = [];
    $j = 0;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "null VS []"; # {{{
echo "\n\n";
$n = 100000000;
$a = [[1,2,3],null];
$b = [[1,2,3],[]];
###
###
echo "> null: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($a[$j]) {$j++;}
  else {$j--;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> []: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b[$j]) {$j++;}
  else {$j--;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "empty(array) VS !array"; # {{{
echo "\n\n";
$n = 100000000;
$a = [1,2,3];
$b = [];
###
###
echo "> A: empty(array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (empty($a)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: !array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!$a) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: empty(array): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (empty($b)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: !array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!$b) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "truthy VS ===1"; # {{{
echo "\n\n";
$n = 100000000;
$a = 1;
$b = 0;
###
###
echo "> A: truthy: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($a) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: ===1: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($a === 1) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: truthy: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: ===1: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b === 1) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "boolval() VS !!"; # {{{
echo "\n\n";
$n = 10000000;
$a = [1,2,3];
$b = [];
###
###
echo "> A: boolval(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (boolval($a)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: !!: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!!$a) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: boolval(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (boolval($b)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: !!: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!!$b) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "[e0,e1] = a; VS e0=a[0];e1=a[1]"; # {{{
echo "\n\n";
$n = 10000000;
class PoopObject {
  const MAP=['item' => [1,2]];
}
$o = new PoopObject();
$k = 'item';
###
###
echo "> [e0,e1]=[..]: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  [$e0,$e1] = $o::MAP[$k];
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> e0=[.],e1=[.]: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $e0 = $o::MAP[$k][0];
  $e1 = $o::MAP[$k][1];
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "object property shortcut?"; # {{{
echo "\n\n";
$n = 1000000;
class ObjPropShortcut {
  public array $prop=[1,2,3,4,5];
}
$o = new ObjPropShortcut();
$k = 4;
###
###
echo "> without shortcut: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  for ($k=0; $k < 5; ++$k) {
    $o->prop[$k] = $i + 1;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> with shortcut: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $v = &$o->prop;
  for ($k=0; $k < 5; ++$k) {
    $v[$k] = $i + 1;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "check for empty string"; # {{{
echo "\n\n";
$n = 100000000;
$a = '';
$b = 'not empty';
###
###
echo "> A: !not: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!$a) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: ==='': ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($a === '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: =='': ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($a == '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: empty: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (empty($a)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: strlen===0: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (strlen($a) === 0) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: !not: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (!$b) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: ==='': ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b === '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: =='': ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b == '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: empty: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (empty($b)) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: strlen===0: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (strlen($b) === 0) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "substr+=== vs isset+substr"; # {{{
echo "\n\n";
$n = 100000000;
$a = '.is.path.to.something';
$b = '.';
###
###
echo "> A: substr+===: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (($c = \substr($a, 1)) === '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> A: isset+substr: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (isset($a[1])) {
    $c = \substr($a, 1);
  }
  else {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: substr+===: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (($c = \substr($b, 1)) === '') {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> B: isset+substr: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (isset($b[1])) {
    $c = \substr($b, 1);
  }
  else {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "truthy/falsy: array vs int vs bool vs string vs object"; # {{{
echo "\n\n";
$n = 100000000;
###
$a = [0,1,2,3,4,5,6,7,8,9];
$b = [];
$x = &$a;
echo "> array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($x) {$x = &$b;}
  else    {$x = &$a;}
  #if ($a) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> int: ";
$int1 = 123;
$int2 = 0;
$x = &$int1;
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($x) {$x = &$int2;}
  else    {$x = &$int1;}
  #if ($int1) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> bool: ";
$bool1 = true;
$bool2 = false;
$x = &$bool1;
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($x) {$x = &$bool2;}
  else    {$x = &$bool1;}
  #if ($bool1) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> string: ";
$str1 = 'something';
$str2 = '';
$x = &$str1;
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($x) {$x = &$str2;}
  else    {$x = &$str1;}
  #if ($str1) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> object/null: ";
$o1 = (object)['key'=>123];
$o2 = null;
$x = &$o1;
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($x) {$x = &$o2;}
  else    {$x = &$o1;}
  #if ($o1) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "non-empty array vs Deque"; # {{{
echo "\n\n";
$n = 10000000;
$a = [1,2,3];
$b = new Deque([4,5,6]);
###
echo "> array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (count($a)) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> Deque: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($b->count()) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "hashmap foreach vs do..while"; # {{{
echo "\n\n";
$n = 10000;
for ($a=[],$i=0; $i < 1000; ++$i) {
  $a['c'.rand(1000,9999).'-'.rand(1000,9999)] = rand();
}
###
echo "> foreach: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  foreach($a as $k => $v) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> foreach (&): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  foreach($a as $k => &$v) {
    $j++;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> do..while: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  $v = reset($a);
  do
  {
    $k = key($a);
    $j++;
  }
  while (($v = next($a)) !== false);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "unset vs array_pop"; # {{{
echo "\n\n";
$n = 4000000;
###
echo "> unset";
$a = array_fill(0, $n, [1,2,3]);
echo "(): ";
$t = hrtime(true);
for ($i=0,$j=$n; $i < $n; ++$i)
{
  unset($a[--$j]);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "> array_pop";
$a = array_fill(0, $n, [1,2,3]);
echo "(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  \array_pop($a);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "signal vs long sleep blocking";# {{{
echo "\n\n";
$n = 0;
if (function_exists($f = 'sapi_windows_set_ctrl_handler'))
{
  # WinOS
  $f(function (int $e) use (&$n) {
    if ($e === PHP_WINDOWS_EVENT_CTRL_C) {
      echo "[CTRL+C]";
    }
    else {
      echo "[CTRL+BREAK]";
    }
    exit($n = 1);
  });
}
else
{
  # NixOS
  # ...
}
echo "> sleep: ";
sleep(10);
echo "[".$n."]\n";
exit(0);
# }}}
echo "is_array vs is_object"; # {{{
echo "\n\n";
$n = 100000000;
$a = [1,2,3];
$o = (object)$a;
###
echo "(true) is_array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (is_array($a)) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "(true) is_object: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (is_object($o)) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "(false) is_array: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (is_array($o)) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "(false) is_object: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if (is_object($a)) {$j++;}
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
###
exit(0);
# }}}
echo "packed foreach vs for"; # {{{
echo "\n\n";
$n = 1000;
$a = array_fill(0, 100000, 1);
###
echo "(big) foreach: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  foreach ($a as $b => $c) {
    $j += $c;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "(big) for: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  for ($b=0,$c=count($a); $b < $c; ++$b) {
    $j += $a[$b];
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
$n = 1000000;
$a = array_fill(0, 100, 1);
###
echo "(small) foreach: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  foreach ($a as $b => $c) {
    $j += $c;
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "(small) for: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  for ($b=0,$c=count($a); $b < $c; ++$b) {
    $j += $a[$b];
  }
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
exit(0);
# }}}
echo "const:: vs instanceof"; # {{{
echo "\n\n";
abstract class Revers {
  const REVERSIBLE=false;
}
class StasisNotReversible {
  public int $i=0;
}
class StasisFalse extends Revers {
  public int $i=0;
}
class StasisTrue extends Revers
{
  const REVERSIBLE=true;
  public int $i=0;
}
$o1 = new StasisFalse();
$o2 = new StasisTrue();
$o3 = new StasisNotReversible();
$n = 100000000;
###
echo "const:: of false: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($o1::REVERSIBLE) {$j++;}
}
echo Fx::hrtime_delta_ms($t).'ms, '.(($i===$j)?'1':'0')."\n";
###
echo "instanceof false: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($o3 instanceof Revers) {$j++;}
}
echo Fx::hrtime_delta_ms($t).'ms, '.(($i===$j)?'1':'0')."\n";
###
echo "const:: of true: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($o2::REVERSIBLE) {$j++;}
}
echo Fx::hrtime_delta_ms($t).'ms, '.(($i===$j)?'1':'0')."\n";
###
echo "instanceof true: ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i)
{
  if ($o1 instanceof Revers) {$j++;}
}
echo Fx::hrtime_delta_ms($t).'ms, '.(($i===$j)?'1':'0')."\n";
###
exit(0);
# }}}
echo "__invoke() performance"; # {{{
echo "\n\n";
class Test
{
  function method(int $i): int {return $i + 1;}
  function __invoke(int $i): int {return $i + 1;}
}
$o = new Test();
$x = null;
$n = 10000000;
###
echo "calling \$o(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i) {
  $j = $o($i);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "calling \$o->method(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i) {
  $j = $o->method($i);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
echo "calling \$o->__invoke(): ";
$t = hrtime(true);
for ($i=0,$j=0; $i < $n; ++$i) {
  $j = $o->__invoke($i);
}
echo Fx::hrtime_delta_ms($t)."ms\n";
###
exit(0);
# }}}
###
