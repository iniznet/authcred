<?php

namespace AuthCRED\Options;

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
			]
		];

		return $setups;
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