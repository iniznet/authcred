<?php

$currentUser = wp_get_current_user();
$displayName = $currentUser->display_name ?: $currentUser->user_login;
$email = $currentUser->user_email;
$description = get_user_meta($currentUser->ID, 'description', true);
$avatarAlt = sprintf(__('%s avatar', 'authcred'), $displayName);
$avatar = get_avatar($currentUser->ID, 64, '', $avatarAlt, [
	'class' => 'w-16 h-16 rounded-full',
]);
?>

<form
	x-data="form('authcred_update_profile', false)"
	class="my-2 space-y-4 max-w-sm <?= esc_attr($class) ?>"
	:aria-busy="loading"
	@submit.prevent="dispatch"
>
	<div x-show="$store.form.success && $store.form.message" class="p-2 text-green-700 border rounded border-green-900/10 bg-green-50" role="status" aria-live="polite" x-cloak>
		<strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
		<p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
	</div>
	<div x-show="$store.form.success === false && $store.form.message" class="p-2 text-red-700 border rounded border-red-900/10 bg-red-50" role="alert" aria-live="assertive" x-cloak>
		<strong x-text="$store.form.message.title" class="text-sm font-medium"></strong>
		<p x-text="$store.form.message.body" class="mt-1 text-xs m-0" x-show="$store.form.message.body"></p>
	</div>

	<div class="flex items-center gap-3">
		<div class="shrink-0"
			 x-data="avatarUpload({
			 	ajaxUrl: '<?= esc_js($ajax_url ?? '') ?>',
			 	uploadNonce: '<?= esc_js($upload_nonce ?? '') ?>',
			 	removeNonce: '<?= esc_js($remove_nonce ?? '') ?>',
			 	initialAvatarUrl: '<?= esc_js($avatar_url ?? '') ?>',
			 	gravatarUrl: '<?= esc_js($gravatar_url ?? '') ?>',
			 	hasLocalAvatar: <?= !empty($has_local_avatar) ? 'true' : 'false' ?>,
			 	maxSizeKb: <?= (int)($max_size_kb ?? 2048) ?>
			 })"
		>
			<div class="relative group">
				<img :src="currentAvatarUrl" class="w-16 h-16 rounded-full object-cover border border-gray-200" alt="<?= esc_attr($avatarAlt) ?>">

				<label class="absolute inset-0 flex items-center justify-center bg-black/50 text-white rounded-full opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity focus-within:opacity-100">
					<span class="sr-only"><?= __('Change Avatar', 'authcred') ?></span>
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
					<input type="file" class="sr-only" accept="image/jpeg,image/png,image/gif,image/webp" @change="uploadAvatar" :disabled="loading" aria-label="<?= esc_attr__('Upload new avatar', 'authcred') ?>" />
				</label>

				<div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-white/70 rounded-full" x-cloak>
					<svg class="animate-spin w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12v8m8-8a8 8 0 01-8 8"></path></svg>
				</div>
			</div>

			<div class="mt-1 flex flex-col items-center justify-center text-[10px]">
				<button type="button" x-show="hasLocal" @click="removeAvatar" class="text-red-600 hover:underline disabled:opacity-50" :disabled="loading" x-cloak><?= __('Remove', 'authcred') ?></button>
				<span x-show="error" x-text="error" class="text-red-600 block mt-1 text-center" role="alert" aria-live="assertive" x-cloak></span>
			</div>
		</div>
		<div class="min-w-0">
			<p class="m-0 text-xs uppercase tracking-wide text-gray-500"><?= __('Your profile', 'authcred') ?></p>
			<h2 class="m-0 text-base font-medium truncate"><?= esc_html($displayName) ?></h2>
			<p class="m-0 text-xs text-gray-500 truncate"><?= esc_html($email) ?></p>
		</div>
	</div>

	<div>
		<label for="display_name" class="relative z-0 w-full">
			<input type="text" id="display_name" name="display_name" value="<?= esc_attr($displayName) ?>" class="block w-full pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required aria-required="true" autocomplete="nickname" />
			<span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Display Name', 'authcred') ?></span>
		</label>
	</div>
	<div>
		<label for="email" class="relative z-0 w-full">
			<input type="email" id="email" name="email" value="<?= esc_attr($email) ?>" class="block w-full pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer" placeholder=" " required aria-required="true" autocomplete="email" />
			<span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Email', 'authcred') ?></span>
		</label>
	</div>
	<div>
		<label for="description" class="block mb-1 text-sm font-medium"><?= __('Description', 'authcred') ?></label>
		<textarea id="description" name="description" rows="4" class="block w-full px-2 py-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600" placeholder="<?= esc_attr__('Tell readers a little about yourself', 'authcred') ?>" autocomplete="off"><?= esc_textarea($description) ?></textarea>
	</div>

	<div class="flex items-start justify-between gap-4">
		<div>
			<?php if ($change_id && $permalink = get_permalink($change_id)) : ?>
				<a class="text-sm underline" href="<?= esc_url($permalink) ?>"><?= __('Change Password', 'authcred') ?></a>
			<?php endif; ?>
		</div>

		<input type="hidden" name="nonce" value="<?= esc_attr(wp_create_nonce('authcred_update_profile')) ?>">
		<button type="submit" class="px-2 py-1 text-sm font-medium z-10 rounded shadow"><?= __('Update Profile', 'authcred') ?></button>
	</div>
</form>
