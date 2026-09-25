# dresscode/rules-laravel

What the versions of the [Laravel](https://laravel.com) framework renamed, moved and retired, and what to write
instead, as data for [DressCode](https://dresscode.run). With it installed, `dresscode fix` rewrites code
written for older versions of Laravel to the API of the version the project stands on, and reports what has to be
rewritten by hand, with what to write instead. It also offers what Laravel 13 writes with attributes, for a project
that asks for it.


Installation
------------

```shell
composer require --dev dresscode/rules-laravel
```

DressCode finds the package by itself. The data apply in the rules of the group `deprecations`, most of which need the
types of the code from PHPStan:

```neon
types: phpstan

groups:
	- deprecations
```

The data apply only when the project has `laravel/framework`, and only the sections of the versions the project
stands on: the lowest version its constraint in `composer.json` allows, or the version the key `packages` of the
configuration names. They cover Laravel 6 to 13, for code written for Laravel 5.8 and later; the helpers `str_*`
and `array_*` of 5.8 become calls of `Str` and `Arr`, a fix made with `--fix-risky` where the call stands in a
namespace, which could declare a function of the same name.

Larastan is welcome: with it in `phpstan.neon`, or registered by `phpstan/extension-installer`, DressCode starts the
application the way PHPStan does, and a facade is read as the class behind it. An alias of `config/app.php` written
without its namespace (`\Request::get()`) is known to no static analysis and stays unseen.

What the framework changed in the signatures of its contracts and classes, a method a contract added or a return type
a child has to declare, needs no data: the rules `no-unimplemented-abstract-method` and `override-signature` of the same
group report and write them.


Modernization
-------------

Laravel 13 reads attributes of a class where it read properties before (`#[Table('users')]` for `protected $table =
'users'`), Laravel 11 the method `casts()` beside the property `$casts`, and Laravel 12.4 a local scope marked by
`#[Scope]` beside the prefix `scope` of its name. The old shapes keep working, so these are offers, applied in the group
`modernization`:

```neon
groups:
	- modernization
```

The attributes are data of `attribute-for-member` (models, commands, form requests, API resources and queued jobs and
listeners); `laravel/casts-method-for-casts-property` writes the method `casts()`, and
`laravel/scope-attribute-for-scope-prefix` the attribute `#[Scope]` on a protected method of the name without the
prefix, which is risky, as a call by the old name in another file is not seen, so it is made only with
`dresscode fix --fix-risky`.


Development
-----------

The framework the data are about is in `require-dev`, with Larastan and `orchestra/testbench`, so the data are checked
against its installed version and the samples run with the types Larastan gives:

- `php tests/check.php framework` lints `upgrading/framework.neon` against the installed framework and runs its
  sample, `tests/samples/framework.code`, comparing the result with `.expected` and `.violations`, and
  `php tests/check.php framework-attributes` does the same for the attributes,
- `php tests/check.php <file> --update` writes those two from the run; read the diff, it is what the data do,
- `vendor/bin/tester tests` runs all of it, the fixtures of the rules included,
- `php tests/corpus.php <laravel>/tests` runs the data over the tests of the framework, which they must leave as they
  are, and `--modernization` offers the modernizations over them.

How the keys and values are written is described on the page "Maps of replacements" of the DressCode manual.
