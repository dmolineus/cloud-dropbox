<?php 

/**
 * Cloud Api
 * Copyright (C) 2012 David Molineus
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * @copyright  David Molineus 2012
 * @author     David Molineus <mail@netzmacht.de>
 * @package    CloudApi
 * @license    GNU/LGPL
 */


$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'enableDropbox';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] = str_replace(
    '{cloudapi_legend}', 
    '{cloudapi_legend},enableDropbox', 
    $GLOBALS['TL_DCA']['tl_settings']['palettes']['default']
);    

$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['enableDropbox'] = 'dropboxCustomerKey,dropboxCustomerSecret,dropboxRoot,dropboxOauth,dropboxAccessToken';


$GLOBALS['TL_DCA']['tl_settings']['fields']['enableDropbox'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['enableDropbox'],
    'inputType'               => 'checkbox',
    'eval'                    => array('submitOnChange'=>true)
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxCustomerKey'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dropboxCustomerKey'],
    'inputType'               => 'text',
    'eval'                    => array('mandatory'=>true, 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxCustomerSecret'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dropboxCustomerSecret'],
    'inputType'               => 'text',    
    'eval'                    => array('mandatory'=>true, 'nospace'=>'true', 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxRoot'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dropboxRoot'],
    'inputType'               => 'select',
    'options'                 => array('dropbox', 'sandbox'),    
    'eval'                    => array('mandatory'=>true, 'nospace'=>'true', 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxOauth'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dropboxOauth'],
    'inputType'               => 'select',
    'options'                 => array('Curl', 'PEAR', 'PHP' /*, 'Wordpress', 'Zend'*/),  
    'eval'                    => array('mandatory'=>true, 'nospace'=>'true', 'tl_class'=>'w50')
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['dropboxAccessToken'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_settings']['dropboxAccessToken'],
    'inputType'               => 'accesToken',
    'eval'                    => array('nospace'=>'true', 'cloudApi' => 'dropbox', 'tl_class' => 'w50')
);
