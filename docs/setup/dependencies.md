# Project Dependencies

This project requires the following Composer packages:

## Doctrine Inflector
The app depends on [doctrine/inflector](https://github.com/doctrine/inflector) for string transformations (e.g., pluralization, singularization).

Install it with:

```bash
composer require doctrine/inflector
composer require phpmailer/phpmailer

composer require --dev pestphp/pest pestphp/pest-plugin-faker pestphp/pest-plugin-laravel mockery/mockery fakerphp/faker phpstan/phpstan phpstan/phpstan-strict-rules infection/infection symfony/var-dumper

composer require pestphp/pest-plugin --dev
