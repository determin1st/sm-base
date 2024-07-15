# `\SM\Promise` - the promise

## asynchronous thought

everybody knows that asynchronous behaviour
relates to performance, to the blazingly fast
IO operations that greatly outperform synchronous
variants:

```php
$promise = \SM\sleep(2000);# ~0 seconds
\sleep(2);# ~2 seconds
```

the bluff is clearly illustrated above.
although both functions look similar and
have the same purpose, only synchronous did
all the job intended.

refined asynchronous variants usually return
something like promise of completion (passive) or
means of observation (active)..
anyway, it does only preparation for the job or
little part of the job.

head to head speed comparison
will always be after synchronous variant:
```php
$t1 = hrtime(true);
\SM\await(\SM\sleep(2000));# ~2 seconds
$t1 = hrtime(true) - $t1;

##########
### VS ###
##########

$t2 = hrtime(true);
\sleep(2);# ~2 seconds
$t2 = hrtime(true) - $t2;

if ($t1 < $t2) {
  echo "ASYNCHRONOUS IS FASTER!";
}
```
there might be a chance of the quirk
in synchronous sleep, so the message appears,
but generally - not,
because any correct asynchronous job has
***more overhead*** than its synchronous variant,
thus completes slower.
there is not much sense in async VS sync comparisons,
measuring the overhead is a nice method
to battle different async implementations.

the next drop is that asynchronous
means non-blocking and non-blocking means -
heck your sleeps, its not a real IO,
the real IO - is where async truly shines
with the speed and stuff.

imagine a cpu executor as a row in a debugger,
it meets with a function, dives in and out..
this blocks the execution of the current scope;
stressing the call stack alone -
doesnt produce asynchronous behaviour,
no matter how many `O_NONBLOCK` or `O_NDELAY`
flags you pass in, any function call blocks.

a better word exists in the microsoft documents,
somebody named asynchronous calls `OVERLAPPED`,
probably, it came from the multi-threaded
perspective, where one resource is shared among many.

imo, many is only useful hint from it,
blocking perspective suits better -
when something is asynchronous it is
***able to block on more than one thing***:
```php
\SM\await($p1, $p2);# also $p3, but implicitly
```
the code above is analogue of javascript
(and javascript is a good friend of PHP):
```javascript
await Promise.all(p1, p2);// p3 implicitly
```
so i consider "blocking on more than one" or
"blocking on many" as the correct wording
about asynchronicity.

what shall be correct about performance?
check this out:
```php

$p1 = \SM\sleep(1000);
$p2 = \SM\sleep(2000);

\SM\await($p1, $p2);# ~2 seconds

\sleep(1);\sleep(2);# ~3 seconds

```
here and anywhere,
the advantage appears only "en masse",
or, at least with 2 asynchronous jobs,
blocking on multiple targets allows
to merge some gaps of inactivity.

performance topic is quite covered,
so won't spend more bytes on that.
authors may misspell something or
write in fuzzy language,
but when encounter equalization of
"concurrent" and "parallel" -
know the hand of asynchronous amateur.

## active VS passive

https://medium.com/pocket-gems/promise-thenable-and-cancelable-timers-in-javascript-fcb5883dfe80

entering a shallow ground.
it started with some kind of "i call you back",
at least in javascript.
callbacks were utilized as asynchronous 


inclines that
job has already started
spectator

similar javascript code will execute
for about 5 seconds, or, maybe 7 seconds..


it becomes harder to tell exactly
because
as there is an area of magic where things

start to happen automatically, in the background.
***active*** promises start to "tick" immediately,
right after creation. same youll find with
the "futures" in the AMP project and all other places.
this concept [comes from][history] the garbage..
yep, the garbage collector designs,
where "fully parallel evaluation" aka subtle,
implicit action was an inevitable requirement.

contrary, the `SM\Promise` is ***passive***,
it starts explicitly:
```php
$promise = \SM\sleep(5000);# i want to sleep for 5 seconds
sleep(2);# sleep for 2 seconds
await($promise);# sleep for 5 seconds
```



...
there are other ways to start '\SM\Promise',
and, it will be cancelled on shutdown rather than
pushed to completion. (as javascript does).

...
another difference is the control flow -
"resolved" or "rejected" or "suspended"

a goto/jumps out of the straight line.

when there is some uncommon jump/throw
out of the function - it shall not be considered
capable of asynchronous operation.

every promise is a container of actions
that "tick" in the loop.

## await in await / nested loops
...

## asynchroni(ci)ty for masses!
...
heard of promisification?

## iterators
...

## obscure iterators (fibers)
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

# immediate promise controls
## repeatition
...
## delays
...
## expansion
...
## example
obtain 3 quotes with hurl
...

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

