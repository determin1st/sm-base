<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
if ($e = Conio::init())
{
  echo ErrorLog::render($e);
  exit();
}
Conio::set('buffering', false);
###
echo "\n[q] ~ quits";
echo "\nConio::readch(): ";
while (1)
{
  if (!($r = await(Conio::readch()))->ok)
  {
    echo "\n".ErrorLog::render($r);
    break;
  }
  echo '['.$r->value.']';
  if ($r->value === 'q') {
    break;
  }
}
###
echo "\nConio::readch() with immediate control: ";
await(Conio::readch()
->okay(function($r) {
  ###
  echo '['.$r->value.']';
  return match ($r->value) {
    'q' => null,
    'x' => $r->consume(0),
    default => $r->resume()
  };
})
->fail(function($r) {
  ###
  echo "\n".ErrorLog::render($r);
}));
echo "\n\n";
###
