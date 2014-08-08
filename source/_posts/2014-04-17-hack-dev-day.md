---
title: Hack Developer Day Recap
tags:
    - conference
    - facebook
    - hhvm
    - hacklang
    - mongodb
disqus_identifier: 5367e7c5a8780bca7b88158d
---
Last week, Facebook invited 150 developers to [building 15][fb-15] on their
Menlo Park campus for Hack Developer Day 2014, the first of hopefully many
full-day events for all things [Hack] and [HHVM]. The event, which was free for
anyone that could get themselves over to Facebook's office, sold out between the
time I found out about it and was able to approve my travel. I'm very thankful
to [Bryan O'Sullivan][bosullivan] for finding me a spot at the last minute, and
[Francesca Krihely][fkrihely], MongoDB's community manager, for telling me about
it in the first place.

Facebook had their own [recap][fb-recap] of the event posted before my plane
landed back on the East Coast, but I had several pages of presentation notes and
a recently renewed commitment to blogging, so here we are.

### Introducing Hack <small>with Julien Verlaguet</small>

While [HHVM] has had its fair share of publicity over the past year, details
about [Hack] have only recently come to surface. Julien started off the day with
an introduction into the how the language integrates with HHVM and a tour of its
type-checking features. Since being introduced to HHVM, Hack has been an opt-in
feature unlocked by using `<?hh` in lieu of `<?php`. As we'd hear throughout the
day, this design was vital to making Hack's adoption within Facebook as painless
as possible. Developers were free to pick up Hack on a file-by-file basis and
fall back to PHP mode as needed. More importantly, there was never a need to
migrate millions of lines of legacy code over to Hack in one shot.

Hack operates in one of three [modes][hack-modes]: strict, partial (the
default), or decl. Strict mode appeals to the pedant in all of us, and activates
a stringent level of type checking that rules out any interoperability with
untyped code. Partial and decl modes are more forgiving and will actually allow
the developer to call into PHP. Decl mode also serves a purpose when migrating
legacy code to Hack, as typed function signatures will be sufficient to satisfy
a call from strict mode.

For better or worse, PHP developers are accustomed to an instant feedback loop.
We can save, refresh a browser tab, and immediately see the effects of our code
changes. With this in mind, Facebook created a `hh_client` utility, separate
from the HHVM server, which can quickly (e.g. 100ms) scan a project's entire
source tree for type errors. Aside from speed, flexibility was an important
consideration. The tool is easily integrated into most editors as a build
command.

One thing that stood out during Julien's talk was his explanation of how Hack
handles unresolved types. Hack's type checking sits atop the dynamic nature of
PHP; it doesn't replace it. This means that it needs to gracefully handle
ambiguous situations such as `$x = $cond ? 1 : true`. The variable `$x` is not
declared with a type and may be an integer or boolean after the ternary
assignment. Hack ends up deferring type resolution until it's absolutely
necessary (e.g. `$x` is being passed to a function). We see similar behavior
when using generics, such as the built-in [Collection][hack-collections]
classes:

> The bottom line here is that until this generic collection is exposed to code
that takes or returns a type that will cause incompatibility, the type checker
will be relaxed as to what it allows to be added.[^1]

Before leaving the stage, Julien gave us a brief [preview][ide-preview] of an
in-browser IDE for Hack development. One immediate question was whether this was
built atop an existing project, such as [Ace] or [Atom]. Facebook confirmed that
it was an organic project that had been in development for at least a year. In
the ten seconds it was on screen, we saw some code intel and type checking in
action. In a later session, we'd learn that the editor sported an embedded
JavaScript port of `hh_client`, SCM integration, and debugging facilities to
step through an application request launched in a second browser tab.

### Converting PHP to Hack <small>with Josh Watzman</small>

Josh may be a familiar to those on the PHP-FIG mailing list. Last year, he
[reached out][fig-invite] to the list and invited members to preview Hack before
the project was formally announced. Before joining the Language and Tools team,
Josh used Hack (or a previous iteration of it) on the News Feed team for two
years. In this session, he would share a bit about Facebook's workflow for
migrating legacy PHP code over to Hack syntax.

The first part of this process is the `hackificator` command, which does exactly
three things:

 1. Replaces `<?php` with `<?hh` one file at a time. Strict, partial, and decl
    modes are attempted in order, and the file reverts back to `<?php` if none
    of the modes are valid.

 2. Add nullable annotations (e.g. `?string`) to argument types where `null` was
    specified as a default value.

 3. Supply missing constructor arguments as need (e.g. `new Foo;` becomes
    `new Foo();`).

Manual intervention will still be required, but `hackificator` does a fine job
of automating some tedious changes. Before running the tool, Josh began with a
review of how Hack's partial and decl modes interoperate with untyped PHP:

> In the general principle of Hack, if there's something that we can't determine
if it's right or wrong, we assume that the programmer knows what they're doing
and that it's right.

Using [hack-conversion-demo] as an example, Josh walked through conversion of a
basic class inheritance hierarchy from PHP to Hack in two fashions: depth and
breadth. Starting with depth mode, he converted class files to partial Hack
where possible and deferred to decl mode as needed (e.g. when a child class
method referenced an undeclared, untyped property). The alternative breadth
conversion took advantage of "the general principle of Hack" and saw us convert
child classes to partial Hack and leave the parent class as untyped PHP. Each
approach has its pros and cons. Depth allowed for better coverage through the
inheritance hierarchy, while breadth achieved greater coverage overall.

The second step of the conversion process entails running `hh_server --convert`,
which examines functions and class properties and attempts to infer suitable
supertypes that satisfy the type checker. These are referred to as soft types,
as there is no guarantee that the inference will be valid at runtime. The
resulting soft types will be prefixed with `@`, which instructs Hack to
downgrade type violations from fatal errors to loggable warnings.

The final conversion step requires error logs from our application (either unit
test runs or production logs were suggested), which should indicate if any type
inferences added by `hh_server --convert` are invalid. With logs in hand, the
`hack_remove_soft_types` command can be used in one of two modes:

 1. Parse HHVM's error logs for soft type warnings, delete those log entries,
    and remove the corresponding type annotations.

 2. Harden type annotations within a file by removing `@` error suppression.

This entire process is covered in more detail in the
[Hack conversion][hack-conversion] documentation.

### HHVM on Heroku <small>with Craig Kerstiens and Peter van Hardenberg</small>

Before we adjourned for lunch, Craig and Peter took the stage to briefly talk
about recent developments at Heroku for deploying to HHVM. A
[community-supported buildpack][buildpack-hhvm] has existed since last year, but
[David Zuelke][dzuelke] has been hard at work over the past month on a more
robust [solution][buildpack-dzuelke]. Peter's [demo][heroku-hack-demo], which is
a fork of Facebook's basic demo, is published on GitHub.

### Hack Language Features <small>with Drew Paroski and Eugene Letuchy</small>

Post-lunch sessions are often a snoozefest, but Drew and Eugene kept everyone's
attention with one of the most interesting presentations of the day. The talk
jumped through a host of new features to Hack, from small variations to PHP
syntax to more significant additions, such as async functionality.

[Lamba expressions][hack-lambda] were one of the first topics. Defined with a
literal syntax via the `==>` operator, these are an alternative to anonymous
functions and should be accepted by any code that expects a PHP callable. One
important distinction is that lamba's inherit their scope implicitly. This is
similar to how closures operate in JavaScript, and means that PHP's `use`
statement is no longer necessary.

[Collections][hack-collections] are an alternative to using PHP arrays for
anything and everything. The `Vector`, `Map`, and `Set` types should need no
introduction. All collection types can be defined with a literal syntax, are
fully compatible with [generics][hack-generics], and have a corresponding
immutable class. Immutability can be useful for type checking and allows for
some performance optimization in HHVM. Additionally, the collection classes have
several functional methods (e.g. `map()`, `filter()`), which are reminiscent of
Doctrine's [Collection][doctrine-collection] interface. As you might expect, the
collection methods place very nicely with Hack's concise lambda syntax.

If I could pick one feature to pluck from Hack and drop into PHP core today, it
would be collections hands down. A recurring issue while developing the MonogDB
PHP driver and Doctrine ODM has been property converting arrays to and from
[BSON]. Distinguishing associative arrays from sequential, numerically-indexed
arrays, is a [trivial algorithm][php-json], but satisfying the user's
expectations for behavior is a neverending challenge. [PHP-1051] is one such
example.

While most of the room was wiping drool from their chins after Drew's collection
demo, Eugene took the stage to walk us through Hack's async support. If you
had read most of the online discussions in the month following Hack's release,
you would conclude that (a) nearly everyone thought this feature was amazing
and (b) hardly anyone understood how it actually worked. To be fair, there
originally wasn't much documentation outside of a few scripts in the HHVM test
suite.

Eugene aptly described Hack's async implementation as "cooperative multi-tasking
within a request." It does not attempt to mimic a full asynchronous framework
that we find in Node.js or [React]. Instead, Hack allows functions and callers
to opt-in to asynchronous execution via the `async` and `await` keywords,
respectively. Hack's compiler will then apply some transformation to the code
under the hood, similar to what is done for generators. At runtime, async
functions will return an `Awaitable<T>` instance immediately. Calling code is
therefore able to invoke multiple async functions and finally call `join()` on
the `Awaitable` when its time to block until the result(s) are available. As a
rule of thumb, `join()` is best left to the top-most scope of the request (e.g.
before a controller might need to return a response).

Hack also made some small improvements to traits. While traits can be a boon for
reducing code duplication, they introduce a pain point when it comes to API
dependence, particularly for Hack's type checker. In practice, traits are often
destined to be used within a class hierarchy, but PHP offers no way for a trait
to restrict how it should be used. Hack allows traits to require that its using
class be an instance of a particular class or interface.

User attributes were next on the list. In short, these are Hack's alternative to
PHP docblock annotations. The syntax was immediately recognizable to me, since
I've been working with an [Open Academy][fb-openacademy] class this semester to
prototype a [MongoDB driver for HHVM][mongo-hhvm-driver]. HHVM extensions have
the option of being written in pure PHP (actually Hack syntax) or a blend of PHP
and C++ using HNI. In the latter case, PHP functions can be declared without a
body and prefixed `<<__Native>>` (a user attribute) to instruct HHVM to look for
a corresponding C++ implementation. In our project, most of the driver can be
written in PHP, but some functions resort to C++ in order to interact with
[libmongoc] and [libbson].

Before this talk, I wasn't aware that user attributes were utilized for other
language features outside of HNI. For instance, the ``<<Override>>`` attribute
functions as a child class' counterpart to `abstract` and requires the method
to exist in a base class. This can be used to prevent leaving behind dead code
after refactoring out methods from base classes.

One significant difference between user attributes and familiar PHP annotations
is that the attributes are first-class citizens in Hack's syntax. With
Doctrine's [annotations][doctrine-annotations] library, we need to resort to
parsing docblocks at runtime; however, Hack makes attributes easily accessible
through `getUserAttributes()` method in the Reflection API. Beyond that, they
sport similar features to existing PHP annotations and can be attached to
classes, functions, and member properties. Similar to Doctrine's annotations,
user attributes can have parameters. At present, Hack supports array and scalar
values; support for collection type literals (e.g. `Map`) may come later.

Last but not least, we had a quick blurb on trailing commas. One of PHP's minor
idiosyncrasies has been allowing trailing commas after the last element in an
array, but not after the final argument in a function declaration. In the
interest of reducing noise in diffs for multi-line function declarations, Hack
fixed this. We heard it was a huge hit with [Paul Tarjan,,,][ptarjan].

### HHVM Status Update <small>with Sara Golemon and Paul Tarjan</small>

Paul and Sara came on to update the audience on HHVM's development progress.
This session likely had the broadest appeal of any, as HHVM has garnered the
attention of many PHP developers who have no intention of abandoning ship for
Hack's new syntax but are interested in a faster runtime for their applications.
For libaries and frameworks such as Doctrine and Symfony, both of which were
well-represented in the audience, we'd like to ensure that our projects work as
well with HHVM as they do with PHP proper. On a related note, the HHVM team also
had a [Open Academy][fb-openacademy] class this semester assigned to moving a
handful of open-source PHP projects closer to 100% test suite compatibility
(all progress is being tracked [here][hhvm-frameworks]).

For those that have seen Sara present at HHVM over the past year at the various
PHP conferences, this session had few surprises. She began with an overview of
HHVM's release schedule. Facebook aims to publish a new major release each year.
Minor releases are expected every eight weeks, which syncs up with every fourth
Facebook production release (those happen once a fortnight). As an homage to the
"HH" in HHVM, each of the 26 releases this year are named after hip hop artists.
There's still time to submit your suggestions for next year's category.

Now that HHVM has started to appear in Linux package managers, Facebook will
commit to supporting older major versions of HHVM with bug and security fixes as
needed. Sara also hinted that the package structures should mimic PHP, so we
could expect to see `hhvm-dbg` and `hhvm-dev` packages for debug builds and
development headers, respectively, in the near future. The `hhvm-dev` package
cannot arrive soon enough, as extension development currently requires building
HHVM from source, which makes [Travis CI] integration a pipe dream.

Support for platforms other than x86 Linux is also on the agenda. Facebook
enlisted all five feet and two inches of [Elizabeth Smith][esmith] to bring
Windows compatibility up to speed, and progress is also being made on OSX and
ARM. Compatibility for HHVM's JIT and interpreter modes are tracked separately,
but the end goal is obviously full support for both on each platform.

Throughout the presentation, Paul and Sara gave frequent shoutouts to the HHVM
community outside of Facebook. Nearly every slide had a couple of faces in the
margin, highlighting some of the project's most active contributors. Special
mention went to [Simon Welsh][swelsh], who has single-handedly converted more
than 20 HHVM extensions from IDL to HNI. This is all part of a team-wide effort
being tracked [here][hhvm-1480]. Sara candidly admitted that the original IDL
API for HHVM's extensions was bad enough to make PHP's own C API look appealing.
HNI is a huge improvement and has really accelerated the development of new
extensions, MongoDB's driver project included.

On a related note to HHVM extensions, I should mention that [Nils][nadermann]
was in attendance for the event (representing [Composer] and [phpBB]). He
recently began working on a [feature][composer-2898] to support installation of
PHP and HHVM extensions. Composer has long supported dependencies on PECL
extensions, but users were always left to their own devices when it came to
installing them (no easy task on Windows). Recently, PECL has begun automating
Windows builds for extensions in its repository, which overcomes one hurdle.
Assuming the feature comes along as planned, we could ultimately see Composer
picked up as HHVM's de facto extension delivery tool down the line.

One last topic to address was Facebook's commitment to keeping HHVM's
development open. Based on questions asked throughout the day, it was obvious
that Facebook has quite a bit of FUD to overcome when it comes to promoting
adoption of Hack and, to a lesser extent, HHVM. Sara said that code reviews will
happen on [reviews.facebook.net], a public-facing [Phabricator] deployment.
Currently, the [GitHub repository][hhvm-github] is synced once daily, but there
are plans to reduce that latency and implement a two-way sync. The HHVM team is
also considering opening up their biweekly design meetings to the public via
either live video or recordings.

### HHVM Performance <small>with Edwin Smith</small>

We started the day with discussions about type checking, high-level Hack syntax,
and PaaS computing platforms. Edwin was about to take us to the opposite end of
the computing spectrum with a tour of processor dies and assembly code, but not
before telling us a story.

Five years ago, Facebook switched over to HipHop, the infamous PHP-to-C++
compiler and predecessor of HHVM. The company had an internal goal to fully
migrate over to HHVM's JIT by the end of 2012; however, in early 2012 the HHVM
team realized they would need to triple the project's performance in order to
meet that goal. This lead to their first lockdown, which would last six weeks.
The team isolated themselves, grew out their beards (for what it's worth,
[Sara did try][beards]), and focused on racking up as many performance wins as
possible over the next 42 days. Three weeks in, they had hit their goal, but
decided to press on.

Each development sprint now follows a rhythmic schedule: build things, measure,
generate ideas, lockdown, and cleaning up technical debt. Last fall, the team
[blogged][lockdown-before] about their most recent lockdown, which focused on
improving performance and compatibility with leading PHP projects. The
[results][lockdown-after] spoke for themselves.

From here, Edwin segued into explaining how Facebook uses PHP and the team's
approach to unlocking the performance improvements they needed out of HHVM.
Facebook's PHP workload makes heavy use of APC as a key/value store (on the
order of 100s of MB per server). Memory heaps for requests range anywhere from
10-100MB, most of which is arrays and strings (News Feed is one example).
Additionally, requests sometimes need to pull data from external data sources
that aren't in memory. Between network IO, local storage, and RAM, Facebook has
to be concerned with the [latency] of everything. Ultimately, the goal is to
keep the CPU's cores as preoccupied as possible. Idle time is the enemy.

