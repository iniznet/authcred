<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Options/Settings.php';
require_once __DIR__ . '/../src/UserAuth.php';

if (!file_exists(__DIR__ . '/../src/EmailTemplates.php')) {
	fail('EmailTemplates.php is missing');
}

require_once __DIR__ . '/../src/EmailTemplates.php';

use AuthCRED\EmailTemplates;
use AuthCRED\Options\Settings;
use AuthCRED\UserAuth;

function runCase(string $id, string $description, callable $assertion): void
{
	$assertion();
	echo "PASS: {$id} {$description}\n";
}

function assertContains(string $needle, string $haystack, string $message): void
{
	if (false === strpos($haystack, $needle)) {
		fail('Assertion failed: ' . $message);
	}
}

function assertNotContains(string $needle, string $haystack, string $message): void
{
	if (false !== strpos($haystack, $needle)) {
		fail('Assertion failed: ' . $message);
	}
}

$originalSettings = get_option('authcred_settings', null);

try {
	delete_option('authcred_settings');

	$settings = new Settings((object) ['prefix' => 'authcred']);

	runCase('TC-01', 'register() exposes the emails section fields', static function () use ($settings): void {
		$setups = $settings->register([]);
		$fields = $setups[0]['fields']['emails']['fields'] ?? [];

		assertTrue(isset($fields['email_setup_subject']), 'TC-01 email_setup_subject missing');
		assertTrue(isset($fields['email_setup_body']), 'TC-01 email_setup_body missing');
		assertTrue(isset($fields['email_reset_subject']), 'TC-01 email_reset_subject missing');
		assertTrue(isset($fields['email_reset_body']), 'TC-01 email_reset_body missing');
	});

	runCase('TC-02', 'sanitizeSettings strips subjects and restores default email templates when values are blank', static function () use ($settings): void {
		$sanitized = $settings->sanitizeSettings([
			'email_setup_subject' => ' <strong>Custom setup subject</strong> ',
			'email_setup_body' => '',
			'email_reset_subject' => '',
			'email_reset_body' => '',
		]);

		$defaults = EmailTemplates::defaults();

		assertSame('Custom setup subject', $sanitized['email_setup_subject'], 'TC-02 setup subject should strip markup to plain text');
		assertSame($defaults['setup_password']['body'], $sanitized['email_setup_body'], 'TC-02 blank setup body should fall back to default');
		assertSame($defaults['reset_password']['subject'], $sanitized['email_reset_subject'], 'TC-02 blank reset subject should fall back to default');
		assertSame($defaults['reset_password']['body'], $sanitized['email_reset_body'], 'TC-02 blank reset body should fall back to default');
	});

	runCase('TC-03', 'buildSetupPassword() uses defaults and replaces all supported tokens', static function (): void {
		delete_option('authcred_settings');
		$template = EmailTemplates::buildSetupPassword('alice', 'alice@example.com', 'https://example.com/setup');

		assertSame('Set up your new password', $template['subject'], 'TC-03 default setup subject mismatch');
		assertContains('alice', $template['body'], 'TC-03 username token not replaced');
		assertContains('alice@example.com', $template['body'], 'TC-03 email token not replaced');
		assertContains('https://example.com/setup', $template['body'], 'TC-03 link token not replaced');
		assertContains(get_bloginfo('name'), $template['body'], 'TC-03 site_name token not replaced');
		assertNotContains('{username}', $template['body'], 'TC-03 username token placeholder leaked');
		assertNotContains('{email}', $template['body'], 'TC-03 email token placeholder leaked');
		assertNotContains('{link}', $template['body'], 'TC-03 link token placeholder leaked');
		assertNotContains('{site_name}', $template['body'], 'TC-03 site_name token placeholder leaked');
	});

	runCase('TC-04', 'buildResetPassword() uses customized settings and exposes the full template filter', static function (): void {
		update_option('authcred_settings', [
			'email_reset_subject' => 'Reset for {username}',
			'email_reset_body' => '<p>Reset {email} via {link} on {site_name}</p>',
		]);

		$callback = static function (array $email): array {
			$email['subject'] .= ' [filtered]';
			return $email;
		};

		add_filter('authcred/email/reset_request', $callback);
		$template = EmailTemplates::buildResetPassword('bob', 'bob@example.com', 'https://example.com/reset');
		remove_filter('authcred/email/reset_request', $callback);

		assertSame('Reset for bob [filtered]', $template['subject'], 'TC-04 custom reset subject mismatch');
		assertContains('bob@example.com', $template['body'], 'TC-04 custom reset body should include email');
		assertContains('https://example.com/reset', $template['body'], 'TC-04 custom reset body should include link');
		assertContains(get_bloginfo('name'), $template['body'], 'TC-04 custom reset body should include site name');
	});

	runCase('TC-05', 'UserAuth remember-me flag resolves true only for an enabled request value', static function (): void {
		$userAuth = new UserAuth((object) ['prefix' => 'authcred']);
		$userAuth->request = new class('1') {
			private $value;

			public function __construct(string $value)
			{
				$this->value = $value;
			}

			public function input($name, $filter = null)
			{
				return 'remember_me' === $name ? $this->value : null;
			}
		};

		$method = new ReflectionMethod(UserAuth::class, 'shouldRememberUser');
		$method->setAccessible(true);

		assertTrue($method->invoke($userAuth), 'TC-05 remember_me=1 should evaluate true');
	});

	runCase('TC-06', 'UserAuth remember-me flag is false when the checkbox is absent or disabled', static function (): void {
		$userAuth = new UserAuth((object) ['prefix' => 'authcred']);
		$userAuth->request = new class {
			public function input($name, $filter = null)
			{
				return 'remember_me' === $name ? '0' : null;
			}
		};

		$method = new ReflectionMethod(UserAuth::class, 'shouldRememberUser');
		$method->setAccessible(true);

		assertFalse($method->invoke($userAuth), 'TC-06 remember_me=0 should evaluate false');
	});

	echo "ALL TESTS PASSED\n";
} finally {
	if (null === $originalSettings) {
		delete_option('authcred_settings');
	} else {
		update_option('authcred_settings', $originalSettings);
	}
}