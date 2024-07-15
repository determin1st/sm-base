## parallelism

parallelism is a part of concurrency
that is represented by a set of processes or threads
communicating with each other in a client-server fashion.

### parent-child

a process that spawns another process
is called parent because relationship between them
is loose aka undefined.

for example, a shell that starts a program
(the main purpose of the shell)
is a parent process to that program and
the program is a child process.

child typically inherits a bunch of system related
things from the parent, like
evironment variables, current/working directory,
console/terminal window.. etc etc.
some of those are tuneable,
but usually stay predictable defaults.

when parent process quits,
children keep on running orphaned.

### master-slave

master-slave relationship appears only upon
communication between parent and child.

in the process to process communication,
aka in the exchange, a parent is one that writes first,
so it represents a client. a child therefore reads and
answers, so it represents a server.

a slave (server) must obey to any command (request written),
otherwise master should forcibly terminate it.

the very minimal set of commands is a termination command.
when master process quits, all slaves must terminate.



how do you like my initial design of `\SM\Process`?
is it pervert friendly?




<!-- links {{{ -->

<!-- }}} -->
