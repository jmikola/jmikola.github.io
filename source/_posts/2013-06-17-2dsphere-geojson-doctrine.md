---
title: 2dsphere, GeoJSON, and Doctrine MongoDB
also:
    - http://blog.mongodb.org/post/53751481851/2dsphere-geojson-and-doctrine-mongodb
tags:
    - doctrine
    - mongodb
disqus_identifier: 5367e7aaa8780bca7b88158c
---
It seems that GeoJSON is all the rage these days. Last month, Ian Bentley shared
a bit about the [new geospatial features in MongoDB 2.4][mongodb-geo]. Derick
Rethans, one of my PHP driver teammates and a renowned [OpenStreetMap]
aficionado, recently blogged about [importing OSM data into MongoDB][import-osm]
as GeoJSON objects. A few days later, GitHub [added support][github-geo] for
rendering `.geojson` files in repositories, using a combination of [Leaflet.js],
[MapBox], and OpenStreetMap data. Coincidentally, I visited a local [CloudCamp]
meetup last week to present on geospatial data, and for the past two weeks I've
been working on adding support for MongoDB 2.4's
[geospatial query operators][mongodb-geo-ops] to [Doctrine MongoDB].

Doctrine MongoDB is an abstraction for the [PHP driver] that provides a fluent
query builder API among other useful features. It's used internally by
[Doctrine MongoDB ODM], but is completely usable on its own. One of the
challenges in developing the library has been supporting multiple versions of
MongoDB *and* the PHP driver. The introduction of [read preferences] last year
is one such example. We wanted to still allow users to set `slaveOk` bits for
older server and driver versions, but allow read preferences to apply for newer
versions, all without breaking our API and abiding by [semantic versioning].
Now, the `setSlaveOkay()` method in Doctrine MongoDB will invoke
`setReadPreference()` if it exists in the driver, and fall back to the
deprecated `setSlaveOkay()` driver method otherwise.

### Query Builder API

Before diving into the geospatial changes for Doctrine MongoDB, let's take a
quick look at the query builder API. Suppose we had a collection, `test.places`,
with some OpenStreetMap annotations (`key=value` strings) stored in a `tags`
array and a `loc` field containing longitude/latitude coordinates in MongoDB's
legacy point format (a float tuple) for a `2d` index. Doctrine's API allows
queries to be constructed like so:

~~~ php
$connection = new \Doctrine\MongoDB\Connection();
$collection = $connection->selectCollection('test', 'places');

$qb = $collection->createQueryBuilder()
    ->field('loc')
        ->near(-73.987415, 40.757113)
        ->maxDistance(0.00899928);
    ->field('tags')
        ->equals('amenity=restaurant');

$cursor = $qb->getQuery()->execute();
~~~

This above example executes the following query:

~~~ js
{
    "loc": {
        "$near": [-73.987415, 40.757113],
        "$maxDistance": 0.00899928
    },
    "tags": "amenity=restaurant"
}
~~~

This simple query will return restaurants within half a kilometer of 10gen's
[NYC office] at 229 West 43rd Street. If only it was so easy to find *good*
restaurants near Times Square!

### Supporting New and Old Geospatial Queries

When the new `2dsphere` index type was introduced in MongoDB 2.4, operators such
[$near] and [$geoWithin] were changed to accept GeoJSON geometry objects in
addition to their legacy point and shape arguments. `$near` was particularly
problematic because of its optional `$maxDistance` argument. As shown above,
`$maxDistance` previously sat alongside `$near` and was measured in radians. It
now sits within `$near` and is measured in meters. Using a `2dsphere` index and
GeoJSON points, the same query takes on a whole new shape:

~~~ js
{
    "loc": {
        "$near": {
            "$geometry": {
                "type": "Point",
                "coordinates" [-73.987415, 40.757113]
            },
            "$maxDistance": 500
        }
    },
    "tags": "amenity=restaurant"
}
~~~

This posed a hurdle for Doctrine MongoDB's query builder, because we wanted to
support `2dsphere` queries without drastically changing the API. Unfortunately,
there was no obvious way for `near()` to discern whether a pair of floats
denoted a legacy or GeoJSON point, or whether a number signified radians or
meters in the case of `maxDistance()`. I also anticipated we might run into a
similar quandry for the `$geoWithin` builder method, which accepts an array of
point coordinates.