One illustration given was the progression from HipHop's early ZendArray data
structure, designed after its namesake in PHP core, to HphpArray, which aligned
values closer together in memory to require less jumps from CPU cache to RAM.
MixedArray, introduced in 2013, takes this a step pointer and optionally allows
for the entire array to be pulled into cache without individual pointers for its
elements (referred to as PackedArray). Similar improvements were made to the
native string type.

Beyond data structures, HHVM also needs to optimize the code itself. This ranges
from minimizing jumps, which require register stashing, by analyzing a running
program for the most common execution path, to ensuring that functions within
HHVM's own binary are placed efficiently. Using a suite of performance tools,
the team located the "hot" functions and took steps to ensure they were linked
nearest each other in memory. Referred to as "Bert's Heuristic", this unlocked
an unheard-of 4% performance gain in one shot.

Edwin is optimistic that the HHVM team has at least four to five years of
performance wins ahead of them before they hit a wall. There are still gains to
be made with HHVM's garbage collector (JVM is far more advanced), and the team
has yet to really leverage Hack's type annotations with a region compiler to
eliminate redundant guard statements.

### Facebook IDE <small>with Joel Pobar and Joel Beales</small>

For our last session, "The Joels" came on to give us a closer look at the
in-browser IDE that Julien revealed in the morning. Mr. Beales walked us through
the IDE using JoelBook as an example. As a bit of background context, JoelBook
has been described by leading venture capitalists as "Facebook for people named
Joel." Folks on Hacker News have already hailed it as "the killer app of 2014."
For reasons not entirely clear, the audience seemed more interested in the IDE.

