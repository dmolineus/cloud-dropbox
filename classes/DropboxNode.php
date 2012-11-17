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
	protected $objConnection;
	
		
	/**
	 * 
	 * @param string $strPath
	 * @param Dropbox_API $objApi
	 * @param bool load children
	 */
	public function __construct($strPath, $objApi, $blnLoadChildren=true, $arrMetaData=null)
	{
		parent::__construct($strPath, $objApi);
		
		$this->blnLoadChildren = $blnLoadChildren;
		$this->objConnection = $objApi->getConnection();				
		
		// set meta data
		if(is_array($arrMetaData)) 
		{
			$this->setMetaData($arrMetaData, true);
			return;
		}

		if(!$this->isMetaCached) 
		{			
			$this->getMetaData();
			return;
		}				
		
		
		// load cached file informations
		$arrCache = unserialize(Api\CloudCache::get($this->cacheMetaKey));		
		$this->arrCache = $arrCache;		
		$this->updateCache();
		
		$this->blnMetaDataLoaded = true;		
		return;
	}
	
	/**
	 * destructor
	 */
	public function __destruct()
	{
		$this->cacheMetaFile();
	}
	
		
	/**
	 * get variable of node
	 * @param $key
	 * @return mixed
	 */
	public function __get($strKey)
	{		
		if(isset($this->arrCache[$strKey])) 
		{
			return $this->arrCache[$strKey];
		}
		
		switch ($strKey)
		{
			case 'cacheKey':
				$this->arrCache[$strKey] = sprintf('/%s%s',
					DropboxApi::DROPBOX,
					$this->strPath
				);				 
				break;
				
			case 'cacheMetaKey':
				$objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
				$this->arrCache[$strKey] = sprintf('/%s%s.meta',
					DropboxApi::DROPBOX,
					$this->strPath
				);				 
				break;
				
			case 'cacheThumbnailKey':
				$arrPathInfo = pathinfo($this->strPath);
				
				$this->arrCache[$strKey] = sprintf(
					'%s/%s/%s_thumb.%s.jpg', 
					DropboxApi::DROPBOX, 
					$arrPathInfo['dirname'], 
					$arrPathInfo['filename'], 
					$arrPathInfo['extension']
				); 
				break;
			
			case 'downloadUrl':
				$arrMedia = $this->objConnection->media($this->strPath);
				$this->arrCache['downloadUrl'] = $arrMedia['url'];
				
				break;			
			
			// load metadata if they are not loaded
			case 'children':
			case 'childrenLoaded':
			case 'type':				
			case 'hash': 
			case 'hasThumbnail':			
			case 'modified':
			case 'path':
			case 'root':
			case 'filesize':			
			case 'version':			
				$this->getMetaData();
				break;
			
			default:
				return parent::__get($strKey);
				break;				
		}
				
		return $this->arrCache[$strKey];
	}


	/**
	 * save attributes
	 * 
	 * @param string
	 * @param mixed
	 */
	public function __set($strKey, $mxdValue)
	{
		switch ($strKey) 
		{
			case 'thumbnailVersion':
			case 'cachedFileVersion':
				$this->arrCache[$strKey] = $mxdValue;
				break;							
				
			case 'default':
				return;
				break;
		}
		
		$this->blnMetaDataChanged = true;
	}

	/**
	 * cache meta file
	 */
	protected function cacheMetaFile()
	{
		if(!$this->blnMetaDataChanged) {
			return; 
		}
		
		// do not cache isCached and isMetaCached in files
		$arrCache = $this->arrCache;
		
		if(isset($arrCache['isCached'])) 
		{
			unset($arrCache['isCached']);
		
		}
		
		if(isset($arrCache['isMetaCached'])) 
		{
			unset($arrCache['isMetaCached']);
		}
		
		if(isset($arrCache['downloadUrl'])) 
		{
			unset($arrCache['downloadUrl']);
		}
		
		$strCache = serialize($arrCache);
		Api\CloudCache::cache($this->cacheMetaKey, $strCache);	
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
		
		if($blnReturnNode) 
		{		 
			return $this->objApi->getNode($strNewPath);
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
		
		if(!is_array($this->children)) 
		{
			// children were not loaded before so force loading them
			if(!$this->childrenLoaded) 
			{
				$this->getMetaData(true);
			}
			
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
		if(($this->blnMetaDataLoaded == true && $blnLoadChildren == null ) || ($blnLoadChildren == true && $this->childrenLoaded)) 
		{
			return;
		} 
		
		$blnLoadChildren = ($blnLoadChildren == null) ? $this->blnLoadChildren : $blnLoadChildren;
		$arrMetaData = $this->objConnection->getMetaData($this->strPath, $blnLoadChildren);					

		$this->arrCache['filesize'] = $arrMetaData['bytes'];								
		$this->arrCache['hash'] = $arrMetaData['hash'];
		$this->arrCache['hasThumbnail'] = $arrMetaData['thumb_exists'];
		$this->arrCache['path'] = $arrMetaData['path'];
		$this->arrCache['root'] = $arrMetaData['root'];
		$this->arrCache['type'] = $arrMetaData['is_dir'] ? 'folder' : 'file';		
		$this->arrCache['childrenLoaded'] = $blnLoadChildren;
						
		// create children nodes so their meta data are stored
		if($arrMetaData['contents']) 
		{
			$this->arrCache['children'] = array();
						
			foreach($arrMetaData['contents'] as $arrChild) 
			{
				if(!isset($this->arrChildren[$arrChild['path']])) 
				{
					$objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
					$this->arrChildren[$arrChild['path']] =	$objChild;
				}
								
				$this->arrCache['children'][] = $arrChild['path'];
			}			 
		}
		
		if(isset($arrMetaData['rev'])) 
		{
			$this->arrCache['version'] = $arrMetaData['rev'];
		}
		
		if(isset($arrMetaData['modified'])) 
		{
			$this->arrCache['modified'] = $arrMetaData['modified'];
		}
		
		$this->blnMetaDataLoaded = true;
		$this->blnMetaDataChanged = true;		
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
		DropboxApi::DROPBOX . $this->strPath;
				
		// delete cached file. it will be new created when getFile is called 
		Api\CloudCache::deleteFile($strKey);
		
		$this->objConnection->move($this->strPath, $strNewPath);
		$this->strPath = $strNewPath;
		$this->arrCache['path'] = $strNewPath;
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
	 * set metadata. usefull to import metadata from contents block of parent element
	 * 
	 * @param array
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
					$this->arrCache['type'] = $mxdValue ? 'folder' : 'file';
					break;
					
				case 'bytes':
				case 'filesize':
					$this->arrCache['filesize'] = $mxdValue;
					break;
					
				case 'children':
					$this->arrCache['children'] = $mxdValue;
					$this->arrCache['childrenLoaded'] = true;
					break;
					
				case 'contents':
					foreach($mxdValue as $arrChild) 
					{
						$objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
						
						$this->arrChildren[$arrChild['path']] =	$objChild;
						$this->arrCache['children'][] = $arrChild['path'];
					}	
					$this->arrCache['childrenLoaded'] = true;
					break;
					
				case 'hash':
					// hash has changed so folder has changed
					if(isset($this->arrCache['hash']))
					{
						$this->blnHasChanged = ($this->arrCache['hash'] != $mxdValue);						
					}
					
					$this->arrCache['hash'] = $mxdValue;
					break;
									
				case 'rev':
				case 'version':
					// version has changed so folder has changed
					if(isset($this->arrCache['version']))
					{
						$this->blnHasChanged = ($this->arrCache['version'] != $mxdValue);						
					}
					
					$this->arrCache['version'] = $mxdValue;
					break;
						
				case 'thumb_exists':
					$this->arrCache['hasThumbnail'] = $mxdValue;
					break;					
									
				case 'cacheKey':
				case 'cacheMetaKey':
				case 'cacheThumbnailKey':
				case 'cachedFileVersion':				
				case 'extension':				
				case 'hasThumbnail':
				case 'hasCachedFile':
				case 'type':				
				case 'mime':
				case 'modified':
				case 'root':						
				case 'path':
				case 'thumbnailVersion':
					$this->arrCache[$strKey] = $mxdValue;		
					break;
			}
		}

		$this->blnMetaDataLoaded = true;
		$this->blnMetaDataChanged = true;
	}


	/**
	 * check if online file has changed since last call
	 * 
	 * @return bool
	 */
	protected function updateCache()
	{		
		if($this->blnHasChanged !== null) 
		{
			if(!$this->blnHasChanged)
			{
				return false;
			}
			
			// delete thumbnail cache
			if($this->hasThumbnail && $this->thumbnailVersion != $this->version) 
			{
				Api\CloudCache::delete($this->cacheThumbnailKey);
				$this->thumbnailVersion = null;
			}
			
			// delete file cache
			if($this->hasCachedFile && $this->cachedFileVersion != $this->version)
			{
				Api\CloudCache::delete($this->cacheKey);
				$this->cachedFileVersion = null;
			}
			
			return true;
		}		
		
		// let's check for changes going back to the root directory
		// this is useful because we do not have to check it for every 
		// item in a directory. in the best case there will only be one
		// request to dropbox		 
		if($this->strPath != '/')
		{			
			$strParent = dirname($this->strPath);
			$objParent = $this->objApi->getNode($strParent, false);
			
			// if parent has not changed child did it eighter
			if(!$objParent->updateCache()) 
			{
				$this->blnHasChanged = false;
				return $this->blnHasChanged;						
			}					
		}	
		
		// check if folder has changed
		if($this->type == 'folder') 
		{
			if($this->objConnection->getMetadata($this->strPath, false, $this->hash) === true) 
			{
				$this->blnHasChanged = false;
			} 
			else 
			{
				// getMetaData() will update the children as well
				$this->blnMetaDataLoaded = false;			
				$this->getMetaData(); 
				$this->blnHasChanged = true;
			};
		}
		
		// TODO: Do we have to check if file has changed as well or does setMetaData() all we need?
		
		return $this->blnHasChanged;
	}
}
