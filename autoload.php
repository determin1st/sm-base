<?php declare(strict_types=1);
namespace SM;
use function define,defined,spl_autoload_register;
use const DIRECTORY_SEPARATOR;
###
defined('SM\\AUTO') ||
define('SM\\AUTO', new class()
{
  const DIR=__DIR__.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR;
  const MAP=[
    'SM\\Conio'         => 'conio.php',
    'SM\\ErrorEx'       => 'error.php',
    'SM\\ErrorLog'      => 'error.php',
    'SM\\Hurl'          => 'hurl.php',
    'SM\\Process'       => 'process.php',
    'SM\\Promise'       => 'promise.php',
    'SM\\Loop'          => 'promise.php',
    'SM\\SyncExchange'  => 'sync.php',
    'SM\\SyncAggregate' => 'sync.php',
  ];
  public bool $ready=false;
  function autoload(string $class): void
  {
    if (isset(self::MAP[$class])) {
      include self::DIR.self::MAP[$class];
    }
  }
  function register(): bool
  {
    if ($this->ready) {
      return true;
    }
    if (!spl_autoload_register($this->autoload(...))) {
      return false;
    }
    require(self::DIR.'functions.php');
    return $this->ready = true;
  }
});
return (AUTO)->register();
###
