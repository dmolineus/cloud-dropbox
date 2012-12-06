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
	public function __construct($arrRow)
	{
		// will fetch app config
		parent::__construct($arrRow);
		
		// no custom value set so get default app settings
		// we have to encrypt/decrypt it because of dropbox guidlines
		if($this->arrConfig['useCustomApp'] != '1')
		{
			$this->arrConfig['appKey'] = base64_decode($GLOBALS['TL_CONFIG']['dropboxAppKey']);
			$this->arrConfig['appSecret'] = base64_decode($GLOBALS['TL_CONFIG']['dropboxAppSecret']);			
		}
		
		if(!isset($this->arrConfig['dropboxRoot']))
		{
			$this->arrConfig['dropboxRoot'] = $GLOBALS['TL_CONFIG']['dropboxRoot'];			
		}		  
		
		$strOauth = $this->arrConfig['oAuthClass'];
		$strOauthClass = '\Dropbox_OAuth_' . ($strOauth != '') ? $strOauth : 'PHP';
		
		$this->objOauth = new $strOauthClass($this->arrConfig['appKey'], $this->arrConfig['appSecret']);		
	}
	
	
	/**
	 * get settings
	 * 
	 * @param string key
	 * @return mixed
	 */
	public function __get($strKey)
	{
		switch($strKey)
		{
			case 'name':
				return static::DROPBOX;
				break;
			
			case 'mode':
				return $this->arrConfig['mode'];
				break;
				
			default:
				return parent::__get($strKey);
				break;
		}
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
		if(!isset($this->arrConfig['dropboxAccessToken']) || $this->arrConfig['dropboxAccessToken'] == '') 
		{
			$this->import('Session');
			
			$arrRequestToken = $this->Session->get('dropboxRequestToken');
			
			if(!$arrRequestToken) 
			{
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
		$this->objConnection = new \Dropbox_API($this->objOauth, $this->arrConfig['dropboxRoot']);
		
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
	 * return connection object
	 * 
	 * @return DROPBOX_API
	 */
	public function getConnection()
	{
		return $this->objConnection;
	}
	

	/**
	 * get dropbox node (file or folder)
	 * 
	 * @param mixed path, database result or metadata array
	 * @return void
	 */
	public function getNode($mixedData, $blnLoadChildren=true)
	{
		if(is_string($mixedData))
		{
			$strPath = $mixedData;
			$mixedData = null;
		}
		elseif(is_array($mixedData) && isset($mixedData['path'])) 
		{
			$strPath = $mixedData['path'];
		}
		elseif($mixedData instanceof \Result) 
		{
			$strPath = $mixedData->path;			
		}
		else 
		{
			throw new \Exception('Invalid getNode call. Could not fetch file path');
		}
		
		// make sure that key is not empty
		if($strPath == '') 
		{
			$strPath = $this->getRoot();
		}				
		
		if(!isset($this->arrNodes[$strPath])) {									
			$this->arrNodes[$strPath] = new DropboxNode($strPath, $this, $blnLoadChildren, $mixedData);						
		}
		
		return $this->arrNodes[$strPath];		
	}
	
	
	/**
	 * get root path
	 * 
	 * @return string
	 */
	public function getRoot()
	{
		return '/';
	}
	
	
	/**
	 * check if a node exists
	 */
	public function nodeExists($strPath)
	{
		if($this->mode == 'sync')
		{
			$objStmt = $this->Database->prepare('SELECT count(id) AS total FROM tl_cloudapi_nodes WHERE cloudapi=%s AND path=%s');
			$objResult = $objStmt->execute($this->arrRow['id'], $strPath);
			
			return ($objResult->total > 0);
		}
		
		$objNode = $this->getNode($strPath);
		return $objNode->exists;
	}
	
	
	/**
	 * search for nodes
	 * 
	 * @return array
	 * @param string search query
	 * param string starting point
	 */
	public function searchNodes($strQuery, $strPath='')
	{
		$arrResult = $this->objConnection->search($strQuery, null, $strPath);
		
		if(empty($arrResult)) 
		{
			return array();
		}
		
		$arrNodes = array();
		
		foreach ($arrResult as $arrChild) 
		{
			$objNode = $this->getNode($arrChild['path'], false, $arrChild);
			$arrNodes[$objNode->path] = $objNode;
		}
		
		return $arrNodes;		
	}


	/**
	 * parse dropbox date and create a timestamp
	 * 
	 * @return int timestamp
	 */
	public function parseDate($strDate)
	{
		$arrDate = strptime($strDate, '%a, %d %b %Y %H:%M:%S %z');
		
		return mktime(
			$arrDate['tm_hour'], 
			$arrDate['tm_min'], 
			$arrDate['tm_sec'],
            $arrDate['tm_mon'] + 1, 
            $arrDate['tm_mday'], 
            $arrDate['tm_year'] + 1990
		);
	}


	/**
	 * execute the sync
	 * 
	 * @param string delta sync cursor
	 * @return string current cursor
	 */
	protected function execSync($strCursor)
	{
		// use delta sync
		// returns array with entries, reset, cursor, has_more
		$arrDelta = $this->objConnection->delta($strCursor);
		
		// dropbox force to reset all nodes
		if($arrDelta['reset'])
		{
			$objStmt = $this->Database->prepare('DELETE FROM ' . $this->name() . ' WHERE cloudapi=?');
			$objStmt->execute($this->arrRow['id']);			
		}		

		foreach ($arrDelta['entries'] as $strPath => $varValue) 
		{
			// delete path and all children			
			if($varValue === null && !$arrDelta['reset'])
			{
				$objStmt = $this->Database->prepare('DELETE FROM ' . $this->name() . ' WHERE cloudapi=? AND path Like ?');
				$objStmt->execute($this->arrRow['id'], $strPath);
				
				continue;				
			}
			
			// create all path nodes if they do not exists
			$strWalkPath = dirname($strPath);
			for($objResult = Api\CloudNodesModel::findByPath($strWalkPath); $objResult == null; $strPath = dirname($strWalkPath))
			{
				$objNode = $this->getNode($strWalkPath);
				$objNode->save(true);
			}
			
			$objEntry = null;
			
			if(!$arrDelta['reset'])
			{				
				$objEntry = Api\CloudNodesModel::findByPath($strPath);
			}
			
			// create new node
			if($objEntry == null)
			{
				$objNode = $this->getNode($strPath);
			}
			
			// update node
			else 
			{
				$objNode = $this->getNode($objEntry);				
			}
			
			$objNode->save();
		}
		
		// recursively call delta sync
		if($arrDelta['has_more'])
		{
			return $this->execSync($arrDelta['cursor']);
		}
		
		return $arrDelta['cursor'];		
	}
}
