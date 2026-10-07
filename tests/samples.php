<?php declare(strict_types=1);

use DressCode\Testing\UpgradingTester;

/**
 * The decisions a sample runs with beside the upgrading files: the signature of an override as the ancestor declares it,
 * and those of the rules of this package that read the code alone.
 */
const SampleDecisions = [
	'classes' => ['overridingSignature' => 'asAncestor', 'overridingParameterNames' => 'asAncestor'],
	'laravel' => ['castsMethod' => 'adopted', 'scopeAttribute' => 'adopted'],
];


/** The decisions the corpus is run with: the maps the upgrading files feed, and the rules of this package that read the code alone. */
const UpgradingDecisions = [
	'upgrading.libraries.replacedClasses',
	'upgrading.libraries.replacedMembers',
	'upgrading.libraries.replacedCalls',
	'upgrading.libraries.replacedFunctions',
	'upgrading.libraries.forbiddenClasses',
	'upgrading.libraries.forbiddenMembers',
	'upgrading.libraries.forbiddenFunctions',
	'upgrading.libraries.attributeForMember',
	'laravel.castsMethod',
	'laravel.scopeAttribute',
];

/** The decisions of UpgradingDecisions that offer a newer shape of code that is right as it is. */
const ModernizationDecisions = [
	'upgrading.libraries.attributeForMember',
	'laravel.castsMethod',
	'laravel.scopeAttribute',
];


/**
 * What the upgrading file of a library has wrong, checked against the installed library.
 * @return list<string>
 */
function lintLibrary(string $library): array
{
	$root = dirname(__DIR__);
	return UpgradingTester::collectProblems("$root/upgrading/$library.neon", $root);
}


/**
 * The sample of a library, code written for its old API, as the upgrading files fix it, and what they report in it:
 * the fixed code, and the violations as `line: message`, the fixed ones among them.
 * @return array{string, string}
 */
function runSample(string $library): array
{
	$code = (string) file_get_contents(__DIR__ . "/samples/$library.code");
	$errorHandler = set_error_handler(null);
	set_error_handler($errorHandler);
	$exceptionHandler = set_exception_handler(null);
	set_exception_handler($exceptionHandler);
	$result = UpgradingTester::runSample($code, dirname(__DIR__), SampleDecisions, $library);
	// Larastan boots the application of testbench, whose handlers would swallow a failed assertion
	set_error_handler($errorHandler);
	set_exception_handler($exceptionHandler);
	$violations = array_map(fn($violation) => "$violation->line: $violation->message", $result->violations);
	return [(string) $result->output, implode("\n", $violations) . "\n"];
}
