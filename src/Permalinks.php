<?php

namespace AuthCRED;

use WPTrait\Model;

class Permalinks extends Model
{
	public $filters = [
		'post_link' => ['futurePermalink', 1000, 3],
		'post_type_link' => ['futurePermalink', 1000, 3],
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
	 * @param string $permalink
	 * @param \WP_Post $post
	 *
	 * @return string
	 */
	public function futurePermalink($permalink, $post)
	{
		static $done = false;

		$postType = $post->post_type ?? get_post_type($post->ID);

		if (!isset($this->settings['post_type']) || $postType !== $this->settings['post_type']) {
			return $permalink;
		}

		if ($post->post_status !== 'future') {
			return $permalink;
		}

		if ($done) {
			return $permalink;
		}

		$done = true;
		$post->post_status = 'publish';

		$permalink = get_permalink($post);

		return $permalink;
	}
}