The Facebook IDE is already being used and enjoyed within the company for Hack
development. Beyond type checking and auto-completion, the built-in debugging
functionality, which is currently integrated with Facebook's dev environment,
looked quite user-friendly (akin to debugging JavaScript with Chrome or
Firebug). Facebook intends to make a version of the IDE publicly available in
the summer of this year.

### Hackathon

After the closing remarks, Facebook reconfigured the room for an evening
hackathon. I have to confess that I spent most of this time working through a
backlog of Doctrine issues while most other folks were trying out [Hack]. I did
manage to compile [HHVM] from source over the ensuing three hours. We recently
updated [mongo-hhvm-driver] from 2.4.x to 3.0.x and I realized I didn't have the
proper development headers available locally (*this* is why we need
`hhvm-dev`!). Kudos to whomever it was that reminded me of the `-j` compiler
option, as it made things much more bearable. I'll say nothing of
[Victor Berchet][vberchet]'s efforts to assassinate my character:

<blockquote class="twitter-tweet" align="center" lang="en"><p>Everybody work hard at the <a href="https://twitter.com/search?q=%23hackdevday&amp;src=hash">#hackdevday</a> while <a href="https://twitter.com/jmikola">@jmikola</a> keeps drinking.... <a href="http://t.co/gfBIA9BbPf">pic.twitter.com/gfBIA9BbPf</a></p>&mdash; Victor Berchet (@vberchet) <a href="https://twitter.com/vberchet/statuses/454095137103024128">April 10, 2014</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

