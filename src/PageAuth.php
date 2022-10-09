<?php

namespace AuthCRED;

use AuthCRED\Collection\Manifest;
use WPTrait\Collection\Assets;
use WPTrait\Hook\Shortcode;
use WPTrait\Model;

class PageAuth extends Model
{
	use Shortcode, Assets, Manifest;

	public array $shortcode = [
		'method' => 'authcred',
	];

	public function authcred($atts, $content = null)
	{
		$defaults = [
			'login_id' => '',
			'register_id' => '',
			'forgot_id' => '',
			'type' => '',
		];

		$args = shortcode_atts($defaults, $atts, 'authcred');

		$this->add_style('authcred', $this->asset('scss/app.scss'), [], time());
		$this->add_script('authcred', $this->asset('js/app.js'), [], time(), true);

		if ('register' === $args['type']) {
			return $this->view->render('register', $args);
		}

		if ('forgot' === $args['type']) {
			return $this->view->render('forgot', $args);
		}

		if ('login' === $args['type']) {
			return $this->view->render('login', $args);
		}

		return __('You need to specify type of shortcode', 'authcred');
	}
}