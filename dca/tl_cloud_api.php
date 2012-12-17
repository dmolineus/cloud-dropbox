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

$GLOBALS['TL_DCA']['tl_cloud_api']['metapalettes']['dropbox extends _base_'] = array
(
	'custom'		=> array(':hide', 'useCustomApp'),
);

$GLOBALS['TL_DCA']['tl_cloud_api']['cloudapi_metasubselectpalettes']['dropbox'] = array
(
	'enabled' => array('oAuthClass', 'accessToken'),
	'useCustomApp' => array('appKey','appSecret,root'),
);
