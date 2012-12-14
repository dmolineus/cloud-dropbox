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
class DropboxNodeModel extends \CloudNodeModel
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
	public function __construct(\Database\Result $objResult=null, $strPath=null, \Netzmacht\Cloud\Api\CloudApi $objApi=null)
	{
		parent::__construct($objResult, $strPath, $objApi);
		
		if($objResult === null && $strPath === null)
		{
			$this->blnNewNode = true;
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
					static::$objApi->authenticate();
					$arrMedia = static::$objApi->getConnection()->media($this->path);
					$this->downloadUrlExpires = static::$objApi->parseDropboxDate($arrMedia['expires']);
					$this->downloadUrl = $arrMedia['url'];
					$this->arrCache[$strKey] = $arrMedia['url'];
					
					// force saving because we have changed the data
					$this->blnForceSave = true;
					return $this->$arrMedia['url'];				
				}
				else
				{
					return $this->arrData[$strKey];
				}
				
				break;
				
			default:
				return parent::__get($strKey);
				break;
		}
	}
	
	
	/**
	 * copy file to a new path
	 * 
	 * @param string $strNewPath
	 * @return DropboxNodeModel
	 */
	public function copy($strNewPath)
	{
		static::$objApi->authenticate();
		static::$objApi->getConnection()->copy($this->path, $strNewPath);
		
		$objNew = new static();
		$objNew->setRow($this->row());
		$objNew->save();
		
		return $objNew;	
	}
	
	
	/**
	 * delete path
	 * 
	 * @return void
	 */
	public function delete()
	{
		static::$objApi->authenticate();
		static::$objApi->getConnection()->delete($this->path);
		
		parent::delete();
	}
	
	
	/**
	 * get content of the file
	 * 
	 * @return string
	 */
	public function downloadFile()
	{
		// file is cached so not needed to download it again		
		if(Api\CloudCache::isCached($this->cacheKey)) 
		{
			return Api\CloudCache::get($this->cacheKey);
		}
		static::$objApi->authenticate();
		$strContent = static::$objApi->getConnection()->getFile($this->path);
		Api\CloudCache::cache($this->cacheKey, $strContent);
		
		// save cached file version so we can decide if we have to delete it
		// during updating the cache 
		$this->cachedVersion = $this->version;
		$this->blnForceSave = true;
		
		return $strContent;
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
			static::$objApi->authenticate();
			$strContent = static::$objApi->getConnection()->getThumbnail($this->path, $strSize);
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
		static::$objApi->authenticate();
		static::$objApi->getConnection()->move($this->path, $strNewPath);
		$this->path = $strNewPath;
		$this->blnForceSave = true;		
	}
	
	
	/**
	 * save dropbox node
	 *
	 */
	public function save($blnForceInsert=false)
	{
		// new node is created which does not exists on dropbox
		if($this->blnNewNode) 
		{
			static::$objApi->authenticate();
			
			if($this->type == 'folder')
			{
				static::$objApi->getConnection()->createFolder($this->path);				
			}
			else
			{
				//create empty file
				static::$objApi->getConnection()->putFile(tmpfile());
			}
			
			$this->blnNewNode = false;
		}
		
		if(!isset($this->name))
		{
			$this->name = pathinfo($this->path, PATHINFO_BASENAME);			
		}
		
		if($this->type == 'file' && !isset($this->extension))
		{
			$this->extension = pathinfo($this->path, PATHINFO_EXTENSION);			
		}		
		
		$this->tstamp = time();
		$this->path = strtolower($this->path);
		
		parent::save($blnForceInsert);
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
			$this->setRow($arrMetaData);
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
					$this->modified = static::$objApi->parseDropboxDate($mxdValue);
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
					
				case 'revision':
				case 'client_mtime':
				case 'mime_type':
				case 'root':
				case 'icon':
				case 'size':
					// prevent from storing those values
					break;
						
				case 'thumb_exists':
					$this->hasThumbnail = $mxdValue;
					break;
					
				case 'id':
					
				default:
					$this->{$strKey} = $mxdValue;
					break;
			}
		}

		$this->blnNewNode = false;
		$this->blnMetaDataLoaded = true;
	}


	/**
	 * put file into dropbox
	 * 
	 * @param string $mxdPathOrFile open file handle or local path
	 * @return void
	 */
	public function uploadFile($mxdPathOrFile)
	{
		static::$objApi->authenticate();
		static::$objApi->getConnection()->putFile($this->path, $mxdPathOrFile);
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
		
		static::$objApi->authenticate();
		
		try 
		{
			$arrMetaData = static::$objApi->getConnection()->getMetaData($this->path, false);	
		}
		catch(\Exception $e)
		{
			$this->blnNewNode = true;
			return;
		}
		
		// store model informations
		$this->hasThumbnail = $arrMetaData['thumb_exists'];
		$this->path = $arrMetaData['path'];
		$this->type =  isset($arrMetaData['is_dir']) ? 'folder' : 'file';
		$this->filesize = $arrMetaData['bytes'];
		
		if(isset($arrMetaData['hash']))
		{
			$this->hash = $arrMetaData['hash'];
		}
		
		if(isset($arrMetaData['rev'])) 
		{
			$this->version = $arrMetaData['rev'];
		}
		
		if(isset($arrMetaData['modified'])) 
		{
			$this->modified = static::$objApi->parseDropboxDate($arrMetaData['modified']);
		}
		
		$this->blnMetaDataLoaded = true;
		$this->blnNewNode = false;
	}
	
}
