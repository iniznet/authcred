<?php

namespace AuthCRED;

use WPTrait\Model;

class Admin extends Model
{
	public $actions = [
		'setup_theme' => ['disallowAdminPages', 1],
		'admin_enqueue_scripts' => ['enqueueManagedHostingAssets', 10, 1],
		'admin_footer-plugins.php' => ['renderManagedHostingModal', 10],
	];

	public $filters = [
		'show_admin_bar' => ['disableAdminbar', 20],
	];

	public function __construct($plugin)
	{
		parent::__construct($plugin);

		add_filter(
			'plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/authcred.php'),
			[$this, 'addManagedHostingActionLink']
		);
	}

	public function addManagedHostingActionLink($actions)
	{
		$hostingLink = sprintf(
			'<a href="#" data-authcred-managed-hosting-open="1">%s</a>',
			esc_html__('Managed Hosting', 'authcred')
		);

		$actions['authcred_managed_hosting'] = $hostingLink;

		return $actions;
	}

	public function enqueueManagedHostingAssets($hookSuffix)
	{
		if ('plugins.php' !== $hookSuffix) {
			return;
		}

		$scriptPath = dirname(__DIR__) . '/assets/admin/managed-hosting-modal.js';
		$stylePath = dirname(__DIR__) . '/assets/admin/managed-hosting-modal.css';

		wp_enqueue_script(
			'authcred-managed-hosting-modal',
			plugins_url('assets/admin/managed-hosting-modal.js', dirname(__DIR__) . '/authcred.php'),
			[],
			file_exists($scriptPath) ? (string) filemtime($scriptPath) : false,
			true
		);

		wp_enqueue_style(
			'authcred-managed-hosting-modal',
			plugins_url('assets/admin/managed-hosting-modal.css', dirname(__DIR__) . '/authcred.php'),
			[],
			file_exists($stylePath) ? (string) filemtime($stylePath) : false
		);
	}

	public function renderManagedHostingModal()
	{
		?>
		<div id="authcred-managed-hosting-modal" class="authcred-managed-hosting-modal" hidden>
			<div class="authcred-managed-hosting-modal__backdrop" data-authcred-managed-hosting-close="1"></div>
			<div class="authcred-managed-hosting-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="authcred-managed-hosting-title" aria-describedby="authcred-managed-hosting-description" tabindex="-1">
				<button type="button" class="authcred-managed-hosting-modal__close" data-authcred-managed-hosting-close="1" aria-label="<?php echo esc_attr__('Close managed hosting modal', 'authcred'); ?>">&times;</button>
				<p class="authcred-managed-hosting-modal__eyebrow"><?php echo esc_html__('Managed by niznet', 'authcred'); ?></p>
				<h2 id="authcred-managed-hosting-title"><?php echo esc_html__('Private Managed Hosting for WordPress and PHP', 'authcred'); ?></h2>
				<p id="authcred-managed-hosting-description"><?php echo esc_html__('This is an invite-only managed hosting environment for serious WordPress and PHP projects. I handle the technical overhead, performance tuning, and day-to-day server management so you can stay focused on the project itself.', 'authcred'); ?></p>
				<ul class="authcred-managed-hosting-modal__list">
					<li><?php echo esc_html__('LiteSpeed Web Server with LSCache for WordPress', 'authcred'); ?></li>
					<li><?php echo esc_html__('NVMe storage with modern PHP versions and OPcache', 'authcred'); ?></li>
					<li><?php echo esc_html__('Redis object caching and automated off-server backups', 'authcred'); ?></li>
					<li><?php echo esc_html__('Personal optimization, monitoring, and server management', 'authcred'); ?></li>
				</ul>
				<div class="authcred-managed-hosting-modal__actions">
					<a class="button button-primary" href="https://niznet.my.id/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('View Hosting Details', 'authcred'); ?></a>
					<a class="button" href="mailto:niznet@jasedi.com?subject=Managed%20Hosting%20Request"><?php echo esc_html__('Email niznet', 'authcred'); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	public function disableAdminbar()
	{
		if (current_user_can('edit_posts')) {
			return true;
		}

		return false;
	}

	public function disallowAdminPages()
	{
		global $wp_query;

		$settings = $this->option($this->plugin->prefix . '_settings')->get();
		$disallow = isset($settings['disallow_admin']) ? $settings['disallow_admin'] : 0;

		if (wp_doing_ajax() || wp_doing_cron()) {
			return;
		}

		if (!$disallow || !is_admin() || current_user_can('edit_posts')) {
			return;
		}

		$wp_query->set_404();
		status_header(404);
		nocache_headers();

		wp_safe_redirect(wp_get_referer() ?: home_url());
		exit;
	}
}
