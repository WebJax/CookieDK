#!/usr/bin/env php
<?php
/**
 * Bump CookieDK display version across plugin files.
 *
 * Source of truth: COOKIEDK_VERSION in cookiedk.php.
 * COOKIEDK_DB_VERSION is a schema version and is never changed here.
 *
 * Usage:
 *   php scripts/bump-version.php patch
 *   php scripts/bump-version.php minor
 *   php scripts/bump-version.php major
 *   php scripts/bump-version.php --version 1.3.0
 *   php scripts/bump-version.php --dry-run patch
 *   php scripts/bump-version.php --current
 *   php scripts/bump-version.php --notes 1.2.9
 *   php scripts/bump-version.php --check
 */

declare(strict_types=1);

const REPO_URL = 'https://github.com/WebJax/CookieDK';
const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

/**
 * @return array{version?:string,current?:bool,notes?:string,dry_run?:bool,root?:string,stdin?:bool,check?:bool,help?:bool}
 */
function parse_options(array $argv, int &$rest_index): array {
	$parsed = getopt(
		'',
		array(
			'version:',
			'current',
			'notes:',
			'dry-run',
			'root:',
			'stdin',
			'check',
			'help',
		),
		$rest_index
	);

	if (false === $parsed) {
		fwrite(STDERR, "Could not parse arguments.\n");
		exit(1);
	}

	return $parsed;
}

function print_help(): void {
	$help = <<<'TXT'
Bump CookieDK display version across plugin files.

Usage:
  php scripts/bump-version.php patch|minor|major
  php scripts/bump-version.php --version X.Y.Z
  php scripts/bump-version.php --dry-run patch
  php scripts/bump-version.php --current
  php scripts/bump-version.php --notes X.Y.Z
  php scripts/bump-version.php --check

Options:
  --root DIR   Plugin root (default: repository root)
  --stdin      Read cookiedk.php from stdin with --current
  --dry-run    Print the new version without writing files

Does not change COOKIEDK_DB_VERSION.
TXT;
	echo $help . PHP_EOL;
}

function repo_root(array $options): string {
	if (isset($options['root']) && is_string($options['root']) && '' !== $options['root']) {
		$root = $options['root'];
	} else {
		$root = dirname(__DIR__);
	}

	$real = realpath($root);
	if (false === $real) {
		fwrite(STDERR, "Root directory not found: {$root}\n");
		exit(1);
	}

	return $real;
}

function read_version_from_plugin_source(string $source): string {
	if (! preg_match("/define\(\s*'COOKIEDK_VERSION'\s*,\s*'([^']+)'\s*\)/", $source, $matches)) {
		fwrite(STDERR, "Could not find COOKIEDK_VERSION in cookiedk.php.\n");
		exit(1);
	}

	$version = $matches[1];
	assert_semver($version, 'COOKIEDK_VERSION');
	return $version;
}

function read_header_version(string $source): string {
	if (! preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $source, $matches)) {
		fwrite(STDERR, "Could not find Version header in cookiedk.php.\n");
		exit(1);
	}

	$version = $matches[1];
	assert_semver($version, 'plugin header Version');
	return $version;
}

function read_db_version(string $source): string {
	if (! preg_match("/define\(\s*'COOKIEDK_DB_VERSION'\s*,\s*'([^']+)'\s*\)/", $source, $matches)) {
		fwrite(STDERR, "Could not find COOKIEDK_DB_VERSION in cookiedk.php.\n");
		exit(1);
	}

	return $matches[1];
}

function assert_semver(string $version, string $label): void {
	if (! preg_match(VERSION_PATTERN, $version)) {
		fwrite(STDERR, "{$label} is not X.Y.Z: {$version}\n");
		exit(1);
	}
}

function bump_semver(string $current, string $level): string {
	$parts = array_map('intval', explode('.', $current));
	if (3 !== count($parts)) {
		fwrite(STDERR, "Cannot bump version: {$current}\n");
		exit(1);
	}

	if ('major' === $level) {
		return ($parts[0] + 1) . '.0.0';
	}
	if ('minor' === $level) {
		return $parts[0] . '.' . ($parts[1] + 1) . '.0';
	}
	if ('patch' === $level) {
		return $parts[0] . '.' . $parts[1] . '.' . ($parts[2] + 1);
	}

	fwrite(STDERR, "Unknown bump level: {$level}\n");
	exit(1);
}

