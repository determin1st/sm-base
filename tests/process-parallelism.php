<?php declare(strict_types=1);
namespace SM;
require_once(
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php'
);
###
$startCount = [5,10,20,50,100];
$startIdx   = 0;
$e = Process::init([
  'group' => 'sm-process-test',
  'role'  => 'auto',
  'autonomy' => false,
  'handler'  => null
]);
if (ErrorEx::is($e))
{
  echo ErrorLog::render($e, true);
  exit();
}
###
if (Process::is_master())
{
  ###
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
  ###
  Process::set_handler(slave_handler(...));
  echo "hello, im pid=".Fx::$PROCESS_ID."\n";
  await(sleep(10000));
}
exit();
###
function master_loop(): void # {{{
{
  global $startCount,$startIdx;
  $p0 = Promise::Value('i');
  $p1 = null;
  while (1)
  {
    /**
    * WAIT FOR AND DISPLAY THE RESULT
    */
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
      continue;
    }
    /**
    * HANDLE KEY/CHAR COMMANDS
    */
    $p0 = Conio::readch();
    $k  = $r->value;
    echo "> ".$k."\n";
    switch ($k) {
    case 'i':
      show_menu();
      break;
      ###
    case 'n':
      ###
      if (++$startIdx >= count($startCount)) {
        $startIdx = 0;
      }
      show_menu();
      break;
      ###
    case 'q':
      break 2;
      ###
    case '1':
      ###
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
      $count = $startCount[$startIdx];
      $p1 || $p1 = Process
      ::start_group(__FILE__, $count)
      ->then(function(object $r) use ($count): void {
        if ($r->ok) {
          $r->info('pids', implode(',', $r->value));
        }
        else
        {
          $r->warn('tolerate error');
          $r->ok = true;
        }
        $r->title('Process::startGroup',
          $count, __FILE__
        );
      });
      break;
      ###
    case '4':
      ###
      $a = Process::list();
      echo "> list[".count($a)."]";
      if ($a) {
        echo ": ".implode(', ', $a);
      }
      echo "\n";
      break;
      ###
    case '9':
      ###
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
      $p1 || $p1 = Process
      ::stop_all()
      ->then(function(object $r):void {
        $r->title('Process::stop_all');
      });
      break;
    default:
      # unknown
      Conio::clear_output();
      break;
    }
  }
  echo "\n";
}
# }}}
function show_menu(): void # {{{
{
  global $startCount,$startIdx;
  $startList = list_view($startCount, $startIdx);
  $startCnt = $startCount[$startIdx];
  echo <<<TEXT
      process master
    ╔═══╗
    ║ 1 ║ start slave process
    ║ 2 ║ start slamaster process
    ║ 3 ║ start a flock of slave processes ($startCnt)
    ║ 4 ║ list pids
    ╠═══╣
    ║ 8 ║ stop one process
    ║ 9 ║ stop multiple processes
    ║ 0 ║ stop all processes
    ╠═══╣
    ║ i ║ information
    ║ n ║ flock size $startList
    ║ q ║ quit
    ╚═══╝

TEXT;
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
    case 'output':
      echo $e[2];
      break;
    case 'attach':
      $e[2]->title('Process', 'attachment');
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
    switch ($e[0]) {
    case 'error':
      echo ErrorLog::render($e[2]);
      break;
    }
  }
}
# }}}
function list_view($list, $idx): string # {{{
{
  $x = ','.implode(',', $list).',';
  $s = $list[$idx];
  $x = str_replace(','.$s.',', ',['.$s.'],', $x);
  return trim($x, ',');
}
# }}}
###
