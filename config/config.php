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
Netzmacht\Cloud\Api\CloudApiManager::registerApi('dropbox', 'Netzmacht\Cloud\Dropbox\DropboxApi');


/**
 * Set default dropbox app. It is possible to change this in the settings.
 * Changes are usefull, for example, if you have to use dropbox in a sandbox
 */
$GLOBALS['TL_CONFIG']['dropboxRoot'] = 'dropbox';
$GLOBALS['TL_CONFIG']['dropboxAppKey'] = 'YXNjN2F0Z2pkY2JqcWdr';
$GLOBALS['TL_CONFIG']['dropboxAppSecret'] = 'b21xZ3lzNDU2am5vNW5z';


/**
 * register dropbox cache file to affected dirs for purging them
 */
$GLOBALS['TL_PURGE']['folders']['cloud-api']['affected'][] = 'system/cache/cloud-api/dropbox';


/**
 * define hooks
*/
$GLOBALS['TL_PERMISSIONS'][] = 'dropboxFilemounts';
