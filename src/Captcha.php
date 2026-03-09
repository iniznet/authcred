<?php

namespace AuthCRED;

final class Captcha
{
	const RECAPTCHA_URL = 'https://www.google.com/recaptcha/api/siteverify';
	const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const NO_CAPTCHA_PROVIDER = 'none';

	private static $resolvedProviders = [];

	private function __construct()
	{
	}

	public static function verifyFormNonce(string $ajax_action): bool
	{
		return null !== self::resolveNonceProvider($ajax_action);
	}

	public static function nonceAction(string $ajax_action, string $provider = ''): string
	{
		$normalizedProvider = self::normalizeProvider($provider);

		if ('' === $normalizedProvider) {
			$normalizedProvider = self::NO_CAPTCHA_PROVIDER;
		}

		return 'authcred_form_state:' . $ajax_action . ':' . $normalizedProvider;
	}

	public static function verifyRequest(string $ajax_action): bool
	{
		$expectedProvider = self::resolveNonceProvider($ajax_action);

		if (null === $expectedProvider) {
			return false;
		}

		if ('' === $expectedProvider) {
			return true;
		}

		$rawContext = sanitize_text_field(wp_unslash($_POST['captcha_context'] ?? ''));
		$rawProvider = self::normalizeProvider(sanitize_text_field(wp_unslash($_POST['captcha_provider'] ?? '')));

		if ('' === $rawContext) {
			return false;
		}

		if ('' === $rawProvider || $rawProvider !== $expectedProvider) {
			return false;
		}

		$expectedContext = wp_hash('authcred_captcha_context:' . $expectedProvider . ':' . $ajax_action);

		if (!hash_equals($expectedContext, $rawContext)) {
			return false;
		}

		$token = sanitize_text_field(wp_unslash($_POST['captcha_token'] ?? ''));

		if ('' === $token) {
			return false;
		}

		$settings = get_option('authcred_settings', []);

		if ('recaptcha' === $expectedProvider) {
			$secret = sanitize_text_field($settings['recaptcha_secret_key'] ?? '');

			if ('' === $secret) {
				return false;
			}

			return self::verifyRecaptcha($token, $secret, $settings, $ajax_action);
		}

		$secret = sanitize_text_field($settings['turnstile_secret_key'] ?? '');

		if ('' === $secret) {
			return false;
		}

		return self::verifyTurnstile($token, $secret, $ajax_action);
	}

	private static function resolveNonceProvider(string $ajax_action): ?string
	{
		if (array_key_exists($ajax_action, self::$resolvedProviders)) {
			return self::$resolvedProviders[$ajax_action];
		}

		$nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));

		if ('' === $nonce) {
			self::$resolvedProviders[$ajax_action] = null;
			return null;
		}

		foreach (['', 'recaptcha', 'turnstile'] as $provider) {
			if (wp_verify_nonce($nonce, self::nonceAction($ajax_action, $provider))) {
				self::$resolvedProviders[$ajax_action] = $provider;
				return $provider;
			}
		}

		self::$resolvedProviders[$ajax_action] = null;
		return null;
	}

	private static function normalizeProvider(string $provider): string
	{
		$provider = sanitize_key($provider);

		if (!in_array($provider, ['recaptcha', 'turnstile'], true)) {
			return '';
		}

		return $provider;
	}

	private static function verifyRecaptcha(string $token, string $secret, array $settings, string $ajax_action): bool
	{
		$response = wp_remote_post(self::RECAPTCHA_URL, [
			'timeout' => 10,
			'body' => [
				'secret' => $secret,
				'response' => $token,
				'remoteip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
			],
		]);

		if (is_wp_error($response)) {
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!is_array($body) || !($body['success'] ?? false)) {
			return false;
		}

		$version = $settings['recaptcha_version'] ?? 'v3';

		if ('v3' !== $version) {
			return true;
		}

		if (!isset($body['score']) || !is_numeric($body['score'])) {
			return false;
		}

		$threshold = self::normalizedThreshold($settings['recaptcha_v3_score_threshold'] ?? null);

		if ((float) $body['score'] < $threshold) {
			return false;
		}

		$expectedAction = self::captchaActionLabel($ajax_action);
		$returnedAction = sanitize_text_field($body['action'] ?? '');

		if ($returnedAction !== $expectedAction) {
			return false;
		}

		$expectedHost = wp_parse_url(get_site_url(), PHP_URL_HOST);
		$returnedHost = sanitize_text_field($body['hostname'] ?? '');

		if (empty($expectedHost) || $returnedHost !== $expectedHost) {
			return false;
		}

		return true;
	}

	private static function verifyTurnstile(string $token, string $secret, string $ajax_action): bool
	{
		$response = wp_remote_post(self::TURNSTILE_URL, [
			'timeout' => 10,
			'body' => [
				'secret' => $secret,
				'response' => $token,
				'remoteip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
			],
		]);

		if (is_wp_error($response)) {
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (!is_array($body) || !($body['success'] ?? false)) {
			return false;
		}

		$expectedHost = wp_parse_url(get_site_url(), PHP_URL_HOST);
		$returnedHost = sanitize_text_field($body['hostname'] ?? '');

		if (!empty($expectedHost) && '' !== $returnedHost && $returnedHost !== $expectedHost) {
			return false;
		}

		$returnedAction = sanitize_text_field($body['action'] ?? '');

		if ('' !== $returnedAction && $returnedAction !== self::captchaActionLabel($ajax_action)) {
			return false;
		}

		return true;
	}

	private static function captchaActionLabel(string $ajax_action): string
	{
		switch ($ajax_action) {
			case 'authcred_login':
				return 'login';

			case 'authcred_register':
				return 'register';

			case 'authcred_reset_password':
				return 'forgot';

			case 'authcred_reset_new_password':
				return 'reset';

			default:
				return $ajax_action;
		}
	}

	private static function normalizedThreshold($rawThreshold): float
	{
		if (!is_numeric($rawThreshold)) {
			return 0.5;
		}

		$threshold = (float) $rawThreshold;

		if (!is_finite($threshold)) {
			return 0.5;
		}

		return max(0.0, min(1.0, $threshold));
	}
}
