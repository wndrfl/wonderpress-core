# Wonderpress Core

[![wonderpress-coding-standards Status](https://github.com/wndrfl/wonderpress-core/workflows/wonderpress-coding-standards/badge.svg)](https://github.com/wndrfl/wonderpress-core/actions)

## Features

### Bootstrapping
Any logic in `inc/bootstrap.php` will be automatically run on page load.

### Helpers
Wonderpress Core provides various useful helper functions in `inc/helpers.php`.

### Partials

## Standards
The Wonderpress Core upholds the WordPress Coding Standards. 

To run the codesniffer:

```bash
$ composer run-script lint
````

To attempt to automatically fix as many issues as possible:

```bash
$ composer run-script lint-fix
````
