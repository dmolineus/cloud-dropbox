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
 * inject dropbox filemounts for palettes extend and custom
 */
$GLOBALS['TL_DCA']['tl_user']['palettes']['extend'] = str_replace(
	'cloudapi_hook', 'dropboxFilemounts,cloudapi_hook',
	$GLOBALS['TL_DCA']['tl_user']['palettes']['extend']
);

$GLOBALS['TL_DCA']['tl_user']['palettes']['custom'] = str_replace(
	'cloudapi_hook', 'dropboxFilemounts,cloudapi_hook',
	$GLOBALS['TL_DCA']['tl_user']['palettes']['custom']
);

$GLOBALS['TL_DCA']['tl_user']['fields']['dropboxFilemounts'] = array
(
	'label'                   => array (
		$GLOBALS['TL_LANG']['MOD']['cloudapi_dropbox'][0],
		$GLOBALS['TL_LANG']['tl_user']['filemounts'][1]
	),
	'exclude'                 => true,
	'inputType'               => 'cloudFileTree',
	'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox', 'cloudApi' => 'dropbox'),
	'sql'                     => "blob NULL"
);