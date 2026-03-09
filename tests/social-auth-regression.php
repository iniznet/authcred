<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

foreach ([
	__DIR__ . '/../src/Options/Settings.php',
	__DIR__ . '/../src/AuthShortcode.php',
	__DIR__ . '/../src/EmailTemplates.php',
] as $requiredFile) {
	if (!file_exists($requiredFile)) {
		fail(basename($requiredFile) . ' is missing');
	}

	require_once $requiredFile;
}

if (!file_exists(__DIR__ . '/../src/SocialAuth.php')) {
	fail('SocialAuth.php is missing');
}

require_once __DIR__ . '/../src/SocialAuth.php';

use AuthCRED\AuthShortcode;
use AuthCRED\Options\Settings;
use AuthCRED\SocialAuth;

function runCase(string $id, string $description, callable $assertion): void
{
	$assertion();
	echo "PASS: {$id} {$description}\n";
}

function assertContainsText(string $needle, string $haystack, string $message): void
{
	if (false === strpos($haystack, $needle)) {
		fail('Assertion failed: ' . $message);
	}
}

$originalSettings = get_option('authcred_settings', null);
$originalGet = $_GET;
$createdUserIds = [];

try {
	delete_option('authcred_settings');
	$_GET = [];

	$settings = new Settings((object) ['prefix' => 'authcred']);

	runCase('TC-01', 'register() exposes the social settings fields', static function () use ($settings): void {
		$setups = $settings->register([]);
		$fields = $setups[0]['fields']['social']['fields'] ?? [];

		assertTrue(isset($fields['google_client_id']), 'TC-01 google_client_id missing');
		assertTrue(isset($fields['google_client_secret']), 'TC-01 google_client_secret missing');
		assertTrue(isset($fields['discord_client_id']), 'TC-01 discord_client_id missing');
		assertTrue(isset($fields['discord_client_secret']), 'TC-01 discord_client_secret missing');
	});

	runCase('TC-02', 'sanitizeSettings strips social credentials to plain text', static function () use ($settings): void {
		$sanitized = $settings->sanitizeSettings([
			'google_client_id' => ' <b>google-id</b> ',
			'google_client_secret' => ' <script>alert(1)</script>secret ',
			'discord_client_id' => ' discord-id ',
			'discord_client_secret' => ' discord-secret ',
		]);

		assertSame('google-id', $sanitized['google_client_id'], 'TC-02 google client id should be plain text');
		assertSame('secret', $sanitized['google_client_secret'], 'TC-02 google client secret should be plain text');
		assertSame('discord-id', $sanitized['discord_client_id'], 'TC-02 discord client id should be plain text');
		assertSame('discord-secret', $sanitized['discord_client_secret'], 'TC-02 discord client secret should be plain text');
	});

	runCase('TC-03', 'AuthShortcode prepareSocialArgs disables providers and rejects unknown error keys when credentials are absent', static function (): void {
		delete_option('authcred_settings');
		$_GET['authcred-error'] = 'totally_invalid';

		$shortcode = new AuthShortcode((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(AuthShortcode::class, 'prepareSocialArgs');
		$method->setAccessible(true);

		$args = $method->invoke($shortcode, [
			'goto' => 'https://evil.example/redirect',
		]);

		assertFalse($args['social_enabled'], 'TC-03 social_enabled should be false without credentials');
		assertFalse($args['social_google_enabled'], 'TC-03 google should be disabled without credentials');
		assertFalse($args['social_discord_enabled'], 'TC-03 discord should be disabled without credentials');
		assertSame('', $args['social_google_url'], 'TC-03 google URL should be blank without credentials');
		assertSame('', $args['social_discord_url'], 'TC-03 discord URL should be blank without credentials');
		assertSame('', $args['authcred_error'], 'TC-03 invalid error key should be rejected');
		assertSame('', $args['authcred_error_message'], 'TC-03 invalid error message should be blank');
	});

	runCase('TC-04', 'AuthShortcode prepareSocialArgs enables providers, validates goto, and maps allowlisted auth errors', static function (): void {
		update_option('authcred_settings', [
			'google_client_id' => 'google-id',
			'google_client_secret' => 'google-secret',
			'discord_client_id' => 'discord-id',
			'discord_client_secret' => 'discord-secret',
		]);
		$_GET['authcred-error'] = 'email_unverified';

		$shortcode = new AuthShortcode((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(AuthShortcode::class, 'prepareSocialArgs');
		$method->setAccessible(true);

		$args = $method->invoke($shortcode, [
			'goto' => 'https://evil.example/redirect',
		]);
		parse_str((string) wp_parse_url($args['social_google_url'], PHP_URL_QUERY), $googleQueryArgs);

		assertTrue($args['social_enabled'], 'TC-04 social_enabled should be true with both credentials configured');
		assertTrue($args['social_google_enabled'], 'TC-04 google should be enabled');
		assertTrue($args['social_discord_enabled'], 'TC-04 discord should be enabled');
		assertContainsText('authcred-social=google', $args['social_google_url'], 'TC-04 google URL should point to the social init route');
		assertContainsText('authcred-social=discord', $args['social_discord_url'], 'TC-04 discord URL should point to the social init route');
		assertSame(home_url('/'), $googleQueryArgs['goto'] ?? '', 'TC-04 unsafe goto should fall back to home_url');
		assertSame('email_unverified', $args['authcred_error'], 'TC-04 allowlisted error key mismatch');
		assertContainsText('could not be verified', $args['authcred_error_message'], 'TC-04 allowlisted error message mismatch');
	});

	runCase('TC-05', 'SocialAuth exposes stable callback URLs for both providers', static function (): void {
		$socialAuth = new SocialAuth((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(SocialAuth::class, 'getCallbackUrl');
		$method->setAccessible(true);

		assertSame(home_url('/authcred/callback/google/'), $method->invoke($socialAuth, 'google'), 'TC-05 google callback URL mismatch');
		assertSame(home_url('/authcred/callback/discord/'), $method->invoke($socialAuth, 'discord'), 'TC-05 discord callback URL mismatch');
	});

	runCase('TC-06', 'SocialAuth OAuth state storage is single-use and normalizes unsafe goto targets', static function (): void {
		$socialAuth = new SocialAuth((object) ['prefix' => 'authcred']);
		$storeMethod = new ReflectionMethod(SocialAuth::class, 'storeState');
		$storeMethod->setAccessible(true);
		$consumeMethod = new ReflectionMethod(SocialAuth::class, 'consumeState');
		$consumeMethod->setAccessible(true);

		$state = $storeMethod->invoke($socialAuth, 'google', 'https://evil.example/redirect');
		$payload = $consumeMethod->invoke($socialAuth, $state, 'google');

		assertSame('google', $payload['provider'], 'TC-06 provider mismatch');
		assertSame(home_url('/'), $payload['goto'], 'TC-06 unsafe goto should normalize to home_url');

		$secondRead = $consumeMethod->invoke($socialAuth, $state, 'google');
		assertSame(null, $secondRead, 'TC-06 consumed state should not be reusable');
	});

	runCase('TC-07', 'SocialAuth links an existing user by verified email and stores provider metadata', static function () use (&$createdUserIds): void {
		$uniqueSuffix = (string) wp_rand(1000, 999999);
		$username = 'social-auth-existing-' . $uniqueSuffix;
		$email = 'social-auth-existing-' . $uniqueSuffix . '@example.com';
		$userId = wp_create_user($username, wp_generate_password(24), $email);
		if (is_wp_error($userId)) {
			fail('TC-07 failed to create existing user: ' . $userId->get_error_message());
		}

		$createdUserIds[] = $userId;
		wp_set_current_user(0);

		$socialAuth = new SocialAuth((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(SocialAuth::class, 'linkOrCreateUser');
		$method->setAccessible(true);

		$resultUserId = $method->invoke($socialAuth, 'google', [
			'id' => 'google-user-123',
			'email' => $email,
			'email_verified' => true,
			'name' => 'Social Auth Existing',
		]);

		assertSame($userId, $resultUserId, 'TC-07 existing user should be linked instead of recreated');
		assertSame('google-user-123', get_user_meta($userId, 'authcred_google_user_id', true), 'TC-07 google user id meta mismatch');
		assertSame($userId, get_current_user_id(), 'TC-07 linked user should be authenticated');
	});

	echo "ALL TESTS PASSED\n";
} finally {
	wp_set_current_user(0);
	$_GET = $originalGet;

	foreach ($createdUserIds as $createdUserId) {
		wp_delete_user($createdUserId);
	}

	if (null === $originalSettings) {
		delete_option('authcred_settings');
	} else {
		update_option('authcred_settings', $originalSettings);
	}
}
