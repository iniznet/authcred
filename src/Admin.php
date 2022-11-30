<?php

namespace AuthCRED;

use WPTrait\Model;

class Admin extends Model
{
	public $actions = [
		'setup_theme' => ['disallowAdminPages', 1]
	];

	public $filters = [
		'show_admin_bar' => ['disableAdminbar', 20],
	];

	public function __construct($plugin)
	{
		parent::__construct($plugin);
	}

	public function disableAdminbar()
	{
		if (current_user_can('edit_posts')) {
			return true;
		}

		return false;
	}

	public function disallowAdminPages()
	{
		global $wp_query;

		$settings = $this->option($this->plugin->prefix . '_settings')->get();
		$disallow = isset($settings['disallow_admin']) ? $settings['disallow_admin'] : 0;

		if (wp_doing_ajax() || wp_doing_cron()) {
			return;
		}

		if (!$disallow || !is_admin() || current_user_can('edit_posts')) {
			return;
		}

		$wp_query->set_404();
		status_header(404);
		nocache_headers();

		wp_safe_redirect(wp_get_referer() ?: home_url());
		exit;
	}
}