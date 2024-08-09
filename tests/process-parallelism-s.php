<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
$e = Process::init_slave('sm-process-test',
function(array $evt): void {
  ###
  foreach ($evt as $e)
  {
    echo "> event: ".$evt[0].
      " pid=".($evt[1]?:'self')."\n";
    ###
    if ($evt[2] instanceof Loggable) {
      echo ErrorLog::render($evt[2]);
    }
  }
  ###
});
if ($e)
{
  echo ErrorLog::render($e);
  exit();
}
echo "> Process::init_slave()\n";
await(sleep(2000));
Process::$BASE->output('hello');
await(sleep(20000));
###
