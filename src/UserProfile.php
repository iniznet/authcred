<?php

namespace AuthCRED;

use WPTrait\Model;

class UserProfile extends Model
{
	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_action('wp_ajax_authcred_update_profile', [$this, 'admin_ajax_authcred_update_profile']);
		add_action('wp_ajax_authcred_upload_avatar', [$this, 'admin_ajax_authcred_upload_avatar']);
		add_action('wp_ajax_authcred_remove_avatar', [$this, 'admin_ajax_authcred_remove_avatar']);
		add_filter('pre_get_avatar_data', [$this, 'filterAvatarData'], 10, 2);
	}

	public function admin_ajax_authcred_update_profile()
	{
		if (!$this->nonce->verify('nonce', 'authcred_update_profile')) {
			return $this->sendError(__('Invalid request', 'authcred'));
		}

		if (!$this->user->auth()) {
			return $this->sendError(__('You need to be logged in to update your profile', 'authcred'));
		}

		$userId = get_current_user_id();
		$displayName = sanitize_text_field($this->request->input('display_name', ['trim']));
		$email = sanitize_email($this->request->input('email', ['trim']));
		$description = sanitize_textarea_field($this->request->input('description', ['trim']));

		if ('' === $displayName) {
			return $this->sendError(__('Display name is required', 'authcred'));
		}

		if ('' === $email || !is_email($email)) {
			return $this->sendError(__('A valid email is required', 'authcred'));
		}

		$existingUserId = email_exists($email);

		if ($existingUserId && (int) $existingUserId !== $userId) {
			return $this->sendError(__('Email is already in use', 'authcred'));
		}

		$result = wp_update_user([
			'ID' => $userId,
			'display_name' => $displayName,
			'user_email' => $email,
		]);

		if (is_wp_error($result)) {
			return $this->sendError(__('Failed to update profile, please try again.', 'authcred'));
		}

		update_user_meta($userId, 'description', $description);

		$this->response->success([
			'message' => [
				'body' => __('Profile updated successfully', 'authcred'),
			],
		]);

		exit;
	}

	public function admin_ajax_authcred_upload_avatar()
	{
		check_ajax_referer('authcred_upload_avatar', 'nonce');

		if (!is_user_logged_in()) {
			$this->sendAjaxError(__('You need to be logged in to upload an avatar.', 'authcred'), 'unauthorized', 403);
		}

		$file = $_FILES['avatar'] ?? null;

		if (!is_array($file) || empty($file['tmp_name']) || !isset($file['name'])) {
			$this->sendAjaxError(__('Please choose an image to upload.', 'authcred'), 'missing_file');
		}

		if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			$this->sendAjaxError(__('The avatar upload failed. Please try again.', 'authcred'), 'upload_failed');
		}

		if ((int) ($file['size'] ?? 0) > $this->getAvatarUploadLimitBytes()) {
			$this->sendAjaxError(__('The selected image is too large.', 'authcred'), 'file_too_large');
		}

		$fileType = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $this->allowedAvatarMimes());
		$extension = strtolower((string) ($fileType['ext'] ?? ''));
		$mimeType = strtolower((string) ($fileType['type'] ?? ''));

		if ('svg' === strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION))) {
			$this->sendAjaxError(__('SVG avatars are not allowed.', 'authcred'), 'invalid_type');
		}

		if ('' === $extension || '' === $mimeType || !isset($this->allowedAvatarMimes()[$extension])) {
			$this->sendAjaxError(__('Please upload a JPG, PNG, GIF, or WebP image.', 'authcred'), 'invalid_type');
		}

		add_filter('upload_dir', [$this, 'filterAvatarUploadDir']);
		$upload = wp_handle_upload($file, [
			'test_form' => false,
			'mimes' => $this->allowedAvatarMimes(),
		]);
		remove_filter('upload_dir', [$this, 'filterAvatarUploadDir']);

		if (!is_array($upload) || isset($upload['error']) || empty($upload['url'])) {
			$this->sendAjaxError(__('The avatar upload failed. Please try again.', 'authcred'), 'upload_failed');
		}

		$userId = get_current_user_id();
		$this->deleteStoredAvatarForUser($userId);
		update_user_meta($userId, 'authcred_avatar', esc_url_raw($upload['url']));

		wp_send_json_success([
			'message' => [
				'body' => __('Avatar updated successfully.', 'authcred'),
			],
			'avatar' => $this->buildAvatarMutationResult($userId),
		]);
	}

	public function admin_ajax_authcred_remove_avatar()
	{
		check_ajax_referer('authcred_remove_avatar', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error([
				'code' => 'unauthorized',
				'message' => [
					'body' => __('You need to be logged in to remove an avatar.', 'authcred'),
				],
			], 403);
		}

		$userId = get_current_user_id();
		$this->deleteStoredAvatarForUser($userId);

		wp_send_json_success([
			'message' => [
				'body' => __('Avatar removed.', 'authcred'),
			],
			'avatar' => $this->buildAvatarMutationResult($userId),
		]);
	}

	public function filterAvatarData($args, $idOrEmail)
	{
		$userId = $this->resolveAvatarUserId($idOrEmail);

		if ($userId <= 0) {
			return $args;
		}

		$avatarUrl = $this->getStoredAvatarUrl($userId);

		if ('' === $avatarUrl) {
			return $args;
		}

		$args['url'] = $avatarUrl;
		$args['found_avatar'] = true;

		return $args;
	}

	public function filterAvatarUploadDir($uploadDir)
	{
		$uploadDir['subdir'] = '/authcred-avatars';
		$uploadDir['path'] = $uploadDir['basedir'] . $uploadDir['subdir'];
		$uploadDir['url'] = $uploadDir['baseurl'] . $uploadDir['subdir'];

		return $uploadDir;
	}

	private function allowedAvatarMimes(): array
	{
		return [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
		];
	}

	private function getAvatarMaxSizeKb(): int
	{
		$settings = get_option($this->plugin->prefix . '_settings', []);
		$maxSizeKb = isset($settings['avatar_max_size_kb']) ? absint($settings['avatar_max_size_kb']) : 2048;

		if ($maxSizeKb < 64) {
			$maxSizeKb = 2048;
		}

		return min($maxSizeKb, 2048);
	}

	private function getAvatarUploadLimitBytes(): int
	{
		return $this->getAvatarMaxSizeKb() * 1024;
	}

	private function buildAvatarMutationResult(int $userId): array
	{
		$gravatarUrl = $this->getFallbackAvatarUrl($userId);
		$avatarUrl = $this->getStoredAvatarUrl($userId);

		return [
			'avatar_url' => '' !== $avatarUrl ? $avatarUrl : $gravatarUrl,
			'has_local_avatar' => '' !== $avatarUrl,
			'gravatar_url' => $gravatarUrl,
		];
	}

	private function getStoredAvatarUrl(int $userId): string
	{
		$storedUrl = get_user_meta($userId, 'authcred_avatar', true);

		if (!is_string($storedUrl) || '' === $storedUrl) {
			return '';
		}

		$path = $this->resolveAvatarFilePath($storedUrl);

		if (null === $path) {
			return '';
		}

		return esc_url_raw($storedUrl);
	}

	private function getFallbackAvatarUrl(int $userId): string
	{
		remove_filter('pre_get_avatar_data', [$this, 'filterAvatarData'], 10);
		$gravatarUrl = get_avatar_url($userId, ['size' => 96]);
		add_filter('pre_get_avatar_data', [$this, 'filterAvatarData'], 10, 2);

		return esc_url_raw((string) $gravatarUrl);
	}

	private function resolveAvatarUserId($idOrEmail): int
	{
		if ($idOrEmail instanceof \WP_User) {
			return (int) $idOrEmail->ID;
		}

		if ($idOrEmail instanceof \WP_Post) {
			return (int) $idOrEmail->post_author;
		}

		if ($idOrEmail instanceof \WP_Comment) {
			if ((int) $idOrEmail->user_id > 0) {
				return (int) $idOrEmail->user_id;
			}

			$user = get_user_by('email', (string) $idOrEmail->comment_author_email);
			return $user instanceof \WP_User ? (int) $user->ID : 0;
		}

		if (is_numeric($idOrEmail)) {
			return max(0, (int) $idOrEmail);
		}

		if (is_string($idOrEmail) && '' !== $idOrEmail) {
			$user = get_user_by('email', $idOrEmail);
			return $user instanceof \WP_User ? (int) $user->ID : 0;
		}

		return 0;
	}

	private function resolveAvatarFilePath(string $storedUrl): ?string
	{
		$uploadDir = wp_upload_dir();
		$avatarBaseUrl = trailingslashit($uploadDir['baseurl']) . 'authcred-avatars/';

		if (0 !== strpos($storedUrl, $avatarBaseUrl)) {
			return null;
		}

		$relativePath = ltrim(substr($storedUrl, strlen(trailingslashit($uploadDir['baseurl']))), '/\\');
		$localPath = wp_normalize_path(trailingslashit($uploadDir['basedir']) . $relativePath);
		$realPath = realpath($localPath);
		$allowedBase = wp_normalize_path(trailingslashit($uploadDir['basedir']) . 'authcred-avatars');

		if (false === $realPath) {
			return null;
		}

		$normalizedRealPath = wp_normalize_path($realPath);
		$normalizedAllowedBase = trailingslashit($allowedBase);

		if (0 !== strpos($normalizedRealPath, $normalizedAllowedBase)) {
			return null;
		}

		return $normalizedRealPath;
	}

	private function deleteStoredAvatarForUser(int $userId): void
	{
		$storedUrl = get_user_meta($userId, 'authcred_avatar', true);

		if (is_string($storedUrl) && '' !== $storedUrl) {
			$path = $this->resolveAvatarFilePath($storedUrl);

			if (null !== $path && file_exists($path)) {
				@unlink($path);
			}
		}

		delete_user_meta($userId, 'authcred_avatar');
	}

	private function sendAjaxError(string $message, string $code = 'invalid_request', int $status = 400): void
	{
		wp_send_json_error([
			'code' => $code,
			'message' => [
				'body' => $message,
			],
		], $status);
	}

	private function sendError($message)
	{
		$this->response->error([
			'message' => [
				'body' => $message,
			],
		]);

		exit;
	}
}
