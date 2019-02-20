# How to contribute

Thanks for considering to contribute this project. All issues and pull requests are highly appreciated.

## Report Issue

Reporting an [issue](https://github.com/didi/rdebug/issues) is welcomed. But do please include the following details

1. Environment (OS version, rdebug version and so on)
2. Issue description, error logs
3. Steps to reproduce

## Pull Requests

Before sending pull request to this project, please read and follow guidelines below.

1. Coding style: PHP follow PSR-2 coding style.
3. Commit message: Use English and be aware of your spell.
4. Test: Make sure to test your code.

NOTE: We assume all your contribution can be licensed under the [Apache License 2.0](./LICENSE).

## Run Tests

To run tests simply run the `phpunit` executable in the `vendor/bin`

```bash
$ composer install --dev
$ ./vendor/bin/phpunit
```

You should get an output similar to this:

```bash
$ ./vendor/bin/phpunit
PHPUnit 6.5.14 by Sebastian Bergmann and contributors.

.................SS                                               19 / 19 (100%)

Time: 152 ms, Memory: 6.00MB

OK, but incomplete, skipped, or risky tests!
Tests: 19, Assertions: 28, Skipped: 2.
```

