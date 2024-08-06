<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
Conio::init() && exit();
$bufferSize = 100;
$SA = SyncAggregate::new([
  'id'   => 'sync-aggregate-test',
  'size' => $bufferSize,
]);
if (ErrorEx::is($SA))
{
  echo ErrorLog::render($SA);
  exit();
}
###
for ($p0=Promise::Value('i'),$p1=null;;)
{
  $r = await_any($p0, $p1);
  if (!$r->ok && !$r->isCancelled)
  {
    echo "\n".ErrorLog::render($r);
    break;
  }
  if ($r->index)
  {
    $p1 = null;
    echo ErrorLog::render($r);
  }
  else
  {
    # readch {{{
    $p0  = Conio::readch();
    $k   = $r->value;
    $say = "> ".$k."\n";
    ###
    switch ($k) {
    case 'i':
      ###
      echo <<<TEXT
$say
    SyncAggregate, size=$bufferSize
  ╔═══╗
  ║ 1 ║ read
  ║ 2 ║ write
  ╠═══╣
  ║ 0 ║ cancel
  ╠═══╣
  ║ i ║ information
  ║ q ║ quit
  ╚═══╝


TEXT;
      break;
      ###
    case 'q':
      ###
      echo $say;
      break 2;
      ###
    case '0':
      ###
      if ($p1)
      {
        echo $say;
        $p1->cancel();
      }
      break;
      ###
    case '1':
      ###
      if (!$p1)
      {
        echo $say;
        $p1 = $SA
        ->read()
        ->okay(function(object $r): object {
          ###
          echo "> read: ";
          var_dump($r->value);
          echo "\n";
          return $r->reset();
          ###
        });
      }
      break;
      ###
    case '2':
      ###
      if (!$p1)
      {
        echo $say;
        $p1 = $SA->write(
          'hello, I am pid='.Fx::$PROCESS_ID
        );
      }
      break;
    }
    # }}}
  }
}
echo "\n";
exit();
###
