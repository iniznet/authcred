<?php

namespace AuthCRED;

use WPTrait\Hook\Ajax;
use WPTrait\Model;

class UserAuth extends Model
{
	use Ajax;

	public $actions = [
		'user_register' => ['sendNewPassword', 10, 1],
	];

	public $ajax = [
		'methods' => [
			'authcred_register',
			'authcred_login' ,
			'authcred_reset_password',
			'authcred_reset_password_confirm',
			'authcred_reset_password_new',
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
			'emal' => $email,
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
		$userId = $this->user->authenticate($username, $password);

		if (is_wp_error($userId)) {
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
		$body = [
			_x('Hi %s,', 'Hi username,', 'authcred'),
			'',
			__('Someone requested that the password be reset for the following account:', 'authcred'),
			'',
			__('Username: %s', 'authcred'),
			__('Email: %s', 'authcred'),
			'',
			__('If this was a mistake, just ignore this email and nothing will happen.', 'authcred'),
			__('To reset your password, visit the following address:', 'authcred'),
			'',
			'<a href="' . $url . '">' . $url . '</a>',
			'',
			__('Thank you,', 'authcred'),
			get_bloginfo('name'),
		];

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

		$this->email($user->user_email, __('Password Reset', 'authcred'), implode('<br>', $body), $headers);
	}

	# Handle reset password token code verification
	public function authcred_reset_password_confirm()
	{
		if (!$this->nonce->verify('nonce', 'authcred_reset_password_confirm')) {
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
		$token = $this->request->input('token', ['trim', 'sanitize_text_field']);

		# Check if username & token is valid
		if (!$this->request->filled('token')) {
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

		$username = $this->session('authcred_reset_password')->get('username');

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
		$username = $this->user->exists($username);

		if (!$username) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$user = get_user_by('login', $username);

		# Check if token is correct
		if (!wp_check_password($token, $user->user_activation_key, $user->ID)) {
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
	public function admin_ajax_authcred_reset_password_new()
	{
		if (!$this->nonce->verify('nonce', 'authcred_reset_password_new')) {
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

		$username = $this->session('authcred_reset_password')->get('username');
		$token = $this->session('authcred_reset_password')->get('token');

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
		$username = $this->user->exists($username);

		if (!$username) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		$user = get_user_by('login', $username);

		# Check if token is correct
		if (!$this->password->check($token, $user->user_activation_key)) {
			$this->response->error([
				'message' => [
					'body' => __('Username or token is incorrect', 'authcred'),
				]
			]);
			exit;
		}

		# Update password
		$this->password->set($password, $user->ID);
	}

	# Send first-time password request to user email
	public function sendNewPassword($userId)
	{
		# Get user data
		$user = $this->user->get($userId);
		
		# Reset password key
		$key = get_password_reset_key($user);

		# Get reset password page url
		# find page with shortcode [authcred type="forgot"]
		$resetPageId = $this->findPageByShortcode('authcred', ['type' => 'forgot']);
		$url = get_permalink($resetPageId) . "?key=$key&username=" . rawurlencode($user->user_login);

		# Send email
		$body = [
			_x('Hi %s,', 'Hi username,', 'authcred'),
			'',
			__('Thank you for registering on our website.', 'authcred'),
			__('Please click the link below to set your password:', 'authcred'),
			$resetPageId ? '<a href="' . $url . '">' . $url . '</a>' : __('Unfortunately, the reset password page is not set up yet by admin', 'authcred'),
			'',
			__('If you did not make this request, please disregard this email. The account will automatically be deleted after 48 hours.', 'authcred'),
			'',
			__('Thank you,', 'authcred'),
			get_bloginfo('name'),
		];

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		$this->email($user->user_email, __('Set your password', 'authcred'), implode('<br>', $body), $headers);
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