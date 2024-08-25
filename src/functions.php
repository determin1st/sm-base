<?php declare(strict_types=1);
# defs {{{
namespace SM;
use Throwable;
use function
  substr,strlen,ord,dechex,strtoupper,bin2hex,
  clearstatcache,file_exists,unlink,touch,
  hrtime,getmypid;
use const
  DIRECTORY_SEPARATOR;
###
require_once __DIR__.DIRECTORY_SEPARATOR.'error.php';
# }}}
class Fx
{
  static string $PROCESS_ID='';
  static function file_persist(string $file): bool # {{{
  {
    clearstatcache(true, $file);
    return file_exists($file);
  }
  # }}}
  static function file_touch(string $file): bool # {{{
  {
    if (!touch($file)) {
      throw ErrorEx::fail('touch', $file);
    }
    return true;
  }
  # }}}
  static function file_unlink(string $file): bool # {{{
  {
    if (self::file_persist($file) &&
        !unlink($file))
    {
      throw ErrorEx::fail('unlink', $file);
    }
    return true;
  }
  # }}}
  static function try_file_unlink(string $file): bool # {{{
  {
    try {
      return self::file_unlink($file);
    }
    catch (Throwable) {
      return false;
    }
  }
  # }}}
  static function hrtime_delta_ms(int $t0, int $t1=0): int # {{{
  {
    if ($t1 < 1) {
      $t1 = hrtime(true);
    }
    if (($t0 -= $t1) < 0) {
      $t0 = -$t0;
    }
    return (int)($t0 / 1000000);
  }
  # }}}
  static function hrtime_expired(int $ms, int $t0, int $t1=0): bool # {{{
  {
    return self::hrtime_delta_ms($t0, $t1) > $ms;
  }
  # }}}
  static function strhex(string $src, string $sep=''): string # {{{
  {
    if ($sep === '') {
      return strtoupper(bin2hex($src));
    }
    for ($x='',$i=0,$j=strlen($src); $i < $j; ++$i)
    {
      if (($k = ord($src[$i])) < 16) {
        $x .= '0';
      }
      $x .= dechex($k).$sep;
    }
    return strtoupper(substr($x, 0, -strlen($sep)));
  }
  # }}}
  static function inthex(int $num): string # {{{
  {
    $s = strtoupper(dechex($num));
    return (strlen($s) % 2) ? '0'.$s : $s;
  }
  # }}}
}
Fx::$PROCESS_ID = (string)getmypid();
function await(object $p): object # {{{
{
  return Loop::await($p);
}
# }}}
function await_all(object ...$p): array # {{{
{
  return Loop::await_all($p);
}
# }}}
function await_one(?object ...$p): ?object # {{{
{
  return Loop::await_any($p, true);
}
# }}}
function await_any(?object ...$p): ?object # {{{
{
  return Loop::await_any($p, false);
}
# }}}
function sleep(int $ms, ?object $f=null): object # {{{
{
  return Promise::Delay($ms, $f);
}
# }}}
###
