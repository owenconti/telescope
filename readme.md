This is a fork of the Laravel package, [Telescope](https://github.com/laravel/telescope).

This fork includes the following modifications:

* The database migrations are wrapped in `hasTable` statements so that they can run idempotently. This allows a user to use Telescope in combination with unit tests. 
* A new command has been added that shows the percentage of registered routes hit in the last `x` minutes.

## Using Telescope for tests

TODO:

## Using the RouteCoverage command

TODO:
