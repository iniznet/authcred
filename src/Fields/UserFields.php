<?php

namespace AuthCRED\Fields;

class UserFields
{
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		$this->register();
	}

	public function register() {
		$fields = \MakeitWorkPress\WP_Custom_Fields\Framework::instance();

		$fields->add('meta', [
			'id' => $this->plugin->prefix . '_registration_fields',
			'title' => __('User Registration Fields', 'authcred'),
			'type' => 'user',
			'sections' => [
				[
					'id'		=> 'section_1',
					'title' 	=> __('Activation', 'authcred'),
					'fields'	=> [
						[
							'id'	=> 'activated',
							'title'	=> __('Activated', 'authcred'),
							'description'	=> __('Is the user activated?', 'authcred'),
							'type'	=> 'checkbox',
							'style' => 'switcher',
							'options' => [
								'value' => ['label' => __('False/True', 'authcred')],
							],
						],
						[
							'id'	=> 'expiration',
							'title'	=> __('Expiration', 'authcred'),
							'description'	=> __('Expiration date of the user before it going to be deleted.', 'authcred'),
							'type'	=> 'datepicker',
							'mode'	=> 'single',
							'alt-format' => apply_filters('authcred/user/alt-format', 'Y-m-d H:i:s'),
						]
					]
				]
			]
		]);
	}
}