<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

foreach ([
	__DIR__ . '/../src/MyCRED.php',
	__DIR__ . '/../src/UserProfile.php',
	__DIR__ . '/../src/Options/Settings.php',
] as $requiredFile) {
	if (!file_exists($requiredFile)) {
		fail(basename($requiredFile) . ' is missing');
	}

	require_once $requiredFile;
}

use AuthCRED\MyCRED;
use AuthCRED\Options\Settings;
use AuthCRED\UserProfile;

function runCase(string $id, string $description, callable $assertion): void
{
	$assertion();
	echo "PASS: {$id} {$description}\n";
}

function cleanupPath(string $path): void
{
	if (is_file($path)) {
		@unlink($path);
	}

	if (is_dir($path)) {
		@rmdir($path);
	}
}

function assertStringContains(string $needle, string $haystack, string $message): void
{
	if (false === strpos($haystack, $needle)) {
		fail('Assertion failed: ' . $message);
	}
}

function assertStringNotContains(string $needle, string $haystack, string $message): void
{
	if (false !== strpos($haystack, $needle)) {
		fail('Assertion failed: ' . $message);
	}
}

$originalSettings = get_option('authcred_settings', null);
$createdUserId = 0;
$createdPostIds = [];
$createdPaths = [];

try {
	delete_option('authcred_settings');

	$settings = new Settings((object) ['prefix' => 'authcred']);

	runCase('TC-01', 'register() exposes the content preview and avatar settings fields', static function () use ($settings): void {
		$setups = $settings->register([]);
		$contentPreviewFields = $setups[0]['fields']['content_preview']['fields'] ?? [];
		$avatarFields = $setups[0]['fields']['avatar']['fields'] ?? [];

		assertTrue(isset($contentPreviewFields['teaser_word_count']), 'TC-01 teaser_word_count missing');
		assertTrue(isset($avatarFields['avatar_max_size_kb']), 'TC-01 avatar_max_size_kb missing');
	});

	runCase('TC-02', 'sanitizeSettings clamps and defaults teaser/avatar settings values', static function () use ($settings): void {
		$sanitized = $settings->sanitizeSettings([
			'teaser_word_count' => 0,
			'avatar_max_size_kb' => 65535,
		]);

		assertSame(150, $sanitized['teaser_word_count'], 'TC-02 teaser_word_count should fall back to 150');
		assertSame(10240, $sanitized['avatar_max_size_kb'], 'TC-02 avatar_max_size_kb should clamp to 10240 before runtime hard cap');
	});

	runCase('TC-03', 'MyCRED teaser logic references the corrected purchase helper only', static function (): void {
		$source = file_get_contents(__DIR__ . '/../src/MyCRED.php');

		assertStringContains('mycred_user_paid_for_content', $source, 'TC-03 corrected helper should be used in MyCRED.php');
		assertStringNotContains('mycred_user_bought_content', $source, 'TC-03 deprecated helper must not appear in MyCRED.php');
	});

	runCase('TC-04', 'MyCRED teaser text helper strips markup and trims to the configured word limit', static function (): void {
		$mycred = new MyCRED((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(MyCRED::class, 'buildTeaserText');
		$method->setAccessible(true);

		$result = $method->invoke($mycred, '<p>One <strong>two</strong> three four five</p>', 3);

		assertSame('One two three', $result, 'TC-04 teaser text should be plain text trimmed to three words');
	});

	runCase('TC-05', 'MyCRED teaser source includes explicit password and preview guards', static function (): void {
		$source = file_get_contents(__DIR__ . '/../src/MyCRED.php');

		assertStringContains('post_password_required($post)', $source, 'TC-05 password-protected posts must be explicitly guarded');
		assertStringContains('is_preview()', $source, 'TC-05 preview requests must be explicitly guarded');
	});

	runCase('TC-06', 'MyCRED teaser bypasses preview requests for locked posts', static function () use (&$createdPostIds): void {
		$mycred = new MyCRED((object) ['prefix' => 'authcred']);
		$postId = wp_insert_post([
			'post_title' => 'Teaser Preview Guard',
			'post_content' => 'One two three four five six seven eight nine ten.',
			'post_status' => 'publish',
			'post_type' => 'post',
		], true);

		if (is_wp_error($postId)) {
			fail('TC-06 failed to create preview test post: ' . $postId->get_error_message());
		}

		$createdPostIds[] = (int) $postId;
		update_post_meta($postId, 'myCRED_sell_content', ['status' => 'enabled']);
		$testPost = get_post($postId);

		if (!$testPost instanceof \WP_Post) {
			fail('TC-06 failed to load preview test post.');
		}

		global $post, $wp_query;
		$previousPost = $post ?? null;
		$previousQuery = $wp_query ?? null;
		$post = $testPost;
		$wp_query = new \WP_Query();
		$wp_query->is_singular = true;
		$wp_query->in_the_loop = true;
		$wp_query->is_preview = true;
		$wp_query->post = $testPost;
		$wp_query->posts = [$testPost];

		try {
			assertSame($testPost->post_content, $mycred->applyContentTeaser($testPost->post_content), 'TC-06 preview requests should return the original content');
		} finally {
			$post = $previousPost;
			$wp_query = $previousQuery;
		}
	});

	runCase('TC-07', 'MyCRED teaser bypasses password-protected locked posts', static function () use (&$createdPostIds): void {
		$mycred = new MyCRED((object) ['prefix' => 'authcred']);
		$postId = wp_insert_post([
			'post_title' => 'Teaser Password Guard',
			'post_content' => 'Alpha beta gamma delta epsilon zeta eta theta.',
			'post_password' => 'secret',
			'post_status' => 'publish',
			'post_type' => 'post',
		], true);

		if (is_wp_error($postId)) {
			fail('TC-07 failed to create password test post: ' . $postId->get_error_message());
		}

		$createdPostIds[] = (int) $postId;
		update_post_meta($postId, 'myCRED_sell_content', ['status' => 'enabled']);
		$testPost = get_post($postId);

		if (!$testPost instanceof \WP_Post) {
			fail('TC-07 failed to load password test post.');
		}

		global $post, $wp_query;
		$previousPost = $post ?? null;
		$previousQuery = $wp_query ?? null;
		$post = $testPost;
		$wp_query = new \WP_Query();
		$wp_query->is_singular = true;
		$wp_query->in_the_loop = true;
		$wp_query->is_preview = false;
		$wp_query->post = $testPost;
		$wp_query->posts = [$testPost];

		try {
			assertSame($testPost->post_content, $mycred->applyContentTeaser($testPost->post_content), 'TC-07 password-protected posts should return the original content');
		} finally {
			$post = $previousPost;
			$wp_query = $previousQuery;
		}
	});

	runCase('TC-08', 'UserProfile enforces the 2 MB runtime hard cap even when admin settings allow more', static function (): void {
		update_option('authcred_settings', [
			'avatar_max_size_kb' => 4096,
		]);

		$profile = new UserProfile((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(UserProfile::class, 'getAvatarMaxSizeKb');
		$method->setAccessible(true);

		assertSame(2048, $method->invoke($profile), 'TC-08 runtime avatar size cap should be 2048 KB');
	});

	runCase('TC-09', 'UserProfile only resolves local avatar paths under the authcred-avatars upload directory', static function () use (&$createdPaths): void {
		$profile = new UserProfile((object) ['prefix' => 'authcred']);
		$method = new ReflectionMethod(UserProfile::class, 'resolveAvatarFilePath');
		$method->setAccessible(true);

		$uploadDir = wp_upload_dir();
		$avatarDir = trailingslashit($uploadDir['basedir']) . 'authcred-avatars';
		wp_mkdir_p($avatarDir);

		$validPath = trailingslashit($avatarDir) . 'teaser-valid-avatar.jpg';
		$outsidePath = trailingslashit($uploadDir['basedir']) . 'teaser-outside-avatar.jpg';
		file_put_contents($validPath, 'avatar');
		file_put_contents($outsidePath, 'outside');

		$createdPaths[] = $validPath;
		$createdPaths[] = $outsidePath;
		$createdPaths[] = $avatarDir;

		$validUrl = trailingslashit($uploadDir['baseurl']) . 'authcred-avatars/teaser-valid-avatar.jpg';
		$outsideUrl = trailingslashit($uploadDir['baseurl']) . 'teaser-outside-avatar.jpg';

		assertSame(wp_normalize_path(realpath($validPath)), wp_normalize_path($method->invoke($profile, $validUrl)), 'TC-09 valid avatar path should resolve');
		assertSame(null, $method->invoke($profile, $outsideUrl), 'TC-09 outside upload path should be rejected');
	});

	runCase('TC-10', 'UserProfile avatar filter serves a stored local avatar URL site-wide', static function () use (&$createdUserId): void {
		$profile = new UserProfile((object) ['prefix' => 'authcred']);
		$uploadDir = wp_upload_dir();
		$avatarUrl = trailingslashit($uploadDir['baseurl']) . 'authcred-avatars/teaser-valid-avatar.jpg';
		$uniqueSuffix = (string) wp_rand(1000, 999999);
		$username = 'teaser-avatar-user-' . $uniqueSuffix;
		$email = 'teaser-avatar-user-' . $uniqueSuffix . '@example.com';

		$createdUserId = wp_create_user($username, wp_generate_password(24), $email);
		if (is_wp_error($createdUserId)) {
			fail('TC-10 failed to create test user: ' . $createdUserId->get_error_message());
		}

		update_user_meta($createdUserId, 'authcred_avatar', $avatarUrl);

		$result = $profile->filterAvatarData([
			'url' => '',
			'found_avatar' => false,
		], $createdUserId);

		assertSame($avatarUrl, $result['url'], 'TC-10 local avatar URL should override avatar data');
		assertTrue(true === $result['found_avatar'], 'TC-10 filter should mark the avatar as found');
	});

	runCase('TC-11', 'UserProfile avatar mutation payload falls back cleanly when no local avatar is stored', static function () use (&$createdUserId): void {
		$profile = new UserProfile((object) ['prefix' => 'authcred']);
		delete_user_meta($createdUserId, 'authcred_avatar');

		$method = new ReflectionMethod(UserProfile::class, 'buildAvatarMutationResult');
		$method->setAccessible(true);
		$result = $method->invoke($profile, $createdUserId);

		assertFalse($result['has_local_avatar'], 'TC-11 fallback state should report no local avatar');
		assertSame($result['gravatar_url'], $result['avatar_url'], 'TC-11 fallback avatar should use the gravatar URL');
		assertTrue('' !== $result['avatar_url'], 'TC-11 fallback avatar URL should not be empty');
	});

	echo "ALL TESTS PASSED\n";
} finally {
	if ($createdUserId > 0) {
		wp_delete_user($createdUserId);
	}

	foreach (array_reverse(array_unique($createdPostIds)) as $createdPostId) {
		wp_delete_post($createdPostId, true);
	}

	foreach (array_reverse(array_unique($createdPaths)) as $createdPath) {
		cleanupPath($createdPath);
	}

	if (null === $originalSettings) {
		delete_option('authcred_settings');
	} else {
		update_option('authcred_settings', $originalSettings);
	}
}
