<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
Conio::init() && exit();
$o = new \SyncSemaphore('sem-lock-unlock', 1, 0);
echo "locking.. ";
if (!$o->lock(-1))
{
  echo "failed!\n";
  exit(1);
}
echo "ok\n";
echo "press any key to unlock..";
await(Conio::readch());
echo "\n";
echo "unlocking.. ";
if ($o->unlock($i)) {
  echo "ok($i)";
}
else
{
  echo "failed($i)";
  exit(1);
}
echo "\n";
echo "press any key to quit..";
await(Conio::readch());
echo "\n";
exit(0);

