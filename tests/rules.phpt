<?php declare(strict_types=1);

/** The fixtures of every rule of this package, which run against the framework of require-dev. */

use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

$rules = [
	'casts-method-for-casts-property' => DressCode\Laravel\CastsMethodForCastsPropertyRule::class,
	'scope-attribute-for-scope-prefix' => DressCode\Laravel\ScopeAttributeForScopePrefixRule::class,
];

foreach ($rules as $slug => $class) {
	foreach (glob(__DIR__ . "/fixtures/$slug/*.code") ?: [] as $file) {
		// what the rule says and where is part of its contract, so every fixture records it
		Assert::true(is_file(preg_replace('~\.code$~', '.violations', $file)), basename($file) . ' has no .violations file.');
	}

	Assert::noError(fn() => RuleTester::run($class, __DIR__ . "/fixtures/$slug"));
}
