# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.


## What this is

Data for DressCode: what the versions of the Laravel framework renamed, moved and retired, and what to write
instead. The unit is the intent, not the `illuminate/*` package, since an application requires the framework as a
whole: `upgrading/framework.neon` with `package: laravel/framework` and `group: deprecations`, and
`upgrading/framework-attributes.neon` of the same package with `group: modernization`, the attributes of 13 that
replace properties still working. Both are listed under `extra.dresscode.upgrading` of `composer.json`, and the
framework itself is in `require-dev`, so that the data are checked against what is installed. DressCode is required
as `dresscode/dresscode`.

Where an entry comes from is the upgrade guide of Laravel (prose, the sentence about a PHP symbol being the entry)
and the `@deprecated` hints of the framework, checked against the code of the framework at its tags: the tag
decides, not the guide.


## Essential commands

- `php tests/check.php <library>`: the lint of one file and its sample; the verdict is the exit code.
- `php tests/check.php <library> --update`: writes `tests/samples/<library>.expected` and `.violations` from the run.
  Always read the diff of `.code` against `.expected`: a wrong fix recorded there is a wrong fix tested ever after.
- `vendor/bin/tester tests`: every file, every sample and the fixtures of the rules.
- `php tests/corpus.php <laravel>/tests`: the data over the tests of the framework, code written for the current API,
  which they must leave as they are except the files `isLegacy()` lists with the reason; take a checkout at the tag of
  the installed version. `--rename=<Class::member>=<name>` adds an entry wrong on purpose, which must make it fail,
  and `--modernization` runs the modernizations alone, whose rewrites are read, not failed.
- After a change of DressCode itself, `composer reinstall dresscode/dresscode`; the path repository is a copy.


## Writing the data

- A file starts with `package: laravel/framework` and its `group`, and goes on with sections `since <version>`, newest
  first. The section is the minor version whose tag carries the deprecation first (`since 12.51` for `Request::get()`),
  found by `git grep @deprecated` at each minor tag; a member a major removed without deprecating it goes into the
  section of that major. The first section is `since 6`, for code written for 5.8: what 5.8 deprecated and 6 removed,
  the helpers `str_*` and `array_*` among it, goes into it; nothing older is written.
- A function replaced by a static method is `replaced-functions` with the method as the value
  (`str_slug: Illuminate\Support\Str::slug`); a helper whose name PHP took for its own function
  (`str_contains`, `array_first`) gets no entry, since the call may be PHP's.
- The upgrade guide of Laravel is prose: only its sentence about a PHP symbol is an entry. Besides the guide, the
  deprecations at the tags and the declarations the last tag of a major has and the installed version lacks are the
  sources; the tag decides.
- Decide by what happens to the code: a member called differently and used the same way is `replaced-members`, one
  written as another expression `replaced-calls` (a property whose unit changed converts in its hooks,
  `$this->decaySeconds / 60`), a replacement that returns another shape or depends on the database is
  `forbidden-members` with a sentence (`getAllTables()`, `point()`), and a change of behavior or configuration is
  nothing. An argument whose unit changed is nothing too: code for the new version has the same shape, and a rewrite
  would repeat on every run.
- A member gets data even where the installed version declares it with a hint naming the replacement: the next
  major removes it and the hint with it, and a map of rules-symfony on a parent class would reach a method Laravel
  overrides, which the key of the nearer class outweighs.
- A facade needs keys of its own where the member is gone from its `@method` too (`Schema::getAllTables()`,
  `DB::registerDoctrineType()`), besides the key on the class behind it.
- A sentence of `forbidden-*` is English, completes `… is forbidden:` and says what to write instead, lower case, its
  code in backticks, no period, at most 160 characters without the backticks; one with a comma or with parentheses goes in
  apostrophes.
- An attribute of 13 goes into `framework-attributes.neon` as `attribute-for-member`, its key the property of the class
  the framework reads it from (`Model::$table`), or of the interface for classes the framework declares nothing of
  (`ShouldQueue::$tries`); a literal default in the key stands for that value alone (`'Model::$timestamps = false'`).
- A sample holds code of the old API the way an application writes it, a child overriding a method among it; the
  corpus leaves out the modernizations, which change code that is right as it is.

## The rules of the package

What no map can say is a rule in `src/`, registered by `Extension` and named `laravel/<slug>`:
`laravel/casts-method-for-casts-property` and `laravel/scope-attribute-for-scope-prefix`, both in the group
`modernization`. A rule has fixtures in `tests/fixtures/<slug>/`, and the samples run it together with the rules the
data feed.
