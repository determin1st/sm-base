<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
Conio::init() && exit();
###
$hosts = [
  'http://127.0.0.1:8000/',
  'http://httpbin.org/'
];
$hostIdx = 0;
$hi = Hurl::new([
  'base-url' => $hosts[$hostIdx]
]);
if (ErrorEx::is($hi))
{
  echo "\n".ErrorLog::render($hi);
  exit();
}
while ($ch = show_menu($hosts, $hostIdx))
{
  echo "[".$ch."]: ";
  switch ($ch) {
  case '1':# {{{
    echo "simple server-side delay.. \n";
    await(Conio::drain());
    ###
    $p = $hi('', [
      'url'=>'delay/3',
      'curlopt-verbose'=>true
    ]);
    $t = hrtime(true);
    $r = await($p);
    $t = Fx::hrtime_delta_ms($t);
    if (is_failed($r)) {break 2;}
    echo "finished in ".$t." ms\n";
    if ($t < 3000)
    {
      echo "> finished too fast! dumping result: ";
      var_dump($r->value);
      echo "\n";
      break 2;
    }
    break;
  # }}}
  case '2':# {{{
    echo
      "polling is relaxed on CPU and ".
      "usually finishes later\n";
    await(Conio::drain());
    ###
    $p = $hi('', [
      'url'=>'delay/3',
      'curlopt-verbose'=>true,
      'polling'=>true
    ]);
    ###
    $t = hrtime(true);
    $r = await($p);
    $t = Fx::hrtime_delta_ms($t);
    ###
    if (is_failed($r)) {break 2;}
    echo "finished in ".$t." ms\n";
    if ($t < 3000)
    {
      echo "> finished too fast! dumping result: ";
      var_dump($r->value);
      echo "\n";
      break 2;
    }
    break;
  # }}}
  case '3':# {{{
    echo "verbose delay=3 + timeout=2..\n";
    await(Conio::drain());
    ###
    $p = $hi('', [
      'url'=>'delay/3',
      'curlopt-verbose'=>true,
      'timeout'=>2
    ]);
    ###
    $t = hrtime(true);
    $r = await($p);
    $t = Fx::hrtime_delta_ms($t);
    ###
    if ($r->ok)
    {
      echo
        "\n> unexpected, did not timed out!".
        "\n> dumping result: ";
      ###
      var_dump($r->value);
      break 2;
    }
    else
    {
      echo
        "> got an error, as expected:".
        " timed out in ".$t." ms\n";
    }
    break;
  # }}}
  case '4':# {{{
    echo
      "delay=5 + ticking + cancellation..\n".
      "> request started\n".
      "> you have 5 seconds to [c]ancel the request: ";
    await(Conio::drain());
    ###
    $p0 = $hi->url('delay/5')();
    $p1 = Conio::readch()
    ->okay(function($r) {
      return ($r->value !== 'c')
        ? $r->resume()
        : null;
    });
    $p2 = Promise::Func(function($r) {
      echo '.';
      return $r->promiseDelay(300);
    });
    ###
    $t = hrtime(true);
    $r = await_one($p0, $p1, $p2);
    $t = Fx::hrtime_delta_ms($t);
    ###
    if (!$r->ok)
    {
      echo "\n".ErrorLog::render($r);
      break 2;
    }
    echo "ok\n";
    switch ($r->index) {
    case 0:
      echo "> sequence completed in ".$t."ms\n";
      break;
    case 1:
      echo "> sequence cancelled after ".$t."ms\n";
      break;
    }
    echo "\n";
    break;
  # }}}
  case '5':# {{{
    echo "sending multiple HTTP methods: ";
    await(Conio::drain());
    ###
    $a = ['HELLO THERE!', true];
    $t = hrtime(true);
    $a = await_all(
      $hi->method('delete')($a),
      $hi->method('get')(),
      $hi->method('patch')($a),
      $hi->method('post')(),
      $hi->method('put')($a),
    );
    $t = Fx::hrtime_delta_ms($t);
    ###
    if (is_failed(...$a)) {break 2;}
    echo "finished in ".$t." ms\n";
    break;
  # }}}
  case '6':# {{{
    echo "basic-auth\n";
    await(Conio::drain());
    ###
    $a = ['username', 'password'];
    $p = $hi('', [
      'method' => 'GET',
      'url'    => 'basic-auth/'.$a[0].'/'.$a[1],
      'curlopt-verbose' => true
    ])
    ->okay(function($r) {
      # expect HTTP code: 401 UNAUTHORIZED
      $x = $r->value->info['http_code'];
      if ($x !== 401)
      {
        $r->fail('unexpected HTTP code: '.$x);
        return null;
      }
      # report challenge
      echo "\n===> AUTORIZING!\n\n";
      # nested awaits are not supported,
      # you have to keep on chaining
      #await(Conio::drain());
      return Conio::drain();
    })
    ->okay(function($r) use ($hi,$a) {
      # send credentials
      $auth = base64_encode($a[0].':'.$a[1]);
      return $hi('', [
        'method'  => 'GET',
        'url'     => 'basic-auth/'.$a[0].'/'.$a[1],
        'headers' => [
          'authorization' => 'Basic '.$auth
        ],
        'curlopt-verbose' => true
      ]);
    })
    ->okay(function($r) {
      # check HTTP code
      $x = $r->value->info['http_code'];
      if ($x === 200) {
        $r->info('AUTHORIZED!');
      }
      else {
        $r->fail('unexpected HTTP code: '.$x);
      }
      return null;
    });
    ###
    $t = hrtime(true);
    $r = await($p);
    $t = Fx::hrtime_delta_ms($t);
    ###
    if (is_failed($r)) {break 2;}
    echo "finished in ".$t." ms\n";
    echo ErrorLog::render($r)."\n";
    break;
  # }}}
  case '7':# {{{
    echo "inspection...\n";
    await(Conio::drain());
    ###
    $p1 = $hi('',[
      'method'=>'GET',
      'url'=>'headers'
    ])
    ->okay(function($r) {
      ###
      $x = $r->value->info['http_code'];
      if ($x === 200)
      {
        echo "> ";
        var_dump($r->value->content);
      }
      else {
        $r->fail('unexpected HTTP code: '.$x);
      }
      return null;
    });
    $p2 = $hi('',[
      'method'=>'GET',
      'url'=>'ip',
    ])
    ->okay(function($r) {
      ###
      $x = $r->value->info['http_code'];
      if ($x === 200)
      {
        echo "> ";
        var_dump($r->value->content);
      }
      else {
        $r->fail('unexpected HTTP code: '.$x);
      }
      return null;
    });
    $p3 = $hi('',[
      'method'=>'GET',
      'url'=>'user-agent',
    ])
    ->okay(function($r) {
      ###
      $x = $r->value->info['http_code'];
      if ($x === 200)
      {
        echo "> ";
        var_dump($r->value->content);
      }
      else {
        $r->fail('unexpected HTTP code: '.$x);
      }
      return null;
    });
    ###
    $t = hrtime(true);
    $a = await_all($p1, $p2, $p3);
    $t = Fx::hrtime_delta_ms($t);
    ###
    if (is_failed(...$a)) {break 2;}
    echo "finished in ".$t." ms\n";
    break;
  # }}}
  case 'l':# {{{
    $hostIdx = $hostIdx ? 0 : 1;
    $hi->url($hosts[$hostIdx])->save();
    Conio::clear();
    continue 2;
  # }}}
  case 'q':
    echo "quit\n";
    break 2;
  default:
    echo "command is unknown\n";
    break;
  }
  do_reset();
}
exit();
### HELPERS
function show_menu($hosts, $hostIdx): string # {{{
{
  if (Conio::is_ansi()) {echo "\x1B[?1J";}
  $url = $hosts[$hostIdx];
  $what = $hostIdx
    ? 'local/remote'
    : 'remote/local';
  ###
  echo <<<TEXT

   $url
  ╔═══╗
  ║ 1 ║ verbose delay=3
  ║ 2 ║ verbose delay=3 + polling
  ║ 3 ║ verbose delay=3 + timeout=2
  ║ 4 ║ delay=5 + ticking + cancellation
  ╠═══╣
  ║ 5 ║ HTTP methods
  ║ 6 ║ auth methods
  ║ 7 ║ request inspection
  ╠═══╣
  ║ l ║ $what
  ║ q ║ quit
  ╚═══╝

> 
TEXT;
  $r = await(Conio::readch());
  if (!$r->ok)
  {
    echo "\n".ErrorLog::render($r);
    return '';
  }
  return $r->value;
}
# }}}
function do_reset(): void # {{{
{
  echo "> press [enter] to reset or [q] to quit.. ";
  while (($r = await(Conio::readch()))->ok)
  {
    if ($r->value === "\r") {
      break;
    }
    if ($r->value === 'q') {
      echo "\n> quit\n\n";
      exit;
    }
  }
}
# }}}
function is_failed(object ...$list): bool # {{{
{
  foreach ($list as $r)
  {
    if (!$r->ok)
    {
      echo "\n".ErrorLog::render($r);
      return true;
    }
  }
  echo "ok; ";
  return false;
}
# }}}
###
