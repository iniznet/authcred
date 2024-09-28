<?php

namespace AuthCRED;

use WPTrait\Model;

class MyCRED extends Model
{
	public $actions = [
		'pre_get_posts' => ['allowFutureAccess', 1000, 1],
		'transition_post_status' => ['disableSaleOncePublished', 10, 3],
		'mycred_run_this' => ['extendBuyTracking', 10, 1],
	];

	private $settings = [];
	private $postType = '';

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		$this->settings = $this->option($this->plugin->prefix . '_settings')->get();
		$this->postType = isset($this->settings['post_type']) ? $this->settings['post_type'] : '';
	}

	public function allowFutureAccess($query)
	{
		if (!$this->postType || $query->is_feed || $query->is_admin) {
			return;
		}

		if (!$query->is_main_query() || (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] !== $this->postType)) {
            return;
        }

		$query->set('post_status', ['publish', 'future']);
	}

	/**
	 * Disable mycred sale once post is published from scheduled/future status
	 * 
	 * @param string $newStatus
	 * @param string $oldStatus
	 * @param \WP_Post $post
	 * 
	 * @return void
	 */
	public function disableSaleOncePublished($newStatus, $oldStatus, $post)
	{
		if (!$this->postType || $post->post_type !== $this->postType) {
			return;
		}

		if ($newStatus !== 'publish') {
            return;
        }

        if ($oldStatus !== 'future') {
            return;
        }

        $mycredSellMeta = get_post_meta($post->ID, 'myCRED_sell_content', true);

        if (!$mycredSellMeta) {
            return;
        }

        $mycredSellMeta['status'] = 'disabled';
		
		/**
		 * Filters the mycred sell meta
		 * 
		 * @param array $mycredSellMeta
		 * @param \WP_Post $post
		 * @param string $newStatus
		 * @param string $oldStatus
		 */
		$mycredSellMeta = apply_filters('authcred/mycred/sell_meta', $mycredSellMeta, $post, $newStatus, $oldStatus);

		$this->post($post->ID)->meta->save('myCRED_sell_content', $mycredSellMeta, $post->ID, 'post');
	}

	/**
	 * Extend buy tracking
	 * 
	 * @param array $request
	 * 
	 * @return $request
	 */
	public function extendBuyTracking($request)
	{
		if ($request['ref'] !== 'buy_content') {
            return $request;
        }

		$objectId = $request['ref_id'];

		# Add category slug or post_tag slug to the request if they exist
		$categories = get_the_category($objectId);
		$tags = get_the_tags($objectId);

		if ($categories) {
			$request['data']['category'] = $categories[0]->slug;
		}

		if ($tags) {
			foreach ($tags as $tag) {
				$request['data']['tags'][] = $tag->slug;
			}
		}

		$request = apply_filters('authcred/mycred/mycred_buy_tracking', $request, $objectId);

		return $request;
	}
}
