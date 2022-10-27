<?php
/**
 * Plugin Name:       AuthCRED
 * Plugin URI:        https://github.com/iniznet
 * Description:       Provide authentication pages alongside mycred integration
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            niznet
 * Author URI:        https://niznet.my.id
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       authcred
 * Domain Path:       /languages
 */

 require_once dirname( __FILE__ ) . '/vendor/autoload.php';

class AuthCRED extends \WPTrait\Plugin
{
	public function __construct($slug, $args = [])
	{
		parent::__construct($slug, $args);
	}

	public function instantiate(){
		$this->Admin = new \AuthCRED\Admin($this->plugin);
		$this->AuthShortcode = new \AuthCRED\AuthShortcode($this->plugin);
		$this->CredShortcode = new \AuthCRED\CredShortcode($this->plugin);
		$this->UserAuth = new \AuthCRED\UserAuth($this->plugin);
		$this->UserFields = new \AuthCRED\Fields\UserFields($this->plugin);
		$this->MyCRED = new \AuthCRED\MyCRED($this->plugin);
	}

	public function register_activation_hook(){}

	public function register_deactivation_hook(){}

	public static function register_uninstall_hook(){}
}

new AuthCRED('authcred');