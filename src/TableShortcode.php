<?php

namespace AuthCRED;

use WPTrait\Model;

class TableShortcode extends Model
{
	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_shortcode('authcred-toc', [$this, 'tableOfContents']);
	}

	public function tableOfContents($atts, $content = null)
	{
		$defaults = [
			'post_type' => 'post',
			'post_status' => ['publish', 'future'],
			'category' => 'uncategorized',
			'taxonomy' => null,
			'term' => null,
			'limit' => -1,
			'replace' => [],
			'replace_num' => 0,
			'prepend' => '',
			'append' => '',
			'lock' => null, # show when chapter is locked and accept: (plain for plain text or custom text) and icon for lock icon
			'unlock' => null, # show when chapter is unlocked and accept: (plain for plain text or custom text) and icon for lock icon
			'bullet' => 'dot', # dot/disc, number/decimal, and none
		];

		$args = shortcode_atts($defaults, $atts, 'authcred-toc');
		$term = '';

		$tag = 'ul';
		$class = 'authcred-toc';

		if (empty($args['category']) && empty($args['taxonomy'])) {
			return '';
		}

		if ($args['post_status'] && !is_array($args['post_status'])) {
			$args['post_status'] = explode(',', $args['post_status']);
		}
			

		# check if category is not empty and a slug or ID
		if ($args['category'] && !is_numeric($args['category'])) {
			$term = sanitize_title($args['category']);
			$term = get_cat_ID($args['category']);
		}

		if ($args['category'] && is_numeric($args['category'])) {
			$term = (int)$args['category'];
		}

		# check if taxonomy is not empty and a slug or ID
		if ($args['taxonomy'] && !is_numeric($args['taxonomy'])) {
			$term = get_term_by('slug', $args['taxonomy'], 'category');
		}

		if ($args['taxonomy'] && is_numeric($args['taxonomy'])) {
			$term = (int)$args['taxonomy'];
		}

		# load posts from term
		$posts = new \WP_Query([
			'post_type' => $args['post_type'],
			'post_status' => $args['post_status'],
			'posts_per_page' => $args['limit'],
			'tax_query' => [
				[
					'taxonomy' => !empty($args['taxonomy']) ? $args['taxonomy'] : 'category',
					'field' => 'term_id',
					'terms' => $term,
				],
			],
		]);

		# check if posts is not empty
		if (!$posts->have_posts()) {
			return '';
		}

		# manipulate post title based on replace, replace_num, prepend, append
		foreach ($posts->posts as &$post) {
			if (!empty($args['replace'])) {
				$post->post_title = trim(str_replace($args['replace'], '', $post->post_title));
			}

			if ($args['replace_num'] && is_numeric($args['replace_num'])) {
				$post->post_title = substr($post->post_title, $args['replace_num']);
			}

			if (!empty($args['prepend'])) {
				$post->post_title = $args['prepend'] . $post->post_title;
			}

			if (!empty($args['append'])) {
				$post->post_title .= $args['append'];
			}

			if (mycred_post_is_for_sale($post->ID) && $args['lock'] !== null) {
				$suffix = '';

				if (!$args['unlock']) {
					$args['unlock'] = $args['lock'];
				}

				if (mycred_user_paid_for_content( get_current_user_id(), $post->ID )) {
					if ($args['unlock'] === 'icon') {
						$suffix = $this->view->render('partials.unlock-icon');
					}

					if ($args['unlock'] === 'plain') {
						$suffix = __('(Unlocked)', 'authcred');
					}

					if ($args['unlock'] === $args['lock'] || $args['unlock'] === null) {
						continue;
					}

					if ($args['unlock'] !== 'plain' && $args['unlock'] !== 'icon') {
						$suffix = $args['lock'];
					}
				} else {
					if ($args['lock'] === 'icon') {
						$suffix = $this->view->render('partials.lock-icon');
					}

					if ($args['lock'] === 'plain') {
						$suffix = __('(Locked)', 'authcred');
					}

					if ($args['lock'] !== 'plain' && $args['lock'] !== 'icon') {
						$suffix = $args['lock'];
					}
				}

				$post->post_title = sprintf('<span class="authcred-unlock">%s %s</span>', $post->post_title, $suffix);
			}
		}

		if ($args['bullet'] === 'dot' || $args['bullet'] === 'disc') {
			$class .= ' list-disc';
		}

		if ($args['bullet'] === 'number' || $args['bullet'] === 'decimal') {
			$tag = 'ol';
			$class .= ' list-decimal';
		}

		if ($args['bullet'] === 'none') {
			$class .= ' list-none';
		}

		# load template
		return $this->view->render('toc', [
			'posts' => $posts,
			'tag' => $tag,
			'class' => $class,
		]);
	}
}