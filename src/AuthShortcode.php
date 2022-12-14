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
			'type' => '',
			'class' => '',
			'goto' => null,
		];

		$args = shortcode_atts($defaults, $atts, 'authcred');

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

			return $this->view->render('login', $args);
		}

		if ('change' === $args['type']) {
			return $this->view->render('change', $args);
		}

		if ('logout' === $args['type']) {
			if (empty($args['goto']) || is_bool($args['goto'])) {
				$args['goto'] = home_url();
			}

			return $this->view->render('logout', $args);
		}

		return __('You need to specify type of authcred shortcode', 'authcred');
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