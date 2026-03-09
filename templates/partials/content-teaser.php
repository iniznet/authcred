<div class="authcred-teaser relative overflow-hidden my-6">
    <div class="authcred-teaser-content text-gray-500 opacity-80 select-none pointer-events-none" aria-hidden="true" style="-webkit-user-select: none; user-select: none;">
        <?= wp_kses_post($teaser_text ?? '') ?>
    </div>
    <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-white to-transparent pointer-events-none"></div>

    <div class="authcred-teaser-cta relative z-10 p-6 mx-auto -mt-16 text-center border rounded-lg border-gray-200 bg-gray-50 shadow-sm max-w-lg">
        <?php if (!empty($is_logged_in)): ?>
            <?php if (empty($has_purchased)): ?>
                <h3 class="text-lg font-semibold mb-2 text-gray-900"><?= __('Unlock this content', 'authcred') ?></h3>
                <p class="text-sm mb-4 text-gray-600">
                    <?= __('You need to purchase this content to read the rest.', 'authcred') ?>
                </p>
                <div class="inline-flex justify-center mt-2 w-full">
                    <?= do_shortcode($purchase_shortcode ?? '') ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h3 class="text-lg font-semibold mb-2 text-gray-900"><?= __('Member Only Content', 'authcred') ?></h3>
            <p class="text-sm mb-4 text-gray-600">
                <?= __('Please log in to purchase and unlock this content.', 'authcred') ?>
            </p>
            <a href="<?= esc_url($login_page_url ?? wp_login_url(get_permalink($post_id ?? 0))) ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                <?= __('Log In / Register', 'authcred') ?>
            </a>
        <?php endif; ?>
    </div>
</div>
