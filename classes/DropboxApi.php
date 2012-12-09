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
use Netzmacht\Cloud\Api;

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
class DropboxApi extends Api\CloudApi
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
	 * 
	 */
	protected $blnAuthenticated = false;
	
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
		$strOauthClass = '\Dropbox_OAuth_' . (($strOauth != '') ? $strOauth : 'PHP');		
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
		if($this->blnAuthenticated)
		{
			return;
		}
		
		// try to get access token 
		if(!isset($this->arrConfig['accessToken']) || $this->arrConfig['accessToken'] == '') 
		{
			$this->import('Session');
			
			$arrRequestToken = $this->Session->get('dropboxRequestToken');
			
			if(!$arrRequestToken) 
			{
				throw new \Exception('Not able to authenticate Dropbox. No request token found.');
			}						
						
			$this->objOauth->setToken($arrRequestToken);
			$arrToken = $this->objOauth->getAccessToken();
			
			
			$objStmt = $this->Database->prepare('UPDATE tl_cloud_api %s WHERE name =?');
			$objStmt->set(array('accessToken' => $arrToken));
			$objStmt->execute($this->name);
			
			$this->arrConfig['accessToken'] = serialize($arrToken);
		}
		
		$this->objOauth->setToken(unserialize($this->arrConfig['accessToken']));
		$this->objConnection = new \Dropbox_API($this->objOauth, $this->arrConfig['dropboxRoot']);
		$this->blnAuthenticated = true;
		return true;
	}
	

	/**
	 * get dropbox account info
	 * 
	 * @return array
	 */
	public function getAccountInfo()
	{
		$this->authenticate();
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
	public function getNode($mixedData)
	{
		
		// support multiple ways getting a node
		switch (gettype($mixedData)) 
		{
			case 'string':
				$strPath = $mixedData;
				$mixedData = null;
				break;
			
			case 'integer':
			
				$objResult = \CloudNodeModel::findOneById($mixedData);
				$strPath = $objResult === null ? '' : $objResult->path;				
				$mixedData = $objResult;
				break;
				
			case 'array':
				$strPath = $mixedData['path'];
				break;
				
			case 'object':
				$strPath = $mixedData->path;
				break;
				
			default:
				throw new \Exception('Invalid getNode call. Could not fetch file path');
				break;
		}

		
		// make sure that key is not empty
		if($strPath == '') 
		{
			$strPath = $this->getRoot();
		}				
		
		if(!isset($this->arrNodes[$strPath])) {									
			$this->arrNodes[$strPath] = new DropboxNode($strPath, $this, $mixedData);						
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
		$objNode = $this->getNode($strPath);
		return $objNode->exists;
	}
	
	
	/**
	 * search for nodes
	 * 
	 * @return array
	 * @param string search query
	 * param string starting point
	 * TODO: search in database
	 */
	public function searchNodes($strQuery, $strPath='')
	{
		$this->authenticate();
		$arrResult = $this->objConnection->search($strQuery, null, $strPath);
		
		if(empty($arrResult)) 
		{
			return array();
		}
		
		$arrNodes = array();
		
		foreach ($arrResult as $arrChild) 
		{
			$objNode = $this->getNode($arrChild['path'], $arrChild);
			$arrNodes[$objNode->path] = $objNode;
		}
		
		return $arrNodes;		
	}


	/**
	 * parse dropbox date and create a timestamp
	 * 
	 * @return int timestamp
	 */
	public function parseDropboxDate($strDate)
	{
		$arrDate = strptime($strDate, '%a, %d %b %Y %H:%M:%S %z');
		
		return mktime(
			$arrDate['tm_hour'], 
			$arrDate['tm_min'], 
			$arrDate['tm_sec'],
            $arrDate['tm_mon'] + 1, 
            $arrDate['tm_mday'], 
            $arrDate['tm_year'] + 1900
		);
	}


	/**
	 * execute the sync
	 * 
	 * @param string delta sync cursor
	 * @return string current cursor
	 */
	protected function execSync($strCursor, $arrMounted=array(), $arrPids=array())
	{
		// use delta sync
		// returns array with entries, reset, cursor, has_more
		$this->authenticate();
		$arrDelta = $this->objConnection->delta($strCursor);
		
		// dropbox force to reset all nodes
		if($arrDelta['reset'])
		{
			$objStmt = $this->Database->prepare('UPDATE tl_cloud_node SET found="0" WHERE cloudapi=?');
			$objStmt->execute($this->arrRow['id']);			
		}		

		foreach ($arrDelta['entries'] as $varValue) 
		{
			$strPath = strtolower($varValue[0]);
			$arrMetaData = $varValue[1];
			$blnMounted = false;
			
			// only include mounted files
			if(is_array($arrMounted))
			{
				foreach($arrMounted as $strFolder)
				{
					if(strncasecmp($strPath, $strFolder, strlen($strFolder)) !== 0)
					{
						$blnMounted = true;
						continue 2;
					}
				}
			}
			
			// delete path and all children			
			if($arrMetaData === null && !$arrDelta['reset'])
			{
				$objStmt = $this->Database->prepare('DELETE FROM tl_cloud_node WHERE cloudapi=? AND path Like ?');
				$objStmt->execute($this->id, $strPath);
				
				continue;				
			}
			
			// create all path nodes if they do not exists
			// we have to store them in an array to start with last node			
			$arrParents = array();
			for($strWalkPath = dirname($strPath); !in_array($strWalkPath, array('.', '/', '\\', '')); $strWalkPath = dirname($strWalkPath))
			{
				if(isset($arrPids[$strWalkPath]))
				{
					break;
				}

				$objResult = \CloudNodeModel::countBy('path', $strWalkPath);
				
				if($objResult > 0)
				{
					break;
				}
				
				$arrParents[] = $strWalkPath;
			}			
			
			for($i = count($arrParents) - 1; $i >= 0; $i--)
			{
				$objNode = $this->getNode($arrParents[$i]);
				$strParent = dirname($objNode->path);
				
				if(!isset($arrPids[$strParent]))
				{
					$objResult = \CloudNodeModel::findByPath($strParent);
					$arrPids[$strParent] = (isset($objResult->id)) ? $objResult->id : 0;					
				}
				
				$objNode->pid = $arrPids[$strParent];
				$objNode->type = 'folder';
				$objNode->found = '1';
				$objNode->save();				
				$arrPids[$objNode->path] = $objNode->id;
			}
			
			if(!isset($arrPids[$strPath]))
			{
				$objEntry = \CloudNodeModel::findOneByPath($strPath);
				
				// create new node
				if($objEntry === null)
				{
					$objNode = $this->getNode($arrMetaData);
					$strParent = dirname($objNode->path);
					
					if(!isset($arrPids[$strParent]))
					{
						$objResult = \CloudNodeModel::findOneByPath($strParent);
						$arrPids[$strParent] = (isset($objResult->id)) ? $objResult->id : 0;					
					}
					
					$objNode->pid = $arrPids[$strParent];
					$objNode->found = '1';			
					$objNode->save();
					$arrPids[$objNode->path] = $objNode->id;					
				}
				
				// update existing one
				else 
				{
					$objNode = $this->getNode($objEntry);
					$objNode->setMetaData($arrMetaData, true);
					$objNode->found = '1';
					$objNode->save();
				}
			}
			
			// dropbox force to reset all nodes so we delete all leftover nodes
			if($arrDelta['reset'])
			{
				//$objStmt = $this->Database->prepare('DELETE FROM tl_cloud_node WHERE found=0 AND cloudapi=?');
				//$objStmt->execute($this->id);			
			}
		}
		
		// recursively call delta sync
		if($arrDelta['has_more'])
		{
			return $this->execSync($arrDelta['cursor'], $arrMounted, $arrPids);
		}
		
		return $arrDelta['cursor'];		
	}
}
