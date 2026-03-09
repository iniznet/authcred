<?php

namespace AuthCRED;

use WPTrait\Model;

class MyCRED extends Model
{
	public $actions = [
		'pre_get_posts' => ['allowFutureAccess', 1000, 1],
		'transition_post_status' => ['disableSaleOncePublished', 10, 3],
		'mycred_run_this' => ['extendBuyTracking', 10, 1],
		'the_content' => ['applyContentTeaser', 5, 1],
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

	public function applyContentTeaser($content)
	{
		$post = get_post();

		if (!$post instanceof \WP_Post) {
			return $content;
		}

		$eligibility = $this->getContentTeaserEligibility($post);

		if (!$eligibility['should_render_teaser']) {
			return $content;
		}

		return $this->renderContentTeaser($content, $post, (bool) $eligibility['has_purchased']);
	}

	private function getContentTeaserEligibility(\WP_Post $post): array
	{
		$eligibility = [
			'should_render_teaser' => false,
			'has_edit_cap' => false,
			'has_purchased' => false,
			'is_locked_by_mycred' => false,
			'reason' => 'guest_or_unpurchased',
		];

		if (!is_singular()) {
			$eligibility['reason'] = 'not_singular';
			return $eligibility;
		}

		if (!in_the_loop()) {
			$eligibility['reason'] = 'not_in_loop';
			return $eligibility;
		}

		if (post_password_required($post)) {
			$eligibility['reason'] = 'password_required';
			return $eligibility;
		}

		if (is_preview()) {
			$eligibility['reason'] = 'preview';
			return $eligibility;
		}

		$eligibility['is_locked_by_mycred'] = $this->isPostLockedByMyCred($post->ID);

		if (!$eligibility['is_locked_by_mycred']) {
			$eligibility['reason'] = 'not_locked';
			return $eligibility;
		}

		$eligibility['has_edit_cap'] = current_user_can('edit_post', $post->ID);

		if ($eligibility['has_edit_cap']) {
			$eligibility['reason'] = 'editor_bypass';
			return $eligibility;
		}

		$eligibility['has_purchased'] = $this->currentUserHasPurchased($post->ID);

		if ($eligibility['has_purchased']) {
			$eligibility['reason'] = 'purchased';
			return $eligibility;
		}

		$eligibility['should_render_teaser'] = true;

		return $eligibility;
	}

	private function isPostLockedByMyCred(int $postId): bool
	{
		$mycredSellMeta = get_post_meta($postId, 'myCRED_sell_content', true);

		return is_array($mycredSellMeta)
			&& isset($mycredSellMeta['status'])
			&& 'enabled' === $mycredSellMeta['status'];
	}

	private function currentUserHasPurchased(int $postId): bool
	{
		$userId = get_current_user_id();

		if ($userId <= 0 || !function_exists('mycred_user_paid_for_content')) {
			return false;
		}

		return (bool) mycred_user_paid_for_content($userId, $postId);
	}

	private function renderContentTeaser(string $content, \WP_Post $post, bool $hasPurchased): string
	{
		$viewData = [
			'post_id' => (int) $post->ID,
			'teaser_text' => $this->buildTeaserText($content, $this->getTeaserWordCount()),
			'is_logged_in' => is_user_logged_in(),
			'has_purchased' => $hasPurchased,
			'login_page_url' => wp_login_url(get_permalink($post->ID) ?: ''),
			'purchase_shortcode' => sprintf('[mycred_buy post_id="%d"]', (int) $post->ID),
			'teaser_word_count' => $this->getTeaserWordCount(),
		];

		$excerpt = '';
		if ('' !== $viewData['teaser_text']) {
			$excerpt = sprintf(
				'<div class="authcred-teaser__excerpt">%s</div>',
				wpautop(esc_html($viewData['teaser_text']))
			);
		}

		return $excerpt . $this->renderContentTeaserPartial($viewData);
	}

	private function renderContentTeaserPartial(array $viewData): string
	{
		$templatePath = dirname(__DIR__) . '/templates/partials/content-teaser.php';

		if (!file_exists($templatePath)) {
			return $this->renderDefaultTeaserCallToAction($viewData);
		}

		extract($viewData, EXTR_SKIP);
		ob_start();
		include $templatePath;
		$template = (string) ob_get_clean();

		if ('' !== trim($template)) {
			return $template;
		}

		return $this->renderDefaultTeaserCallToAction($viewData);
	}

	private function renderDefaultTeaserCallToAction(array $viewData): string
	{
		if (!$viewData['is_logged_in']) {
			return sprintf(
				'<p class="authcred-teaser__cta"><a href="%s">%s</a></p>',
				esc_url($viewData['login_page_url']),
				esc_html__('Log in to read more', 'authcred')
			);
		}

		return sprintf(
			'<div class="authcred-teaser__cta">%s</div>',
			do_shortcode($viewData['purchase_shortcode'])
		);
	}

	private function getTeaserWordCount(): int
	{
		$settings = get_option($this->plugin->prefix . '_settings', []);
		$count = isset($settings['teaser_word_count']) ? absint($settings['teaser_word_count']) : 150;

		if ($count < 1) {
			return 150;
		}

		return min(1000, $count);
	}

	private function buildTeaserText(string $content, int $wordCount): string
	{
		return trim(wp_trim_words(wp_strip_all_tags($content), $wordCount, ''));
	}
}
