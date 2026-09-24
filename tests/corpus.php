<?php declare(strict_types=1);

/**
 * Runs the upgrading files over code written for the current API, the tests of the framework itself or an application
 * skeleton, which they must leave as they are, except the files that use an old API on purpose:
 *
 *   php tests/corpus.php <dir> [<subdir>...] [--rename=<Class::member>=<name>] [--modernization] [--keep]
 *
 * Copies the directory, or the named subdirectories of it, into corpus-<pid>/ of the root, fixes the copy with the
 * upgrading files of this package alone, and reports every file the run changed: `legacy` for a file known to use an
 * old API on purpose, `CHANGED` with its diff for any other, `BROKEN` for one php -l refuses, and a second run must
 * change nothing. Take the tests of a checkout of the framework at the tag of the installed version. --rename adds an
 * entry of replacedMembers that is wrong on purpose, for the harness to prove it fails; --modernization runs the
 * modernizations alone, the attributes of 13 among them, whose rewrites are `offered` with their diff to be read;
 * --keep leaves the copy. The verdict is the exit code: 1 for a file CHANGED or BROKEN, or a second run that changed
 * anything.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/samples.php';

/** Files of the tests of the framework that are no code on purpose, left out of the copy. */
const NotCode = [
	'Foundation/Fixtures/bad-syntax-strategy.php', // a syntax error the framework tests it reports
];

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$options = array_values(array_filter($args, fn(string $arg) => str_starts_with($arg, '--')));
$subdirs = array_values(array_diff($args, $options));
$source = array_shift($subdirs);
if ($source === null || !is_dir($source)) {
	exit("Usage: php tests/corpus.php <dir> [<subdir>...] [--rename=<Class::member>=<name>] [--modernization] [--keep]\n");
}

$source = rtrim(str_replace('\\', '/', $source), '/');
$name = 'corpus-' . getmypid(); // so that runs over several parts do not meet
$corpus = "$root/$name";
Nette\Utils\FileSystem::delete($corpus);
foreach ($subdirs ?: ['.'] as $subdir) {
	if (!is_dir("$source/$subdir")) {
		echo "Directory $subdir is not in $source.\n";
		exit(1);
	}

	Nette\Utils\FileSystem::copy("$source/$subdir", "$corpus/$subdir");
}

foreach (NotCode as $file) {
	@unlink("$corpus/$file"); // @ the part copied may not have the file
}

// Larastan reflects the classes of the code at runtime too, which an application loads by its Composer autoload. It
// starts two applications of testbench, one discovering no packages, which rewrite the manifest of providers of each
// other, and on Windows a scanner holding the file just written makes the rename of the other fail: with no packages
// to discover the two agree, and each process writes a manifest of its own once
$php = str_replace('\\', '/', "$root/$name-php");
$cache = preg_replace('~^[a-z]:~i', '', $php); // Laravel takes only a path beginning with a slash for an absolute one
Nette\Utils\FileSystem::write("$php/packages.php", '<?php return [];');
Nette\Utils\FileSystem::write("$php/prepend.php", '<?php $map = ' . var_export(findClasses($corpus), return: true) . ';'
	. ' spl_autoload_register(function (string $class) use ($map): void { if (isset($map[$class])) { require_once $map[$class]; } }, prepend: true);'
	. ' putenv(' . var_export("APP_PACKAGES_CACHE=$cache/packages.php", return: true) . ');'
	. ' putenv(' . var_export("APP_SERVICES_CACHE=$cache/services-", return: true) . ' . getmypid() . \'.php\');');

$originals = hashFiles($corpus);
// the data alone: overridingSignature declares what a child may leave out, which is no mistake of the data, and the
// modernizations are offered for code that is right as it is
$modernization = in_array('--modernization', $options, true);
$rules = array_fill_keys(
	$modernization ? ModernizationRules : array_diff(UpgradingRules, ['overridingSignature'], ModernizationRules),
	true,
);
foreach ($options as $option) {
	if (preg_match('~^--rename=(.+::\w+)=(\w+)$~', $option, $m)) {
		$rules['replacedMembers'] = [$m[1] => $m[2]];
	}
}

$installed = array_filter(array_map(
	fn(array $package) => $package['version'],
	DressCode\Config\ProjectPackages::read($root)->installed,
));
Nette\Utils\FileSystem::write("$root/$name.neon", Nette\Neon\Neon::encode([
	'paths' => [$name],
	'fileExtensions' => ['php'],
	'types' => 'phpstan',
	'packages' => $installed,
	'rules' => $rules,
], blockMode: true));

