<?php declare(strict_types=1);

use DressCode\Testing\UpgradingTester;

/** The rules a sample is run with: those the upgrading files feed. */
const UpgradingRules = [
	'replacedClasses',
	'replacedMembers',
	'replacedCalls',
	'replacedFunctions',
	'forbiddenClasses',
	'forbiddenMembers',
	'forbiddenFunctions',
	'attributeForAnnotation',
	'attributeForMember',
	'overridingSignature',
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
