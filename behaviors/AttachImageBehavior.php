<?php
namespace salopot\attach\behaviors;


use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;

class AttachImageBehavior extends AttachFileBehavior
{

    /**
     * Process types (when create|save image)
     */
    //manual by call processTypeImage
    const PT_MANUAL = 0;
    //call getUrl/getPath(type)
    const PT_GET_DATA = 1;
    //after upload img file
    const PT_UPLOAD = 2;
    //Attention: If rendered through module set INDEX on field "attributeName"
    //when get request to image (through get action)
    const PT_DEMAND = 3;
    //when get request to image (through get action)
    const PT_RENDER = 4; //for types used once (same xFileUpload)
    //process type call getUrl(type) and return base64 page imported link
    const PT_BASE64_ENCODED = 5;


    public $processOn = self::PT_GET_DATA;

    /**
     * @var string Type files extension
     */
    public $format;

    /**
     * @var array Addition options used for GetImageContent
     */
    public $contentOptions;

    public $attributeName = 'image';

    protected $types = array();

    public function getRelativeDir()
    {
        if ($this->_relativeDir === null) {
            $this->_relativeDir = 'upload/images/' . $this->getModelBasedDir();
        }
        return $this->_relativeDir;
    }

    /**
     * @var mixed
     * If value NULL then init default, if value FALSE then use RelativeDir
     */
    protected $_relativeTypeDir;

    public function getRelativeTypeDir()
    {
        if ($this->_relativeTypeDir === null) {
            $this->_relativeTypeDir = 'upload/thumb/' . $this->getModelBasedDir();
        } elseif ($this->_relativeTypeDir === false) {
            return $this->getRelativeDir();
        }
        return $this->_relativeTypeDir;
    }

    public function setRelativeTypeDir($value)
    {
        $this->_relativeTypeDir = is_string($value) ? ltrim(FileHelper::normalizePath($value, self::PS), self::PS) : $value;
    }

    public function setTypes($value)
    {
        foreach ($value as $name => $params) {
            //old version support 2 process type
            if (isset($params['process']) && is_callable($params['process'])) {
                $this->types[$name] = $params;

                //Params priority
                if (!isset($this->types[$name]['processOn']))
                    $this->types[$name]['processOn'] = $this->processOn;

                if (!isset($this->types[$name]['format']) && !empty($this->format))
                    $this->types[$name]['format'] = $this->format;

                if (!isset($this->types[$name]['contentOptions']) && !empty($this->contentOptions)) {
                    $this->types[$name]['contentOptions'] = $this->contentOptions;

                }
            } else
                throw new InvalidConfigException('Process must be callback: function($behavior, $image) { //return converted image }');
        }
    }



    protected function getTypeDirPath()
    {
        return $this->getBasePath() . self::PS . $this->getRelativeTypeDir();
    }

    public function hasType($type) {
        return isset($this->types[$type]);
    }

    protected function getTypeParams($type)
    {
        if (!isset($this->types[$type]))
            throw new Exception('Not supported type: ' . $type);
        return $this->types[$type];
    }


    protected function getExt($type)
    {
        $params = $this->getTypeParams($type);
        return isset($params['format']) ? $params['format'] : pathinfo($this->getAttribute(), PATHINFO_EXTENSION);
    }

    protected function getRelativePath($type = null)
    {
        if ($type !== null) {
            if ($this->getHasAttachLink()) {
                $fileName = pathinfo($this->getAttribute(), PATHINFO_FILENAME);
                $relPath = $this->getRelativeTypeDir() ? $this->getRelativeTypeDir() . self::PS : '';
                return $relPath . self::pathByName($fileName . '_' . $type . '.' . $this->getExt($type), $this->getDirectoryLevel());
            } else
                return false;
        } else
            return parent::getRelativePath();
    }


    protected function getTypeUrl($type)
    {
        return $this->getHasAttachLink() ? $this->getBaseUrl() . self::PS . $this->getRelativePath($type) : false;
    }

    protected function getTypePath($type)
    {
        if ($this->getHasAttachLink()) {
            $path = $this->getBasePath() . self::PS . $this->getRelativePath($type);
            if (self::PS !== DIRECTORY_SEPARATOR) {
                $path = str_replace(self::PS, DIRECTORY_SEPARATOR, $path);
            }
            return $path;
        } else
            return false;
    }

    public function clearAttachType($type)
    {
        if ($this->getHasAttachLink()) {
            $path = $this->getTypePath($type);
            if (file_exists($path)) @unlink($path);
        }
    }

    public function clearAttachTypes()
    {
        if ($this->getHasAttachLink()) {
            foreach (array_keys($this->types) as $type) {
                $path = $this->getTypePath($type);
                if (file_exists($path))
                    @unlink($path);
            }
            if ($this->_relativeTypeDir !== null && isset($path)) {
                $this->clearEmptyLevelPath(dirname($path));
            }
        }
    }

