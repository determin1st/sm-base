<?php declare(strict_types=1);
# defs {{{
namespace SM;
use Throwable;
use function getenv;
###
use const DIRECTORY_SEPARATOR;
require_once __DIR__.DIRECTORY_SEPARATOR.'sync.php';
require_once __DIR__.DIRECTORY_SEPARATOR.(
  (\PHP_OS_FAMILY === 'Windows')
  ? 'process-win.php'
  : 'process-nix.php'
);
# }}}
class Process # {{{
{
  # basis {{{
  function __construct()
  {}
  static function init(): ?object
  {
  }
  static function new(array $o): object
  {
  }
  # }}}
  # stasis {{{
  # }}}
}
# }}}
###
