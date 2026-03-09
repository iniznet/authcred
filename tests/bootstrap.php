<?php

declare(strict_types=1);

$_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Allow CI or other environments to override the local WordPress bootstrap path.
$wpLoadPath = getenv('WP_LOAD_PATH');

if (!is_string($wpLoadPath) || '' === trim($wpLoadPath)) {
	$wpLoadPath = 'F:\\laragon\\www\\fictioneer\\wp-load.php';
}

if (!file_exists($wpLoadPath)) {
	fwrite(STDERR, "Unable to locate wp-load.php at {$wpLoadPath}\n");
	exit(1);
}

require_once $wpLoadPath;

function fail(string $message): void
{
	fwrite(STDERR, $message . "\n");
	exit(1);
}

function assertTrue($condition, string $message): void
{
	if (true !== $condition) {
		fail('Assertion failed: ' . $message);
	}
}

function assertFalse($condition, string $message): void
{
	if (false !== $condition) {
		fail('Assertion failed: ' . $message);
	}
}

function assertSame($expected, $actual, string $message): void
{
	if ($expected !== $actual) {
		$expectedExport = var_export($expected, true);
		$actualExport = var_export($actual, true);
		fail("Assertion failed: {$message}. Expected {$expectedExport}, got {$actualExport}");
	}
}

function resetCaptchaCache(): void
{
	$property = new ReflectionProperty(AuthCRED\Captcha::class, 'resolvedProviders');
	$property->setAccessible(true);
	$property->setValue(null, []);
}
