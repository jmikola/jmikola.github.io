---
title: Designing the Next PHP and HHVM MongoDB Drivers
also:
    - http://www.mongodb.com/blog/post/call-feedback-new-php-and-hhvm-drivers
tags:
    - mongodb
    - php
    - hhvm
disqus_identifier: 55007b9af710fd5e149f0c63
---
> In the beginning [Kristina][kchodorow] created the MongoDB PHP driver. Now the
PECL `mongo` extension was new and untested, write operations tended to be
fire-and-forget, and Boolean parameters made more sense than `$options` arrays.
And Kristina said, "Let there be MongoCollection," and there was basic
functionality.

Since the PHP driver first appeared on the scene, MongoDB has gone through many
changes. Replica sets and sharding arrived early on, but things like the
aggregation framework and command cursors were little more than a twinkle in
[Eliot][ehorowitz]'s eye at the time. The early drivers were designed with many
assumptions in mind: write operations and commands were very different; the
largest replica set would have no more than a dozen nodes; cursors were only
returned by basic queries. In 2015, we know that these assumptions no longer
hold true.

Beyond MongoDB's features, our ecosystem has also changed. When the PHP driver,
a C extension, was first implemented, there wasn't yet a [C driver][libmongoc]
that we could utilize. Therefore, the 1.x PHP driver contains its own BSON and
connection management C libraries. [HHVM][hhvm], an alternative PHP runtime with
its own C++ extension API, also did not exist years ago, nor was [PHP 7.0][php7]
on the horizon. Lastly, methods of packaging and distributing libraries have
changed. [Composer][composer] has superseded PEAR as the de facto standard for
PHP libaries and support for extensions (currently handled by PECL) is
forthcoming.

