<?php declare(strict_types=1);
namespace SM;
require_once(
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php'
);
###
if ($e = Process::init('sm-process-test'))
{
  echo ErrorLog::render($e);
  exit();
}
if (Process::is_master())
{
  # MASTER has the console
  if ($e = Conio::init())
  {
    echo ErrorLog::render($e);
    exit();
  }
  Conio::set('buffering', false);
  Process::set_handler(master_handler(...));
  master_loop();
}
else
{
  # SLAVE process/worker operates in silence
  Process::set_handler(slave_handler(...));
  await(sleep(30000));
}
exit();
###
function master_loop(): void # {{{
{
  $p0 = Promise::Value('i');
  $p1 = null;
  while (1)
  {
    if (!($r = await_any($p0,$p1))->ok &&
        !$r->isCancelled)
    {
      echo ErrorLog::render($r);
      break;
    }
    if ($r->index)
    {
      $p1 = null;
      echo ErrorLog::render($r);
    }
    else
    {
      $p0  = Conio::readch();
      $k   = $r->value;
      $say =  "> ".$k."\n";
      ###
      switch ($k) {
      case 'i':
        echo <<<TEXT
$say
      Process master
    ╔═══╗
    ║ 1 ║ start new process
    ║ 2 ║ list process identifiers
    ║ 3 ║ stop one (first)
    ║ 0 ║ stop all
    ╠═══╣
    ║ i ║ information
    ║ q ║ quit
    ╚═══╝


TEXT;
        ###
        break;
      case 'q':
        echo $say;
        break 2;
        ###
      case '1':
        ###
        echo $say;
        $p1 || $p1 = Process
        ::start(__FILE__)
        ->then(function(object $r): void {
          if ($r->ok) {
            $r->info('pid', $r->value);
          }
          else
          {
            $r->warn('tolerate error');
            $r->ok = true;
          }
          $r->title('Process::start', __FILE__);
        });
        break;
        ###
      case '2':
        ###
        $a = Process::list();
        echo "> list[".count($a)."]";
        if ($a) {
          echo ": ".implode(', ', $a);
        }
        echo "\n";
        break;
        ###
      case '3':
        ###
        echo $say;
        if (!$p1)
        {
          $id = ($a = Process::list())
            ? $a[0]
            : '12345';
          ###
          $p1 = Process
          ::stop($id)
          ->then(function(object $r) use ($id): void {
            $r->title('Process::stop', $id);
          });
        }
        break;
        ###
      case '0':
        ###
        echo $say;
        $p1 || $p1 = Process
        ::stop_all()
        ->then(function(object $r):void {
          $r->title('Process::stop_all');
        });
        break;
      }
    }
  }
  echo "\n";
}
# }}}
function master_handler(array $event): void # {{{
{
  foreach ($event as $e)
  {
    echo "> event: ".$e[0].
      " pid=".($e[1] ?: "self")."\n";
    ###
    switch ($e[0]) {
    case 'stop':
    case 'error':
      echo ErrorLog::render($e[2]);
      break;
    }
  }
}
# }}}
function slave_handler(array $event): void # {{{
{
  foreach ($event as $e)
  {
    echo "> command: ".$e[0]."\n";
    ###
    switch ($e[0]) {
    case 'error':
      echo ErrorLog::render($e[2]);
      break;
    }
  }
}
# }}}
###
