# `Con`sole `i`nput and `o`utput
## about
`\SM\Conio` represents a static interface
to the terminal/console. It operates directly
with the input and output file descriptors/handles,
avoiding the possible redirection of
the standard input and output (STDIN/STDOUT).

most console-oriented tasks are related
to the `i`-input - recieving, parsing and decoding it
into consumable events. the `o`-output routine
is limited and mostly transparent -
the standard PHP output buffer is reused,
it is accumulated and flushed periodically.
this implies some level of writing asynchronicity
but underlying subsystem may expand it further,
for example [alertable IO][alertable]
is utilized on modern windows system.

`SM\Conio` is a part of asynchonous state machine -
its methods return `SM\Promise` objects.


## api spec
<details>
<summary><code>Conio::init(): ?<u>ErrorEx</u>
</code></summary>

lets call it "initialize the terminal",
it must be called first, prior to any other method:
```php
if ($e = Conio::init())
{
  # initialization failed for some reason,
  # display the details and terminate
  echo ErrorLog::render($e);
  exit;
}
# no problemo, continue
```
what it does is mostly identification of capabilities
and switching into so-called "raw" mode where more
input information could be fetched.

</details>
<details>
<summary><code>Conio::read(): <u>Promise</u>
</code></summary>


reads all the input events. events?

### event model
...
resize/scroll and focus events are debounced
based on the previous state - when multiple events
appear in-between reads, only the final one
that causes the state change is reported.
if the state is not changed - it is not generated.
...
mouse move or drag event on nix-based terminals
is debounced based on the previous coordinates -
only the change in coordinate produces new event.
...

</details>
<details>
<summary><code>Conio::readch(<u>int</u> $n=1): <u>Promise</u>
</code></summary>


reads the specified **n**umber of characters,
discrading every other event type
</details>
<details>
<summary><code>Conio::readline(<u>array</u> $o=[]): <u>Promise</u>
</code></summary>

reads input as line of characters,
simple edits are included by default,
extended/custom handling is possible
within the handler routine.
</details>
<details>
<summary><code>Conio::drain(): <u>Promise</u>
</code></summary>

a simple wait promise that settles
when all the output is drained (written):
```php
echo "hello world!";
await(Conio::drain());
# all is written at this point
```
</details>
<details>
<summary><code>Conio::query(<u>string</u> $q): <u>Promise</u>
</code></summary>

TODO?
ESC code / request-response
...
</details>


<!-- links {{{ -->

[alertable]: https://learn.microsoft.com/en-us/windows/win32/fileio/alertable-i-o

<!-- }}} -->
