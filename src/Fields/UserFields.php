<?php

namespace AuthCRED\Fields;

class UserFields
{
	public function __construct($plugin)
	{
		$this->plugin = $plugin;

		add_action('cmb2_admin_init', [$this, 'register']);
	}

	public function register()
	{
		$cmb = new_cmb2_box([
			'id' => $this->plugin->prefix . '_registration_fields',
			'title' => __('User Registration Fields', 'authcred'),
			'object_types' => ['user'],
			'show_names' => true,
		]);

		$cmb->add_field([
			'name' => __('User Registration Fields', 'authcred'),
			'id' => $this->plugin->prefix . '_registration_fields',
			'type' => 'title',
		]);

		$cmb->add_field([
			'name' => __('Activated', 'authcred'),
			'desc' => __('Is the user activated?', 'authcred'),
			'id' => $this->plugin->prefix . '_activated',
			'type' => 'checkbox',
		]);
		$cmb->add_field([
			'name' => __('Expiration', 'authcred'),
			'desc' => __('Expiration date of the user before it going to be deleted.', 'authcred'),
			'id' => $this->plugin->prefix . '_expiration',
			'type' => 'text_datetime_timestamp',
			'date_format' => apply_filters('authcred/user/date_format', 'Y-m-d'),
		]);
	}
}