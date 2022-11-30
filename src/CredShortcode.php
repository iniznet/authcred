<?php

namespace AuthCRED;

use AuthCRED\Collection\Manifest;
use WPTrait\Collection\Assets;
use WPTrait\Model;

class CredShortcode extends Model
{
	use Assets, Manifest;

	public $actions = [
		'walker_nav_menu_start_el' => ['navShortcodes', 15],
		'widget_text' => ['widgetShortcodes', 15, 3],
	];

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_shortcode('authcred-balance', [$this, 'authcredBalance']);
		add_shortcode('authcred-buy', [$this, 'authcredBuy']);
		add_shortcode('authcred-buy-dynamic', [$this, 'authcredBuyDynamic']);
	}

	public function navShortcodes($item)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return $item;
		}

		if (strpos($item, '[authcred-balance') !== false) {
			$item = shortcode_unautop($item);
			$item = do_shortcode($item);
		}

		return $item;
	}

	public function widgetShortcodes($text, $instance, $widget)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return $text;
		}

		if (strpos($text, '[authcred-balance') !== false || strpos($text, '[authcred') !== false) {
			$text = shortcode_unautop($text);
			$text = do_shortcode($text);
		}

		return $text;
	}

	public function authcredBalance($atts, $content = null)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return '';
		}

		$defaults = [];
		$args = shortcode_atts($defaults, $atts, 'authcred-balance');

		return mycred_get_users_fcred(get_current_user_id()) ?: 0;
	}

	public function authcredBuy($atts, $content = null)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return '';
		}

		$this->add_style('authcred', $this->asset('scss/app.scss'), [], time());
		$this->add_script('authcred', $this->asset('js/app.js'), [], time(), true);

		$settings = mycred_get_buycred_settings();
		$defaults = [
			'gateway' => 'paypal-standard',
			'ctype' => MYCRED_DEFAULT_TYPE_KEY,
			'amount' => '',
			'gift_to' => '',
			'class' => 'authcred-buy mycred-buy-link btn btn-primary btn-lg mt-4',
			'login'   => $settings['login'],
			'title'   => '',
			'btn_label' => '',
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-buy');
		$shortcode = ['[mycred_buy '];

		if ($args['gateway']) {
			$shortcode[] = 'gateway="' . $args['gateway'] . '" ';
		}

		if ($args['ctype']) {
			$shortcode[] = 'ctype="' . $args['ctype'] . '" ';
		}

		if ($args['amount']) {
			$shortcode[] = 'amount="' . $args['amount'] . '" ';
		}

		if ($args['gift_to']) {
			$shortcode[] = 'gift_to="' . $args['gift_to'] . '" ';
		}

		if ($args['class']) {
			$shortcode[] = 'class="' . $args['class'] . '" ';
		}

		if ($args['login']) {
			$shortcode[] = 'login="' . $args['login'] . '" ';
		}

		$shortcode[] = ']';

		if ($args['btn_label']) {
			$shortcode[] = $args['btn_label'];
		}

		$shortcode[] = '[/mycred_buy]';

		$mycredButton = do_shortcode(implode('', $shortcode));

		if (is_user_logged_in()) {
			$button = $this->view->render('partials.buy-button', [
				'title' => $args['title'],
				'description' => $content,
				'label' => $args['btn_label'],
			]);
	
			# replace the content inside the <a> tag with the content we want
			$content = preg_replace('/(<a[^>]*>)(.*?)(<\/a>)/i', "$1$button$3", $mycredButton);
		} else {
			$mycred = mycred($args['ctype']);
			$content = sprintf('<div class="authcred-buy p-2 btn">%s</div>', $mycred->template_tags_general($args['login']));
		}

		return $content;
	}

	public function authcredBuyDynamic($atts, $content = null)
	{
		if (!function_exists('mycred_get_users_fcred')) {
			return '';
		}

		$this->add_style('authcred', $this->asset('scss/app.scss'), [], time());
		$this->add_script('authcred', $this->asset('js/app.js'), [], time(), true);

		$settings = mycred_get_buycred_settings();
		$defaults = [
			'gateway' => 'paypal-standard',
			'ctype' => MYCRED_DEFAULT_TYPE_KEY,
			'amount' => '',
			'gift_to' => '',
			'class' => 'authcred-buy myCRED-buy-form mt-4',
			'login'   => $settings['login'],
			'title'   => '',
			'btn_label' => '',
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-buy-dynamic');

		$args['nonce'] = wp_create_nonce('mycred-buy-creds');

		if (is_user_logged_in()) {
			$content = $this->view->render('buy-dynamic', [
				'title' => $args['title'],
				'description' => $content,
				'label' => $args['btn_label'],
			], $args);
		} else {
			$mycred = mycred($args['ctype']);
			$content = sprintf('<div class="authcred-buy p-2 btn">%s</div>', $mycred->template_tags_general($args['login']));
		}

		return $content;
	}	
}