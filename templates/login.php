<?php
$gotoValue = wp_json_encode($goto);
$usesCaptcha = !empty($captcha) && !empty($captcha_sitekey) && !empty($captcha_context);
$formBinding = $usesCaptcha
	? sprintf("captchaForm('authcred_login', %s, false, %s, %s)", $gotoValue, wp_json_encode($captcha_action), wp_json_encode($captcha))
	: sprintf("form('authcred_login', %s)", $gotoValue);
?>

<form
  x-data="<?= esc_attr($formBinding) ?>"
  class="my-2 space-y-4 max-w-sm <?= esc_attr($class) ?>"
  :aria-busy="loading"
  @submit.prevent="dispatch"
>
  <?php if (!empty($authcred_error_message)) : ?>
    <div class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" role="alert">
      <strong class="text-sm font-medium"><?= __('Notice', 'authcred') ?></strong>
      <p class="mt-1 text-xs m-0"><?= esc_html($authcred_error_message) ?></p>
    </div>
  <?php endif; ?>

  <div x-show="$store.form.success && $store.form.message" class="p-2 text-green-700 border rounded border-green-900/10 bg-green-50" role="status" aria-live="polite" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>
  <div x-show="$store.form.success === false && $store.form.message" class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" role="alert" aria-live="assertive" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>

  <div>
    <label for="username" class="relative z-0 w-full">
      <input type="text" id="username" name="username" class="block w-full pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Username or email', 'authcred') ?></span>
    </label>
  </div>
  <div>
    <label for="password" class="relative z-0 w-full">
      <input type="password" id="password" name="password" class="block w-full pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Password', 'authcred') ?></span>
    </label>
  </div>

  <?php if ($usesCaptcha) : ?>
    <?php include __DIR__ . '/partials/captcha-widget.php'; ?>
    <input type="hidden" name="captcha_context" value="<?= esc_attr($captcha_context) ?>">
    <input type="hidden" name="captcha_provider" value="<?= esc_attr($captcha) ?>">
  <?php endif; ?>

  <div class="flex items-center">
    <input type="checkbox" id="remember_me" name="remember_me" value="1" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2" />
    <label for="remember_me" class="ml-2 text-sm text-gray-700"><?= __('Remember me', 'authcred') ?></label>
  </div>

  <div class="flex items-start gap-4">
    <div class="space-y-1 text-sm">
      <?php if ($register_id && $permalink = get_permalink($register_id)) : ?>
        <p class="m-0">
          <?= __("Don't have account?", 'authcred') ?>
          <a class="underline" href="<?= esc_url($permalink) ?>"><?= __('Register', 'authcred') ?></a>
        </p>
      <?php endif; ?>
      <?php if ($forgot_id && $permalink = get_permalink($forgot_id)) : ?>
        <p class="m-0">
          <a class="underline" href="<?= esc_url($permalink) ?>"><?= __('Forgot Password', 'authcred') ?></a>
        </p>
      <?php endif; ?>
    </div>

    <input type="hidden" name="nonce" value="<?= esc_attr($nonce_value) ?>">
    <button type="submit" class="ml-auto px-2 py-1 text-sm font-medium z-10 rounded shadow"><?= __('Log In', 'authcred') ?></button>
  </div>

  <?php if (!empty($social_enabled)) : ?>
    <div class="mt-4 pt-4 border-t border-gray-200">
      <?php include __DIR__ . '/partials/social-buttons.php'; ?>
    </div>
  <?php endif; ?>
</form>