During the spring of 2014, we worked with a team of students from
[Facebook's Open Academy][fb-openacademy] program to prototype an
[HHVM driver][mongo-hhvm-driver] modeled after the 1.x API. The purpose of that
project was twofold: research [HHVM's extension API][hhvm-extension-api] and
determine the feasibility of building a driver atop [libmongoc][] (our then new
C driver) and [libbson][]. Although the final result was not feature complete,
the project was a valuable learning experience. The C driver proved quite up to
the task, and HNI, which allows an HHVM extension to be written with a
combination of PHP and C++, highlighted critical areas of the driver for which
we'd want to use C.

This all leads up to the question of how best to support PHP 5.x, HHVM, and PHP
7.0 with our next-generation driver. Maintaining three disparate, monolithic
extensions is not sustainable. We also cannot eschew the extension layer for a
pure PHP library, like [mongofill][], without sacrificing performance.
Thankfully, we can compromise! Here is a look at the architecture for our
next-generation PHP driver:

![](/images/20150310-driver_arch.svg){.img-responsive}

At the top of this stack sits a pure PHP library, which we will distribute as a
Composer package. This library will provide an API similar to what users have
come to expect from the 1.x driver (e.g. CRUD methods, database and collection
objects, command helpers) and we expect it to be a common dependency for most
applications built with MongoDB. This library will also implement common
[specifications][specs], in the interest of improving API consistency across all
of the [drivers][] maintained by MongoDB (and hopefully some community drivers,
too).

Sitting below that library we have the lower level drivers (one per platform).
These extensions will effectively form the glue between PHP and HHVM and our
system libraries (libmongoc and libbson). These extensions will expose an
identical public API for the most essential and performance-sensitive
functionality:

 * Connection management
 * BSON encoding and decoding
 * Object document serialization (to support ODM libraries)
 * Executing commands and write operations
 * Handling queries and cursors

By decoupling the driver internals and a high-level API into extensions and PHP
libraries, respectively, we hope to reduce our maintainence burden and allow for
faster iteration on new features. As a welcome side effect, this also makes it
easier for anyone to contribute to the driver. Additionally, an identical public
API for these extensions will make it that much easier to port an application
across PHP runtimes, whether the application uses the low-level driver directly
or a higher-level PHP library.

[GridFS][gridfs] is a great example of why we chose this direction. Although we
implemented GridFS in C for our 1.x driver, it is actually quite a high-level
specification. Its API is just an abstraction for accessing two collections:
files (i.e. metadata) and chunks (i.e. blocks of data). Likewise, all of the
syntactic sugar found in the 1.x driver, such as processing uploaded files or
exposing GridFS files as PHP streams, can be implemented in pure PHP. Provided
we have performant methods for reading from and writing to GridFS' collections
– and thanks to our low level extensions, we will – shifting this API to PHP is
win-win.

Earlier I mentioned that we expect the PHP library to be a common dependency for
*most* applications, but not *all*. Some users may prefer to stick to the
no-frills API offered by the extensions, or create their own high-level
abstraction (akin to [Doctrine MongoDB][doctrine-mongodb] for the 1.x driver),
and that's great! [Hannes][bjori] has talked about creating a PHP library geared
for MongoDB administration, which provides an API for various user management
and ops commands. I'm looking forward to building the next major version of
[Doctrine MongoDB ODM][doctrine-mongodb-odm] directly atop the extensions.

While we will continue to maintain and support the 1.x driver and its users for
the foreseeable future, we invite everyone to check out our next-generation
driver and consider it for any new projects going forward. You can find all of
the essential components across GitHub and JIRA:

<table class="table">
    <thead>
        <tr>
            <th>Project</th>
            <th>GitHub</th>
            <th>JIRA</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PHP Library</td>
            <td markdown="1">[10gen-labs/mongo-php-library-prototype][gh-phplib]</td>
            <td markdown="1">[PHPLIB][jira-phplib]</td>
        </tr>
        <tr>
            <td>PHP 5.x Driver (phongo)</td>
            <td markdown="1">[10gen-labs/mongo-php-driver-prototype][gh-phpc]</td>
            <td markdown="1">[PHPC][jira-phpc]</td>
        </tr>
        <tr>
            <td>HHVM Driver (hippo)</td>
            <td markdown="1">[10gen-labs/mongo-hhvm-driver-prototype][gh-hhvm]</td>
            <td markdown="1">[HHVM][jira-hhvm]</td>
        </tr>
    </tbody>
</table>

The existing [PHP][jira-php] project in JIRA will remain open for reporting bugs
against the 1.x driver, but we would ask that you use the new projects above for
anything pertaining to our next-generation drivers.

  *[API]: Application programming interface
  *[CRUD]: Create read update delete
  *[HHVM]: HipHop Virtual Machine
  *[HNI]: HHVM native interface
  *[ODM]: Object document mapper
  *[PEAR]: PHP Extension and Application Repository
  *[PECL]: PHP Extension Community Library

  [bjori]: http://twitter.com/bjori
  [composer]: https://getcomposer.org/
  [derickr]: http://twitter.com/derickr
  [doctrine-mongodb]: https://github.com/doctrine/mongodb
  [doctrine-mongodb-odm]: https://github.com/doctrine/mongodb-odm
  [drivers]: http://docs.mongodb.org/ecosystem/drivers/
  [ehorowitz]: http://www.eliothorowitz.com/
  [gridfs]: http://docs.mongodb.org/manual/core/gridfs/
  [fb-openacademy]: https://www.facebook.com/notes/facebook-engineering/facebook-open-academy-bringing-open-source-to-cs-curricula/10151806121378920
  [hhvm]: http://hhvm.com/
  [hhvm-extension-api]: https://github.com/facebook/hhvm/wiki/Extension-API
  [kchodorow]: http://www.kchodorow.com/
  [mongo-hhvm-driver]: https://github.com/10gen-labs/mongo-hhvm-driver
  [gh-phplib]: https://github.com/10gen-labs/mongo-php-library-prototype
  [gh-phpc]: https://github.com/10gen-labs/mongo-php-driver-prototype
  [gh-hhvm]: https://github.com/10gen-labs/mongo-hhvm-driver-prototype
  [jira-phplib]: https://jira.mongodb.org/browse/PHPLIB
  [jira-php]: https://jira.mongodb.org/browse/PHP
  [jira-phpc]: https://jira.mongodb.org/browse/PHPC
  [jira-hhvm]: https://jira.mongodb.org/browse/HHVM
  [libbson]: https://github.com/mongodb/libbson
  [libmongoc]: https://github.com/mongodb/mongo-c-driver
  [mongofill]: https://github.com/mongofill/mongofill
  [php7]: https://wiki.php.net/rfc/php7timeline
  [specs]: https://github.com/mongodb/specifications