During the hackathon, I had a moment to poke [Fabien][fabpot] about his thoughts
on the day's events as [Symfony] edges closer to 100% test suite compatibility
with HHVM. While there's no indication that Symfony would ever drop PHP, Fabien
was talking with several Hack developers about the feasibility of automating a
PHP-to-Hack conversion based on docblock comments and some of the tools shown
in Josh's earlier presentation. Take this with a grain of salt, but if that idea
comes to fruition, it could allow Symfony to be utilized in a strict Hack
project. Additionally, Hack's type checking could conceivably be integrated into
Symfony's CI process to catch deviations between documented types and runtime
behavior.

For those already using Hack and looking for a web framework, there are a
couple of options out there already. [Simon Welsh][swelsh] and
[James Miller][jmiller], who work together at [PocketRent] and both flew out
from New Zealand to attend to event, are actively developing [beatbox].
Unfortunately, searching for "hack framework" is futile, and the results only
get worse if you add "PHP" to the mix. Hopefully the HHVM team can compile a
list of projects in a future blog post.

### Conclusion

This was the first Facebook event I've attended, excluding last year's field
trip after ZendCon when 25 PHP developers all attempted to visit under
[Sara][sgolemon]'s name (apparently there's a limit for that sort of thing).
The staff, everyone from speakers to catering to the AV crew, did an amazing job
throughout the day. The event was also streamed live for those that couldn't
physically attend, so [videos][fb-videos] are available of each presentation and
the Q&A sessions that followed.

