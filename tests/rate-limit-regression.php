<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!file_exists(__DIR__ . '/../src/RateLimit.php')) {
	fail('RateLimit.php is missing');
}

require_once __DIR__ . '/../src/RateLimit.php';
require_once __DIR__ . '/../src/Options/Settings.php';

use AuthCRED\Options\Settings;
use AuthCRED\RateLimit;

function runCase(string $id, string $description, callable $assertion): void
{
	$assertion();
	echo "PASS: {$id} {$description}\n";
}

function clearRateLimitState(): void
{
	RateLimit::clear('login', 'tester', '127.0.0.1');
	RateLimit::clear('login', 'tester', '127.0.0.2');
	RateLimit::clear('login', 'other', '127.0.0.1');
	RateLimit::clear('register', 'tester', '127.0.0.1');
}

$originalSettings = get_option('authcred_settings', null);

try {
	delete_option('authcred_settings');
	clearRateLimitState();

	$settings = new Settings((object) ['prefix' => 'authcred']);

	runCase('TC-01', 'register() exposes the security section fields', static function () use ($settings): void {
		$setups = $settings->register([]);
		$fields = $setups[0]['fields']['security']['fields'] ?? [];

		assertTrue(isset($fields['max_login_attempts']), 'TC-01 max_login_attempts missing');
		assertTrue(isset($fields['login_lockout_minutes']), 'TC-01 login_lockout_minutes missing');
		assertTrue(isset($fields['max_register_attempts']), 'TC-01 max_register_attempts missing');
		assertTrue(isset($fields['register_lockout_minutes']), 'TC-01 register_lockout_minutes missing');
	});

	runCase('TC-02', 'sanitizeSettings clamps and defaults rate-limit security values', static function () use ($settings): void {
		$sanitized = $settings->sanitizeSettings([
			'max_login_attempts' => 0,
			'login_lockout_minutes' => 99999,
			'max_register_attempts' => 'bad',
			'register_lockout_minutes' => -4,
		]);

		assertSame(5, $sanitized['max_login_attempts'], 'TC-02 invalid max_login_attempts should fall back to 5');
		assertSame(10080, $sanitized['login_lockout_minutes'], 'TC-02 login_lockout_minutes should clamp to 10080');
		assertSame(3, $sanitized['max_register_attempts'], 'TC-02 invalid max_register_attempts should fall back to 3');
		assertSame(60, $sanitized['register_lockout_minutes'], 'TC-02 invalid register_lockout_minutes should fall back to 60');
	});

	runCase('TC-03', 'login attempts block at the configured threshold and expose remaining time', static function (): void {
		clearRateLimitState();

		for ($attempt = 1; $attempt <= 4; $attempt++) {
			$result = RateLimit::record('login', 'tester', '127.0.0.1');
			assertFalse($result['blocked'], 'TC-03 attempts before threshold should not block');
		}

		$blocked = RateLimit::record('login', 'tester', '127.0.0.1');

		assertTrue($blocked['blocked'], 'TC-03 fifth attempt should block');
		assertSame(5, $blocked['attempts'], 'TC-03 fifth attempt count mismatch');
		assertSame(5, $blocked['limit'], 'TC-03 login limit mismatch');
		assertTrue($blocked['remaining_seconds'] > 0, 'TC-03 remaining_seconds should be positive');
	});

	runCase('TC-04', 'clear() removes the stored counter', static function (): void {
		clearRateLimitState();
		RateLimit::record('login', 'tester', '127.0.0.1');
		RateLimit::clear('login', 'tester', '127.0.0.1');

		$status = RateLimit::check('login', 'tester', '127.0.0.1');

		assertFalse($status['blocked'], 'TC-04 cleared rate limit should not be blocked');
		assertSame(0, $status['attempts'], 'TC-04 cleared attempts should be zero');
	});

	runCase('TC-05', 'the key combines username and IP so either dimension changes the bucket', static function (): void {
		clearRateLimitState();

		for ($attempt = 1; $attempt <= 5; $attempt++) {
			RateLimit::record('login', 'tester', '127.0.0.1');
		}

		assertTrue(RateLimit::check('login', 'tester', '127.0.0.1')['blocked'], 'TC-05 baseline bucket should be blocked');
		assertFalse(RateLimit::check('login', 'tester', '127.0.0.2')['blocked'], 'TC-05 different IP should use a different bucket');
		assertFalse(RateLimit::check('login', 'other', '127.0.0.1')['blocked'], 'TC-05 different username should use a different bucket');
	});

	runCase('TC-06', 'register attempts use their own default threshold independently from login', static function (): void {
		clearRateLimitState();

		for ($attempt = 1; $attempt <= 2; $attempt++) {
			$result = RateLimit::record('register', 'tester', '127.0.0.1');
			assertFalse($result['blocked'], 'TC-06 attempts before register threshold should not block');
		}

		$blocked = RateLimit::record('register', 'tester', '127.0.0.1');

		assertTrue($blocked['blocked'], 'TC-06 third register attempt should block');
		assertSame(3, $blocked['limit'], 'TC-06 register limit mismatch');
		assertFalse(RateLimit::check('login', 'tester', '127.0.0.1')['blocked'], 'TC-06 login attempts should remain independent');
	});

	echo "ALL TESTS PASSED\n";
} finally {
	clearRateLimitState();

	if (null === $originalSettings) {
		delete_option('authcred_settings');
	} else {
		update_option('authcred_settings', $originalSettings);
	}
}
