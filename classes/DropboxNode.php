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
	 * node is forced to save by the destructur
	 * used if internally some metadata has changed, f.e by storing thumbnail paths
	 * 
	 * @var bool
	 */
	protected $blnForceSave = false;
	
	/**
	 * node if something has changed 
	 * 
	 * @var bool
	 */
	protected $blnHasChanged = null;
	
	/**
	 * store if metadata are loaded from dropbox
	 * 
	 * @var bool
	 */
	protected $blnMetaDataLoaded = false;
	
	/**
	 * true if node is created and does not exists on dropbox
	 * 
	 * @var bool
	 */
	protected $blnNewNode = false;
	
		
	/**
	 * 	cunstructor 
	 * @param string path of the node
	 * @param CloudApi object
	 * @param mixed path, database result or metadata array
	 */
	public function __construct($strPath, $objApi, $mixedData=null)
	{
		parent::__construct($strPath, $objApi);
		
		// set meta data
		if(is_array($mixedData))
		{
			$this->objModel = new \CloudapiNodeModel();
			$this->objModel->cloudapi = $this->objApi->id;
			$this->objModel->path = $strPath;		
			$this->setMetaData($mixedData, true);
			return;
		}
		elseif($mixedData instanceof Contao\Model)
		{
			$this->objModel = $mixedData;
			return;
		}
		
		// try to find node in the database
		$this->objModel = \CloudapiNodeModel::findOneByPath($strPath);
		
		if($this->objModel === null)
		{			
			$this->objModel = new \CloudapiNodeModel();
			$this->objModel->path = $strPath;
			$this->objModel->cloudapi = $objApi->id;
			
			if($strPath !== '/')
			{
				$this->getMetaData();	
			}
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
					$arrMedia = $this->objApi->getConnection()->media($this->strPath);
					$this->downloadUrlExpires = $this->objApi->parseDropboxDate($arrMedia['expires']);
					$this->downloadUrl = $arrMedia['url'];
					$this->arrCache[$strKey] = $this->downloadUrl;
					
					// force saving because we have changed the data
					$this->blnForceSave = true;					
				}
				
				return $this->objModel->$strKey;
				break;
				
			default:
				return parent::__get($strKey);
		}
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
		$this->objApi->authenticate();
		$this->objApi->getConnection()->copy($this->strPath, $strNewPath);
		
		$objNew = new \CloudapiNodeModel();
		$objNew->setRow($this->objModel->row());
		$objNew->path = $strNewPath;
		$objNew->save();
		
		if($blnReturnNode) 
		{		 
			return $this->objApi->getNode($objNew);
		}	
	}
	
	
	/**
	 * delete path
	 * 
	 * @return void
	 */
	public function delete()
	{
		$this->objApi->authenticate();
		$this->objApi->getConnection()->delete($this->strPath);
		$this->objModel->delete();
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
		$objResult = \CloudapiNodeModel::findBy('pid', $this->id === null ? 0 : $this->id);
		
		if($objResult === null)
		{
			return $this->arrChildren;
		}
		
		while($objResult->next())
		{
			$objChild = $this->objApi->getNode($objResult->current());
			$this->arrChildren[$objChild->path] = $objChild;			
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
		
		$strContent = $this->objApi->getConnection()->getFile($this->strPath);
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
	protected function getMetaData()
	{
		if($this->blnMetaDataLoaded)
		{
			return;
		}
		
		$this->objApi->authenticate();
		
		try 
		{
			$arrMetaData = $this->objApi->getConnection()->getMetaData($this->strPath, false);	
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
		
		if(isset($arrMetaData['rev'])) 
		{
			$this->version = $arrMetaData['rev'];
		}
		
		if(isset($arrMetaData['modified'])) 
		{
			$this->modified = $this->objApi->parseDropboxDate($arrMetaData['modified']);
		}
		
		$this->blnMetaDataLoaded = true;
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
			$this->objApi->authenticate();
			$strContent = $this->objApi->getConnection()->getThumbnail($this->strPath, $strSize);
			Api\CloudCache::cache($this->cacheThumbnailKey, $strContent);
			 
			$this->thumbnailVersion = $this->version;	
			$this->blnForceSave = true;	 
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
		$this->objApi->authenticate();
		$this->objApi->getConnection()->move($this->strPath, $strNewPath);
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
		$this->objApi->authenticate();
		$this->objApi->getConnection()->putFile($this->strPath, $mxdPathOrFile);
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
			$this->objApi->authenticate();
			
			if($this->type == 'folder')
			{
				$this->objApi->getConnection()->createFolder($this->strPath);				
			}
			else
			{
				//create empty file
				$this->objApi->getConnection()->putFile(tmpfile());
			}
			
			$this->blnNewNode = false;
		}
		
		if(!isset($this->objModel->name))
		{
			$this->objModel->name = pathinfo($this->strPath, PATHINFO_BASENAME);			
		}
		
		if($this->objModel->type == 'file' && !isset($this->objModel->extension))
		{
			$this->objModel->extension = pathinfo($this->strPath, PATHINFO_EXTENSION);			
		}		
		
		$this->objModel->tstamp = time();
		$this->objModel->path = strtolower($this->objModel->path);
		$this->objModel->save();
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
			$this->objModel->setRow($arrMetaData);
			return;
		}
		
		// match keys because meta data is in dropbox style
		foreach ($arrMetaData as $strKey => $mxdValue) 
		{
			switch($strKey) 
			{
				case 'is_dir':
					$this->type = $mxdValue ? 'folder' : 'file';
					break;
					
				case 'is_file':
					$this->type = $mxdValue ? 'file' : 'folder';
					break;
					
				case 'bytes':
					$this->filesize = $mxdValue;
					break;
					
				case 'modified':
					$this->modified = $this->objApi->parseDropboxDate($mxdValue);
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
					// version has changed so folder/file has changed
					if($this->version !== null)
					{
						$this->blnHasChanged = ($this->version != $mxdValue);						
					}
					
					$this->version = $mxdValue;
					break;
						
				case 'thumb_exists':
					$this->hasThumbnail = $mxdValue;
					break;					
									
				default:
					$this->{$strKey} = $mxdValue;		
					break;
			}
		}

		$this->blnMetaDataLoaded = true;
	}
}
