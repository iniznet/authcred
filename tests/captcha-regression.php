<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Captcha.php';

use AuthCRED\Captcha;

function runCase(string $id, string $description, callable $assertion): void
{
	$_POST = [];
	$assertion();
	echo "PASS: {$id} {$description}\n";
}

function resetCaptchaCase(): void
{
	$_POST = [];
	resetCaptchaCache();
}

runCase('TC-01', 'nonceAction empty provider normalizes to none', static function (): void {
	assertSame(
		'authcred_form_state:authcred_login:none',
		Captcha::nonceAction('authcred_login', ''),
		'TC-01 empty provider should map to none'
	);
});

runCase('TC-02', 'nonceAction recaptcha provider is preserved', static function (): void {
	assertSame(
		'authcred_form_state:authcred_login:recaptcha',
		Captcha::nonceAction('authcred_login', 'recaptcha'),
		'TC-02 recaptcha provider should be preserved'
	);
});

runCase('TC-03', 'nonceAction turnstile provider is preserved', static function (): void {
	assertSame(
		'authcred_form_state:authcred_login:turnstile',
		Captcha::nonceAction('authcred_login', 'turnstile'),
		'TC-03 turnstile provider should be preserved'
	);
});

runCase('TC-04', 'nonceAction invalid provider normalizes to none', static function (): void {
	assertSame(
		'authcred_form_state:authcred_login:none',
		Captcha::nonceAction('authcred_login', 'invalid_captcha'),
		'TC-04 invalid provider should map to none'
	);
});

runCase('TC-05', 'verifyFormNonce rejects missing nonce', static function (): void {
	resetCaptchaCase();
	assertFalse(Captcha::verifyFormNonce('authcred_tc05'), 'TC-05 missing nonce should be rejected');
});

runCase('TC-06', 'verifyFormNonce accepts valid no-captcha nonce', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc06', ''));
	assertTrue(Captcha::verifyFormNonce('authcred_tc06'), 'TC-06 valid no-captcha nonce should be accepted');
});

runCase('TC-14', 'verifyFormNonce rejects present but invalid nonce', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = 'invalid-nonce-string';
	assertFalse(Captcha::verifyFormNonce('authcred_tc14'), 'TC-14 invalid nonce should be rejected');
});

runCase('TC-07', 'verifyRequest rejects request without nonce', static function (): void {
	resetCaptchaCase();
	assertFalse(Captcha::verifyRequest('authcred_tc07'), 'TC-07 missing nonce should fail closed');
});

runCase('TC-08', 'verifyRequest accepts valid no-captcha nonce without captcha fields', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc08', ''));
	assertTrue(Captcha::verifyRequest('authcred_tc08'), 'TC-08 no-captcha request should pass');
});

runCase('TC-09', 'verifyRequest rejects recaptcha nonce with empty captcha_context', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc09', 'recaptcha'));
	$_POST['captcha_context'] = '';
	$_POST['captcha_provider'] = 'recaptcha';
	assertFalse(Captcha::verifyRequest('authcred_tc09'), 'TC-09 empty captcha_context should fail closed');
});

runCase('TC-10', 'verifyRequest rejects recaptcha nonce with wrong provider', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc10', 'recaptcha'));
	$_POST['captcha_context'] = wp_hash('authcred_captcha_context:recaptcha:authcred_tc10');
	$_POST['captcha_provider'] = 'turnstile';
	assertFalse(Captcha::verifyRequest('authcred_tc10'), 'TC-10 mismatched provider should be rejected');
});

runCase('TC-11', 'verifyRequest rejects recaptcha nonce with empty token', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc11', 'recaptcha'));
	$_POST['captcha_context'] = wp_hash('authcred_captcha_context:recaptcha:authcred_tc11');
	$_POST['captcha_provider'] = 'recaptcha';
	$_POST['captcha_token'] = '';
	assertFalse(Captcha::verifyRequest('authcred_tc11'), 'TC-11 empty token should be rejected');
});

runCase('TC-12', 'verifyRequest rejects turnstile nonce with mismatched provider', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc12', 'turnstile'));
	$_POST['captcha_context'] = wp_hash('authcred_captcha_context:turnstile:authcred_tc12');
	$_POST['captcha_provider'] = 'recaptcha';
	assertFalse(Captcha::verifyRequest('authcred_tc12'), 'TC-12 mismatched provider should be rejected');
});

runCase('TC-13', 'verifyRequest rejects tampered non-empty captcha_context', static function (): void {
	resetCaptchaCase();
	$_POST['nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_tc13', 'recaptcha'));
	$_POST['captcha_context'] = 'tampered_context_does_not_match_expected_hash';
	$_POST['captcha_provider'] = 'recaptcha';
	$_POST['captcha_token'] = 'any-token';
	assertFalse(Captcha::verifyRequest('authcred_tc13'), 'TC-13 tampered context should be rejected');
});

echo "ALL TESTS PASSED\n";
