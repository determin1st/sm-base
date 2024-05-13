# `\SM\Promise` - the promise
## thoughts on asynchronicity

masses may talk about the magic as long as it takes,
the fact that any function call is synchronous -
will not change - executor dives in and
later returns, thats how the call stack operates.

whether there is some uncommon jump/throw
out of the function - it shall not be considered
capable of asynchronous operation.

one of the major advantages of `SM\Promise`,
it doesnt split into "resolve" or "reject" or
"suspension" or other buzzwords
which are a goto/jumps out of the straight line.

the difference between synchronous and
asynchrounous call is the logic itself,
for example, an asynchronous `sleep` function
instead of delaying returns the means of delay,
almost instantly:

```php
$p = \SM\sleep(5000);# 5 seconds
sleep(2);# 2 seconds
```

the above code will take 2 seconds to complete,
because asynchronous sleep doesnt really do anything,
except creating the promise of the delay.

that's it, every asynchronous function returns only
the means to complete. those are designated for
the executor aka for the "event loop",
but caller prefers to name it a promise.

every promise is simply a container of
actions/operations that "tick" in the loop.

another difference, which is sort of obscure -
similar javascript code would take about 5 seconds,
because its promise is ***active*** -
it starts "ticking" right after creation.
same you see with the "futures"
proposed by the AMP project.
this concept [comes from][history] garbage..
yep, the garbage collector designs, where
"fully parallel evaluation" aka subtle,
implicit action is an inevitable requirement.

`SM\Promise` is ***passive***, it starts explicitly.
thus, this will end in 7 seconds:

```php
$p = \SM\sleep(5000);# 5 seconds
sleep(2);# 2 seconds
\SM\await($p);
```

## asynchroni(ci)ty for masses!

heard of promisification?
...

## what are effects?

effects are promises that do the impact and
which result you dont need.

either there is no necessity in the result, or
there is no reliable way to obtain the result
or the result is self-consumed, used internally,
somehow displayed, logged for later observation.

for example `\SM\sleep` shall do the impact of delay,
but there is no necessity in checking,
same as synchronous `usleep` returns `void`.


# `\SM\Loop` - the loop
(shitmap representation of row and column)

## promise row
each promise is placed in a row.

## promise columns
promises may be stacked one after another by identifier.
identifier identifies the column.
columns remove the burden of managing effects.


<!-- links {{{ -->

[history]: https://samsaccone.com/posts/history-of-promises.html

<!-- }}} -->

