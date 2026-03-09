<div class="my-2 max-w-sm <?= esc_attr($class) ?>">
	<p class="m-0 text-sm font-medium"><?= __('You need to be logged in to view your profile.', 'authcred') ?></p>
	<?php if ($login_id && $permalink = get_permalink($login_id)) : ?>
		<p class="mt-2 mb-0 text-sm">
			<a class="underline" href="<?= esc_url($permalink) ?>"><?= __('Log In', 'authcred') ?></a>
		</p>
	<?php endif; ?>
</div>
