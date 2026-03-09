<?php

namespace AuthCRED;

use League\OAuth2\Client\Provider\Google;
use WPTrait\Model;
use Wohali\OAuth2\Client\Provider\Discord;

class SocialAuth extends Model
{
	private const CALLBACK_QUERY_VAR = 'authcred_social_callback';
	private const STATE_TRANSIENT_PREFIX = 'authcred_oauth_state_';
	private const STATE_TTL = 1800;

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_action('init', [$this, 'registerRewriteRules']);
		add_action('init', [$this, 'maybeRefreshRewriteRules'], 20);
		add_filter('query_vars', [$this, 'registerQueryVars']);
		add_action('template_redirect', [$this, 'handleTemplateRedirect']);
	}

	public function registerRewriteRules(): void
	{
		add_rewrite_rule('^authcred/callback/google/?$', 'index.php?' . self::CALLBACK_QUERY_VAR . '=google', 'top');
		add_rewrite_rule('^authcred/callback/discord/?$', 'index.php?' . self::CALLBACK_QUERY_VAR . '=discord', 'top');
	}

	public function maybeRefreshRewriteRules(): void
	{
		$rules = get_option('rewrite_rules', []);

		if (!is_array($rules)) {
			return;
		}

		if (isset($rules['authcred/callback/google/?$'], $rules['authcred/callback/discord/?$'])) {
			return;
		}

		$this->registerRewriteRules();
		flush_rewrite_rules(false);
	}

	public function registerQueryVars(array $queryVars): array
	{
		if (!in_array(self::CALLBACK_QUERY_VAR, $queryVars, true)) {
			$queryVars[] = self::CALLBACK_QUERY_VAR;
		}

		return $queryVars;
	}

	public function handleTemplateRedirect(): void
	{
		$callbackProvider = $this->normalizeProvider((string) get_query_var(self::CALLBACK_QUERY_VAR));

		if ('' !== $callbackProvider) {
			$this->handleCallback($callbackProvider);
		}

		$requestedProvider = $this->normalizeProvider(sanitize_key(wp_unslash($_GET['authcred-social'] ?? '')));

		if ('' !== $requestedProvider) {
			$this->handleInitiate($requestedProvider);
		}
	}

	public static function sanitizeRedirectTarget($goto): string
	{
		$default = home_url('/');

		if (!is_string($goto)) {
			return $default;
		}

		$goto = trim($goto);

		if ('' === $goto) {
			return $default;
		}

		$validated = wp_validate_redirect($goto, $default);

		return is_string($validated) && '' !== $validated ? $validated : $default;
	}

	public static function normalizeErrorKey(string $errorKey): string
	{
		$errorKey = sanitize_key($errorKey);
		$messages = self::errorMessages();

		return array_key_exists($errorKey, $messages) ? $errorKey : '';
	}

	public static function getErrorMessage(string $errorKey): string
	{
		$messages = self::errorMessages();

		return $messages[$errorKey] ?? '';
	}

	public static function errorMessages(): array
	{
		return [
			'invalid_state' => __('Login attempt expired or could not be verified. Please try again.', 'authcred'),
			'email_unverified' => __('Your social account\'s email address could not be verified. Please use a different login method or verify your email with the provider.', 'authcred'),
			'oauth_failed' => __('Social login encountered an error. Please try again or use another login method.', 'authcred'),
		];
	}

	private function handleInitiate(string $provider): void
	{
		if (!$this->hasProviderCredentials($provider)) {
			$this->redirectToLoginPage();
		}

		if (is_user_logged_in()) {
			wp_safe_redirect(self::sanitizeRedirectTarget(wp_unslash($_GET['goto'] ?? '')));
			exit;
		}

		$goto = self::sanitizeRedirectTarget(wp_unslash($_GET['goto'] ?? ''));
		$state = $this->storeState($provider, $goto);

		try {
			$authorizationUrl = $this->createProvider($provider)->getAuthorizationUrl([
				'scope' => $this->getScopes($provider),
				'state' => $state,
			]);
		} catch (\Throwable $throwable) {
			$this->redirectToLoginPage('oauth_failed');
		}

		wp_safe_redirect($authorizationUrl);
		exit;
	}

	private function handleCallback(string $provider): void
	{
		if (!$this->hasProviderCredentials($provider)) {
			$this->redirectToLoginPage();
		}

		$state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
		$payload = $this->consumeState($state, $provider);

		if (null === $payload) {
			$this->redirectToLoginPage('invalid_state');
		}

		if ('' !== sanitize_text_field(wp_unslash($_GET['error'] ?? '')) || '' === sanitize_text_field(wp_unslash($_GET['code'] ?? ''))) {
			$this->redirectToLoginPage('oauth_failed');
		}

		try {
			$providerClient = $this->createProvider($provider);
			$accessToken = $providerClient->getAccessToken('authorization_code', [
				'code' => sanitize_text_field(wp_unslash($_GET['code'] ?? '')),
			]);
			$resourceOwner = $providerClient->getResourceOwner($accessToken);
			$resourceData = $this->normalizeResourceData($provider, $resourceOwner->toArray());

			if (!$this->hasVerifiedEmail($resourceData)) {
				$this->redirectToLoginPage('email_unverified');
			}

			$this->linkOrCreateUser($provider, $resourceData);
		} catch (\Throwable $throwable) {
			$this->redirectToLoginPage('oauth_failed');
		}

		$redirectTo = self::sanitizeRedirectTarget($payload['goto'] ?? '');
		wp_safe_redirect($redirectTo);
		exit;
	}

	private function createProvider(string $provider)
	{
		$settings = get_option($this->plugin->prefix . '_settings', []);
		$options = [
			'clientId' => sanitize_text_field($settings[$provider . '_client_id'] ?? ''),
			'clientSecret' => sanitize_text_field($settings[$provider . '_client_secret'] ?? ''),
			'redirectUri' => $this->getCallbackUrl($provider),
		];

		if ('google' === $provider) {
			return new Google($options);
		}

		return new Discord($options);
	}

	private function getScopes(string $provider): array
	{
		if ('google' === $provider) {
			return ['openid', 'email', 'profile'];
		}

		return ['identify', 'email'];
	}

	private function getCallbackUrl(string $provider): string
	{
		return home_url('/authcred/callback/' . $provider . '/');
	}

	private function storeState(string $provider, string $goto): string
	{
		$state = wp_generate_password(32, false, false);
		$transientKey = $this->getStateTransientKey($state);
		$timestamp = time();

		set_transient($transientKey, [
			'provider' => $provider,
			'goto' => self::sanitizeRedirectTarget($goto),
			'created_at' => $timestamp,
			'expires_in' => self::STATE_TTL,
			'state_hash' => hash('sha256', $state),
		], self::STATE_TTL);

		return $state;
	}

	private function consumeState(string $state, string $provider): ?array
	{
		if ('' === $state) {
			return null;
		}

		$transientKey = $this->getStateTransientKey($state);
		$payload = get_transient($transientKey);
		delete_transient($transientKey);

		if (!is_array($payload)) {
			return null;
		}

		$stateHash = hash('sha256', $state);

		if (!hash_equals((string) ($payload['state_hash'] ?? ''), $stateHash)) {
			return null;
		}

		if ($provider !== ($payload['provider'] ?? '')) {
			return null;
		}

		$payload['goto'] = self::sanitizeRedirectTarget($payload['goto'] ?? '');

		return $payload;
	}

	private function normalizeResourceData(string $provider, array $resourceData): array
	{
		if ('google' === $provider) {
			return [
				'id' => sanitize_text_field((string) ($resourceData['sub'] ?? $resourceData['id'] ?? '')),
				'email' => sanitize_email((string) ($resourceData['email'] ?? '')),
				'email_verified' => $this->normalizeBoolean($resourceData['email_verified'] ?? false),
				'name' => sanitize_text_field((string) ($resourceData['name'] ?? $resourceData['email'] ?? '')),
			];
		}

		return [
			'id' => sanitize_text_field((string) ($resourceData['id'] ?? '')),
			'email' => sanitize_email((string) ($resourceData['email'] ?? '')),
			'email_verified' => $this->normalizeBoolean($resourceData['verified'] ?? false),
			'name' => sanitize_text_field((string) ($resourceData['global_name'] ?? $resourceData['username'] ?? $resourceData['email'] ?? '')),
		];
	}

	private function hasVerifiedEmail(array $resourceData): bool
	{
		return '' !== ($resourceData['email'] ?? '')
			&& true === ($resourceData['email_verified'] ?? false)
			&& '' !== ($resourceData['id'] ?? '');
	}

	private function linkOrCreateUser(string $provider, array $resourceData): int
	{
		$email = sanitize_email($resourceData['email'] ?? '');
		$providerUserId = sanitize_text_field((string) ($resourceData['id'] ?? ''));

		if ('' === $email || '' === $providerUserId) {
			throw new \RuntimeException('Missing provider identity.');
		}

		$existingUserId = email_exists($email);

		if ($existingUserId) {
			$this->storeProviderLink((int) $existingUserId, $provider, $providerUserId);
			$this->authenticateUser((int) $existingUserId);

			return (int) $existingUserId;
		}

		$userId = wp_insert_user([
			'user_login' => $this->generateUniqueUsername($resourceData['name'] ?? '', $email),
			'user_email' => $email,
			'user_pass' => wp_generate_password(24, true, true),
			'display_name' => sanitize_text_field((string) ($resourceData['name'] ?? '')),
			'role' => $this->getNewUserRole(),
		]);

		if (is_wp_error($userId)) {
			throw new \RuntimeException($userId->get_error_message());
		}

		$this->storeProviderLink((int) $userId, $provider, $providerUserId);
		$this->sendSetupPasswordEmail((int) $userId);
		$this->authenticateUser((int) $userId);

		return (int) $userId;
	}

	private function authenticateUser(int $userId): void
	{
		$user = get_user_by('id', $userId);

		if (!$user instanceof \WP_User) {
			throw new \RuntimeException('Unable to authenticate user.');
		}

		wp_set_current_user($userId);
		wp_set_auth_cookie($userId, true);
		do_action('wp_login', $user->user_login, $user);
	}

	private function storeProviderLink(int $userId, string $provider, string $providerUserId): void
	{
		update_user_meta($userId, 'authcred_' . $provider . '_user_id', $providerUserId);
	}

	private function sendSetupPasswordEmail(int $userId): void
	{
		$user = get_user_by('id', $userId);

		if (!$user instanceof \WP_User) {
			return;
		}

		$key = get_password_reset_key($user);
		$resetPageId = $this->findPageByShortcode('authcred', ['type' => 'forgot']);
		$url = $resetPageId
			? get_permalink($resetPageId) . '#3?reset=' . $key . '&username=' . rawurlencode($user->user_login)
			: __('Unfortunately, the reset password page is not set up yet by admin', 'authcred');
		$emailTemplate = EmailTemplates::buildSetupPassword($user->user_login, $user->user_email, $url);

		wp_mail($user->user_email, $emailTemplate['subject'], $emailTemplate['body'], [
			'Content-Type: text/html; charset=UTF-8',
		]);
	}

	private function findPageByShortcode(string $shortcode, array $args = []): int
	{
		$like = "post_content LIKE '%[{$shortcode}%";

		foreach ($args as $key => $value) {
			$like .= esc_sql(sprintf(' %s=\"%s\"', $key, $value));
		}

		$like .= "]%'";
		$page = $this->db->get_row("SELECT ID FROM {$this->db->posts} WHERE post_type = 'page' AND post_status = 'publish' AND $like");

		return (int) ($page->ID ?? 0);
	}

	private function getLoginPageUrl(): string
	{
		$pageId = $this->findPageByShortcode('authcred', ['type' => 'login']);

		if ($pageId > 0) {
			$permalink = get_permalink($pageId);

			if (is_string($permalink) && '' !== $permalink) {
				return $permalink;
			}
		}

		return home_url('/');
	}

	private function redirectToLoginPage(string $errorKey = ''): void
	{
		$loginPageUrl = $this->getLoginPageUrl();
		$normalizedErrorKey = self::normalizeErrorKey($errorKey);

		if ('' !== $normalizedErrorKey) {
			$loginPageUrl = add_query_arg('authcred-error', $normalizedErrorKey, $loginPageUrl);
		}

		wp_safe_redirect($loginPageUrl);
		exit;
	}

	private function hasProviderCredentials(string $provider): bool
	{
		$settings = get_option($this->plugin->prefix . '_settings', []);

		return '' !== sanitize_text_field($settings[$provider . '_client_id'] ?? '')
			&& '' !== sanitize_text_field($settings[$provider . '_client_secret'] ?? '');
	}

	private function getStateTransientKey(string $state): string
	{
		return self::STATE_TRANSIENT_PREFIX . hash('sha256', $state);
	}

	private function getNewUserRole(): string
	{
		$role = sanitize_key((string) get_option('default_role', 'subscriber'));

		if (in_array($role, ['administrator', 'editor'], true)) {
			return 'subscriber';
		}

		return '' !== $role ? $role : 'subscriber';
	}

	private function generateUniqueUsername(string $displayName, string $email): string
	{
		$base = sanitize_user($displayName, true);

		if ('' === $base) {
			$localPart = strstr($email, '@', true);
			$base = sanitize_user($localPart ?: 'authcred_user', true);
		}

		if ('' === $base) {
			$base = 'authcred_user';
		}

		if (!username_exists($base)) {
			return $base;
		}

		for ($suffix = 2; $suffix <= 6; $suffix++) {
			$candidate = $base . $suffix;

			if (!username_exists($candidate)) {
				return $candidate;
			}
		}

		return $base . wp_rand(1000, 9999);
	}

	private function normalizeBoolean($value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes'], true);
		}

		return 1 === $value;
	}

	private function normalizeProvider(string $provider): string
	{
		$provider = sanitize_key($provider);

		return in_array($provider, ['google', 'discord'], true) ? $provider : '';
	}
}
