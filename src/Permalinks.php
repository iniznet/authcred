<?php

namespace AuthCRED;

use WPTrait\Model;

class Permalinks extends Model
{
	public $filters = [
		'is_post_status_viewable' => ['futurePermalink', 999],
	];

	/** @var array */
	public $settings = [];

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		$this->settings = $this->option($this->plugin->prefix . '_settings')->get();
	}

	/**
	 * Generate future permalink similar to published permalink
	 * instead of returning post id
	 *
	 * @param bool $isViewable
	 *
	 * @return bool
	 */
	public function futurePermalink($isViewable)
	{
		global $wp_query, $wp_post_statuses;

		if (is_admin()) {
			return $isViewable;
		}

		if (!isset($this->settings['post_type']) || !count($wp_query->posts)) {
			return $isViewable;
		}
		
		foreach ($wp_query->posts as $post) {
			if ($post->post_type !== $this->settings['post_type']) {
				continue;
			}

			if ($post->post_status !== 'future') {
				return $isViewable;
			}

			$wp_post_statuses['future']->protected = 1;
			$mycredSellMeta = get_post_meta($post->ID, 'myCRED_sell_content', true);

			if (!$mycredSellMeta || $mycredSellMeta['status'] === 'disabled') {
				return $isViewable;
			}

			$wp_post_statuses['future']->protected = 0;
			
			return true;
		}

		return $isViewable;
	}
}