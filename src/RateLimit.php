<?php

namespace AuthCRED;

final class RateLimit
{
	private const DEFAULTS = [
		'login' => [
			'limit' => 5,
			'window_minutes' => 15,
		],
		'register' => [
			'limit' => 3,
			'window_minutes' => 60,
		],
	];

	private function __construct()
	{
	}

	public static function check(string $action, string $identifier, string $ip = ''): array
	{
		$action = self::normalizeAction($action);
		$config = self::getConfig($action);
		$state = self::getState($action, $identifier, $ip);

		return self::buildStatus($action, $config, $state);
	}

	public static function record(string $action, string $identifier, string $ip = ''): array
	{
		$action = self::normalizeAction($action);
		$config = self::getConfig($action);
		$state = self::getState($action, $identifier, $ip);
		$now = time();
		$expiresAt = max($now + $config['window_seconds'], (int) ($state['expires_at'] ?? 0));
		$attempts = (int) ($state['attempts'] ?? 0) + 1;

		set_transient(
			self::transientKey($action, $identifier, $ip),
			[
				'attempts' => $attempts,
				'expires_at' => $expiresAt,
			],
			max(1, $expiresAt - $now)
		);

		return self::buildStatus($action, $config, [
			'attempts' => $attempts,
			'expires_at' => $expiresAt,
		]);
	}

	public static function clear(string $action, string $identifier, string $ip = ''): void
	{
		delete_transient(self::transientKey(self::normalizeAction($action), $identifier, $ip));
	}

	private static function buildStatus(string $action, array $config, ?array $state): array
	{
		$attempts = (int) ($state['attempts'] ?? 0);
		$remainingSeconds = max(0, (int) ($state['expires_at'] ?? 0) - time());
		$blocked = $attempts >= $config['limit'] && $remainingSeconds > 0;
		$remainingMinutes = $blocked ? max(1, (int) ceil($remainingSeconds / 60)) : 0;

		return [
			'blocked' => $blocked,
			'action' => $action,
			'attempts' => $attempts,
			'limit' => $config['limit'],
			'remaining_seconds' => $blocked ? $remainingSeconds : 0,
			'remaining_minutes' => $remainingMinutes,
			'message' => $blocked
				? sprintf(
					_n(
						'Too many failed attempts. Please try again in %d minute.',
						'Too many failed attempts. Please try again in %d minutes.',
						$remainingMinutes,
						'authcred'
					),
					$remainingMinutes
				)
				: '',
		];
	}

	private static function getConfig(string $action): array
	{
		$settings = get_option('authcred_settings', []);
		$defaults = self::DEFAULTS[$action];

		if ('register' === $action) {
			$limit = self::sanitizePositiveInt($settings['max_register_attempts'] ?? null, 1, 100, $defaults['limit']);
			$windowMinutes = self::sanitizePositiveInt($settings['register_lockout_minutes'] ?? null, 1, 10080, $defaults['window_minutes']);
		} else {
			$limit = self::sanitizePositiveInt($settings['max_login_attempts'] ?? null, 1, 100, $defaults['limit']);
			$windowMinutes = self::sanitizePositiveInt($settings['login_lockout_minutes'] ?? null, 1, 10080, $defaults['window_minutes']);
		}

		return [
			'limit' => $limit,
			'window_seconds' => $windowMinutes * MINUTE_IN_SECONDS,
		];
	}

	private static function getState(string $action, string $identifier, string $ip): ?array
	{
		$state = get_transient(self::transientKey($action, $identifier, $ip));

		if (!is_array($state)) {
			return null;
		}

		$attempts = absint($state['attempts'] ?? 0);
		$expiresAt = (int) ($state['expires_at'] ?? 0);

		if ($attempts < 1 || $expiresAt <= time()) {
			self::clear($action, $identifier, $ip);
			return null;
		}

		return [
			'attempts' => $attempts,
			'expires_at' => $expiresAt,
		];
	}

	private static function transientKey(string $action, string $identifier, string $ip): string
	{
		$normalizedIdentifier = strtolower(trim((string) $identifier));
		$normalizedIp = sanitize_text_field($ip);

		return 'authcred_rl_' . $action . '_' . hash('sha256', $normalizedIp . ':' . $normalizedIdentifier);
	}

	private static function normalizeAction(string $action): string
	{
		return 'register' === $action ? 'register' : 'login';
	}

	private static function sanitizePositiveInt($value, int $min, int $max, int $default): int
	{
		if (!is_numeric($value)) {
			return $default;
		}

		$value = (int) $value;

		if ($value < $min) {
			return $default;
		}

		return min($max, $value);
	}
}
