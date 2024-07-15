<?php declare(strict_types=1);
### defs {{{
$repeatCount = [1,10,20,50,100];
$repeatIdx = 0;
$groupSize = [10,20,30,40,50,70,100];
$groupIdx = 0;
$hosts = [
  'http://127.0.0.1:8000/',
  'http://httpbin.org/'
];
$hostIdx = 0;
# Hurl
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
\SM\Conio::init() && exit();
###
$HURL = \SM\Hurl::new([
  'base-url' => $hosts[$hostIdx],
  'curlopt-http_version'=>\CURL_HTTP_VERSION_1_1
]);
if (\SM\ErrorEx::is($HURL))
{
  echo "\n".\SM\ErrorLog::render($HURL);
  exit;
}
###
# AMP http-client
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '__http-client'.DIRECTORY_SEPARATOR.
  'vendor'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
$AMP = \Amp\Http\Client\HttpClientBuilder::buildDefault();
###
# Guzzle
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '__guzzle'.DIRECTORY_SEPARATOR.
  'vendor'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
$GUZ = new \GuzzleHttp\Client();
###
# phasync
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '__phasync'.DIRECTORY_SEPARATOR.
  'vendor'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
$PHASYNC = new phasync\HttpClient\HttpClient();
### }}}
###
while ($ch = show_menu())
{
  switch ($ch) {
  case '1':# {{{
    $m = $repeatCount[$repeatIdx];
    echo "flexing on a single request x".$m." times..\n\n";
    ###
    $path = 'delay/1';
    $url = $hosts[$hostIdx].$path;
    $a = [];
    /***/
    $a[$k = 'SM\\Hurl'] = # {{{
    (function() use ($HURL,$path,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ### exclude parsing and construction stuff
      $p = $HURL('', [
        'method'  => 'GET',
        'url'     => $path
      ]);
      ###
      $t = hrtime(true);
      $r = \SM\await($p);
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $AMP && $a[$k = 'Amp\\Http\\Client'] = # {{{
    (function() use ($AMP,$url,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      $q = new \Amp\Http\Client\Request($url, 'GET');
      $q->setProtocolVersions(["1.1"]);
      ###
      $t = hrtime(true);
      $r = $AMP->request($q);
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $a[$k = 'curl_exec()'] = # {{{
    (function() use ($url,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      $curl = \curl_init();
      $o = \SM\HurlRequest::CURLOPT;
      $o[\CURLOPT_URL] = $url;
      $o[\CURLOPT_CUSTOMREQUEST] = 'GET';
      $o[\CURLOPT_HTTP_VERSION] = \CURL_HTTP_VERSION_1_1;
      \curl_setopt_array($curl, $o);
      ###
      $t = hrtime(true);
      $r = \curl_exec($curl);
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      \curl_close($curl);
      return $t;
    });
    # }}}
    $GUZ && $a[$k = 'GuzzleHttp\\Client'] = # {{{
    (function() use ($GUZ,$url,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      $q = new \GuzzleHttp\Psr7\Request(
        'GET', $url, ['version' => 1.1]
      );
      $p = $GUZ->sendAsync($q);
      ###
      $t = hrtime(true);
      $p->wait();
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $PHASYNC && $a[$k = 'phasync\\HttpClient'] = # {{{
    (function() use ($PHASYNC,$url,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      $t = hrtime(true);
      $r = \phasync::run(function() use ($PHASYNC,$url) {
        return $PHASYNC->get($url);
      });
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $k = 'Swoole\\Coroutine\\Http\\Client';# {{{
    class_exists($k) && $a[$k] =
    (function() use ($url,$path,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      $i = strpos($url, '/');
      if ($j = strpos($url, ':', $i))
      {
        $host = substr($url, 2+$i, $j-2-$i);
        $i = strpos($url, '/', $j);
        $port = (int)substr($url, 1+$j,  $i-$j-1);
      }
      else
      {
        $j = strpos($url, '/', 2+$i);
        $host = substr($url, 2+$i, $j-$i-2);
        $port = 80;
      }
      ###
      $t = hrtime(true);
      \Swoole\Coroutine\run(function () use ($host,$port,$path) {
        $client = new \Swoole\Coroutine\Http\Client(
          $host, $port
        );
        #$client->set(['timeout' => 10]);
        $client->get('/'.$path);
        $client->close();
      });
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    ###
    [$avg,$rnk] = test_exec($a, $m, 1000, [
      10,20,30,40,50,60,70,80,90
    ]);
    ###
    show_avg($avg, $m.' requests');
    show_p($rnk, $m.' requests');
    break;
  # }}}
  case '2':# {{{
    $n = $groupSize[$groupIdx];
    $m = $repeatCount[$repeatIdx];
    $what = $n."x".$m." concurrent requests";
    echo $what."..\n\n";
    ###
    $path = 'delay/2';
    $url = $hosts[$hostIdx].$path;
    $a = [];
    /***/
    $a[$k = 'SM\\Hurl'] = # {{{
    (function() use ($HURL,$n,$path,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      for ($a=[],$i=0; $i < $n; ++$i)
      {
        $a[] = $HURL('', [
          'method'  => 'GET',
          'url'     => $path,
        ]);
      }
      ###
      $t = hrtime(true);
      $a = \SM\await_all(...$a);
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $AMP && $a[$k = 'Amp\\Http\\Client'] = # {{{
    (function() use ($AMP,$url,$n,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      for ($a=[],$i=0; $i < $n; ++$i)
      {
        $q = new \Amp\Http\Client\Request($url, 'GET');
        $q->setProtocolVersions(["1.1"]);
        $a[] = (function($AMP,$q) {
          return \Amp\async(function() use ($AMP,$q) {
            return $AMP->request($q);
          });
        })($AMP,$q);
      }
      ###
      $t = hrtime(true);
      $r = \Amp\Future\await($a);
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $GUZ && $a[$k = 'GuzzleHttp\\Client'] = # {{{
    (function() use ($GUZ,$url,$n,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      for ($a=[],$i=0; $i < $n; ++$i)
      {
        $a[] = new \GuzzleHttp\Psr7\Request(
          'GET', $url, [
            'version' => 1.1,
            #'options' => ['verify' => false],
          ]
        );
      }
      #$p = $GUZ->sendAsync($q);
      $p = new \GuzzleHttp\Pool($GUZ, $a);
      $p = $p->promise();
      ###
      $t = hrtime(true);
      $p->wait();
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    $PHASYNC && $a[$k = 'phasync\\HttpClient'] = # {{{
    (function() use ($PHASYNC,$url,$n,$k) {
      echo "> ".$k.": ";
      \SM\await(\SM\Conio::drain());
      ###
      $t = hrtime(true);
      $r = \phasync::run(function() use ($PHASYNC,$n,$url)
      {
        for ($i=0; $i < $n; ++$i) {
          $PHASYNC->get($url);
        }
      });
      $t = \SM\Fx::hrtime_delta_ms($t);
      ###
      echo $t."ms\n";
      \SM\await(\SM\Conio::drain());
      return $t;
    });
    # }}}
    ###
    [$avg,$rnk] = test_exec($a, $m, 2000, [
      200,400,600,800,1000,1200,1400,1600
    ]);
    ###
    show_avg($avg, $what);
    show_p($rnk, $what);
    break;
  # }}}
  case 'l':# {{{
    $hostIdx = $hostIdx ? 0 : 1;
    $HURL->url($hosts[$hostIdx])->save();
    \SM\Conio::clear();
    continue 2;
  # }}}
  case 'n':# {{{
    if (++$repeatIdx >= count($repeatCount)) {
      $repeatIdx = 0;
    }
    continue 2;
  # }}}
  case 'o':# {{{
    if (++$groupIdx >= count($groupSize)) {
      $groupIdx = 0;
    }
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
###
function show_menu(): string # {{{
{
  global
    $repeatCount,$repeatIdx,
    $groupSize,$groupIdx,
    $hosts,$hostIdx;
  ###
  $url = $hosts[$hostIdx];
  $repeat = list_view($repeatCount, $repeatIdx);
  $group = list_view($groupSize, $groupIdx);
  ###
  if (\SM\Conio::is_ansi()) {echo "\x1B[?1J";}
  echo <<<TEXT

  ╔═══╗
  ║ 1 ║ single request (flex)
  ║ 2 ║ concurrent requests
  ╠═══╣
  ║ l ║ $url
  ║ n ║ repeat: $repeat
  ║ o ║ group size: $group
  ║ q ║ quit
  ╚═══╝

> 
TEXT;
  $r = \SM\await(\SM\Conio::readch());
  if (!$r->ok)
  {
    echo "\n".\SM\ErrorLog::render($r);
    return '';
  }
  return $r->value;
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
function do_reset(): void # {{{
{
  echo "> press [enter] to reset or [q] to quit.. ";
  while (($r = \SM\await(\SM\Conio::readch()))->ok)
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
function test_exec($list, $n, $delay, $buckets): array # {{{
{
    ### prepare result stores
    $avg = [];# averages
    $rnk = [];# ranks
    $max = count($buckets);
    foreach ($list as $k => $f)
    {
      $avg[$k] = 0;
      $rnk[$k] = \array_fill(0, 1 + $max, 0);
    }
    ### randomize order
    $keys = \array_keys($list);
    \shuffle($keys);
    ### execute and collect stats
    for ($i=0; $i < $n; ++$i)
    {
      foreach ($keys as $k)
      {
        $j = $list[$k]() - $delay;
        $avg[$k] += $j;
        $fit = false;
        foreach ($buckets as $bi => $v)
        {
          if ($j <= $v)
          {
            $rnk[$k][$bi]++;
            $fit = true;
            break;
          }
        }
        if (!$fit) {
          $rnk[$k][$max]++;
        }
      }
    }
    echo "\n";
    ### determine averages
    foreach ($avg as $k => $i) {
      $avg[$k] = (int)($i / $n);
    }
    \asort($avg, \SORT_NUMERIC);
    ### determine ranks and percentiles
    foreach ($rnk as $k => $a)
    {
      for ($b=[],$i=0; $i < $max; ++$i) {
        $b[$buckets[$i].'ms'] = $a[$i];
      }
      $b['>'.$buckets[$max - 1].'ms'] = $a[$max];
      ###
      \arsort($b, \SORT_NUMERIC);
      ###
      $a = [];
      foreach ($b as $name => $cnt)
      {
        if ($cnt)
        {
          $p = round(100 * $cnt / $n, 1);
          $a[$name] = [$cnt, $p];
        }
      }
      $rnk[$k] = $a;
    }
    return [$avg,$rnk];
}
# }}}
function show_avg($data, $what): void # {{{
{
  echo "> average of ".$what."\n";
  foreach ($data as $k => $i) {
    echo ">> ".$k.": ".$i."\n";
  }
  echo "\n";
}
# }}}
function show_p($data, $what): void # {{{
{
  echo "> percentiles of ".$what."\n";
  ####
  foreach ($data as $k => $a)
  {
    echo ">> ".$k.":";
    echo ((strlen($k) < 12)?"\t\t":"\t");
    foreach ($a as $s => $rnk) {
      echo $s.'/'.$rnk[1].'% ';
    }
    echo "\n";
  }
  echo "\n";
}
# }}}
###
