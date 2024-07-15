<?php declare(strict_types=1);
namespace SM;
require_once
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'autoload.php';
###
Conio::init() && exit();
###
$TESTLOG = [
# top multilines {{{
[
  'level' => 0,
  'msg'   => [# type=2 (no title)
    "A major change was made by the first Emperor,\n".
    "Augustus (27 BC — 14 AD), who reformed revenue\n".
    "collection, bringing large parts of Rome’s empire\n".
    "under consistent direct taxation instead of asking\n".
    "for intermittent tributes. Taxation was determined\n".
    "by population census and private tax farming\n".
    "was abolished in favor for civil service tax collectors.\n".
    "This made provincial administration far more tolerable,\n".
    "decreased corruption, oppression and increased revenues."
  ],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
[
  'level' => 2,
  'msg'   => [# type=2 (title)
    'Slavery burden',
    "\n".
    "While it is true that the Roman Empire wasn’t as heavily\n".
    "bureaucratized as the Han Dynasty of Imperial China,\n".
    "the Roman Empire did have a bureaucracy even in this early\n".
    "imperial period that tends to be underestimated; the officials,\n".
    "as stated above, had a large number of slaves who worked\n".
    "informally in administrative jobs."
  ],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
[
  'level' => 0,
  'msg'   => [# type=3 (no title)
    'history','city-states',"confederation\n".
    "Rome has been called by some historians a confederation\n".
    "of city-states and that is true as the Roman state\n".
    "preferred on a local level to delegate tasks to city-states\n".
    "and in provinces that did not have such traditions,\n".
    "Rome brought them into being as far as it could.\n".
    "City authorities had to maintain order and extract revenue\n".
    "not only for the city itself but also from the countryside\n".
    "that was allocated to that city and where\n".
    "the majority of the population lived."
  ],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
[
  'level' => 2,
  'msg'   => [# type=3 (title)
    'Empire','problems','Tax burden',
    "\n".
    "Under Diocletian and Constantine in the late third and\n".
    "early fourth centuries, the Roman Empire underwent an even\n".
    "greater bureaucratization in order to deal with the threat\n".
    "of external enemies and internal instability.\n".
    "The Empire was to be ruled by two Emperors, one in the West\n".
    "and one in the East, who could respond faster to internal\n".
    "and external threats. In order to maintain a large army\n".
    "to defend the Empire from the ‘barbarian’ threat,\n".
    "more taxes were needed."
  ],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
# }}}
# oneliners {{{
[
  'level' => 0,
  'msg'   => ['one'],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
[
  'level' => 0,
  'msg'   => ['one','two'],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
[
  'level' => 0,
  'msg'   => ['one','two','three'],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  => [],
],
# }}}
# nesting {{{
[
  'level' => 2,
  'msg'   => ['go','deeper'],
  'span'  => 123456,
  'time'  => 1700073135,
  'logs'  =>
  [
    [
      'level' => 2,
      'msg'   => ['one'],
    ],
    [
      'level' => 0,
      'msg'   => ['one','two'],
    ],
    [
      'level' => 0,
      'msg'   => ['one','two','three'],
    ],
    [
      'level' => 0,
      'msg'   => ['one','two','three','four'],
    ],
    [
      'level' => 0,
      'msg'   => ['one','two','three','four','five'],
    ],
    ###
    [
      'level' => 0,
      'msg'   => ['one two three'],
      'logs'  =>
      [
        [
          'level' => 1,
          'msg'   => [
            'one','two','three','four',
            "five\n".
            "the bunny went for a walk\n".
            "suddenly, the hunter runs out and.."
          ],
          'logs'  =>
          [
            [
              'level' => 1,
              'msg'   => [
                "..shots stright away!\n".
                "oh my, oh my"
              ],
              'logs'  =>
              [
                [
                  'level' => 1,
                  'msg'   => ["message 1\nmessage 2\nmessage 3"],
                ],
                [
                  'level' => 1,
                  'msg'   => ['bunny is dead'],
                ],
                [
                  'level' => 1,
                  'msg'   => ["bunny\nis dead"],
                ],
                [
                  'level' => 1,
                  'msg'   => ["bunny","is\ndead"],
                ],
              ],
            ],
            [
              'level' => 0,
              'msg'   => ['everything is fine'],
            ],
          ],
        ],
        ###
        [
          'level' => 0,
          'msg'   => ['one','two','three','four','five'],
        ],
        [
          'level' => 0,
          'msg'   => ['one','two','three','four'],
        ],
        [
          'level' => 0,
          'msg'   => ['one','two','three'],
        ],
        [
          'level' => 0,
          'msg'   => ['one','two'],
        ],
      ],
    ],
  ],
],
# }}}
];
while (1)
{
  switch (show_menu()) {
  case '1':# {{{
    echo "TESTLOG: \n";
    echo ErrorLog::render($TESTLOG);
    break;
  # }}}
  case '2':# {{{
    echo "> PromiseResult:\n";
    $r = await(
      Promise::Func(function($r) {
        $r->info('information','is','very','good');
        $r->warn(
          'wow',"warning\nplus multiline message"
        );
        $r->info('standard positive message');
        $r->error(ErrorEx::fatal());
        $r->pupa();
        $r->confirm('title','number','one');
      })
      ->okay(function($r) {
        $r->info('this message never appears');
      })
      ->failFuse(function($r) {
        $r->warn('something bad happened but im going to fix it all!');
        $r->info('yes','yes','well done!!!');
        $r->info(# 1
          'this','title','is','oneliner'
        );
        $r->confirm('the test of promise result','complete');
      })
      ->okay(function($r) {
        $r->info('this message never appears');
      })
    );
    /***/
    echo ErrorLog::render($r);
    break;
  # }}}
  case '3':# {{{
    echo "DUMP: ";
    $r = await(
      Promise::Func(function($r) {
        $r->info('message #1')
          ->valueSet('value #1')
          ->valuePut('value #2')
          ->valuePut(12345)
          ->valuePut([1,2,3])
          ->valueSet('1-2-3')
          ->warn('message #2')
          ->fail('message #3')
          ->confirm('OPERATION','COOPERATION');
        ###
      })
      ->thenColumn([
        Promise::Func(function($r) {
          $a = 'element #1';
          $r->valuePut($a);
        }),
        Promise::Func(function($r) {
          $r->value = 'element #2';
          $r->fail('oops');
        }),
        Promise::Func(function($r) {
          $r->valueSet('element #3');
        }),
      ])
      ->then(function($r) {
        $r->confirm('subtitle');
        $r->confirm('main title');
      })
    );
    var_dump($r);
    echo "\n";
    break;
  # }}}
  case '4':# {{{
    echo "DUMP: \n";
    $r = await(
      Promise::Func(function($r) {
        $r->info('row results follow');
      })
      ->thenRow([
        Promise::Delay(10, function($r) {
          $r->valuePut('element #1');
        }),
        Promise::Func(function($r) {
          $a = 'element #2';
          $r->valueSet($a)->fail('oops');
        }),
        Promise::Func(function($r) {
          $r->valueSet('element #3-1');
          $r->valuePut('element #3-2');
          $r->valuePut('element #3-3');
        }),
      ])
    );
    var_dump($r);
    #echo ErrorLog::render($r);
    break;
  # }}}
  case 'q':
    echo "quit\n";
    break 2;
  }
}
function show_menu(): string # {{{
{
  echo <<<TEXT

 [1] ErrorLog::render([TESTLOG])
 [2] ErrorLog::render(<PromiseResult>)
 [3] dump Promise::Column
 [4] dump Promise::Row
 [q] quit

> 
TEXT;
  if (!($r = await(Conio::readch()))->ok)
  {
    echo "\n".ErrorLog::render($r);
    exit();
  }
  return $r->value;
}
# }}}
/***
if (0)
{
  file_put_contents(
    substr(__FILE__, 0, -4).'.out',
    #mb_convert_encoding($s, 'UTF-16BE', 'UTF-8')
    #iconv('UTF-8', 'CP866', $s)
    mb_convert_encoding($s, 'CP866', 'UTF-8')
  );
}
/***/
###