function replace_once(string $pattern, string $replacement, string $source, string $label): string {
	$result = preg_replace($pattern, $replacement, $source, 1, $count);
	if (null === $result || 1 !== $count) {
		fwrite(STDERR, "Failed to update {$label}.\n");
		exit(1);
	}

	return $result;
}

function today_copenhagen(): string {
	return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
}

function extract_section_body(string $changelog, string $heading_version): string {
	$quoted = preg_quote($heading_version, '/');
	if (! preg_match('/^## \[' . $quoted . '\][^\n]*\n/m', $changelog, $matches, PREG_OFFSET_CAPTURE)) {
		fwrite(STDERR, "Could not find CHANGELOG section for {$heading_version}.\n");
		exit(1);
	}

	$start = $matches[0][1] + strlen($matches[0][0]);
	if (preg_match('/^## \[/m', $changelog, $next, PREG_OFFSET_CAPTURE, $start)) {
		$end = $next[0][1];
	} elseif (preg_match('/^\[/m', $changelog, $next, PREG_OFFSET_CAPTURE, $start)) {
		$end = $next[0][1];
	} else {
		$end = strlen($changelog);
	}

	return rtrim(substr($changelog, $start, $end - $start), "\n");
}

function wordpress_changelog_items(string $unreleased_body): string {
	$items = array();
	$lines = preg_split('/\R/', $unreleased_body);
	if (! is_array($lines)) {
		return "* See CHANGELOG.md for the full list of changes.\n";
	}

	foreach ($lines as $line) {
		if (preg_match('/^####\s+(.+)/', $line, $matches)) {
			$items[] = '* ' . trim($matches[1]);
		}
	}

	if (array() === $items) {
		foreach ($lines as $line) {
			if (preg_match('/^-\s+(.+)/', $line, $matches)) {
				$items[] = '* ' . trim($matches[1]);
			}
		}
	}

	$items = array_slice($items, 0, 15);
	if (array() === $items) {
		return "* See CHANGELOG.md for the full list of changes.\n";
	}

	return implode("\n", $items) . "\n";
}

