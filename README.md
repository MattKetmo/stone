# Stone - Proxy for Composer

Stone is a repository proxy for [Composer][]. It will create a local repository
with all the packages you want to mirror. Then you can use the global
configuration of Composer to fetch the packages from this local repository
instead of [Packagist][].

[composer]: https://github.com/composer/composer
[packagist]: http://packagist.org/

## Installation

Download the source:

    git clone git://github.com/mattketmo/stone.git

Compile it to a PHAR file:

    ./bin/compile

Now its recommended to `chmod +x stone.phar` and make it available into your
`$PATH` to use it everywhere you need.

You can automatically initialize the local repository with the `init` command:

    stone.phar init

Or you can do it manually by editing the global Composer configuration
(`~/.composer/config.json` on Unix system):

    {
        "repositories": [
            {
                "type": "composer",
                "url": "file:///<HOME>/.composer/stone"
            }
        ]
    }

and creating an empty `~/.composer/stone/packages.json`:

    {
        "packages": { }
    }

## Usage

To mirror any package from a `composer.json` file just run:

    stone.phar mirror /path/to/composer.json

Be sure to regulary update your local packages by running:

    stone.phar update

That's it! Composer will now use all your mirrored packages instead of fetching
them from Packagist.

## Known issues

- Branch alias are not recognized by composer (eg. "doctrine/common":
  "2.3.x-dev" will fetch from GitHub instead of the local repository)

- Packages replacement have strange behaviour. For instance, if you've mirror
  "symfony/symfony", then requiring "symfony/console" will fetch
  "symfony/symfony" (and all its dependencies) instead of just the "subtree"
