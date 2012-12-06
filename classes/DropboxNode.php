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
use Result;

/**
 * DropboxNode for file or or directory
 * 
 * @copyright 2012 David Molineus netzmacht creative 
 * @license GNU/LPGL
 */
class DropboxNode extends Api\CloudNode 
{
	
	/**
	 * 
	 */
	protected $arrRow = array();

	/**
	 * 
	 */
	protected $blnForceSave = false;
	
	/**
	 * 
	 */
	protected $blnHasChanged = null;
	
	/**
	 * 
	 */
	protected $blnLoadChildren = false;
	
	/**
	 * 
	 */
	protected $blnMetaDataLoaded = false;
	
	/**
	 * 
	 */
	protected $blnMetaDataChanged = false;
	
	/**
	 * 
	 */
	protected $blnNewNode = false;
	
	/**
	 * 
	 */
	protected $objConnection;
	
		
	/**
	 * 	 
	 * @param Dropbox_API $objApi
	 * @param bool load children
	 * @param mixed path, database result or metadata array
	 */
	public function __construct($strPath, $objApi, $blnLoadChildren=true, $mixedData=null)
	{
		parent::__construct($strPath, $objApi);
		
		$this->blnLoadChildren = $blnLoadChildren;
		$this->objConnection = $objApi->getConnection();
		
		// set meta data
		if(is_array($mixedData))
		{
			$this->arrRow = $mixedData;
			return;
		}
		
		// try to find node in the database
		Api\CloudNodesModel::setApi($this->objApi->getName());		
		$objModel = Api\CloudNodesModel::findByPath($strPath);
		$this->arrRow = $objModel->row();
		
		if($objModel == null)
		{
			$this->blnNewNode = true;
			
			$objModel = new Api\CloudNodesModel();
			$objModel->cloudapi = $objApi->getName();
			
			$this->arrRow = $objModel->row();
			$this->getMetaData();
		}		
	}

	
	/**
	 * destructor
	 */
	public function __destruct()
	{
		if($this->blnForceSave)
		{
			$this->save();
		}	
	}
	
		
	/**
	 * get variable of node
	 * @param $key
	 * @return mixed
	 */
	public function __get($strKey)
	{
		// value is cached
		if(isset($this->arrCache[$strKey]))
		{
			return $this->arrCache[$strKey];
		}
		
		switch ($strKey)
		{
			case 'exists':
				return !$this->blnNewNode;
				break;
			
			case 'new':
				return $this->blnNewNode;
				break;
					
			case 'downloadUrl':
				// check if download url has expired
				if(time() > $this->downloadUrlExpires)
				{
					$arrMedia = $this->objConnection->media($this->strPath);
					$this->downloadUrlExpires = $this->objApi->parseDate($arrMedia['expires']);
					$this->downloadUrl = $arrMedia['url'];
					$this->arrCache[$strKey] = $this->downloadUrl;
					
					// force saving because we have changed the data
					$this->forceSave = true;					
				}
				
				return $this->arrRow[$strKey];
				break;
				
			default:
				return parent::__get($strKey);
		}
	}


