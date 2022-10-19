<form
  x-data="form('authcred_reset_password', false, true)"
  class="my-2 space-y-4 max-w-sm"
  @submit.prevent="dispatch"
  x-show="$store.form.step === 1"
  style="display: none"
  x-cloak
>
  <div x-show="$store.form.success && $store.form.message" class="p-2 text-green-700 border rounded border-green-900/10 bg-green-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>
  <div x-show="$store.form.success === false && $store.form.message" class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>

  <div>
    <label for="username" class="relative z-0 w-full">
      <input type="text" id="username" name="username" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Username or email', 'authcred') ?></span>
    </label>
  </div>

  <div class="flex items-center justify-between">
    <?php if ($register_id && $permalink = get_permalink($register_id)) : ?>
      <p class="text-sm">
        <?= __("Don't have account?", 'authcred') ?>
        <a class="underline" href="<?= $permalink ?>"><?= __('Register', 'authcred') ?></a>
      </p>
    <?php endif; ?>

    <input type="hidden" name="nonce" value="<?= wp_create_nonce('authcred_reset_password') ?>">
    <button type="submit" class="px-2 py-1 text-sm font-medium z-10 rounded shadow dark:text-white dark:border-white"><?= __('Request', 'authcred') ?></button>
  </div>
</form>

<form
  x-data="form('authcred_confirm_reset_password', false, true)"
  class="my-2 space-y-4 max-w-sm"
  @submit.prevent="dispatch"
  style="display: none"
  x-show="$store.form.step === 2"
  x-cloak
>
  <div x-show="$store.form.success && $store.form.message" class="p-2 text-green-700 border rounded border-green-900/10 bg-green-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>
  <div x-show="$store.form.success === false && $store.form.message" class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>

  <div>
    <label for="reset" class="relative z-0 w-full">
      <input type="text" id="reset" name="reset" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Reset Key', 'authcred') ?></span>
    </label>
  </div>

  <div class="flex items-center justify-between">
    <?php if ($register_id && $permalink = get_permalink($register_id)) : ?>
      <p class="text-sm">
        <?= __("Don't have account?", 'authcred') ?>
        <a class="underline" href="<?= $permalink ?>"><?= __('Register', 'authcred') ?></a>
      </p>
    <?php endif; ?>

    <input type="hidden" name="nonce" value="<?= wp_create_nonce('authcred_confirm_reset_password') ?>">
    <button type="submit" class="px-2 py-1 text-sm font-medium z-10 rounded shadow dark:text-white dark:border-white"><?= __('Verify', 'authcred') ?></button>
  </div>
</form>

<form
  x-data="form('authcred_reset_new_password', <?= $goto ?>)"
  class="my-2 space-y-4 max-w-sm"
  @submit.prevent="dispatch"
  style="display: none"
  x-show="$store.form.step === 3"
  x-cloak
>
  <div x-show="$store.form.success && $store.form.message" class="p-2 text-green-700 border rounded border-green-900/10 bg-green-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>
  <div x-show="$store.form.success === false && $store.form.message" class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" x-cloak>
    <strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
    <p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
  </div>

  <div>
    <label for="password" class="relative z-0 w-full">
      <input type="password" id="password" name="password" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Password', 'authcred') ?></span>
    </label>
  </div>
  <div>
    <label for="confirm_password" class="relative z-0 w-full">
      <input type="password" id="confirm_password" name="confirm_password" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Confirm Password', 'authcred') ?></span>
    </label>
  </div>

  <div class="flex items-center justify-between">
    <?php if ($register_id && $permalink = get_permalink($register_id)) : ?>
      <p class="text-sm">
        <?= __("Don't have account?", 'authcred') ?>
        <a class="underline" href="<?= $permalink ?>"><?= __('Register', 'authcred') ?></a>
      </p>
    <?php endif; ?>

    <input type="hidden" name="nonce" value="<?= wp_create_nonce('authcred_reset_new_password') ?>">
    <input type="hidden" name="reset" x-model="fill.reset">
    <input type="hidden" name="username" x-model="fill.username">
    <button type="submit" class="px-2 py-1 text-sm font-medium z-10 rounded shadow dark:text-white dark:border-white"><?= __('Update', 'authcred') ?></button>
  </div>
</form>