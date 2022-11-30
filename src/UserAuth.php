<?php

namespace AuthCRED;

use WPTrait\Hook\Ajax;
use WPTrait\Model;

class UserAuth extends Model
{
	use Ajax;

	public $actions = [
		'user_register' => [
			['sendSetupPasswordEmail', 10, 1],
			['setAsPendingUser', 10, 1],
		]
	];

	public $ajax = [
		'methods' => [
			'authcred_register',
			'authcred_login' ,
			'authcred_reset_password',
			'authcred_change_password',
			'authcred_confirm_reset_password',
			'authcred_reset_new_password',
			'authcred_logout',
		]
	];
	
	public function __construct($plugin)
	{
		parent::__construct($plugin);
	}
	
	# Register only need username & email without asking password as we will send password to user email act as a confirmation
	public function admin_ajax_authcred_register()
	{
		if (!$this->nonce->verify('nonce', 'authcred_register')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}

		# Check if user is already logged in
		if ($this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are already logged in', 'authcred'),
				]
			]);
			exit;
		}
		
		# get username & email from request
		$username = $this->request->input('username', ['trim', 'sanitize_text_field']);
		$email = $this->request->input('email', ['trim', 'sanitize_email']);

		# Check if username & email is valid
		if (!$this->request->filled('username')) {
			$this->response->error([
				'message' => [
					'body' => __('Username is required', 'authcred'),
				]
			]);
			exit;
		}

		if (!$this->request->filled('email')) {
			$this->response->error([
				'message' => [
					'body' => __('Email is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if username & email is already exist
		if ($this->user->exists($username)) {
			$this->response->error([
				'message' => [
					'body' => __('Username is already exist', 'authcred'),
				]
			]);
			exit;
		}

		if ($this->user->exists($email)) {
			$this->response->error([
				'message' => [
					'body' => __('Email is already exist', 'authcred'),
				]
			]);
			exit;
		}
		
		# Create user
		$password = wp_generate_password(12);
		$userId = $this->user->add([
			'username' => $username,
			'email' => $email,
			'user_pass' => $password,
		]);
		
		if ($this->error->has($userId)) {
			$this->response->error([
				'message' => [
					'body' => __('Failed to create user, please try again or report', 'authcred')
				]
			]);
			exit;
		}

		# Return success response
		$this->response->success([
			'message' => [
				'body' => __('Successfully registered, please check your email to set your password', 'authcred')
			]
		]);

		exit;
	}

	public function admin_ajax_authcred_login()
	{
		if (!$this->nonce->verify('nonce', 'authcred_login')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}
		
		# Check if user is already logged in
		if ($this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are already logged in', 'authcred'),
				]
			]);
		}

		# get username & password from request
		$username = $this->request->input('username', ['trim', 'sanitize_text_field']);
		$password = $this->request->input('password', ['trim', 'sanitize_text_field']);

		# Check if username & password is valid
		if (!$this->request->filled('username')) {
			$this->response->error([
				'message' => [
					'body' => __('Username is required', 'authcred'),
				]
			]);
			exit;
		}

		if (!$this->request->filled('password')) {
			$this->response->error([
				'message' => [
					'body' => __('Password is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if username & password is correct
		$user = $this->user->login($username, $password, true);

		if (is_wp_error($user)) {
			$this->response->error([
				'message' => [
					'body' => __('Username or password is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		# Return success response
		$this->response->success([
			'message' => [
				'body' => __('Successfully logged in', 'authcred'),
			]
		]);

		exit;
	}

	# Handle reset password request
	public function admin_ajax_authcred_reset_password()
	{
		if (!$this->nonce->verify('nonce', 'authcred_reset_password')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}

		# Check if user is already logged in
		if ($this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are already logged in', 'authcred'),
				]
			]);
			exit;
		}

		# get username or email from request
		$usernameOrEmail = $this->request->input('username', ['trim', 'sanitize_text_field']);

		# Check if username or email is valid
		if (!$this->request->filled('username')) {
			$this->response->error([
				'message' => [
					'body' => __('Username or email is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if username or email is correct
		$userId = $this->user->exists($usernameOrEmail);

		if (!$userId) {
			$this->response->error([
				'message' => [
					'body' => __('Username or email is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$user = get_user_by('id', $userId);

		# Generate reset password token
		$token = get_password_reset_key($user);
		$url = get_permalink($this->findPageByShortcode('authcred', ['type' => 'forgot'])) . '#2?key=' . $token . '&username=' . rawurlencode($user->user_login);

		# Send reset password email
		$subject = __('Reset Password', 'authcred');
		
		$body = implode('<br>', [
			sprintf(_x('Hi %s,', 'Hi username,', 'authcred'), $user->user_login),
			'',
			__('Someone requested that the password be reset for the following account:', 'authcred'),
			sprintf(_x('Username: %s', 'Username: username', 'authcred'), $user->user_login),
			sprintf(_x('Email: %s', 'Email: email', 'authcred'), $user->user_email),
			'',
			__('To reset your password, visit the following address:', 'authcred'),
			'<a href="' . $url . '">' . $url . '</a>',
			'',
			__('If this was a mistake, just ignore this email and nothing will happen.', 'authcred'),
			'',
			__('Thank you,', 'authcred'),
			get_bloginfo('name'),
		]);

		$body = apply_filters('authcred/email/reset_request_body', $body);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		# Store to session
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		$this->session('authcred_reset_password')->set('username', $user->user_login);
		$this->session('authcred_reset_password')->set('token', $token);

		session_write_close();

		$this->email->send($subject, $body, $headers, [], $user->user_email);

		# Return success response
		$this->response->success([
			'message' => [
				'body' => __('Please check your email for further instructions', 'authcred'),
			]
		]);
	}

	# Handle change password request
	public function admin_ajax_authcred_change_password()
	{
		if (!$this->nonce->verify('nonce', 'authcred_change_password')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}

		# Check if user is already logged in
		if (!$this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are not logged in', 'authcred'),
				]
			]);
			exit;
		}

		# get password from request
		$password = $this->request->input('password', ['trim', 'sanitize_text_field']);
		$newPassword = $this->request->input('new_password', ['trim', 'sanitize_text_field']);
		$confrimNewPassword = $this->request->input('confirm_new_password', ['trim', 'sanitize_text_field']);
		
		# Check if password is valid
		if (!$this->request->filled('password')) {
			$this->response->error([
				'message' => [
					'body' => __('Current password is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if new password is valid
		if (!$this->request->filled('new_password')) {
			$this->response->error([
				'message' => [
					'body' => __('New password is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if confirm new password is valid
		if (!$this->request->filled('confirm_new_password') || $newPassword != $confrimNewPassword) {
			$this->response->error([
				'message' => [
					'body' => __('New password does not match', 'authcred'),
				]
			]);
			exit;
		}

		# Check if password is correct
		if (!$this->password->check($password)) {
			$this->response->error([
				'message' => [
					'body' => __('Current password is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		# Change password
		$this->password->set($newPassword, $this->user->id());

		# Reauthenticate user
		$this->user->authAs($this->user->id(), true);

		# Return success response
		$this->response->success([
			'message' => [
				'body' => __('Password changed successfully', 'authcred'),
			]
		]);
	}

	# Handle reset password token code verification
	public function admin_ajax_authcred_confirm_reset_password()
	{
		if (!$this->nonce->verify('nonce', 'authcred_confirm_reset_password')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}

		# Check if user is already logged in
		if ($this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are already logged in', 'authcred'),
				]
			]);
			exit;
		}

		# get username & token from request
		$token = $this->request->input('reset', ['trim', 'sanitize_text_field']);

		# Check if username & token is valid
		if (!$this->request->filled('reset')) {
			$this->response->error([
				'message' => [
					'body' => __('Token is required', 'authcred'),
				]
			]);
			exit;
		}

		# get user from session
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		$username = $this->session('authcred_reset_password')->get(null, 'username');

		session_write_close();

		# Check if username is valid
		if (!$username) {
			$this->response->error([
				'message' => [
					'body' => __('Unable to fetch user from session', 'authcred'),
				]
			]);
			exit;
		}

		# Check if username & token is correct
		if (!$this->user->exists($username)) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$check = check_password_reset_key($token, $username);

		# Check if token is correct
		if (is_wp_error($check)) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$this->response->success([
			'message' => [
				'body' => __('Please set your new password', 'authcred'),
			]
		]);
	}

	# Handle reset password, set new password
	public function admin_ajax_authcred_reset_new_password()
	{
		if (!$this->nonce->verify('nonce', 'authcred_reset_new_password')) {
			$this->response->error([
				'message' => [
					'body' => __('Invalid request', 'authcred'),
				]
			]);
			exit;
		}

		# Check if user is already logged in
		if ($this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are already logged in', 'authcred'),
				]
			]);
			exit;
		}

		# get username & token from request
		$password = $this->request->input('password', ['trim', 'sanitize_text_field']);
		$confirmPassword = $this->request->input('confirm_password', ['trim', 'sanitize_text_field']);
		$resetKey = $this->request->input('reset', ['trim', 'sanitize_text_field']);		

		# Check if username & token is valid
		if (!$this->request->filled('password')) {
			$this->response->error([
				'message' => [
					'body' => __('Password is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if username & token is valid
		if (!$this->request->filled('confirm_password')) {
			$this->response->error([
				'message' => [
					'body' => __('Confirm password is required', 'authcred'),
				]
			]);
			exit;
		}

		# Check if password & confirm password is same
		if ($password != $confirmPassword) {
			$this->response->error([
				'message' => [
					'body' => __('Password and confirm password is not same', 'authcred'),
				]
			]);
			exit;
		}

		# get user from session
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

		$username = $this->session('authcred_reset_password')->get(null, 'username');

		session_write_close();

		# Check if username is empty then get from request
		if (!$username) {
			$username = $this->request->input('username', ['trim', 'sanitize_text_field']);
		}

		# Check if username is valid
		if (!$username) {
			$this->response->error([
				'message' => [
					'body' => __('Unable to fetch user from session', 'authcred'),
				]
			]);
			exit;
		}

		# Check if resetKey is not empty then verify it
		if (!empty($resetKey)) {
			$check = check_password_reset_key($resetKey, $username);

			# Check if token is correct
			if (is_wp_error($check)) {
				$this->response->error([
					'message' => [
						'body' => __('Username or token is incorrect', 'authcred'),
					]
				]);
				exit;
			}
		}

		# Check if username & token is correct
		if (!$this->user->exists($username)) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$user = get_user_by('login', $username);

		# Update password
		$this->password->set($password, $user->ID);

		# Return success
		$this->response->success([
			'message' => [
				'body' => __('Successfully updated password', 'authcred'),
			]
		]);

		exit;
	}

	# Handle logout
	public function admin_ajax_authcred_logout()
	{
		# Check if user is already logged in
		if (!$this->user->auth()) {
			$this->response->error([
				'message' => [
					'body' => __('You are not logged in', 'authcred'),
				]
			]);
		}

		# Logout user
		$this->user->logout();

		# Return success response
		$this->response->success([
			'message' => [
				'body' => __('Successfully logged out', 'authcred'),
			]
		]);

		exit;
	}

	# Send first-time password request to user email
	public function sendSetupPasswordEmail($userId)
	{
		# Get user data
		$user = $this->user->get($userId);
		
		# Reset password key
		$key = get_password_reset_key($user);

		# Get reset password page url
		# find page with shortcode [authcred type="forgot"]
		$resetPageId = $this->findPageByShortcode('authcred', ['type' => 'forgot']);
		$url = get_permalink($resetPageId) . "#3?reset=$key&username=" . rawurlencode($user->user_login);

		# Send email
		$subject = __('Set up your new password', 'authcred');

		$body = implode('<br>', [
			sprintf(_x('Hi %s,', 'Hi username,', 'authcred'), $user->user_login),
			'',
			__('Thank you for registering on our website.', 'authcred'),
			__('Please click the link below to set your password:', 'authcred'),
			$resetPageId ? '<a href="' . $url . '">' . $url . '</a>' : __('Unfortunately, the reset password page is not set up yet by admin', 'authcred'),
			'',
			__('If you did not make this request, please disregard this email. The account will automatically be deleted after 48 hours.', 'authcred'),
			'',
			__('Thank you,', 'authcred'),
			get_bloginfo('name'),
		]);

		$body = apply_filters('authcred/email/setup_password_body', $body);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		$this->email->send($subject, $body, $headers, [], $user->user_email);
	}

	public function setAsPendingUser($userId)
	{
		# Set Pending Meta
		$meta = [
			'activated' => '',
			'expiration' => $this->user->can('edit_posts', $userId) ? null : wp_date('U') + 172800, # 48 hours
		];

		$this->user($userId)->meta->save($this->plugin->prefix . '_registration_fields', $meta, $userId, 'user');
	}

	private function findPageByShortcode($shortcode, $attributes = [])
	{
		# create multiple post_content LIKE operation for shortcode and attributes
		$like = [];
		$like[] = "post_content LIKE '%[$shortcode%'";
		foreach ($attributes as $key => $value) {
			$like[] = "post_content LIKE '%$key=\"$value\"%'";
		}
		$like = implode(' AND ', $like);

		# find page with shortcode
		$page = $this->db->get_row("SELECT ID FROM {$this->db->posts} WHERE post_type = 'page' AND post_status = 'publish' AND $like");
		
		return $page->ID ?? 0;
	}
}