    public function clearAttachData()
    {
        $this->clearAttachTypes();
        parent::clearAttachData();
    }

    public static function getExtensionByMimeType($mimeType) {
        $ext = parent::getExtensionByMimeType($mimeType);
        if (in_array($ext, ['jpe', 'jpeg'])) $ext = 'jpg'; //jpeg for gd
        return $ext;
    }


    protected function attachUploadedFile()
    {
        if (parent::attachUploadedFile()) {
            foreach ($this->types as $type => $params) {
                if ($params['processOn'] == self::PT_UPLOAD) {
                    $this->processTypeImage($type);
                }
            }
            return true;
        } else
            return false;
    }


    public function processTypeImage($type, $save = true, $overwrite = false) {
        $params = $this->getTypeParams($type);
        $destination = $this->getTypePath($type);
        if ($overwrite || !file_exists($destination)) {
            $source = $this->getPath();
            if(!file_exists($source)) {
                Yii::error('Not found base path: "'. $source.'"');
                return null;
            }
            $image = call_user_func($this->getImageLoaderCallback(), $this, $source);
            if (($image = call_user_func($params['process'], $this, $image)) === null)
                throw new InvalidConfigException('Process property must return image instance');

            $contentOptions = isset($params['contentOptions']) ? $params['contentOptions'] : [];
            $content = call_user_func($this->getImageContentCallback(), $this, $type, $image, $contentOptions);
            if ($save) {
                FileHelper::createDirectory(dirname($destination));
                if (@file_put_contents($destination, $content) === false) {
                    throw new Exception('Save operation failed');
                }
            }
            return $content;
        }
    }


    protected $_imageLoaderCallback = null;

    public function setImageLoaderCallback($func)
    {
        if (is_callable($func))
            $this->_imageLoaderCallback = $func;
        else
            throw new InvalidConfigException('Must be callback: function($behavior, $path) { //return image resource }');
    }

    protected function getImageLoaderCallback()
    {
        if ($this->_imageLoaderCallback !== null)
            return $this->_imageLoaderCallback;
        else {
            return function ($behavior, $path) {
                return \yii\imagine\Image::getImagine()->open($path);
            };
        }
    }


    protected $_imageContentCallback = null;

    public function setImageContentCallback($func)
    {
        if (is_callable($func))
            $this->_imageContentCallback = $func;
        else
            throw new InvalidConfigException('Must be callback: function($behavior, $type, $image, $contentOptions) { //return image resource }');
    }

    public function getImageContentCallback()
    {
        if ($this->_imageContentCallback !== null)
            return $this->_imageContentCallback;
        else
            return function ($behavior, $type, $image, $contentOptions) {
                $ext = $behavior->getExt($type);
                return $image->get($ext, $contentOptions);
            };
    }


    public function getUrl($type = null)
    {
        if ($type !== null) {
            $params = $this->getTypeParams($type);
            $url = $this->getTypeUrl($type);
            if ($url) {
                if (($params['processOn'] == self::PT_GET_DATA)) {
                    $this->processTypeImage($type);
                } elseif($params['processOn'] == self::PT_BASE64_ENCODED) {
                    $mimeType = FileHelper::getMimeTypeByExtension($this->getRelativePath($type));
                    return 'data:' . $mimeType. ';base64,' . base64_encode($this->processTypeImage($type, false));
                }
                return $url;
            }
        } else
            return parent::getUrl();
    }


    public function getPath($type = null)
    {
        if ($type !== null) {
            $params = $this->getTypeParams($type);
            $path = $this->getTypePath($type);
            if ($path && ($params['processOn'] == self::PT_GET_DATA))
                $this->processTypeImage($type);
            return $path;
        } else
            return parent::getPath();
    }

    public function getContent($type = null)
    {
        if ($type !== null) {
            $destination = $this->getTypePath($type);
            if ($destination === false) return false;
            if (!file_exists($destination)) {
                $params = $this->getTypeParams($type);
                return $this->processTypeImage($type, $params['processOn'] == self::PT_GET_DATA);
            } else {
                return file_get_contents($destination);
            }
        } else {
            $source = $this->getPath();
            return file_get_contents($source);
        }
    }

    public function render($type = null)
    {
        $fileName = $this->getTypePath($type);
        if ($fileName === false) return null;
        $mimeType = FileHelper::getMimeTypeByExtension($fileName);
        header('Content-type: ' . $mimeType);
        if ($type !== null) {
            if (!file_exists($fileName)) {
                $params = $this->getTypeParams($type);
                echo $this->processTypeImage($type, $params['processOn'] == self::PT_DEMAND);
            } else {
                return readfile($fileName);
            }
        } else {
            return readfile($fileName);
        }
    }


}