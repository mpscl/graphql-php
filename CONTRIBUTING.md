# Contributing to GraphQL PHP

## Workflow

If your contribution requires significant or breaking changes, or if you plan to propose a major new feature,
we recommend you to create an issue on the [GitHub](https://github.com/webonyx/graphql-php/issues) with
a brief proposal and discuss it with us first.

For smaller contributions just use this workflow:

* Fork the project.
* Add your features and or bug fixes.
* Add tests. Tests are important for us.
* Send a pull request

## Using GraphQL PHP from a Git checkout
```sh
git clone https://github.com/webonyx/graphql-php.git
cd graphql-php
composer install
```

## Running tests
```sh
./vendor/bin/phpunit
```

## Coding Standard
Coding standard of this project is based on [Doctrine CS](https://github.com/doctrine/coding-standard). To run the inspection:

```sh
./vendor/bin/phpcs
```
