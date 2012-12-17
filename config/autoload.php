<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package   cloud-dropbox 
 * @author    David Molineus <http://www.netzmacht.de>
 * @license   GNU/LGPL 
 * @copyright Copyright 2012 David Molineus netzmacht creative 
 *  
 **/


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
	'Netzmacht\Cloud\Dropbox\Model\DropboxNodeModel' => 'system/modules/cloud-dropbox/models/DropboxNodeModel.php',

));
