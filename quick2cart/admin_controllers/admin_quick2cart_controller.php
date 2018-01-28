<?php
/*
 * This file is part of the JReviews Quick2Cart Add-on
 *
 * Copyright (C) ClickFWD LLC 2010-2018 <sales@jreviews.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

defined( 'MVC_FRAMEWORK') or die;

/**
 * The class name matches the file name with the first letter capitalized
 */

class AdminQuick2cartController extends MyController
{
	var $uses = ['acl'];

	var $helpers = ['admin/admin_settings'];

	var $components = ['config'];

 	function beforeFilter()
	{
		parent::beforeFilter();
	}

	function index()
	{
		// We use an existing method in the Criteria model to get the Listing Types

		$listingTypes = $this->listing_type->toList();

		// Send the $listingTypes variable to the View

		$this->set([
			'listingTypes'=>$listingTypes,
			'accessGroups'=>$this->Acl->getAccessGroupList()
		]);

		return $this->render('quick2cart','index');
	}

	function _save()
	{
		$this->config->store($this->data['Config']);
	}

}