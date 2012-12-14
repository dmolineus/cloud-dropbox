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
 **/

$GLOBALS['TL_DCA']['tl_cloud_api']['palettes']['dropbox'] = '{connection_legend},title,enabled;{folder_legend:hide},mountedFolders;{custom_legend:hide},useCustomApp';
$GLOBALS['TL_DCA']['tl_cloud_api']['customSubPalettes']['dropbox'] = array
(
	'enabled' => 'oAuthClass,accessToken',
	'useCustomApp' => 'appKey,appSecret,root',
);
