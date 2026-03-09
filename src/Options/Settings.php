<?php

namespace AuthCRED\Options;

use AuthCRED\EmailTemplates;
use WPTrait\Model;

class Settings extends Model
{

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param \WPTrait\Plugin $plugin
	 */
	public function __construct($plugin)
	{
		$this->plugin = $plugin;

		add_filter('wpcfto_options_page_setup', [$this, 'register']);
		add_filter('sanitize_option_' . $this->plugin->prefix . '_settings', [$this, 'sanitizeSettings']);
	}

	/**
	 * Register Options Page & Fields
	 *
	 * @param array $setups
	 *
	 * @return array
	 */
	public function register($setups)
	{
		$setups[] = [

			'option_name' => $this->plugin->prefix . '_settings',

			'title' => esc_html__('AuthCRED Settings', 'authcred'),
			'sub_title' => esc_html__('by niznet', 'authcred'),
			'logo' => 'https://blobcdn.com/blob.svg',

			'page' => [
				'page_title' => 'AuthCRED Settings',
				'menu_title' => 'AuthCRED',
				'menu_slug' => $this->plugin->prefix . '_settings',
				'icon' => 'dashicons-editor-unlink',
				'position' => 40,
			],

			'fields' => [
				'setup' => [
					'name' => esc_html__('Setup', 'authcred'),
					'fields' => [
						'post_type' => [
							'type' => 'select',
							'label' => esc_html__('Post Type', 'authcred'),
							'description' => esc_html__('Select post type for allowing user to access future/scheduled post. It\'s a trick to prevent posts from being indexed by Novel Updates, once the post published it automatically disable the lock feature.', 'authcred'),
							'options' => $this->getPostTypes(),
						],
						'disallow_admin' => [
							'type' => 'checkbox',
							'label' => esc_html__('Disallow Admin access', 'authcred'),
							'description' => esc_html__('Disallow WordPress admin dashboard access for normal users by redirecting to previous/homepage. It\'s best to combined with a plugin that can mask admin URL.', 'authcred'),
						],
					]
				],
				'topup' => [
					'name' => esc_html__('Top Up', 'authcred'),
					'fields' => [
						'topup_dynamic_calc_preview' => [
							'type' => 'checkbox',
							'label' => esc_html__('Dynamic Calculation Preview', 'authcred'),
							'description' => esc_html__('Display dynamic top up calculation preview after the input field.', 'authcred'),
						],
					],
				],
				'captcha' => [
					'name' => esc_html__('Captcha', 'authcred'),
					'fields' => [
						'recaptcha_site_key' => [
							'type' => 'text',
							'label' => esc_html__('reCAPTCHA Site Key', 'authcred'),
							'description' => esc_html__('Public site key from the Google reCAPTCHA console.', 'authcred'),
						],
						'recaptcha_secret_key' => [
							'type' => 'text',
							'label' => esc_html__('reCAPTCHA Secret Key', 'authcred'),
							'description' => esc_html__('Secret key used for server-side verification only.', 'authcred'),
						],
						'recaptcha_version' => [
							'type' => 'select',
							'label' => esc_html__('reCAPTCHA Version', 'authcred'),
							'description' => esc_html__('Choose the reCAPTCHA mode used by shortcode-enabled forms.', 'authcred'),
							'options' => [
								'v2_checkbox' => esc_html__('v2 Checkbox', 'authcred'),
								'v2_invisible' => esc_html__('v2 Invisible', 'authcred'),
								'v3' => esc_html__('v3', 'authcred'),
							],
						],
						'recaptcha_v3_score_threshold' => [
							'type' => 'text',
							'label' => esc_html__('reCAPTCHA v3 Score Threshold', 'authcred'),
							'description' => esc_html__('Float from 0.0 to 1.0. Lower scores are rejected. Default: 0.5.', 'authcred'),
						],
						'turnstile_site_key' => [
							'type' => 'text',
							'label' => esc_html__('Turnstile Site Key', 'authcred'),
							'description' => esc_html__('Public site key from the Cloudflare Turnstile dashboard.', 'authcred'),
						],
						'turnstile_secret_key' => [
							'type' => 'text',
							'label' => esc_html__('Turnstile Secret Key', 'authcred'),
							'description' => esc_html__('Secret key used for server-side verification only.', 'authcred'),
						],
					],
				],
				'security' => [
					'name' => esc_html__('Security', 'authcred'),
					'fields' => [
						'max_login_attempts' => [
							'type' => 'text',
							'label' => esc_html__('Max Login Attempts', 'authcred'),
							'description' => esc_html__('Maximum failed login attempts allowed before the same IP and username combination is temporarily locked out. Default: 5.', 'authcred'),
						],
						'login_lockout_minutes' => [
							'type' => 'text',
							'label' => esc_html__('Login Lockout Minutes', 'authcred'),
							'description' => esc_html__('How long to block failed login attempts for the same IP and username combination. Default: 15 minutes.', 'authcred'),
						],
						'max_register_attempts' => [
							'type' => 'text',
							'label' => esc_html__('Max Registration Attempts', 'authcred'),
							'description' => esc_html__('Maximum failed registration attempts allowed before the same IP and identifier combination is temporarily locked out. Default: 3.', 'authcred'),
						],
						'register_lockout_minutes' => [
							'type' => 'text',
							'label' => esc_html__('Registration Lockout Minutes', 'authcred'),
							'description' => esc_html__('How long to block failed registration attempts for the same IP and identifier combination. Default: 60 minutes.', 'authcred'),
						],
					],
				],
				'emails' => [
					'name' => esc_html__('Emails', 'authcred'),
					'fields' => [
						'email_setup_subject' => [
							'type' => 'text',
							'label' => esc_html__('Setup Password Subject', 'authcred'),
							'description' => esc_html__('Available tokens: {username}, {email}, {link}, {site_name}.', 'authcred'),
						],
						'email_setup_body' => [
							'type' => 'textarea',
							'label' => esc_html__('Setup Password Body', 'authcred'),
							'description' => esc_html__('Supports basic HTML plus the tokens {username}, {email}, {link}, and {site_name}.', 'authcred'),
						],
						'email_reset_subject' => [
							'type' => 'text',
							'label' => esc_html__('Reset Password Subject', 'authcred'),
							'description' => esc_html__('Available tokens: {username}, {email}, {link}, {site_name}.', 'authcred'),
						],
						'email_reset_body' => [
							'type' => 'textarea',
							'label' => esc_html__('Reset Password Body', 'authcred'),
							'description' => esc_html__('Supports basic HTML plus the tokens {username}, {email}, {link}, and {site_name}.', 'authcred'),
						],
					],
				],
				'content_preview' => [
					'name' => esc_html__('Content Preview', 'authcred'),
					'fields' => [
						'teaser_word_count' => [
							'type' => 'text',
							'label' => esc_html__('Teaser Word Count', 'authcred'),
							'description' => esc_html__('Number of words to show before locked myCRED content switches to the teaser call to action. Default: 150.', 'authcred'),
						],
					],
				],
				'avatar' => [
					'name' => esc_html__('Avatar', 'authcred'),
					'fields' => [
						'avatar_max_size_kb' => [
							'type' => 'text',
							'label' => esc_html__('Avatar Max Size (KB)', 'authcred'),
							'description' => esc_html__('Maximum avatar upload size stored in settings. Runtime uploads are still capped at 2048 KB for safety. Default: 2048.', 'authcred'),
						],
					],
				],
				'social' => [
					'name' => esc_html__('Social Login', 'authcred'),
					'fields' => [
						'google_client_id' => [
							'type' => 'text',
							'label' => esc_html__('Google Client ID', 'authcred'),
							'description' => sprintf(
								esc_html__('OAuth callback URI: %s', 'authcred'),
								home_url('/authcred/callback/google/')
							),
						],
						'google_client_secret' => [
							'type' => 'text',
							'label' => esc_html__('Google Client Secret', 'authcred'),
							'description' => esc_html__('Secret from your Google OAuth application. Leave blank to hide the Google button.', 'authcred'),
						],
						'discord_client_id' => [
							'type' => 'text',
							'label' => esc_html__('Discord Client ID', 'authcred'),
							'description' => sprintf(
								esc_html__('OAuth callback URI: %s', 'authcred'),
								home_url('/authcred/callback/discord/')
							),
						],
						'discord_client_secret' => [
							'type' => 'text',
							'label' => esc_html__('Discord Client Secret', 'authcred'),
							'description' => esc_html__('Secret from your Discord application. Leave blank to hide the Discord button.', 'authcred'),
						],
					],
				],
			]
		];

		return $setups;
	}

