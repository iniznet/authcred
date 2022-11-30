<?php

namespace AuthCRED;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class Updater
{
	public function __construct()
	{
		$updater = PucFactory::buildUpdateChecker(
			'https://github.com/iniznet/authcred',
			plugin_dir_path(__DIR__) . 'authcred.php',
			'authcred'
		);

		$updater->setBranch('master');
		$updater->getVcsApi()->enableReleaseAssets();
	}
}