Since the event was held on a Wednesday, I spent the remainder of the week
working at MongoDB's Palo Alto office with [Hannes][bjori]. Until he moves to
New York (fingers crossed), the PHP team ([Derick][derickr] included) is spread
out across eight hours of time zones, which makes any sort of in-person
collaboration a rare treat. We have some exciting plans in store for the PHP
driver as we hope to port it over to [libmongoc] and [libbson]. Combined with
some learnings from the HHVM research I've been leading, I'm hopeful that we'll
be in a good place next year to efficiently support multiple platforms (PHP C,
HHVM, and pure PHP, à la [mongofill]) with a set of high-level driver components
(published as [Composer] packages) and a couple of platform-specific, no-frills
core drivers.

It was a great week for learning, reconnecting with old friends, and meeting new
ones. I have no idea when I'll make it back to Palo Alto again, but you can bet
the very first place I'll visit (after the obligatory In-N-Out stop) will be
[Philz Coffee] to order a Mint Mojito Iced Coffee.[^2]

![](/images/20140417-philz_coffee.jpg "Mint Mojito Iced Coffee from Philz Coffee"){.img-responsive .img-rounded}

  [^1]: [Generics and Type Inference][hack-inference]

  [^2]: I gave up trying to find a natural way to work a mention of this into
        the post, but suffice it to say this beverage is utterly amazing.

  *[CI]: Continuous integration
  *[FUD]: Fear, uncertainty, and doubt
  *[HNI]: HHVM native interface
  *[IDE]: Integrated development environment
  *[IDL]: Interface description language
  *[JIT]: Just-in-time
  *[JVM]: Java virtual machine
  *[PaaS]: Platform as a service
  *[SCM]: Source code management

  [Ace]: http://ace.c9.io/
  [Atom]: http://atom.io/
  [beards]: http://hhvm.com/wp-content/uploads/2013/12/2013-11-22-16.11.241.jpg
  [beatbox]: https://github.com/PocketRent/beatbox
  [BSON]: http://bsonspec.org/
  [buildpack-hhvm]: https://github.com/hhvm/heroku-buildpack-hhvm
  [buildpack-dzuelke]: https://github.com/dzuelke/heroku-buildpack-php
  [Composer]: http://getcomposer.org/
  [composer-2898]: https://github.com/composer/composer/pull/2898
  [doctrine-annotations]: https://github.com/doctrine/annotations
  [doctrine-collection]: https://github.com/doctrine/collections/blob/v1.2/lib/Doctrine/Common/Collections/Collection.php
  [fb-recap]: https://code.facebook.com/posts/683726355017955/hack-developer-day-recap/
  [fb-group]: https://www.facebook.com/groups/hackdevday14/
  [fb-videos]: https://www.youtube.com/playlist?list=PLb0IAmt7-GS2fdbb1vVdP8Z8zx1l2L8YS
  [fb-openacademy]: https://www.facebook.com/notes/facebook-engineering/facebook-open-academy-bringing-open-source-to-cs-curricula/10151806121378920
  [fb-15]: https://foursquare.com/v/facebook-mpk-15/504e4421e4b0deb86ca8b33a
  [fig-invite]: https://groups.google.com/d/msg/php-fig/iwMXyrruwvk/z_ZELhZBAU8J
  [Hack]: http://hacklang.org/
  [hack-collections]: http://docs.hhvm.com/manual/en/hack.collections.php
  [hack-conversion]: http://docs.hhvm.com/manual/en/install.hack.conversion.php
  [hack-conversion-demo]: https://github.com/hhvm/hack-conversion-demo
  [hack-generics]: http://docs.hhvm.com/manual/en/hack.generics.php
  [hack-inference]: http://docs.hhvm.com/manual/en/hack.generics.typeinference.php
  [hack-lambda]: http://docs.hhvm.com/manual/en/hack.lambda.php
  [hack-modes]: http://docs.hhvm.com/manual/en/hack.modes.php
  [heroku-hack-demo]: https://github.com/pvh/hack-example-site
  [HHVM]: http://hhvm.com/
  [hhvm-1480]: https://github.com/facebook/hhvm/issues/1480
  [hhvm-frameworks]: http://hhvm.com/frameworks/
  [hhvm-github]: https://github.com/facebook/hhvm/
  [ide-preview]: https://twitter.com/jmikola/status/453954102150434816
  [latency]: http://gist.github.com/jboner/2841832
  [libbson]: https://github.com/mongodb/libbson
  [libmongoc]: https://github.com/mongodb/mongo-c-driver
  [lockdown-before]: http://hhvm.com/blog/1499/locking-down-for-performance-and-parity
  [lockdown-after]: http://hhvm.com/blog/2813/we-are-the-98-5-and-the-16
  [mongo-hhvm-driver]: https://github.com/10gen-labs/mongo-hhvm-driver
  [mongofill]: https://github.com/mongofill/mongofill
  [reviews.facebook.net]: https://reviews.facebook.net/
  [Phabricator]: http://phabricator.org/
  [Philz Coffee]: https://foursquare.com/v/philz-coffee/4dd1580eb3adb047f5024231
  [philz-mojito]: https://foursquare.com/v/philz-coffee/4dd1580eb3adb047f5024231/photos?openPhotoId=53443a3f498ef511366cbc41
  [PHP-1051]: https://jira.mongodb.org/browse/PHP-1051
  [php-json]: https://github.com/mongodb/mongo-php-driver/blob/1.5.1/contrib/php-json.c
  [phpBB]: https://www.phpbb.com/
  [PocketRent]: https://pocketrent.com/
  [React]: http://reactphp.org/
  [Symfony]: http://symfony.com/
  [Travis CI]: http://travis-ci.org/

  [bjori]: https://twitter.com/bjori
  [bosullivan]: https://twitter.com/bos31337
  [derickr]: https://twitter.com/derickr
  [dzuelke]: https://twitter.com/dzuelke
  [esmith]: https://twitter.com/auroraeosrose
  [fabpot]: https://twitter.com/fabpot
  [fkrihely]: https://twitter.com/francium
  [jmiller]: https://twitter.com/MrAatch
  [jtorres]: https://twitter.com/onema
  [nadermann]: https://twitter.com/naderman
  [ptarjan]: https://twitter.com/ptarjan
  [swelsh]: https://twitter.com/simon_w
  [sgolemon]: https://twitter.com/SaraMG
  [vberchet]: https://twitter.com/vberchet
