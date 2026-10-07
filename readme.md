DressCode Rules for Laravel
===========================

[![Latest Stable Version](https://poser.pugx.org/dresscode/rules-laravel/v/stable)](https://packagist.org/packages/dresscode/rules-laravel)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://packagist.org/packages/dresscode/rules-laravel)

 <!---->

<h3>

✅ Upgrades code written for Laravel 5.8 [all the way to 13](#laravel-versions-covered)<br>
✅ Rewrites models, commands and jobs to [the attributes of Laravel 13](#laravel-13-attributes)<br>
✅ Reports [what needs a human](#what-is-left-to-you), with what to write instead<br>
✅ Sees [through facades](#what-gets-upgraded) with Larastan

</h3>

 <!---->

**Upgrade a Laravel application without hunting for renames.** Install this package, run `dresscode fix`, and code
written for older versions of Laravel is rewritten to the API of the version your project stands on:

```diff
- use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
+ use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

- $name = $request->get('name');
+ $name = $request->input('name');

- URL::forceRootUrl(config('app.url'));
+ URL::useOrigin(config('app.url'));

- $limit->decayMinutes = 5;
+ $limit->decaySeconds = 5 * 60;

- $table->unsignedDecimal('price', 10, 2);
+ $table->decimal('price', 10, 2)->unsigned();
```

Five changes across middleware, requests, URLs, rate limiting and migrations, and not a byte more of the file
touched. The package holds the data, what each version of Laravel renamed, moved or retired; the rewriting is done
by [DressCode](https://dresscode.run), the PHP coding standard and upgrade tool, which comes with it.

 <!---->

Installation and first run
==========================

**1️⃣ Install it: `composer require --dev dresscode/rules-laravel`**<br>
**2️⃣ Turn the upgrade on in `dresscode.neon`**<br>
**3️⃣ Look at the changes and make them: `vendor/bin/dresscode check --diff`, `vendor/bin/dresscode fix`**

The package brings DressCode along, and DressCode finds its upgrading data by itself. The upgrade is the set
`deprecations`, and most of its rewrites need to know what a variable is, which DressCode asks your PHPStan:

```neon
typeAnalysis: phpstan

use:
	- deprecations
```

If your project already has a `dresscode.neon`, add the two keys to it. If it does not, these lines are the whole
file: with no coding standard named, DressCode upgrades the code and leaves its style alone. Then run your tests and
PHPStan, read the diff, and commit.

 <!---->

What gets upgraded
==================

None of it is search and replace. DressCode asks PHPStan what each variable is, so `get()` becomes `input()` where it
is called on a request and nowhere else. With Larastan in your `phpstan.neon`, DressCode boots the application the
way PHPStan does, and a facade is read as the class behind it, so `Request::get()` is rewritten too. What the package
rewrites:

- **the helpers of Laravel 5.8**: `str_slug()`, `str_limit()`, `array_get()` and their kin to the methods of `Str`
  and `Arr`,
- **renamed classes and traits**: the middleware `VerifyCsrfToken` and `ValidateCsrfToken` to
  `PreventRequestForgery`, `HasVersion7Uuids` to `HasUuids`, with the import, the type, `new`, `instanceof` and
  attributes,
- **renamed methods**: `Request::get()` to `input()`, `URL::forceRootUrl()` to `useOrigin()`, `containsOneItem()` of
  a collection to `hasSole()`, `cascadeOnTrucate()` to `cascadeOnTruncate()`,
- **renamed properties**, the value converted where the unit changed: `$decayMinutes` of a rate limit to
  `$decaySeconds`, `$connection` of a queue event to `$connectionName`,
- **columns of migrations** that Laravel 11 removed: `unsignedDecimal()` and `unsignedDouble()` to the column with
  `unsigned()`, `double()` without its total and places,
- **signatures** a contract or a parent class changed: a method a newer contract added is reported, a return type a
  child has to declare is written, from the installed code with no data needed.

The rewritten code takes the shape of the file around it, or of your coding standard, if the configuration names one.

 <!---->

Laravel 13 attributes
=====================

Laravel 13 reads attributes where it read properties before, and the old properties keep working, so this is an
offer rather than an upgrade. It comes in the set `modernizations`, next to `deprecations`, and its rules come
with the plugin of the package, which loads where `use` names the package:

```neon
use:
	- dresscode/rules-laravel
	- deprecations
	- modernizations
```

A model written the old way:

```php
class Post extends Model
{
	protected $table = 'blog_posts';

	protected $fillable = ['title', 'body'];

	protected $hidden = ['secret'];

	public $timestamps = false;
}
```

after `dresscode fix`:

```php
#[WithoutTimestamps]
#[Table(name: 'blog_posts')]
#[Fillable(['title', 'body'])]
#[Hidden(['secret'])]
class Post extends Model
{
}
```

The same goes for commands, form requests, API resources, and queued jobs and listeners (`$tries`, `$timeout`,
`$backoff`, `$uniqueFor` and the rest). A class that reads such a property itself, `$this->tries`, keeps it and is
only reported, because removing the property would break the read. Two rules of the package go with them:
`laravel/castsMethodForCastsProperty` writes the method `casts()` of Laravel 11 for the property `$casts`, and
`laravel/scopeAttributeForScopePrefix` marks a local scope with `#[Scope]` of Laravel 12 instead of the prefix
`scope` of its name; since a call by the old name in another file cannot be seen, that one waits for your consent.

 <!---->

What is left to you
===================

Where there is no replacement, or one that needs a decision, DressCode does not guess. It reports the place and says
what to write instead:

```
Static method `Illuminate\Support\Facades\Schema::useNativeSchemaOperationsIfPossible()` is forbidden: there is no replacement, the schema is always changed natively; drop the call
```

An upgrade is a rewrite plus a list of what remains, and the list is half of its value. Every such sentence is
written as an instruction, so the list can be worked through by you or handed to an AI coding agent as it is.

A few rewrites could change what the code does. A helper such as `str_slug()` called inside a namespace is one: the
namespace could declare a function of the same name. Those wait for your consent: `dresscode fix --review` asks about
each one with its diff, `--fix-risky` makes them all.

 <!---->

One major version at a time
===========================

The data apply only when your project has `laravel/framework`, and only the sections of the versions it stands on:
the lowest version the constraint in `composer.json` allows. So an upgrade goes the way you would do it by hand:
raise the constraint to the next major version, `composer update`, `dresscode fix`, tests, commit, and on to the
next one. In each step the data meet the installed framework, whose `@deprecated` annotations tell DressCode what
the next version will remove.

Code can also be upgraded before the framework is, with the key `targets`, which names the version to write for:

```neon
targets:
	laravel/framework: '12.0'
```

`dresscode config` shows the version the data start from and the versions a further update would lead to.

 <!---->

Where the data come from
========================

The data come from the upgrade guides of Laravel and from the `@deprecated` hints of the framework, each entry
checked against the code of the framework at its tags: where the guide and the code differ, the code decides. They
are checked against the installed framework, so a replacement that does not exist cannot get in, and a sample of
old code must turn into the expected new code.

Then the data run over the tests of the framework itself, code written for the current API, and must leave them as
they are. An entry that would touch correct code does not get in.

 <!---->

Compared to Rector
==================

[Rector](https://getrector.com) is the usual answer to an upgrade, and it has sets for Laravel too. The difference is
in the shape of the work. Where Rector changes the structure of a statement, it prints the statement anew, so it
needs a coding standard tool after it, and a rule of Rector either rewrites the code or says nothing. DressCode is
one tool, with one configuration and one diff: the rewrite keeps the formatting of everything around it, and what
cannot be rewritten is reported with what to do. It is also
[faster](https://github.com/dg/dresscode#performance-php-cs-fixer-php_codesniffer), the types from PHPStan included.

And where Rector has a class for each attribute of Laravel 13, an entry here is a line of text:

```neon
since 13:
	attributeForMember:
		Illuminate\Contracts\Queue\ShouldQueue::$tries: 'Illuminate\Queue\Attributes\Tries($value)'
		Illuminate\Contracts\Queue\ShouldQueue::$timeout: 'Illuminate\Queue\Attributes\Timeout($value)'
```

 <!---->

Laravel versions covered
========================

Laravel 6 to 13, which is code written for Laravel 5.8 and later, with more than 200 entries for the framework and
its attributes. The [manual](https://dresscode.run/upgrading-laravel) has the details.

 <!---->

Limits
======

- **Most rewrites need PHPStan.** Without `typeAnalysis: phpstan`, only classes, functions and annotations are rewritten.
- **DressCode runs on PHP 8.4 to 8.6.** The code it upgrades may be written for PHP 8.0 and newer, but the package is
  installed into the project, so the project has to install on PHP 8.4 or newer.
- **An alias of `config/app.php` written without its namespace**, `\Request::get()`, is known to no static analysis
  and stays as it is.
- **The data fix the code, not its meaning.** A change of behavior and the configuration files are not in the data;
  the upgrade guides of Laravel describe them. Run your tests after a fix.

 <!---->

Other ecosystems
================

| package | upgrades |
|---|---|
| [`dresscode/rules-nette`](https://github.com/dg/dresscode-rules-nette) | every Nette library, from 3.0 |
| [`dresscode/rules-symfony`](https://github.com/dg/dresscode-rules-symfony) | the components and bridges of Symfony, from 6.0 |
| [`dresscode/rules-laravel`](https://github.com/dg/dresscode-rules-laravel) | the Laravel framework from 6, and the attributes of Laravel 13 |
| [`dresscode/rules-deegee`](https://github.com/dg/dresscode-rules-deegee) | Dibi and Texy |

A package like these can be written for any library; the [manual](https://dresscode.run/upgrading-data) says how.

 <!---->

Development
===========

The framework is in `require-dev` with Larastan and `orchestra/testbench`, so the data are checked against its
installed version and the samples run with the types Larastan gives:

- `php tests/check.php framework` lints `upgrading/framework.neon` and runs its sample, `tests/samples/framework.code`,
  comparing the result with `.expected` and `.violations`; the attributes of 13 have a sample of their own,
  `tests/samples/framework-attributes.code`,
- `php tests/check.php <file> --update` writes those two from the run; read the diff, it is what the data do,
- `vendor/bin/tester tests` runs all of it, the fixtures of the rules included,
- `php tests/corpus.php <laravel>/tests` runs the data over the tests of the framework, which they must leave as they
  are, and `--modernization` offers the modernizations over them.
