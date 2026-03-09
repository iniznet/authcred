<?php

namespace AuthCRED;

use AuthCRED\Collection\Manifest;
use WPTrait\Collection\Assets;
use WPTrait\Hook\Shortcode;
use WPTrait\Model;

class AuthShortcode extends Model
{
	use Shortcode, Assets, Manifest;

	public $actions = [
		'walker_nav_menu_start_el' => ['navShortcodes', 20],
		'template_redirect' => ['logoutUser', 20],
	];

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_shortcode('authcred', [$this, 'authcred']);
		add_shortcode('authcred-login', [$this, 'authcredLogin']);
		add_shortcode('authcred-logout', [$this, 'authcredLogout']);
		add_shortcode('authcred-user-icon', [$this, 'authcredUserIcon']);
	}

	public function navShortcodes($item)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return $item;
		}

		if (strpos($item, '[authcred') !== false) {
			$item = strip_tags($item);

			$item = shortcode_unautop($item);
			$item = do_shortcode($item);
		}

		return $item;
	}

	public function logoutUser()
	{
		$isLogout = $_GET['authcred-logout'] ?? null;

		if ($isLogout != 1) {
			return;
		}

		$redirectTo = $_GET['goto'] ?? home_url();

		if (!is_user_logged_in()) {
			wp_safe_redirect($redirectTo);
		}

		wp_destroy_current_session();
		wp_logout();
		session_unset();
		wp_safe_redirect($redirectTo);
		exit;
	}

	public function authcred($atts, $content = null)
	{
		$defaults = [
			'login_id' => '',
			'register_id' => '',
			'forgot_id' => '',
			'change_id' => '',
			'type' => '',
			'class' => '',
			'goto' => null,
			'captcha' => '',
		];

		$args = shortcode_atts($defaults, $atts, 'authcred');
		$args = $this->prepareCaptchaArgs($args);

		if (is_numeric($args['goto'])) {
			$args['goto'] = get_permalink($args['goto']) ?: null;
		}

		$this->add_style('authcred', $this->asset('scss/app.scss'), [], time());
		$this->add_script('authcred', $this->asset('js/app.js'), [], time(), true);

		if ('register' === $args['type']) {
			return $this->view->render('register', $args);
		}

		if ('forgot' === $args['type']) {
			return $this->view->render('forgot', $args);
		}

		if ('login' === $args['type']) {
			if ($args['goto'] === null) {
				$args['goto'] = true;
			}

			$args = $this->prepareSocialArgs($args);

			return $this->view->render('login', $args);
		}

		if ('change' === $args['type']) {
			return $this->view->render('change', $args);
		}

		if ('profile' === $args['type']) {
			if (!is_user_logged_in()) {
				return $this->view->render('partials.logged-out-prompt', $args);
			}

			return $this->view->render('profile', $args);
		}

		if ('logout' === $args['type']) {
			if (empty($args['goto']) || is_bool($args['goto'])) {
				$args['goto'] = home_url();
			}

			return $this->view->render('logout', $args);
		}

		return __('You need to specify type of authcred shortcode', 'authcred');
	}

	private function prepareCaptchaArgs(array $args): array
	{
		$args['captcha'] = sanitize_key($args['captcha'] ?? '');
		$args['captcha_sitekey'] = '';
		$args['captcha_version'] = 'v3';
		$args['nonce_value'] = '';
		$args['captcha_action'] = '';
		$args['captcha_context'] = '';
		$args['captcha_request_action'] = 'forgot';
		$args['captcha_request_nonce'] = '';
		$args['captcha_request_context'] = '';
		$args['captcha_reset_action'] = 'reset';
		$args['captcha_reset_nonce'] = '';
		$args['captcha_reset_context'] = '';

		if (!in_array($args['captcha'], ['recaptcha', 'turnstile'], true)) {
			$args['captcha'] = '';
		}

		if ('' !== $args['captcha']) {
			$settings = get_option($this->plugin->prefix . '_settings', []);

			if ('recaptcha' === $args['captcha']) {
				$args['captcha_sitekey'] = sanitize_text_field($settings['recaptcha_site_key'] ?? '');
				$args['captcha_version'] = in_array($settings['recaptcha_version'] ?? 'v3', ['v2_checkbox', 'v2_invisible', 'v3'], true)
					? $settings['recaptcha_version']
					: 'v3';
			} else {
				$args['captcha_sitekey'] = sanitize_text_field($settings['turnstile_site_key'] ?? '');
			}

			if ('' === $args['captcha_sitekey']) {
				$args['captcha'] = '';
			}
		}

		switch ($args['type']) {
			case 'login':
				$args['nonce_value'] = wp_create_nonce(Captcha::nonceAction('authcred_login', $args['captcha']));
				$args['captcha_action'] = 'login';
				$args['captcha_context'] = $this->signCaptchaContext($args['captcha'], 'authcred_login');
				break;

			case 'register':
				$args['nonce_value'] = wp_create_nonce(Captcha::nonceAction('authcred_register', $args['captcha']));
				$args['captcha_action'] = 'register';
				$args['captcha_context'] = $this->signCaptchaContext($args['captcha'], 'authcred_register');
				break;

			case 'forgot':
				$args['captcha_request_nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_reset_password', $args['captcha']));
				$args['captcha_reset_nonce'] = wp_create_nonce(Captcha::nonceAction('authcred_reset_new_password', $args['captcha']));
				$args['captcha_request_context'] = $this->signCaptchaContext($args['captcha'], 'authcred_reset_password');
				$args['captcha_reset_context'] = $this->signCaptchaContext($args['captcha'], 'authcred_reset_new_password');
				break;

			default:
				$args['captcha'] = '';
				$args['captcha_sitekey'] = '';
				$args['captcha_version'] = 'v3';
				break;
		}

		return $args;
	}

	private function signCaptchaContext(string $provider, string $ajaxAction): string
	{
		if ('' === $provider) {
			return '';
		}

		return wp_hash('authcred_captcha_context:' . $provider . ':' . $ajaxAction);
	}

	private function prepareSocialArgs(array $args): array
	{
		$settings = get_option($this->plugin->prefix . '_settings', []);
		$safeGoto = SocialAuth::sanitizeRedirectTarget($args['goto'] ?? '');
		$errorKey = SocialAuth::normalizeErrorKey(sanitize_key(wp_unslash($_GET['authcred-error'] ?? '')));

		$args['social_google_enabled'] = $this->socialProviderConfigured($settings, 'google');
		$args['social_google_url'] = $args['social_google_enabled']
			? add_query_arg([
				'authcred-social' => 'google',
				'goto' => $safeGoto,
			], home_url('/'))
			: '';
		$args['social_discord_enabled'] = $this->socialProviderConfigured($settings, 'discord');
		$args['social_discord_url'] = $args['social_discord_enabled']
			? add_query_arg([
				'authcred-social' => 'discord',
				'goto' => $safeGoto,
			], home_url('/'))
			: '';
		$args['social_enabled'] = $args['social_google_enabled'] || $args['social_discord_enabled'];
		$args['authcred_error'] = $errorKey;
		$args['authcred_error_message'] = SocialAuth::getErrorMessage($errorKey);

		return $args;
	}

	private function socialProviderConfigured(array $settings, string $provider): bool
	{
		return '' !== sanitize_text_field($settings[$provider . '_client_id'] ?? '')
			&& '' !== sanitize_text_field($settings[$provider . '_client_secret'] ?? '');
	}

	public function authcredLogin($atts, $content = null)
	{
		if (is_user_logged_in()) {
			return $this->authcredLogout($atts, $content);
		}

		$defaults = [
			'id' => 0,
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-login');
		$pageLink = get_permalink($args['id']) ?: '#';

		$url = sprintf('<a href="%s">%s</a>', $pageLink, __('Login', 'authcred'));

		return $url;
	}

	public function authcredLogout($atts, $content = null)
	{
		$defaults = [
			'goto' => null,
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-logout');

		if (is_numeric($args['goto'])) {
			$args['goto'] = get_permalink($args['goto']) ?: null;
		}

		if (empty($args['goto']) || is_bool($args['goto'])) {
			$args['goto'] = home_url();
		}

		$url = sprintf('<a href="%s">%s</a>', home_url('/logout?authcred-logout=1&goto=' . $args['goto']), __('Logout', 'authcred'));

		return $url;
	}

	public function authcredUserIcon($atts, $content = null)
	{
		$defaults = [
			'size' => 18,
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-user-icon');

		$this->add_style('authcred', $this->asset('scss/app.scss'), [], time());

		return $this->view->render('partials.user-icon', $args);
	}
}
