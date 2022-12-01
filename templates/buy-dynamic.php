<form
  x-data="{rate:<?= $rate ?>, amount: null}"
  class="p-2 space-y-4 max-w-sm <?= esc_html($class) ?>"
>
  <div class="p-2 block">
    <strong class="truncate text-lg font-bold"><?= esc_html($title); ?></strong>
  </div>

  <div class="p-2 block text-sm"><?= esc_html($description); ?></div>

  <div>
    <label for="amount" class="relative z-0 w-full">
      <input x-model="amount" type="number" id="amount" name="amount" class="block w-full pt-4 pb-1 px-2 border-gray-200 text-sm rounded bg-transparent border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer dark:text-white" placeholder=" " required />
      <span class="absolute text-xs duration-300 transform -translate-y-2.5 top-3 peer-focus:left-2 left-2 peer-focus:text-blue-600 peer-placeholder-shown:translate-y-0 peer-focus:text-xs peer-placeholder-shown:text-sm peer-focus:-translate-y-2.5"><?= __('Amount', 'authcred') ?></span>
    </label>
  </div>

  <div class="flex items-center justify-between">
    <input type="hidden" name="token" value="<?= esc_html($nonce) ?>">
    <input type="hidden" name="ctype" value="<?= esc_html($ctype) ?>">
    <input type="hidden" name="mycred_buy" value="<?= esc_html($gateway) ?>">

    <?php if ($e_rate) : ?>
      <input x-ref="e_rate" type="hidden" name="e_random" value="<?= esc_html(mycred_encode_values($e_rate)) ?>">
    <?php endif; ?>

    <?php if ($preview) : ?>
      <small x-text="<?= $e_rate ? '$refs.e_rate.value' : 'rate' ?> + ' x ' + (amount || 0) + ' = ' + Math.round((<?= $e_rate ? '$refs.e_rate.value' : 'rate' ?> * amount + Number.EPSILON) * 100) / 100 + ' <?= $currency ?>'" class="px-2 font-bold"></small>
    <?php endif; ?>
    <button type="submit" class="flex p-2 justify-center items-center uppercase text-xs"><?= esc_html($label); ?></button>
  </div>
</form>
