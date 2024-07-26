<?php declare(strict_types=1);
namespace SM;
require_once(
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php'
);
###
if ($e = Process::init())
{
  echo "\n".ErrorLog::render($e);
  exit();
}
###
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
  slave_loop();
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
          $r->ok && $r->info('pid', $r->value);
          $r->confirm('Process::start', __FILE__);
        });
        break;
        ###
      case '2':
        ###
        echo "> list: ".implode(',', Process::list())."\n";
        break;
        ###
      case '3':
        ###
        echo $say;
        if (!$p1 && ($a = Process::list()))
        {
          $id = $a[0];
          $p1 = Process
          ::stop($id)
          ->then(function(object $r) use ($id): void {
            $r->confirm('Process::stop', $id);
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
          $r->confirm('Process::stop_all');
        });
        break;
      }
    }
  }
  echo "\n";
}
# }}}
function master_handler(# {{{
  string $eventName, ?object $o=null
):void
{
  echo "> event: ".$eventName."\n";
  if ($o && ($o instanceof Loggable)) {
    echo ErrorLog::render($o);
  }
}
# }}}
function slave_loop(): void # {{{
{
  await(sleep(30000));
}
# }}}
###
