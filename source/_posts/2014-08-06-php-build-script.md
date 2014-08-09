---
title: Building and Managing Multiple Versions of PHP
tags:
    - php
disqus_identifier: 53e4eda0a1797b61365fa59c
---
Before joining MongoDB to work on the [PHP driver][mongo-php-driver] a couple of
years ago, I was perfectly content the PHP binaries in my operating system's
package manager. Sure, every now and then I dipped into a [PPA][ppa] for a
custom build, but one PHP version at a time sufficed. Coming to work on an
extension meant that I'd need to juggle between various versions of PHP for
building and testing.

<blockquote class="twitter-tweet" align="center" lang="en"><p>The nightmare that is managing multiple versions of PHP on the same machine. Has anyone used libs mentioned in <a href="http://t.co/Hz8oMcwE65">http://t.co/Hz8oMcwE65</a>?</p>&mdash; Jeremy Mikola (@jmikola) <a href="https://twitter.com/jmikola/statuses/370989222070476800">August 23, 2013</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

I did get a number of responses to that tweet, mentioning projects such as
[phpenv][] and [phpbrew][], but they were mostly geared towards PHP application
development. At the time, those projects had limitations when it came to
customizing build flags; however, I should note that [phpbrew][] has had a very
active year of development since then and likely deserves some reconsideration.
[Thierry Marianne][thierry-marianne] also shared some research he was doing to
compile multiple versions of PHP using Fabric, which he since published as
[php-compiler][].

I initially started with Derick's [blog post][derick-php] on managing multiple
PHP versions. One of the first changes I made was modifying it to use the
[git-new-workdir][] command instead of a [sparse SVN checkout][php-svn-sparse],
which is also mentioned in PHP's own [Git FAQ][php-git-multiple]. I'm pretty
sure that the current iteration of his build script differs a bit from the one
in his blog post, which actually pre-dates PHP's move from SVN to Git.

Compiling directly from Git (or SVN) had a few extra hoops, which is discussed
in the [Building PHP][building-php] chapter of the
[PHP Internals Book][internals-book]. Aside from ensuring tags and commits were
fetched locally, having to run `buildconf` was a special chore. Each version of
PHP seemed to have a slightly different (and conflicting) requirements for
[Bison][bison], which is needed by `buildconf`. Rather than dive down the rabbit
hole of writing something to juggle Bison versions, I opted to look into using
PHP's release tarballs, which conveniently do not require `buildconf` at all.

I ended up with a relatively simple script that does the following:

 * Downloads a PHP tarball for a specific version (e.g. 5.5.15)
 * Allow a label (e.g. 5.5) to be specified for naming INI and install directories
 * Adds a common set of config flags (e.g. debug mode, extensions, ZTS)
 * Builds PHP for the given options, INI and install directories
 * Logs the build process and deletes the temporary build directory afterwards

The script follows:

<script src="https://gist.github.com/jmikola/f19db4e8de105ee60c06.js?file=build_php.sh"></script>

There are a few shortcomings. For one, Derick's build script allowed for 32-bit
builds, which I've yet to add in here. His build options are also more flexible,
being a string that gets concatenated into the `configure` command. I've limited
things to a list of `--enable` flags, which appear in a `for` loop. It shouldn't
be too hard to change either of these.

An interesting factoid I learned while creating this script is that
[POSIX shells do not support array types][sh-array]. Portability is something to
keep in mind when targetting a script for `/bin/sh`. After six years, I still
forget that Ubuntu uses [Dash][dash].