[Method overloading] seemed preferable to creating separate builder methods or
introducing a new "mode" parameter to handle `2dsphere` queries. Although PHP
has no language-level support for overloading, it is commonly implemented by
inspecting an argument's type at runtime. In our case, this would necessitate
having classes for GeoJSON geometries (e.g. Point, LineString, Polygon), which
we could differentiate from the legacy geometry arrays.

### Introducing a GeoJSON Library for PHP

A cursory search for GeoJSON PHP libraries turned up [php-geojson], from the
the [MapFish] project, and [geoPHP]. I was pleased to see that geoPHP was
available via [Composer] (PHP's de facto package manager), but neither library
implemented the [GeoJSON spec] in its entirety. This seemed like a ripe
opportunity to create such a library, and so [geojson] was born a few days
later.

At the time of this writing, `2dsphere` support for Doctrine's query builder
is still [being developed][pr-109]; however, I envision it will take the
following form when complete:

~~~ php
use GeoJson\Geometry\Point;

// ...

$qb = $collection->createQueryBuilder()
    ->field('loc')
        ->near(new Point([-73.987415, 40.757113]))
        ->maxDistance(0.00899928);
    ->field('tags')
        ->equals('amenity=restaurant');
~~~

All of the GeoJson classes implement [JsonSerializable], one of the newer
interfaces introduced in PHP 5.4, which will allow Doctrine to prepare them for
MongoDB queries with a single [method call][jsonserialize]. One clear benefit
over the legacy geometry arrays is that the GeoJson library performs its own
validation. When a Polygon is passed to `geoWithin()`, Doctrine won't have to
worry about whether all of its rings are closed LineStrings; the library would
catch such an error in the constructor. This helps achieve a
[separation of concerns], which in turn increases the maintainability of both
libraries.

I look forward to finishing up `2dsphere` support for Doctrine MongoDB in the
coming weeks (things are a bit busy with [MongoNYC] right around the corner). In
the meantime, if you happen to fall in the fabled demographic of PHP developers
in need of a full GeoJSON implementation, please give [geojson] a look and share
some feedback.

  [mongodb-geo]: http://blog.mongodb.org/post/50984169045/new-geo-features-in-mongodb-2-4
  [OpenStreetMap]: http://openstreetmap.org/
  [import-osm]: http://derickrethans.nl/importing-osm-into-mongodb.html
  [github-geo]: https://github.com/blog/1528-there-s-a-map-for-that
  [Leaflet.js]: http://leafletjs.com/
  [MapBox]: http://www.mapbox.com/
  [CloudCamp]: http://www.cloudcamp.org/newark/379
  [mongodb-geo-ops]: http://docs.mongodb.org/manual/reference/operator/query-geospatial/
  [Doctrine MongoDB]: https://github.com/doctrine/mongodb
  [Doctrine MongoDB ODM]: https://github.com/doctrine/mongodb-odm
  [PHP driver]: http://php.net/mongo
  [read preferences]: http://docs.mongodb.org/manual/core/read-preference/
  [semantic versioning]: http://semver.org/
  [NYC office]: http://www.mongodb.com/press/10gen-moves-former-new-york-times-building
  [$near]: http://docs.mongodb.org/manual/reference/operator/near/
  [$geoWithin]: http://docs.mongodb.org/manual/reference/operator/geoWithin/
  [Method overloading]: http://en.wikipedia.org/wiki/Function_overloading
  [php-geojson]: http://www.mapfish.org/svn/mapfish/contribs/php-geojson/
  [MapFish]: http://www.mapfish.org/
  [geoPHP]: https://github.com/phayes/geoPHP
  [Composer]: http://getcomposer.org/
  [GeoJSON spec]: http://www.geojson.org/geojson-spec.html
  [geojson]: https://github.com/jmikola/geojson
  [pr-109]: https://github.com/doctrine/mongodb/pull/109
  [JsonSerializable]: http://php.net/manual/en/class.jsonserializable.php
  [jsonserialize]: http://php.net/manual/en/jsonserializable.jsonserialize.php
  [separation of concerns]: http://en.wikipedia.org/wiki/Separation_of_concerns
  [MongoNYC]: http://www.mongodb.com/events/mongonyc-2013
