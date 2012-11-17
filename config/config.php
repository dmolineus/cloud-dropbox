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
 * register dropbox api. tl config are loaded by cloud-api/config.php
 */
Netzmacht\Cloud\Api\CloudApiManager::registerApi('dropbox', array(
    'name' => 'Netzmacht\Cloud\Dropbox\DropboxApi',
    'enabled' => &$GLOBALS['TL_CONFIG']['enableDropbox']
));


/**
 * Set default dropbox app. It is possible to change this in the settings.
 * Changes are usefull, for example, if you have to use dropbox in a sandbox
 */
$GLOBALS['TL_CONFIG']['dropboxRoot'] = 'dropbox';
$GLOBALS['TL_CONFIG']['dropboxCustomerKey'] = 'asc7atgjdcbjqgk';
$GLOBALS['TL_CONFIG']['dropboxCustomerSecret'] = 'omqgys456jno5ns';


/**
 * register dropbox cache file to affected dirs for purging them
 */
$GLOBALS['TL_PURGE']['folders']['cloud-api']['affected'][] = 'system/cache/cloud-api/dropbox';
