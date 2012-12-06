<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package   cloud-api 
 * @author    David Molineus <http://www.netzmacht.de>
 * @license   GNU/LGPL 
 * @copyright Copyright 2012 David Molineus netzmacht creative 
 **/

namespace Netzmacht\Cloud\Dropbox;
use Backend;

/**
 * Stores methods to handle the ajax request for the cloud file tree
 */
class DropboxInstaller extends Backend
{
	
	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		$this->import('BackendUser', 'User');
		parent::__construct();

		$this->User->authenticate();
	}
	
	
	/**
	 * install dropbox api
	 */
	public function run()
	{
		if($this->User->isAdmin)
		{
			Api\CloudApiManager::installApi('dropbox', 'DropboxApi', array('sync', 'request'));	
		}
	}
}

// run the installer
$objInstaller = new DropboxInstaller();
$objInstaller->run();
