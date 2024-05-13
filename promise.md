# `\SM\Promise` - the promise
## some thoughts about

masses may talk about the magic as long as it takes,
the fact that on every function call the executor
dives in and out stressing the call stack - wont change,
naming it synchronous or asynchronous doesnt matter.

the difference between synchronous and
asynchrounous call is only the logic,
for example, asynchronous `sleep` doesnt sleep or delay
according to its argument, but returns the means of delay:

```php
$promise = \SM\sleep(5000);# want to sleep 5 seconds
sleep(2);# 2 seconds
```

the above code shall take 2 seconds to complete,
because wanting isnt acting, but similar javascript code,
for example, will execute for about 5 seconds
or maybe 7 seconds.. it kind of becomes a magic,
because promises in javascript are ***active*** -
they start "ticking" right after creation.
same youll find with the "futures" in the AMP project,
and other places. this concept [comes from][history]
the garbage..  ye, the garbage collector designs,
where "fully parallel evaluation" aka subtle,
implicit action was and is an inevitable requirement.

contrary, the `SM\Promise` is ***passive***,
it starts explicitly:

```php
$promise = \SM\sleep(5000);# want to sleep 5 seconds
sleep(2);# 2 seconds
await($promise);# sleep 5 seconds here
```

...
another difference is the absense of states
obscure states named "resolved" or "rejected" or "suspended" or other stateword.
it can be 
which are a goto/jumps out of the straight line.

when there is some uncommon jump/throw
out of the function - it shall not be considered
capable of asynchronous operation.

every promise is a container of actions
that "tick" in the loop.



## asynchroni(ci)ty for masses!

heard of promisification?
...

## effects

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

