<?php

namespace AuthCRED;

final class EmailTemplates
{
	private const OPTION_NAME = 'authcred_settings';

	private const DEFAULTS = [
		'setup_password' => [
			'subject' => 'Set up your new password',
			'body' => 'Hi {username},<br><br>We received a registration request for {email} on {site_name}.<br>Please click the link below to set your password:<br>{link}<br><br>If you did not make this request, please disregard this email. The account will automatically be deleted after 48 hours.<br><br>Thank you,<br>{site_name}',
		],
		'reset_password' => [
			'subject' => 'Reset Password',
			'body' => 'Hi {username},<br><br>Someone requested that the password be reset for the following account:<br>Username: {username}<br>Email: {email}<br><br>To reset your password, visit the following address:<br>{link}<br><br>If this was a mistake, just ignore this email and nothing will happen.<br><br>Thank you,<br>{site_name}',
		],
	];

	private function __construct()
	{
	}

	public static function defaults(): array
	{
		return self::DEFAULTS;
	}

	public static function buildSetupPassword(string $username, string $email, string $setPasswordLink): array
	{
		return self::build(
			'setup_password',
			'email_setup_subject',
			'email_setup_body',
			'authcred/email/setup_password_subject',
			'authcred/email/setup_password_body',
			'authcred/email/setup_password',
			$username,
			$email,
			$setPasswordLink
		);
	}

	public static function buildResetPassword(string $username, string $email, string $resetLink): array
	{
		return self::build(
			'reset_password',
			'email_reset_subject',
			'email_reset_body',
			'authcred/email/reset_request_subject',
			'authcred/email/reset_request_body',
			'authcred/email/reset_request',
			$username,
			$email,
			$resetLink
		);
	}

	private static function build(
		string $template,
		string $subjectSettingKey,
		string $bodySettingKey,
		string $subjectFilter,
		string $bodyFilter,
		string $templateFilter,
		string $username,
		string $email,
		string $link
	): array {
		$settings = get_option(self::OPTION_NAME, []);
		$defaults = self::DEFAULTS[$template];
		$siteName = sanitize_text_field(get_bloginfo('name'));

		$subject = sanitize_text_field($settings[$subjectSettingKey] ?? '');
		$body = wp_kses_post($settings[$bodySettingKey] ?? '');

		if ('' === $subject) {
			$subject = $defaults['subject'];
		}

		if ('' === trim($body)) {
			$body = $defaults['body'];
		}

		$tokens = [
			'{username}' => esc_html($username),
			'{email}' => esc_html($email),
			'{link}' => esc_html($link),
			'{site_name}' => esc_html($siteName),
		];

		$context = [
			'template' => $template,
			'username' => $username,
			'email' => $email,
			'link' => $link,
			'site_name' => $siteName,
		];

		$subject = sanitize_text_field(strtr($subject, $tokens));
		$body = wp_kses_post(strtr($body, $tokens));

		$subject = apply_filters($subjectFilter, $subject, $context);
		$body = apply_filters($bodyFilter, $body, $context);

		$templateData = [
			'subject' => sanitize_text_field($subject),
			'body' => wp_kses_post($body),
			'tokens_used' => array_keys($tokens),
		];

		return apply_filters($templateFilter, $templateData, $context);
	}
}