function update_changelog(string $content, string $new_version, string $date): string {
	if (! preg_match('/^## \[Unreleased\][^\n]*\n/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
		fwrite(STDERR, "Could not find ## [Unreleased] in CHANGELOG.md.\n");
		exit(1);
	}

	$heading_end = $matches[0][1] + strlen($matches[0][0]);
	if (! preg_match('/^## \[/m', $content, $next, PREG_OFFSET_CAPTURE, $heading_end)) {
		fwrite(STDERR, "Could not find the next CHANGELOG heading after Unreleased.\n");
		exit(1);
	}

	$next_pos = $next[0][1];
	$body     = substr($content, $heading_end, $next_pos - $heading_end);
	if (! preg_match('/\S/', $body)) {
		$body = "\n";
	} else {
		$body = rtrim($body, "\n") . "\n\n";
	}

	$replacement = "## [Unreleased]\n\n## [{$new_version}] – {$date}\n" . $body;
	$content     = substr($content, 0, $matches[0][1]) . $replacement . substr($content, $next_pos);

	$unreleased_link = '[Unreleased]: ' . REPO_URL . '/compare/v' . $new_version . '...HEAD';
	$version_link    = '[' . $new_version . ']: ' . REPO_URL . '/releases/tag/v' . $new_version;

	if (preg_match('/^\[Unreleased\]:.*$/m', $content)) {
		$content = preg_replace('/^\[Unreleased\]:.*$/m', $unreleased_link, $content, 1);
		if (null === $content) {
			fwrite(STDERR, "Failed to update Unreleased changelog link.\n");
			exit(1);
		}
	} elseif (preg_match('/^\[\d+\.\d+\.\d+\]:/m', $content)) {
		$content = preg_replace('/^\[\d+\.\d+\.\d+\]:/m', $unreleased_link . "\n$0", $content, 1);
		if (null === $content) {
			fwrite(STDERR, "Failed to insert Unreleased changelog link.\n");
			exit(1);
		}
	} else {
		$content = rtrim($content, "\n") . "\n\n" . $unreleased_link . "\n";
	}

	$quoted_new = preg_quote($new_version, '/');
	if (! preg_match('/^\[' . $quoted_new . '\]:/m', $content)) {
		$content = preg_replace('/^\[Unreleased\]:.*$/m', '$0' . "\n" . $version_link, $content, 1);
		if (null === $content) {
			fwrite(STDERR, "Failed to insert version changelog link.\n");
			exit(1);
		}
	}

	return $content;
}

function update_readme_txt(string $content, string $new_version, string $unreleased_body): string {
	$content = replace_once(
		'/^Stable tag:\s*.+$/m',
		'Stable tag: ' . $new_version,
		$content,
		'readme.txt Stable tag'
	);

	$quoted_new = preg_quote($new_version, '/');
	if (preg_match('/^= ' . $quoted_new . ' =/m', $content)) {
		return $content;
	}

	$items = wordpress_changelog_items($unreleased_body);
	$block = "= {$new_version} =\n{$items}";
	if (! preg_match('/^== Changelog ==\s*$/m', $content)) {
		return rtrim($content, "\n") . "\n\n== Changelog ==\n" . $block;
	}

	$result = preg_replace('/^== Changelog ==\s*$/m', "== Changelog ==\n" . $block, $content, 1, $count);
	if (null === $result || 1 !== $count) {
		fwrite(STDERR, "Failed to prepend readme.txt changelog.\n");
		exit(1);
	}

	return $result;
}

function update_plugin_file(string $content, string $new_version): string {
	$db_version = read_db_version($content);

	$content = replace_once(
		'/^(\s*\*\s*Version:\s*).+$/m',
		'${1}' . $new_version,
		$content,
		'plugin header Version'
	);
	$content = replace_once(
		"/define\(\s*'COOKIEDK_VERSION'\s*,\s*'[^']+'\s*\)/",
		"define( 'COOKIEDK_VERSION', '{$new_version}' )",
		$content,
		'COOKIEDK_VERSION'
	);

	if (read_db_version($content) !== $db_version) {
		fwrite(STDERR, "Refusing to continue: COOKIEDK_DB_VERSION changed unexpectedly.\n");
		exit(1);
	}

	return $content;
}

function update_readme_md(string $content, string $new_version): string {
	return replace_once(
		'/Version-\d+\.\d+\.\d+-orange/',
		'Version-' . $new_version . '-orange',
		$content,
		'README.md version badge'
	);
}

function read_file(string $path): string {
	$contents = file_get_contents($path);
	if (false === $contents) {
		fwrite(STDERR, "Could not read {$path}.\n");
		exit(1);
	}

	return $contents;
}

function write_file(string $path, string $contents): void {
	if (false === file_put_contents($path, $contents)) {
		fwrite(STDERR, "Could not write {$path}.\n");
		exit(1);
	}
}

function run_check(string $root): int {
	$plugin      = read_file($root . '/cookiedk.php');
	$readme_txt  = read_file($root . '/readme.txt');
	$readme_md   = read_file($root . '/README.md');
	$version     = read_version_from_plugin_source($plugin);
	$header      = read_header_version($plugin);
	$errors      = array();

	if ($header !== $version) {
		$errors[] = "Plugin header Version is {$header}, COOKIEDK_VERSION is {$version}.";
	}

	if (! preg_match('/^Stable tag:\s*(\S+)/m', $readme_txt, $matches)) {
		$errors[] = 'readme.txt has no Stable tag.';
	} elseif ($matches[1] !== $version) {
		$errors[] = "readme.txt Stable tag is {$matches[1]}, COOKIEDK_VERSION is {$version}.";
	}

	if (! preg_match('/Version-(\d+\.\d+\.\d+)-orange/', $readme_md, $matches)) {
		$errors[] = 'README.md has no Version-x.y.z badge.';
	} elseif ($matches[1] !== $version) {
		$errors[] = "README.md badge is {$matches[1]}, COOKIEDK_VERSION is {$version}.";
	}

	if (array() !== $errors) {
		fwrite(STDERR, implode("\n", $errors) . "\n");
		return 1;
	}

	echo "Display version {$version} is consistent. COOKIEDK_DB_VERSION is " . read_db_version($plugin) . ".\n";
	return 0;
}

function unreleased_body_from_changelog(string $changelog): string {
	if (! preg_match('/^## \[Unreleased\][^\n]*\n/m', $changelog, $matches, PREG_OFFSET_CAPTURE)) {
		return '';
	}

	$start = $matches[0][1] + strlen($matches[0][0]);
	if (! preg_match('/^## \[/m', $changelog, $next, PREG_OFFSET_CAPTURE, $start)) {
		return trim(substr($changelog, $start));
	}

	return trim(substr($changelog, $start, $next[0][1] - $start));
}

$rest_index = 0;
$options    = parse_options($argv, $rest_index);
$positionals = array_slice($argv, $rest_index);

if (isset($options['help'])) {
	print_help();
	exit(0);
}

$root = repo_root($options);

if (isset($options['current'])) {
	if (isset($options['stdin'])) {
		$source = stream_get_contents(STDIN);
		if (false === $source) {
			fwrite(STDERR, "Could not read cookiedk.php from stdin.\n");
			exit(1);
		}
	} else {
		$source = read_file($root . '/cookiedk.php');
	}
	echo read_version_from_plugin_source($source) . PHP_EOL;
	exit(0);
}

if (isset($options['check'])) {
	exit(run_check($root));
}

if (isset($options['notes'])) {
	$notes_version = (string) $options['notes'];
	assert_semver($notes_version, '--notes');
	$changelog = read_file($root . '/CHANGELOG.md');
	$body      = trim(extract_section_body($changelog, $notes_version));
	if ('' === $body) {
		$body = 'See CHANGELOG.md for details.';
	}
	echo "## CookieDK v{$notes_version}\n\n{$body}\n";
	exit(0);
}

$level = $positionals[0] ?? null;
if (isset($options['version'])) {
	$new_version = (string) $options['version'];
	assert_semver($new_version, '--version');
} elseif (is_string($level) && in_array($level, array( 'patch', 'minor', 'major' ), true)) {
	$new_version = '';
} else {
	fwrite(STDERR, "Provide patch, minor, major, or --version X.Y.Z.\n");
	print_help();
	exit(1);
}

$plugin_path   = $root . '/cookiedk.php';
$readme_txt_path = $root . '/readme.txt';
$readme_md_path  = $root . '/README.md';
$changelog_path  = $root . '/CHANGELOG.md';

$plugin      = read_file($plugin_path);
$readme_txt  = read_file($readme_txt_path);
$readme_md   = read_file($readme_md_path);
$changelog   = read_file($changelog_path);
$current     = read_version_from_plugin_source($plugin);
$db_version  = read_db_version($plugin);

if ('' === $new_version) {
	$new_version = bump_semver($current, (string) $level);
}

if ($new_version === $current) {
	fwrite(STDERR, "Already at {$current}.\n");
	exit(1);
}

$unreleased_body = unreleased_body_from_changelog($changelog);
$date            = today_copenhagen();

echo "Bumping display version {$current} -> {$new_version}\n";
echo "COOKIEDK_DB_VERSION stays {$db_version}\n";

if (isset($options['dry-run'])) {
	exit(0);
}

$updated_plugin = update_plugin_file($plugin, $new_version);
if (read_db_version($updated_plugin) !== $db_version) {
	fwrite(STDERR, "COOKIEDK_DB_VERSION would change; aborting.\n");
	exit(1);
}

write_file($plugin_path, $updated_plugin);
write_file($readme_txt_path, update_readme_txt($readme_txt, $new_version, $unreleased_body));
write_file($readme_md_path, update_readme_md($readme_md, $new_version));
write_file($changelog_path, update_changelog($changelog, $new_version, $date));

if (0 !== run_check($root)) {
	fwrite(STDERR, "Version files are inconsistent after bump.\n");
	exit(1);
}

echo "Updated cookiedk.php, readme.txt, README.md, CHANGELOG.md\n";
