<?php

namespace AuthCRED;

use WPTrait\Model;

class Admin extends Model
{
	public $filters = [
		'show_admin_bar' => ['disableAdminbar', 20, 1],
	];

	public function __construct($plugin)
	{
		parent::__construct($plugin);
	}

	public function disableAdminbar()
	{
		if (current_user_can('edit_posts')) {
			return true;
		}

		return false;
	}
}