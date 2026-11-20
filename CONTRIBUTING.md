# Contributing

Thanks for considering a contribution to Laravel API Resource Tool.

## Prerequisites

- PHP 8.2 or later
- Composer
- Laravel 12 or 13 for integration work

Laravel 12 compatibility is deprecated and ends on February 24, 2027. Laravel versions below 12 are not supported.

## Local setup

Clone the repository and install its Composer dependencies:

```bash
composer install
```

Run the test suite and formatting check before opening a pull request:

```bash
composer test
composer format:check
composer validate --no-check-publish
```

The test suite uses an in-memory SQLite database. CI covers Laravel 12 on PHP 8.2 through 8.5 and Laravel 13 on PHP 8.3 through 8.5. Contributions that change behavior should add or update focused tests.

## Branches and commits

Use a short branch name such as `feat/filter-operators` or `fix/enum-sorting`. Use Conventional Commit messages such as `feat: add enum ordering` or `fix: validate relation filters`.

## Pull requests

Pull requests should explain the problem and the chosen change. Include relevant test results, update documentation when public behavior changes, and call out compatibility or breaking changes. Keep changes focused on this package.

For questions or proposals, use the [GitHub issue tracker](https://github.com/EvanSchleret/laravel-api-resource-tool/issues). Please follow the [code of conduct](https://github.com/EvanSchleret/laravel-api-resource-tool/blob/main/CODE_OF_CONDUCT.md).