The main things to configure in the script are:

 * `base_install_dir`: Where the INI and install directories should be created.
   If you build 5.5.15 with a label of 5.5, this is where the script will create
   the `etc-5.5` and `php-5.5` directories. I use `~/bin/php-bin` by default.
 * `download_dir`: Some scratch directory for the script to use when downloading
   PHP tarballs. The script will check this directory for previously downloaded
   tarballs before invoking curl. I use `~/bin/php-cache` by default.
 * The config flags alluded to above reside in a `for` loop somewhere in the
   middle of the script. The current defaults work for me, but this will likely
   be changed to a simple string variable (as in Derick's script) later on.

Running the script should product the following:

```no-highlight
$ build_php.sh 5.5.15 5.5
===> Downloading: http://php.net/get/php-5.5.15.tar.gz/from/this/mirror => /home/jmikola/bin/php-cache/php-5.5.15.tar.gz
===> Unpacking: /home/jmikola/bin/php-cache/php-5.5.15.tar.gz => /tmp/build_php-5.5.15.Wh2
===> Changed build directory to: /tmp/build_php-5.5.15.Wh2/php-5.5.15
===> Creating config file directory: /home/jmikola/bin/php-bin/etc-5.5
===> Creating config file scan directory: /home/jmikola/bin/php-bin/etc-5.5/conf.d
===> Building and installing to: /home/jmikola/bin/php-bin/php-5.5
===> Build output will be logged to: /tmp/build_php-5.5.15.log.rA2
===> Build complete; deleting temporary directory: /tmp/build_php-5.5.15.Wh2
$
```

If the script completes successfully, it will delete the temporary build
directory; however, the build log (output from `configure` and `make`) will
stick around. You can also `tail -f` the build log while the script is running.

And no script is complete without a usage example:

```no-highlight
$ build_php.sh 
Usage: /home/jmikola/bin/build_php.sh version [label] [zts]
  version: Full x.y.z version (e.g. 5.5.15)
  label:   Short version for install path (e.g. 5.5); defaults to full version
  zts:     Add --enable-maintainer-zts build flag if 1; defaults to false
$
```

I should note that in the previous example, the build script created two folders
in `$base_install_dir` for storing INI and conf files: `etc-5.5` and
`etc-5.5/conf.d`. Were I to build PHP with the same label again, we wouldn't see
these messages as it'd find the existing directories and use them as-is. In
practice, I only use a single INI file for these PHP installs, but we create
both paths as PHP has build options for each of them.

My INI file looks like the following:

<script src="https://gist.github.com/jmikola/f19db4e8de105ee60c06.js?file=php.ini"></script>

Once a version (or two) of PHP is installed, we can use a small shell function
to toggle between them:

<script src="https://gist.github.com/jmikola/f19db4e8de105ee60c06.js?file=pe.sh"></script>

My typical workflow on the MongoDB driver is running `pe 5.5` immediately after
opening a terminal in my project directory. This bumps the binary directory for
"5.5" to the front of my session's `$PATH`, which means I'll use that version
when executing `phpize` or `php` for extension compilation and testing,
respectively. Jumping between versions works fine, but I haven't bothered to
add support for reverting back to my system path as I usually just open a new
shell at that point.

Managing multiple PHP versions is still a headache, but this has saved me from
throwing in the towel and using a cluster of virtual machines. Hopefully someone
else finds it useful, too.

  *[Dash]: Debian Almquist Shell
  *[POSIX]: Portable Operating System Interface
  *[PPA]: Personal Package Archive
  *[ZTS]: Zend Thread Safety

  [bison]: http://www.gnu.org/software/bison/
  [building-php]: http://www.phpinternalsbook.com/build_system/building_php.html
  [dash]: https://wiki.ubuntu.com/DashAsBinSh
  [derick-php]: http://derickrethans.nl/multiple-php-version-setup.html
  [git-new-workdir]: http://nuclearsquid.com/writings/git-new-workdir/
  [internals-book]: http://www.phpinternalsbook.com/
  [mongo-php-driver]: https://github.com/mongodb/mongo-php-driver
  [php-compiler]: https://github.com/thierrymarianne/php-compiler
  [php-git-multiple]: https://wiki.php.net/vcs/gitfaq#multiple_working_copies_workflow
  [php-svn-sparse]: https://wiki.php.net/vcs/svnfaq#sparse_directory_checkout_instructions
  [phpbrew]: https://github.com/phpbrew/phpbrew
  [phpenv]: https://github.com/phpenv/phpenv
  [ppa]: https://launchpad.net/ubuntu/+ppas
  [sh-array]: http://stackoverflow.com/a/6500474/162228
  [thierry-marianne]: https://twitter.com/thierrymarianne