	/**
	 * save attributes
	 * 
	 * @param string
	 * @param mixed
	 */
	protected function __set($strKey, $mxdValue)
	{
		switch ($strKey) 
		{
			case 'children':
			case 'childrenLoaded':
				$this->arrCache[$strKey] = $mxdValue;
				break;
				
			case 'default':
				return parent::__set($strKey, $mxdValue);
				break;
		}
		
		$this->blnMetaDataChanged = true;
	}
	
	
	/**
	 * copy file to a new path
	 * 
	 * @param string $strNewPath
	 * @param bool $blnReturnNode set true if node shall be returned
	 * @return DropboxNode
	 */
	public function copy($strNewPath, $blnReturnNode=false)
	{
		$this->objConnection->copy($this->strPath, $strNewPath);
		
		$objNew = new Api\CloudNodesModel();
		$objNew->setRow($this->arrRow);
		$objNew->path = $strNewPath;
		$objNew->save();
		
		if($blnReturnNode) 
		{		 
			return $this->objApi->getNode($objNew->row());
		}	
	}
	
	
	/**
	 * delete path
	 * 
	 * @return void
	 */
	public function delete()
	{
		$this->objConnection->delete($this->strPath);
		
		$objModel = new Api\CloudNodesModel();
		$objModel->setRow($this->arrRow);
		$objModel->delete();
	}
	
	
	/**
	 * get children nodes
	 * 
	 * @return array
	 */
	public function getChildren()
	{
		if(is_array($this->arrChildren)) 
		{
			return $this->arrChildren;
		}
		
		$this->arrChildren = array();
		
		// sync mode is used so children should exists in database
		if($this->objApi->mode == 'sync')
		{				
			$objResult = Api\CloudNodesModel::findBy('pid', $this->id);
			
			while($objResult->next())
			{
				$objChild = $this->objApi->getNode($objResult->row(), false);
				$this->arrChildren[$objChild->path] = $objChild;			
			}
			
			return $this->arrChildren;
		}
		
		// try to load children by getting metadata
		if(!is_array($this->children) && !$this->childrenLoaded) 
		{
			$this->getMetaData(true);			
			return $this->arrChildren;
		}
		
		// children meta data are stored but nodes are not created yet		
		foreach ($this->children as $strChild) 
		{
			$objChild = $this->objApi->getNode($strChild, false);
			$this->arrChildren[$strChild] = $objChild;			
		}
		
		return $this->arrChildren;
	}
	
	
	/**
	 * get content of the file
	 * 
	 * @return string
	 */
	public function getFile()
	{
		// file is cached so not needed to download it again		
		if(Api\CloudCache::isCached($this->cacheKey)) 
		{
			return Api\CloudCache::getFile($this->cacheKey);
		}
		
		$strContent = $this->objConnection->getFile($this->strPath);
		Api\CloudCache::cache($this->cacheKey, $strContent);
		
		// save cached file version so we can decide if we have to delete it
		// during updating the cache 
		$this->cachedFileVersion = $this->version;
		
		return $strContent;
	}
	
	
	/**
	 * get meta data from dropbox
	 * 
	 * @return void
	 * @param mixed set true if force loading children
	 */
	protected function getMetaData($blnLoadChildren = null)
	{
		// check if meta data are already loaded
		if(($this->blnMetaDataLoaded == true && $blnLoadChildren == null ) || ($blnLoadChildren == true && $this->childrenLoaded) || $this->blnNewNode) 
		{
			return;
		} 
		
		$blnLoadChildren = ($blnLoadChildren == null) ? ($this->objApi->mode == 'sync' ? false : $this->blnLoadChildren) : $blnLoadChildren;
		
		try 
		{
			$arrMetaData = $this->objConnection->getMetaData($this->strPath, $blnLoadChildren);	
		}
		catch(\Exception $e)
		{
			$this->blnNewNode = true;
			return;
		}
		
		// store model informations
		$this->hash = $arrMetaData['hash'];
		$this->hasThumbnail = $arrMetaData['thumb_exists'];
		$this->path = $arrMetaData['path'];
		$this->type =  $arrMetaData['is_dir'] ? 'folder' : 'file';
		$this->filesize = $arrMetaData['bytes'];
		
		$this->childrenLoaded = $blnLoadChildren;
		
		if(isset($arrMetaData['rev'])) 
		{
			$this->version = $arrMetaData['rev'];
		}
		
		if(isset($arrMetaData['modified'])) 
		{
			$this->modified = $arrMetaData['modified'];
		}
		
		$this->blnMetaDataLoaded = true;
		$this->blnMetaDataChanged = true;	
		
		// sync mode is used so children should be in the database
		if($this->objApi->mode == 'sync')
		{
			return;
		}
		
		// create children nodes so their meta data are stored
		if($arrMetaData['contents']) 
		{
			$this->children = array();
						
			foreach($arrMetaData['contents'] as $arrChild) 
			{
				if(!isset($this->arrChildren[$arrChild['path']])) 
				{
					$objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
					$this->arrChildren[$arrChild['path']] =	$objChild;
				}
								
				$this->children[] = $arrChild['path'];
			}			 
		}	
	}
	
	
	/**
	 * get path to thumbnail
	 * 
	 * @return string
	 */
	public function getThumbnail()
	{
		if(!$this->hasThumbnail) 
		{
			return false;
		}		

		if (!Api\CloudCache::isCached($this->cacheThumbnailKey)) 
		{
			$strContent = $this->objConnection->getThumbnail($this->strPath, $strSize);
			Api\CloudCache::cache($this->cacheThumbnailKey, $strContent);
			
			// save thumbnail version so we can decide if we have to delete it
			// during updating the cache 
			$this->thumbnailVersion = $this->version;		 
		}
		
		return Api\CloudCache::getPath($this->cacheThumbnailKey);
	}
	
	
	/**
	 * move file to new path
	 * 
	 * @param string $strNewPath
	 * @return void
	 */
	public function move($strNewPath)
	{		
		$this->objConnection->move($this->strPath, $strNewPath);
		$this->path = $strNewPath;
		$this->blnForceSave = true;		
	}
	
	
	/**
	 * put file into dropbox
	 * 
	 * @param string $mxdPathOrFile open file handle or local path
	 * @return void
	 */
	public function putFile($mxdPathOrFile)
	{
		$this->objConnection($this->strPath, $mxdPathOrFile);
	}
	
	
	/**
	 * save dropbox node
	 *
	 */
	public function save()
	{
		// new node is created which does not exists on dropbox
		if($this->blnNewNode) 
		{
			if($this->type == 'folder')
			{
				$this->objConnection->createFolder($this->strPath);				
			}
			else
			{
				// create empty file
				$this->putFile(tmpfile());
			}
			
			$this->blnNewNode = false;
		}
		
		$objModel = new Api\CloudNodesModel();
		$objModel->setRow($this->arrRow);
		$objModel->save();
	}
	
	
	/**
	 * set metadata. usefull to import metadata from contents block of parent element
	 * 
	 * @param array
	 * @param bool match keys
	 * @return void
	 */
	public function setMetaData($arrMetaData, $blnMatchKeys=false)
	{
		// simply pass by element
		if(!$blnMatchKeys) 
		{
			$this->arrCache = $arrMetaData;
			return;
		}
		
		// set default value
		$this->arrCache['childrenLoaded'] = false;
		
		// match keys because meta data is in dropbox style
		foreach ($arrMetaData as $strKey => $mxdValue) 
		{
			switch($strKey) 
			{
				case 'is_dir':
					$this->type = $mxdValue ? 'folder' : 'file';
					break;
					
				case 'bytes':
				case 'filesize':
					$this->filesize = $mxdValue;
					break;
					
				case 'children':
					$this->children = $mxdValue;
					$this->childrenLoaded = true;
					break;
					
				case 'contents':
					foreach($mxdValue as $arrChild) 
					{
						$objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
						
						$this->arrChildren[$arrChild['path']] = $objChild;
						$this->children[] = $arrChild['path'];
					}	
					$this->childrenLoaded = true;
					break;
					
				case 'hash':
					// hash has changed so folder has changed
					if($this->hash !== null)
					{
						$this->blnHasChanged = ($this->hash != $mxdValue);						
					}
					
					$this->hash = $mxdValue;
					break;
									
				case 'rev':
				case 'version':
					// version has changed so folder has changed
					if($this->version !== null)
					{
						$this->blnHasChanged = ($this->version != $mxdValue);						
					}
					
					$this->version = $mxdValue;
					break;
						
				case 'thumb_exists':
					$this->hasThumbnail = $mxdValue;
					break;					
									
				case 'cacheKey':
				case 'cacheMetaKey':
				case 'cacheThumbnailKey':
				case 'cachedFileVersion':
				case 'dirname':				
				case 'extension':				
				case 'hasThumbnail':
				case 'hasCachedFile':
				case 'type':				
				case 'mime':
				case 'modified':
				case 'root':						
				case 'path':
				case 'thumbnailVersion':
					$this->{$strKey} = $mxdValue;		
					break;
			}
		}

		$this->blnMetaDataLoaded = true;
		$this->blnMetaDataChanged = true;
	}
}
