<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package Cloud-dropbox
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'Netzmacht',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'Netzmacht\Cloud\Dropbox\DropboxApi'  => 'system/modules/cloud-dropbox/classes/DropboxApi.php',
	'Netzmacht\Cloud\Dropbox\DropboxNode' => 'system/modules/cloud-dropbox/classes/DropboxNode.php',

	// Vendor
	'APITest'                             => 'system/modules/cloud-dropbox/vendor/dropbox-php/tests/APITest.php',
	'Dropbox'                             => 'system/modules/cloud-dropbox/vendor/Dropbox/OAuth/Consumer/Dropbox.php',
));
