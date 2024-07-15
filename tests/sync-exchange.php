<?php declare(strict_types=1);
namespace SM;
require_once(
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php'
);
###
Conio::init() && exit();
###
$EXCHG = SyncExchange::new([
  'id'          => 'sync-exchange-test',
  'share-read'  => true,
  'share-write' => true,
  #'boost'       => true,
  'size'        => 3
]);
if (ErrorEx::is($EXCHG))
{
  echo ErrorLog::render($EXCHG);
  exit();
}
for ($p0=Promise::Value('i'),$p1=$p2=null;;)
{
  if (!($r = await_any($p0,$p1,$p2))->ok &&
      !$r->isCancelled)
  {
    echo "\n".ErrorLog::render($r);
    echo "> press any key to quit..\n";
    await(Conio::readch());
    break;
  }
  switch ($r->index) {
  case 0:# console {{{
    ###
    $p0 = Conio::readch();
    ###
    switch ($k = $r->value) {
    case 'i':
      echo <<<TEXT

    SyncExchange
  ╔═══╗
  ║ 1 ║ client => notification ~ w
  ║ 2 ║ client => echo ~ w+r
  ║ 3 ║ client => w+r+w
  ║ 4 ║ client => w+... (randomize)
  ╠═══╣
  ║ 5 ║ server => read and follow protocol
  ║ 0 ║ cancel all
  ╠═══╣
  ║ i ║ information
  ║ q ║ quit
  ╚═══╝


TEXT;
      ###
      break;
    case 'q':
      echo "> quit\n\n";
      exit();
    case '1':
    case '2':
    case '3':
    case '4':
      ###
      if ($p2) {
        break;
      }
      $s = $k.':hello from PID='.Fx::$PROCESS_ID;
      $p2 = $EXCHG
      ->client()
      ->okay(function($r) use ($k) {
        echo "> writing #".$k.": ";
        return Conio::drain();
      })
      ->okay(
        new_client_handler($k, $s)
      );
      break;
      ###
    case '5':
      ###
      echo "> starting server..\n";
      await(Conio::drain());
      $p1 = $EXCHG
      ->server()
      ->okay(function($r) {
        echo "> serving the first request..\n";
        return Conio::drain();
      })
      ->okay(
        server_handler(...)
      )
      ->whenDone(function($r) {
        if ($r->isCancelled) {
          echo "> server cancelled\n";
        }
      });
      break;
      ###
    case '0':
      ###
      $p1 && $p1->cancel();
      $p2 && $p2->cancel();
      break;
    }
    break;
  # }}}
  case 1:# server
    $p1 = null;
    break;
  case 2:# client
    $p2 = null;
    break;
  }
}
###
function new_client_handler($k, $s) # {{{
{
  return match ($k) {
    '1' => (function($r) use ($s) {
      ###
      if ($r->index === 0) {
        return $r->write($s);
      }
      echo "ok\n";
      return null;
      ###
    }),
    '2' => (function($r) use ($s) {
      ###
      switch ($r->index) {
      case 0: return $r->write($s);
      case 1: return $r->read();
      }
      echo $r->value."\n";
      return Conio::drain();
      ###
    }),
    '3' => (function($r) use ($s) {
      ###
      echo $r->index." ";
      switch ($r->index) {
      case 0: return $r->write($s);
      case 1: return $r->read();
      case 2: return $r->write($s);
      }
      echo "\n";
      return Conio::drain();
      ###
    }),
    '4' => (function($r) {
      ###
      static $N=0;
      if ($r->index === 0)
      {
        $N = rand(1, 1000);
        echo "[".$N."] w";
        return $r->write('4:'.$N);
      }
      if ($r->index < $N)
      {
        if ($r->index % 2 === 0)
        {
          echo "w";
          return $r->write('.');
        }
        else
        {
          echo "r";
          return $r->read();
        }
      }
      echo " ok\n";
      return Conio::drain();
      ###
    }),
  };
}
# }}}
function server_handler($r) # {{{
{
  # determine exchange protocol
  static $X='none';
  if ($r->index === 0)
  {
    $a = explode(':', $r->value, 2);
    $X = $a[0];
  }
  # handle request
  switch ($X) {
  case '1':
    switch ($r->index) {
    case 0:
      echo "> NOTIFICATION: ".$a[1]."\n";
      break;
    }
    break;
  case '2':
    switch ($r->index) {
    case 0:
      echo "> ECHO: ";
      return $r->write($r->value);
    case 1:
      echo "ok\n";
      break;
    }
    break;
  case '3':
    switch ($r->index) {
    case 0:
      echo "> ECHO + NOTIFICATION: ";
      return $r->write($r->value);
    case 1:
      echo "o";
      return $r->read();
    case 2:
      echo "k\n";
      break;
    }
    break;
  case '4':
    static $N=0;
    if ($r->index === 0)
    {
      $N = (int)$a[1];
      echo "> RANDOM COUNT=".$N.": r";
    }
    if ($r->index < ($N - 1))
    {
      if ($r->index % 2 === 0)
      {
        echo "w";
        return $r->write('.');
      }
      else
      {
        echo "r";
        return $r->read();
      }
    }
    echo " ok\n";
    break;
  default:
    echo "> ERROR: unknown protocol=".$X."\n";
    break;
  }
  # finish and restart exchange
  return $r->reset();
}
# }}}
###
