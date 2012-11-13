<?php

// namespace settings
namespace Netzmacht\Cloud\Dropbox;
use Netzmacht\Cloud\Api\CloudApi;

// load dropbox-api autoload file
require_once TL_ROOT . '/system/modules/cloud-dropbox/vendor/Dropbox/autoload.php';

/**
 * Dropbox Api compatible with the cloud API 
 * 
 * @author David Molineus <mail@netzmacht.de>
 * @copyright David Molineus netzmacht creative
 * @link http://www.netzmacht.de
 * @license GNU/LGPL
 */
class DropboxApi extends CloudApi
{
    /**
     * dropbox const for 
     * 
     * @var string
     */
    const DROPBOX = 'dropbox';
    
    /**
     * dropbox const for 
     * 
     * @var string
     */
    const SANDBOX = 'sandbox';
    
    /**
     * reference to config array
     * 
     * @var array
     */
	protected $arrConfig;
    
    /**
     * vendor/dropbox API
     * 
     * @var Dropbox_API
     */
	protected $objConnection;
    
    /**
     * oauth for dropbox
     * 
     * @var OAuth
     */
    protected $objOauth;


    /**
     * constructor 
     * 
     * @return void
     */
	public function __construct()
	{
		$this->arrConfig = &$GLOBALS['TL_CONFIG'];
		
		$strOauth = $this->arrConfig['dropboxOauth'];
        if($strOauth == '') {
            $strOauth = 'PHP';
        }
        $strOauthClass = '\Dropbox_OAuth_' . $strOauth;

        $this->objOauth = new $strOauthClass($this->arrConfig['dropboxCustomerKey'], $this->arrConfig['dropboxCustomerSecret']);

    }
    

    /**
     * authenticate and create drobox api
     * 
     * @throws Exception if no valid token has found
     * @return bool
     */
	public function authenticate()
	{
	    // try to get access token 
	    if(!isset($this->arrConfig['dropboxAccessToken']) || $this->arrConfig['dropboxAccessToken'] == '') {
	        $this->import('Session');
            
            $arrRequestToken = $this->Session->get('dropboxRequestToken');
            
            if(!$arrRequestToken) {
                throw new \Exception('Not able to authenticate Dropbox. No request token found.');
            }                       
            
            $this->objOauth->setToken($arrRequestToken);
            $arrToken = $this->objOauth->getAccessToken();     
            
            $this->import('Config');            
            $this->Config->add('$GLOBALS[\'TL_CONFIG\'][\'dropboxAccessToken\']', serialize($arrToken));
            $this->Config->save();
            
            $this->arrConfig['dropboxAccessToken'] = serialize($arrToken);
	    }
        
		$this->objOauth->setToken(unserialize($this->arrConfig['dropboxAccessToken']));
		$this->objConnection = new \Dropbox_API($this->objOauth, $this->getRoot());
        return true;
	}
    

    /**
     * get dropbox account info
     * 
     * @return array
     */
	public function getAccountInfo()
	{
		return $this->objConnection->getAccountInfo();
	}


    /**
     * get authorize url
     * 
     * @return string
     */
    public function getAuthorizeUrl()
    {
        $this->import('Session');
        
        $strToken = $this->objOauth->getRequestToken();
        $this->Session->set('dropboxRequestToken', $strToken);
        $this->objOauth->setToken($strToken);
        return $this->objOauth->getAuthorizeUrl();
    }
   

    /**
     * get dropbox node (file or folder)
     * 
     * @param string $strPath
     * @return void
     */
	public function getNode($strPath, $blnLoadChildren = true)
	{
	    return new DropboxNode($strPath, $this->objConnection, $blnLoadChildren);
    }
    
    
    public function getRoot()
    {
        return $this->arrConfig['dropboxRoot'];
    }

}
