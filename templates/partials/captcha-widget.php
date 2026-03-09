<?php

if (empty($captcha) || empty($captcha_sitekey) || empty($captcha_action)) {
	return;
}

$actionKey = sanitize_key($captcha_action);
$containerId = 'authcred-captcha-' . $actionKey;
$tokenFieldId = 'captcha_token_' . $actionKey;
?>
<input type="hidden" name="captcha_token" id="<?= esc_attr($tokenFieldId) ?>" value="">

<script>
	window.authcredCaptcha = window.authcredCaptcha || {};
	window.authcredCaptcha.errors = Object.assign(
		window.authcredCaptcha.errors || {},
		{
			load: <?= wp_json_encode(__('Security verification could not load. Please refresh the page.', 'authcred')) ?>,
			failed: <?= wp_json_encode(__('Security check failed. Please try again.', 'authcred')) ?>,
			incomplete: <?= wp_json_encode(__('Security check failed. Please complete the captcha and try again.', 'authcred')) ?>
		}
	);
</script>

<?php if ('recaptcha' === $captcha) : ?>
	<?php if ('v3' !== $captcha_version) : ?>
		<div id="<?= esc_attr($containerId) ?>"></div>
	<?php endif; ?>

	<script>
		window.authcredCaptcha = window.authcredCaptcha || {};
		window.authcredCaptcha[<?= wp_json_encode($captcha_action) ?>] = Object.assign(
			window.authcredCaptcha[<?= wp_json_encode($captcha_action) ?>] || {},
			{
				provider: 'recaptcha',
				siteKey: <?= wp_json_encode($captcha_sitekey) ?>,
				version: <?= wp_json_encode($captcha_version) ?>,
				action: <?= wp_json_encode($captcha_action) ?>,
				containerId: <?= wp_json_encode($containerId) ?>,
				tokenFieldId: <?= wp_json_encode($tokenFieldId) ?>
			}
		);

		if (!window.authcredSetCaptchaToken) {
			window.authcredSetCaptchaToken = function(action, token) {
				const config = window.authcredCaptcha && window.authcredCaptcha[action];

				if (!config) {
					return;
				}

				const tokenField = document.getElementById(config.tokenFieldId);

				if (tokenField) {
					tokenField.value = token || '';
				}
			};
		}

		if (!window.authcredRegisterRecaptchaWidget) {
			window.authcredRegisterRecaptchaWidget = function(action) {
				const config = window.authcredCaptcha && window.authcredCaptcha[action];

				if (!config || typeof window.grecaptcha === 'undefined' || config.version === 'v3') {
					return;
				}

				if (typeof config.widgetId !== 'undefined') {
					return;
				}

				const container = document.getElementById(config.containerId);

				if (!container) {
					return;
				}

				config.widgetId = window.grecaptcha.render(container, {
					sitekey: config.siteKey,
					size: config.version === 'v2_invisible' ? 'invisible' : 'normal',
					badge: config.version === 'v2_invisible' ? 'bottomright' : undefined,
					callback: function(token) {
						window.authcredSetCaptchaToken(action, token);
					},
					'expired-callback': function() {
						window.authcredSetCaptchaToken(action, '');
					},
					'error-callback': function() {
						window.authcredSetCaptchaToken(action, '');
					}
				});
			};

			window.authcredOnloadRecaptcha = function() {
				Object.keys(window.authcredCaptcha || {}).forEach(function(action) {
					if (action === 'errors') {
						return;
					}

					const config = window.authcredCaptcha[action];

					if (!config || typeof config !== 'object' || config.provider !== 'recaptcha') {
						return;
					}

					window.authcredRegisterRecaptchaWidget(action);
				});
			};
		}

		if (window.grecaptcha && <?= wp_json_encode('v3' !== $captcha_version) ?>) {
			window.authcredRegisterRecaptchaWidget(<?= wp_json_encode($captcha_action) ?>);
		}
	</script>

	<?php if ('v3' === $captcha_version) : ?>
		<?php wp_enqueue_script('authcred-google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($captcha_sitekey), [], null, true); ?>
	<?php else : ?>
		<?php wp_enqueue_script('authcred-google-recaptcha', 'https://www.google.com/recaptcha/api.js?onload=authcredOnloadRecaptcha&render=explicit', [], null, true); ?>
	<?php endif; ?>
<?php endif; ?>

<?php if ('turnstile' === $captcha) : ?>
	<?php
	$turnstileCallback = 'authcredTurnstileCallback_' . $actionKey;
	$turnstileExpiredCallback = 'authcredTurnstileExpiredCallback_' . $actionKey;
	$turnstileErrorCallback = 'authcredTurnstileErrorCallback_' . $actionKey;
	?>
	<div
		id="<?= esc_attr($containerId) ?>"
		class="cf-turnstile"
		data-sitekey="<?= esc_attr($captcha_sitekey) ?>"
		data-action="<?= esc_attr($captcha_action) ?>"
		data-callback="<?= esc_attr($turnstileCallback) ?>"
		data-expired-callback="<?= esc_attr($turnstileExpiredCallback) ?>"
		data-error-callback="<?= esc_attr($turnstileErrorCallback) ?>"
		data-response-field="false"
	></div>

	<script>
		window.authcredCaptcha = window.authcredCaptcha || {};
		window.authcredCaptcha[<?= wp_json_encode($captcha_action) ?>] = Object.assign(
			window.authcredCaptcha[<?= wp_json_encode($captcha_action) ?>] || {},
			{
				provider: 'turnstile',
				siteKey: <?= wp_json_encode($captcha_sitekey) ?>,
				action: <?= wp_json_encode($captcha_action) ?>,
				containerId: <?= wp_json_encode($containerId) ?>,
				tokenFieldId: <?= wp_json_encode($tokenFieldId) ?>
			}
		);

		if (!window.authcredSetTurnstileToken) {
			window.authcredSetTurnstileToken = function(action, token) {
				const config = window.authcredCaptcha && window.authcredCaptcha[action];
				const tokenField = config ? document.getElementById(config.tokenFieldId) : null;

				if (tokenField) {
					tokenField.value = token || '';
				}
			};
		}

		window[<?= wp_json_encode($turnstileCallback) ?>] = function(token) {
			window.authcredSetTurnstileToken(<?= wp_json_encode($captcha_action) ?>, token);
		};

		window[<?= wp_json_encode($turnstileExpiredCallback) ?>] = function() {
			window.authcredSetTurnstileToken(<?= wp_json_encode($captcha_action) ?>, '');
		};

		window[<?= wp_json_encode($turnstileErrorCallback) ?>] = function() {
			window.authcredSetTurnstileToken(<?= wp_json_encode($captcha_action) ?>, '');
		};
	</script>

	<?php wp_enqueue_script('authcred-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true); ?>
<?php endif; ?>
