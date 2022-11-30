<form
  x-data="form('authcred_change_password', <?= $goto ?>)"
  class="my-2 space-y-4 max-w-sm <?= $class ?>"
  @submit.prevent="dispatch"
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
      <input type="password" id="password" name="password" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer dark:text-white" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Old Password', 'authcred') ?></span>
    </label>
  </div>
  <div>
    <label for="new_password" class="relative z-0 w-full">
      <input type="password" id="new_password" name="new_password" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer dark:text-white" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('New Password', 'authcred') ?></span>
    </label>
  </div>
  <div>
    <label for="confirm_new_password" class="relative z-0 w-full">
      <input type="password" id="confirm_new_password" name="confirm_new_password" class="block pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer dark:text-white" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('New Password', 'authcred') ?></span>
    </label>
  </div>

  <div class="flex items-start justify-between">
    <input type="hidden" name="nonce" value="<?= wp_create_nonce('authcred_change_password') ?>">
    <button type="submit" class="px-2 py-1 text-sm font-medium z-10 rounded shadow dark:text-white dark:border-white"><?= __('Update', 'authcred') ?></button>
  </div>
</form>