	public function sanitizeSettings($settings)
	{
		if (!is_array($settings)) {
			$settings = [];
		}

		$keyFields = [
			'recaptcha_site_key',
			'recaptcha_secret_key',
			'turnstile_site_key',
			'turnstile_secret_key',
			'google_client_id',
			'google_client_secret',
			'discord_client_id',
			'discord_client_secret',
		];

		foreach ($keyFields as $field) {
			if (isset($settings[$field])) {
				$settings[$field] = sanitize_text_field($settings[$field]);
			}
		}

		$emailDefaults = EmailTemplates::defaults();
		$emailSubjectFields = [
			'email_setup_subject' => $emailDefaults['setup_password']['subject'],
			'email_reset_subject' => $emailDefaults['reset_password']['subject'],
		];

		foreach ($emailSubjectFields as $field => $default) {
			$value = sanitize_text_field($settings[$field] ?? '');
			$settings[$field] = '' === $value ? $default : $value;
		}

		$emailBodyFields = [
			'email_setup_body' => $emailDefaults['setup_password']['body'],
			'email_reset_body' => $emailDefaults['reset_password']['body'],
		];

		foreach ($emailBodyFields as $field => $default) {
			$value = wp_kses_post($settings[$field] ?? '');
			$settings[$field] = '' === trim($value) ? $default : $value;
		}

		$integerClampFields = [
			'max_login_attempts' => [1, 100, 5],
			'login_lockout_minutes' => [1, 10080, 15],
			'max_register_attempts' => [1, 100, 3],
			'register_lockout_minutes' => [1, 10080, 60],
			'teaser_word_count' => [1, 1000, 150],
			'avatar_max_size_kb' => [64, 10240, 2048],
		];

		foreach ($integerClampFields as $field => $rules) {
			[$min, $max, $default] = $rules;
			$settings[$field] = $this->sanitizeBoundedInteger($settings[$field] ?? null, $min, $max, $default);
		}

		$allowedVersions = ['v2_checkbox', 'v2_invisible', 'v3'];

		if (!isset($settings['recaptcha_version']) || !in_array($settings['recaptcha_version'], $allowedVersions, true)) {
			$settings['recaptcha_version'] = 'v3';
		}

		$rawThreshold = $settings['recaptcha_v3_score_threshold'] ?? '0.5';

		if (!is_numeric($rawThreshold)) {
			$settings['recaptcha_v3_score_threshold'] = '0.5';
			return $settings;
		}

		$threshold = (float) $rawThreshold;

		if (!is_finite($threshold)) {
			$settings['recaptcha_v3_score_threshold'] = '0.5';
			return $settings;
		}

		$settings['recaptcha_v3_score_threshold'] = (string) max(0.0, min(1.0, $threshold));

		return $settings;
	}

	private function sanitizeBoundedInteger($value, int $min, int $max, int $default): int
	{
		if (!is_numeric($value)) {
			return $default;
		}

		$value = (int) $value;

		if ($value < $min) {
			return $default;
		}

		return min($max, $value);
	}

	private function getPostTypes()
	{
		$post_types = get_post_types([
			'public' => true,
		], 'objects');

		$options = [
			'' => esc_html__('None', 'authcred'),
		];

		foreach ($post_types as $post_type) {
			$options[$post_type->name] = $post_type->label;
		}

		return $options;
	}
}
