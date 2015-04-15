---
title: A Consistent CRUD API for Next Generation MongoDB Drivers
also:
    - http://www.mongodb.com/blog
tags:
    - mongodb
disqus_identifier: 55256f29d01a873fb40608ec
draft: true
---
One of the more notable challenges with maintaining a suite of
[drivers][drivers] across many languages has been following individual language
idioms while still keeping their APIs consistent with each other. For
example, the Ruby driver should *feel* like any other Ruby library when it comes
to design and naming conventions. At the same time, the behavior for API calls should be the same across all drivers.

Towards the end of 2014, a handful of MongoDB driver developers started working
on a [CRUD API specification][crud] for our [next generation drivers][nextgen].
The CRUD acronym refers to create, read, update, and delete operations, which
are commonly found on each driver's Collection interface. In truth, the spec
covers a bit more than those four methods:

 * Create
 * Read
 * Update
 * Delete
 * Count
 * Replace
 * Aggregate
 * Distinct
 * Bulk, One or Many
 * Find and Modify

For obvious reasons, we decided to do without the full CRUDCRADBOoMFaM acronym
and stick with CRUD.

Compared to the [Server Selection][selection] and [SDAM][sdam] specifications,
which deal with internal driver behavior, the CRUD API is a high-level
specification; however, the goal of improving consistency across our drivers is
one and the same. To ensure that multiple language viewpoints were considered in
drafting the spec, the team included Craig Wilson (C#), Jeff Yemin (Java), Tyler
Brock (C and C++), and myself (representing PHP and other dynamic languages).

### What's in a Name?

> There are only two hard things in Computer Science: cache invalidation and
> naming things.
>
> &mdash; Phil Karlton

The spec's position on function and option names perhaps best illustrates the
balancing act between language idiomaticity and cross-driver consistency. While
the spec is flexible on style (e.g. snake_case or camelCase, common suffixes),
certain root words are non-negotiable. The spec doesn't attempt to define an
exhaustive list of permitted deviations, but it does provide a few examples for
guidance:

 * `batchSize` and `batch_size` are both acceptable, but `batchCount` is not
   since "batch" and "size" are root words.
 * `maxTimeMS` can be abbreviated as `maxTime` if the language provides a data
   type with millisecond precision (e.g. TimeSpan in C#), but `maximumTime` is
   too verbose.
 * If a driver's `find()` method needs a typed options class (e.g. Java) in
   lieu of a hash literal (e.g. JavaScript) or named parameters (e.g. Python),
   `FindOptions` or `FindArgs` are both OK, but `QueryParams` would be
   inconsistent.
 * Some languages may prefer to prefix a boolean options with "is" or "has", so
   a bulk write's `ordered` option could be named `isOrdered`.

### Several Options for Handling Options

In addition to naming conventions, the spec acknowledges that each language has
its own conventions for expressing optional parameters to functions. Ruby and
Python support named parameters, JavaScript and PHP might use hash literals, C++
or C# may use an options class, and Java could opt for a fluent builder class.
Ultimately, we decided not to require method overloading, since it was only
supported by a few languages.

Required parameters, such as the `fieldName` for a distinct command or the
`pipeline` for an aggregation, must always be positional arguments on the
CRUD method. This ensures that all drivers will present a consistent public API
for each method and their essential inputs.

### Query Modifiers and Cursor Flags

The query API found in our legacy drivers differentiates between
[query modifiers][qm] and [wire protocol][wp] flags. Commonly used query
modifiers include `$orderBy`, for sorting query results, or `$hint`, for
suggesting an index. Wire protocol flags, on the other hand, might be used to
instruct the server to create a tailable cursor. Depending on the driver, these
options might be specified via arguments to `find()` or any of various setter
methods on a mutable Cursor object. The CRUD API now enforces consistent naming
for these options and ensures they will all be specified in the same manner, be
it an options structure for `find()` or a fluent interface.

Ultimately, users should never have to think about whether these query options
are modifiers within the query document or bit flags at the protocol level. That
distinction is an implementation detail of today's server API. Similar to how
MongoDB 2.6 introduced [write commands][wc] and deprecated write operations in
the wire protocol, we expect a future version of the server to do the same for
queries. In fact, progress for `find` and `getMore` commands has already begun
in [SERVER-15176][]. By abstracting away these details in the CRUD API, we can
achieve a bit of future-proofing for our drivers and the applications that use
them.

### A Step Towards Self-documenting Code

One of the common pain points with our legacy API, especially for beginners, was
that update operations affected only a single document by default while deletes
would remove *everything* matching the criteria. The inconsistency around the
name of this limit option (is it `multi`, `multiple`, or `justOne`?) was icing
on the cake. This is definitely something we wanted to fix in the CRUD spec, but
one has to tread carefully when changing the behavior of methods that can
modify or delete data.

In the interest of not surprising any users by silently changing defaults, we
opted to define some new, more descriptive methods:

 * `deleteOne(filter)`
 * `deleteMany(filter)`
 * `replaceOne(filter, replacement, options)`
 * `updateOne(filter, update, options)`
 * `updateMany(filter, update, options)`

The most striking change is that we've moved the limit option into the name of
each method. This allows drivers to leave their existing `update()` and
`delete()` (or `remove()`) methods as-is. Secondly, delete operations will now
require a `filter` option, which means it will take a bit more effort to
inadvertently wipe out a collection (`deleteMany({})` instead of `remove()`).
And lastly, we wanted to acknowledge that the difference between replacing an
entire document and updating specific fields in one or many documents. By having
each method check if the document contains atomic modifiers, we hope to help
users avoid the mistake of clobbering an entire document when they expected to
modify specific fields, or vice versa.

### Less is More

Some things are better left unsaid. While the CRUD spec contains a lot of
detail, there are a few subjects which aren't addressed:

 * Read preferences
 * Write concerns
 * Fluent API for bulk writes
 * Explaining queries

With regard to read preferences and write concerns, we noted that not every
driver allows those options to be specified on a per-operation basis. For some,
read preferences and write concerns are only set on the Client, Database, or
Collection objects. Nevertheless, the spec happily permits drivers to support
additional options on its read and write methods.

The [Bulk API][bulk], which first appeared in the MongoDB shell and select
drivers around the time MongoDB 2.6 was released, was left alone. The CRUD spec
defines a single `bulkWrite()` method, that receives an array of models each
describing the parameters for insert, update, or delete operations. We felt this
method was more versatile, as it does not impose a fluent API (with all of its
method calls) upon the user, nor does it hide the list of operations within a
builder object. Users can create, examine, or modify the list however they like
before executing it through the new method, or even re-use it entirely in a
subsequent call.

Lastly, we spent a fair amount of time discussing (and bikeshedding) the API for
explaining queries, aggregation pipelines, and any other operations that might
be supported by MongoDB 3.0 and beyond (e.g. [SERVER-10448][]). Ultimately, we
determined that explain is not a typical use case for drivers, in contrast to
the shell. We also did not want to effectively double the public API of the CRUD
specification by defining explainable variants of each method. That said, all
drivers will continue to provide the necessary tools to execute explains (either
through queries or command execution).

### Wrapping Up

If you're interested in digging deeper into any of the topics discussed in this
article (and some that weren't, such as error reporting), do give the
[CRUD API spec][crud] a look. We've also published a set of
[standardized acceptance tests][tests] in YAML and JSON formats, which are being
used by many of our [next generation drivers][nextgen] that implement the spec.

  *[API]: Application programming interface
  *[CRUD]: Create read update delete
  *[JSON]: JavaScript object notation
  *[SDAM]: Server discovery and monitoring
  *[YAML]: YAML ain't markup language

  [bulk]: http://docs.mongodb.org/manual/reference/method/js-bulk/
  [crud]: https://github.com/mongodb/specifications/blob/master/source/crud/crud.rst
  [drivers]: http://docs.mongodb.org/ecosystem/drivers/
  [nextgen]: http://www.mongodb.com/blog/post/announcing-next-generation-drivers-mongodb
  [qm]: http://docs.mongodb.org/manual/reference/operator/query-modifier/
  [sdam]: http://www.mongodb.com/blog/post/server-discovery-and-monitoring-next-generation-mongodb-drivers
  [selection]: http://www.mongodb.com/blog/post/server-selection-next-generation-mongodb-drivers
  [SERVER-10448]: https://jira.mongodb.org/browse/SERVER-10448
  [SERVER-15176]: https://jira.mongodb.org/browse/SERVER-15176
  [tests]: https://github.com/mongodb/specifications/tree/master/source/crud/tests
  [wc]: http://docs.mongodb.org/manual/reference/command/nav-crud/
  [wp]: http://docs.mongodb.org/meta-driver/latest/legacy/mongodb-wire-protocol/
