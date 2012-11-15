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
        if(is_array($arrMetaData)) {
            $this->setMetaData($arrMetaData, true);
            //$this->hasChanged();
            return;
        }

        if(!$this->isMetaCached) {
            $this->getMetaData();
            return;
        }
        
        // load cached file informations
        $arrCache = unserialize(Api\CloudCache::get($this->cacheMetaKey));
        $this->arrCache = $arrCache;
        //$this->hasChanged();
        
        $this->blnMetaDataLoaded = true;        
        return;
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
                $this->arrCache[$strKey] = sprintf('/%s/%s',
                    DropboxApi::DROPBOX,
                    $this->strPath
                );                 
                break;
                
            case 'cacheMetaKey':
                $objApi = Api\CloudApiManager::getApi(DropboxApi::DROPBOX);
                $this->arrCache[$strKey] = sprintf('/%s/%s.meta',
                    DropboxApi::DROPBOX,
                    $this->strPath
                );                 
                break;
                
            case 'cacheThumbnailKey':
                $arrPathInfo = pathinfo($this->strPath);
                
                $this->arrCache[$strKey] = sprintf(
                    '%s/%s/%s_large.%s.jpg', 
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
            case 'childrenLoaded':
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
     * cache meta file
     */
    protected function cacheMetaFile()
    {
        // do not cache isCached and isMetaCached in files
        $arrCache = $this->arrCache;
        
        if(isset($arrCache['isCached'])) {
            unset($arrCache['isCached']);
        
        }
        
        if(isset($arrCache['isMetaCached'])) {
            unset($arrCache['isMetaCached']);
        }
        
        if(isset($arrCache['downloadUrl'])) {
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
        
        if($blnReturnNode) {         
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
        if(is_array($this->arrChildren)) {
            return $this->arrChildren;
        }
        
        $this->arrChildren = array();
        
        if(!is_array($this->children)) {
            // children were not loaded before so force loading them
            if(!$this->childrenLoaded) {
                $this->getMetaData(true);
            }
            return $this->arrChildren;
        }
        
        foreach ($this->children as $strChild) {
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
        if(($this->blnMetaDataLoaded == true && $blnLoadChildren == null ) || ($blnLoadChildren == true && $this->childrenLoaded)) {
            return;
        } 
        
        $blnLoadChildren = ($blnLoadChildren == null) ? $this->blnLoadChildren : $blnLoadChildren;
        $arrMetaData = $this->objConnection->getMetaData($this->strPath, $blnLoadChildren);                    
                
        $this->arrCache['isFile'] = !$arrMetaData['is_dir'];
        $this->arrCache['isDir'] = $arrMetaData['is_dir'];
        $this->arrCache['hash'] = $arrMetaData['hash'];
        $this->arrCache['hasThumbnail'] = $arrMetaData['thumb_exists'];
        $this->arrCache['path'] = $arrMetaData['path'];
        $this->arrCache['root'] = $arrMetaData['root'];
        $this->arrCache['size'] = $arrMetaData['bytes'];
        $this->arrCache['childrenLoaded'] = $blnLoadChildren;
        
        if($arrMetaData['contents']) {
            foreach($arrMetaData['contents'] as $arrChild) {
                $objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
                
                $this->arrChildren[$arrChild['path']] =  $objChild;
                $this->arrCache['children'][] = $arrChild['path'];
            }             
        }
        
        if(isset($arrMetaData['rev'])) {
            $this->arrCache['version'] = $arrMetaData['rev'];
        }
        
        if(isset($arrMetaData['modified'])) {
            $this->arrCache['modified'] = $arrMetaData['modified'];
        }

        $this->cacheMetaFile();  
        $this->blnMetaDataLoaded = true; 
    }
    
    
    /**
     * get path to thumbnail
     * 
     * @return string
     */
    public function getThumbnail()
    {
        if(!$this->hasThumbnail) {
            return false;
        }       

        if (!Api\CloudCache::isCached($this->cacheThumbnailKey)) {

            $strContent = $this->objConnection->getThumbnail($this->strPath, $strSize);
            Api\CloudCache::cache($this->cacheThumbnailKey, $strContent);         
        }
        
        return Api\CloudCache::getPath($this->cacheThumbnailKey);
    }
    
    
    /**
     * check if online file has changed since last call
     * 
     * @return bool
     */
    public function hasChanged()
    {
        if($this->blnHasChanged !== null) {
            return $this->blnHasChanged;
        }
        
        if($this->isDir) {
            $this->blnHasChanged = !$this->objConnection->getMetadata($this->strPath, false, $this->hash);
            return $this->blnHasChanged;
        }
        
        // have to check if parent has changed
        $strParent = dirname($this->strPath);
        $objParent = $this->objApi->getNode($strParent, false);
        
        // parent hash has not changed so child did not changed also
        if(!$objParent->hasChanged()) {
            $this->blnHasChanged = false;
            return $this->blnHasChanged;
        }        
        
        $strOldVersion = $this->version;
        
        $this->blnMetaDataLoaded = false;
        
        // if file has changed also meta data is reloaded. 
        // so there will be no need to do it again manually         
        $this->getMetaData(false);
        
        // compare versioins        
        if($strOldVersion != $this->version) {         
            $this->blnHasChanged = true;
            return $this->blnHasChanged;                      
        }
        
        // delete thumbnail so it has to created again            
        if($this->hasThumbnail) {
            Api\CloudCache::delete($this->cacheThumbnailKey);
        }
        
        $this->blnHasChanged = false;
        return $this->blnHasChanged;
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
    public function setMetaData($arrMetaData, $blnMatchKeys=false)
    {
        // simply pass by element
        if(!$blnMatchKeys) {
            $this->arrCache = $arrMetaData;
            return;
        }
        
        // set default value
        $this->arrCache['childrenLoaded'] = false;
        
        // match keys because meta data is in dropbox style
        foreach ($arrMetaData as $strKey => $mxdValue) {
            switch($strKey) {
                case 'is_dir':
                    $this->arrCache['isFile'] = !$mxdValue;
                    $this->arrCache['isDir'] = $mxdValue;
                    break;
                    
                case 'bytes':
                    $this->arrCache['size'] = $mxdValue;
                    break;
                    
                case 'children':
                    $this->arrCache['children'] = $mxdValue;
                    $this->arrCache['childrenLoaded'] = true;
                    break;
                    
                case 'contents':
                    foreach($mxdValue as $arrChild) {
                        $objChild = $this->objApi->getNode($arrChild['path'], false, $arrChild);
                        
                        $this->arrChildren[$arrChild['path']] =  $objChild;
                        $this->arrCache['children'][] = $arrChild['path'];
                    }   
                    $this->arrCache['childrenLoaded'] = true;
                    break;
                
                case 'rev':
                    $this->arrCache['version'] = $mxdValue;
                    break;
                        
                case 'thumb_exists':
                    $this->arrCache['hasThumbnail'] = $mxdValue;
                    break;                   
                                    
                case 'cacheKey':
                case 'cacheMetaKey':                
                case 'extension':
                case 'hash':
                case 'hasThumbnail':
                case 'icon':
                case 'isFile':
                case 'isDir':
                case 'mime':
                case 'modified':
                case 'root':
                case 'size':
                case 'cacheThumbnailKey':
                case 'version':
                case 'path':
                    $this->arrCache[$strKey] = $mxdValue;        
                    break;
            }
        }

        $this->cacheMetaFile();
        $this->blnMetaDataLoaded = true;   
    }
}
