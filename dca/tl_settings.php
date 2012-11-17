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
 * define palettes and selectors
 * 
 * use cloudapi_hook for inserting fields
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'enableDropbox';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'dropboxCustomApp';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
	'cloudapi_hook', 
	'enableDropbox,cloudapi_hook', 
	$GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);	


/**
 * defining sub palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['enableDropbox'] = 'dropboxOauth,dropboxAccessToken,dropboxCustomApp';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['dropboxCustomApp'] = 'dropboxCustomerKey,dropboxCustomerSecret,dropboxRoot';


/**
 * defining fields
 */
$GLOBALS['TL_DCA']['tl_settings']['fields']['enableDropbox'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['enableDropbox'],
	'inputType'				=> 'checkbox',
	'eval'					=> array('submitOnChange'=>true)
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxCustomApp'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxCustomApp'],
	'inputType'				=> 'checkbox',
	'eval'					=> array('submitOnChange'=>true, 'tl_class' => 'clr')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxCustomerKey'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxCustomerKey'],
	'inputType'				=> 'text',
	'eval'					=> array('mandatory'=>true, 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxCustomerSecret'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxCustomerSecret'],
	'inputType'				=> 'text',	
	'eval'					=> array('mandatory'=>true, 'nospace'=>'true', 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxRoot'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxRoot'],
	'inputType'				=> 'select',
	'options'				 => array('dropbox', 'sandbox'),
	'eval'					=> array('mandatory'=>true, 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxOauth'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxOauth'],
	'inputType'				=> 'select',
	'options'				 => array('Curl', 'PEAR', 'PHP' /*, 'Wordpress', 'Zend'*/),	
	'eval'					=> array('mandatory'=>true, 'nospace'=>'true', 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxAccessToken'] = array
(
	'label'					=> &$GLOBALS['TL_LANG']['tl_settings']['dropboxAccessToken'],
	'inputType'				=> 'accesToken',
	'eval'					=> array('nospace'=>'true', 'cloudApi' => 'dropbox', 'tl_class' => 'w50')
);