$failed = false;
$copies = [];
foreach ([1, 2] as $run) {
	$before = hashFiles($corpus);
	exec('php -d auto_prepend_file=' . escapeshellarg("$php/prepend.php") . ' ' . escapeshellarg("$root/vendor/dresscode/dresscode/bin/dresscode") . ' fix --no-cache --fix-risky --format bare --config ' . escapeshellarg("$root/$name.neon") . ' 2>&1', $output, $exit);
	if ($exit !== 0 && $exit !== 1) {
		echo "run $run: dresscode exited with $exit\n" . implode("\n", array_slice($output, -30)) . "\n";
		$failed = true;
	}

	$after = hashFiles($corpus);
	if ($run === 2 && $after !== $before) {
		echo 'second run changed: ' . implode(', ', array_keys(array_diff_assoc($after, $before))) . "\n";
		$failed = true;
	}

	$copies = $after;
	$output = [];
}

$counts = ['legacy' => 0, 'offered' => 0, 'CHANGED' => 0, 'BROKEN' => 0];
foreach (array_keys(array_diff_assoc($copies, $originals)) as $file) {
	$path = "$corpus/$file";
	exec('php -l ' . escapeshellarg($path) . ' 2>&1', $lint, $lintExit);
	$lint = [];
	$verdict = match (true) {
		$lintExit !== 0 => 'BROKEN',
		$modernization => 'offered',
		isLegacy($file) => 'legacy',
		default => 'CHANGED',
	};
	$counts[$verdict]++;
	echo "$verdict  $file\n";
	if ($verdict !== 'legacy') {
		exec('git diff --no-index --no-color -U1 ' . escapeshellarg("$source/$file") . ' ' . escapeshellarg($path) . ' 2>&1', $diff);
		echo implode("\n", array_slice($diff, 4)) . "\n";
		$diff = [];
		$failed = $failed || $verdict !== 'offered';
	}
}

echo 'files ' . count($originals) . ', changed ' . array_sum($counts) . " (legacy $counts[legacy], offered $counts[offered], CHANGED $counts[CHANGED], BROKEN $counts[BROKEN])\n";
if (!in_array('--keep', $options, true)) {
	try {
		Nette\Utils\FileSystem::delete("$root/$name.neon");
		Nette\Utils\FileSystem::delete($php);
		Nette\Utils\FileSystem::delete($corpus);
	} catch (Nette\IOException) {
		echo "left behind, a file of it is locked: $name\n"; // a scanner of the system may hold a file for a moment
	}
}

exit($failed ? 1 : 0);


/**
 * The file of every class, interface, trait and enum the PHP files under the directory declare, by its name.
 * @return array<string, string>
 */
function findClasses(string $dir): array
{
	$map = [];
	foreach (Nette\Utils\Finder::findFiles('*.php')->from($dir) as $file) {
		$namespace = '';
		$tokens = token_get_all((string) file_get_contents($file->getPathname()));
		foreach ($tokens as $i => $token) {
			if ($token[0] === T_NAMESPACE && isset($tokens[$i + 2][1])) {
				$namespace = $tokens[$i + 2][1] . '\\';
			} elseif (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
				&& ($tokens[$i - 1][0] ?? null) !== T_DOUBLE_COLON // Foo::class
				&& ($tokens[$i + 2][0] ?? null) === T_STRING // not an anonymous class
			) {
				$map[$namespace . $tokens[$i + 2][1]] = $file->getPathname();
			}
		}
	}

	return $map;
}


/**
 * The hash of every PHP file under the directory, by its path relative to it.
 * @return array<string, string>
 */
function hashFiles(string $dir): array
{
	$hashes = [];
	foreach (Nette\Utils\Finder::findFiles('*.php')->from($dir) as $file) {
		$hashes[str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1))] = md5((string) file_get_contents($file->getPathname()));
	}

	ksort($hashes);
	return $hashes;
}


/**
 * Whether the file uses an old API on purpose: the tests of Laravel mark no intent for it, so the files are listed,
 * each with the reason.
 */
function isLegacy(string $file): bool
{
	$legacy = [
		'Foundation/Http/KernelTest.php', // getRouteMiddleware(), deprecated since 10
		'Foundation/Http/Middleware/ConvertEmptyStringsToNullTest.php', // get() of the request, deprecated since 12.51
		'Foundation/Http/Middleware/TransformsRequestTest.php', // the same
		'Foundation/Http/Middleware/TrimStringsTest.php', // the same
		'Http/HttpRequestTest.php', // the same, besides testing get() itself
		'Routing/RoutingUrlGeneratorTest.php', // forceRootUrl(), deprecated since 11.42
		'Support/SupportCollectionTest.php', // tests containsOneItem() and containsManyItems(), deprecated since 12.49 and 12.50
		'Support/SupportLazyCollectionTest.php', // the same
	];
	return array_any($legacy, fn(string $path) => str_ends_with($file, $path));
}
