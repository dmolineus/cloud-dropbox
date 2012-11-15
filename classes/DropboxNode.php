<?php

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
     * @var array
     */
    protected $arrCache = array();
    
    /**
     * 
     */
    protected $blnLoadChildren = false;
    
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
    public function __construct($strPath, $objApi, $blnLoadChildren)
    {
        parent::__construct($strPath, $objApi);
        
        $this->blnLoadChildren = $blnLoadChildren;
        $this->objConnection = $objApi->getConnection();
                
        if(!$this->isMetaCached) {
            return;
        }
        
        // load cached file informations
        $arrCache = unserialize(Api\CloudCache::get($this->cacheMetaKey));
        //$this->hash = $arrCache['hash'];
        echo var_dump($arrCache);
        $this->arrCache = $arrCache;
        return;
        
        // nothing changed so stored cache data can be used
        if(!$this->hasChanged()) {            
            $this->arrCache = $arrCache;
        }
        else {
            Api\CloudCache::delete($this->cacheMetaKey);
        }
    }
    
    /**
     * store metadata in cache file
     * 
     * @return void
     */
    public function __destruct()
    {
        $strCache = serialize($this->arrCache);
        Api\CloudCache::cache($this->cacheMetaKey, $strCache);        
    }
    
        
    /**
     * get variable of node
     * @param $key
     * @return mixed
     */
    public function __get($strKey)
    {
        if(isset($this->arrCache[$strKey])) {
            $this->arrCache[$strKey];
        }
        
        switch ($strKey) {
            case 'cacheKey':
                $objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
                $this->arrCache[$strKey] = sprintf('%s-%s.%s',
                    DropboxApi::DROPBOX,
                    $objApi->getRoot(),
                    $this->strPath
                );                 
                break;
                
            case 'cacheMetaKey':
                $objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
                $this->arrCache[$strKey] = sprintf('%s-%s.%s.meta',
                    DropboxApi::DROPBOX,
                    $objApi->getRoot(),
                    $this->strPath
                );                 
                break;
            
            case 'cloudUrl':
                $arrMedia = $this->objConnection->media($this->strPath);
                $this->arrCache[$strKey] = $arrMedia['url'];
                
                break;
            
            case 'extension':
                $this->arrCache[$strKey] = pathinfo($this->strPath, PATHINFO_EXTENSION);
                break;
            
            case 'icon':
                $arrMimeInfo = $this->getMimeInfo();
                $this->arrCache[$strKey] = $arrMimeInfo[1];             
                break;
                
            case 'isCached':
                $this->arrCache[$strKey] = Api\CloudCache::isCached($this->cacheKey);
                break;
                
            case 'isMetaCached':
                $this->arrCache[$strKey] = Api\CloudCache::isCached($this->cacheMetaKey);
                break;
                
            case 'mime':
                $arrMimeInfo = $this->getMimeInfo();
                $this->arrCache[$strKey] = $arrMimeInfo[0];
                break;
            
            // load metadata if they are not loaded
            case 'children':
            case 'isFile':
            case 'isDir':   
            case 'hash': 
            case 'hasThumbnail':
            case 'modified':
            case 'path':
            case 'root':
            case 'size':            
            case 'version':            
                $this->getMetaData();
                break;                   
        }
        
        // some meta data aren't created always so check if cache exists
        if(!isset($this->arrCache[$strKey])) {
            return null;
        }        
        return $this->arrCache[$strKey];
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
        
        if($blnReturnNode) {
            $objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
            
            return $objApi->getNode($strNewPath);
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
        if(!is_array($this->arrChildren)) {
            $this->arrChildren = array();
            $objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
            
            foreach($this->children as $arrChild) {
                $objChild = $objApi->getNode($arrChild['path'], false);
                $objChild->setMetaData($arrChild);
                
                $this->arrChildren[$arrChild['path']] =  $objChild;
            }   
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
        if(Api\CloudCache::isCached($this->cacheKey)) {
            return Api\CloudCache::getFile($this->cacheKey);
        }
        
        $strContent = $this->objConnection->getFile($this->strPath);
        Api\CloudCache::cache($this->cacheKey, $strContent);
        
        return $strContent;
    }
    
    
    /**
     * get meta data from dropbox
     * 
     * @return void
     */
    protected function getMetaData($blnLoadChildren = null)
    {
        $blnLoadChildren = ($blnLoadChildren == null) ? $this->blnLoadChildren : $blnLoadChildren;
        $arrMetaData = $this->objConnection->getMetaData($this->strPath, $blnLoadChildren);                    
                
        $this->arrCache['isFile'] = !$arrMetaData['is_dir'];
        $this->arrCache['isDir'] = $arrMetaData['is_dir'];
        $this->arrCache['hash'] = $arrMetaData['hash'];
        $this->arrCache['hasThumbnail'] = $arrMetaData['thumb_exists'];
        $this->arrCache['path'] = $arrMetaData['path'];
        $this->arrCache['root'] = $arrMetaData['root'];
        $this->arrCache['size'] = $arrMetaData['bytes'];
        
        if($arrMetaData['contents']) {
            $this->arrCache['children'] = $arrMetaData['contents'];   
        }
        else {
            $this->arrCache['children'] = array();
        }       
        
        if(isset($arrMetaData['rev'])) {
            $this->arrCache['version'] = $arrMetaData['rev'];
        }
        else {
            $this->arrCache['version'] = false;
        }
        
        if(isset($arrMetaData['modified'])) {
            $this->arrCache['modified'] = $arrMetaData['modified'];
        }
        else {
            $this->arrCache['modified'] = false;
        }
    }
    
    
    /**
     * get path to thumbnail
     * 
     * @return string
     * @param string $strSize on of the defined sizes in CloudNode::THUMBNAIL_*
     */
    public function getThumbnail($strSize = CloudeNode::THUMBNAIL_SMALL)
    {
        if(!$this->hasThumbnail) {
            return false;
        }
        
        $strKey = DropboxApi::DROPBOX . $this->strPath . $strSize;      
        
        if (Api\CloudCache::isCached($strKey)) {
            $strPath = Api\CloudCache::getPath($strKey);
        }
        else {
            $strContent = $this->objConnection->getThumbnail($this->strPath, $strSize);
            $strPath = Api\CloudCache::cache($strKey, $strContent);         
        }
        
        return $strPath;
    }
    
    
    /**
     * check if online file has changed since last call
     * 
     * @return bool
     */
    public function hasChanged()
    {
        return $this->objConnection->getMetadata($this->strPath, false, $this->hash);
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
   public function setMetaData($arrMetaData)
   {
       $this->arrCache = $arrMetaData;
   }
}
