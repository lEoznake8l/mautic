<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AssetBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Entity\FormEntity;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Asset
 * @ORM\Table(name="assets")
 * @ORM\Entity(repositoryClass="Mautic\AssetBundle\Entity\AssetRepository")
 * @Serializer\ExclusionPolicy("all")
 */
class Asset extends FormEntity
{

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails", "assetList"})
     */
    private $id;

    /**
     * @ORM\Column(name="title", type="string")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails", "assetList"})
     */
    private $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $description;

    /**
     * @ORM\Column(name="storage_location", type="string", nullable=true)
     */
    private $storageLocation = 'local';

    /**
     * @ORM\Column(name="path", type="string", nullable=true)
     */
    private $path;

    /**
     * @ORM\Column(name="remote_path", type="string", nullable=true)
     */
    private $remotePath;

    /**
     * @ORM\Column(name="original_file_name", type="string", nullable=true)
     */
    private $originalFileName;

    /**
     * @Assert\File
     */
    private $file;

    /**
     * Holds upload directory
     */
    private $uploadDir;

    /**
     * Holds max size of uploaded file
     */
    private $maxSize;

    /**
     * Holds file type (file extension)
     */
    private $fileType;

    /**
     * Temporary location when asset file is beeing updated.
     * We need to keep the old file till we are sure the new
     * one is stored correctly.
     */
    private $temp;

    /**
     * Temporary ID used for file upload and validations
     * before the actual ID is known.
     */
    private $tempId;

    /**
     * Temporary file name used for file upload and validations
     * before the actual ID is known.
     */
    private $tempName;

    /**
     * @ORM\Column(name="alias", type="string")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails", "assetList"})
     */
    private $alias;

    /**
     * @ORM\Column(name="lang", type="string")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $language = 'en';

    /**
     * @ORM\Column(name="publish_up", type="datetime", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $publishUp;

    /**
     * @ORM\Column(name="publish_down", type="datetime", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $publishDown;

    /**
     * @ORM\Column(name="download_count", type="integer")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $downloadCount = 0;

    /**
     * @ORM\Column(name="unique_download_count", type="integer")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $uniqueDownloadCount = 0;

    /**
     * @ORM\Column(name="revision", type="integer")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $revision = 1;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\CategoryBundle\Entity\Category")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails", "assetList"})
     **/
    private $category;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $extension;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $mime;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Serializer\Expose
     * @Serializer\Since("1.0")
     * @Serializer\Groups({"assetDetails"})
     */
    private $size;

    public function __clone()
    {
        $this->id = null;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets file.
     *
     * @param File $file
     */
    public function setFile(File $file = null)
    {
        $this->file = $file;

        // check if we have an old asset path
        if (isset($this->path)) {
            // store the old name to delete after the update
            $this->temp = $this->path;
            $this->path = null;
        }
    }

    /**
     * Get file.
     *
     * @return UploadedFile
     */
    public function getFile()
    {
        // if file is not set, try to find it at temp folder
        if ($this->getStorageLocation() == 'local' && empty($this->file)) {
            $tempFile = $this->loadFile(true);

            if ($tempFile) {
                $this->setFile($tempFile);
            }
        }

        return $this->file;
    }

    /**
     * Set title
     *
     * @param string $title
     *
     * @return Asset
     */
    public function setTitle($title)
    {
        $this->isChanged('title', $title);
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return mixed
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param mixed $extension
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * @return mixed
     */
    public function getMime()
    {
        return $this->mime;
    }

    /**
     * @param mixed $mime
     */
    public function setMime($mime)
    {
        $this->mime = $mime;
    }

    /**
     * Set originalFileName
     *
     * @param string $originalFileName
     *
     * @return Asset
     */
    public function setOriginalFileName($originalFileName)
    {
        $this->isChanged('originalFileName', $originalFileName);
        $this->originalFileName = $originalFileName;

        return $this;
    }

    /**
     * Get originalFileName
     *
     * @return string
     */
    public function getOriginalFileName()
    {
        return $this->originalFileName;
    }

    /**
     * Set storage location
     *
     * @param string $storageLocation
     *
     * @return Asset
     */
    public function setStorageLocation($storageLocation)
    {
        $this->isChanged('storageLocation', $storageLocation);
        $this->storageLocation = $storageLocation;

        return $this;
    }

    /**
     * Get storage location
     *
     * @return string
     */
    public function getStorageLocation()
    {
        if ($this->storageLocation === null) {
            $this->storageLocation = 'local';
        }
        return $this->storageLocation;
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return Asset
     */
    public function setPath($path)
    {
        $this->isChanged('path', $path);
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set remote path
     *
     * @param string $remotePath
     *
     * @return Asset
     */
    public function setRemotePath($remotePath)
    {
        $this->isChanged('remotePath', $remotePath);
        $this->remotePath = $remotePath;

        return $this;
    }

    /**
     * Get remote path
     *
     * @return string
     */
    public function getRemotePath()
    {
        return $this->remotePath;
    }

    /**
     * Set alias
     *
     * @param string $alias
     *
     * @return Asset
     */
    public function setAlias($alias)
    {
        $this->isChanged('alias', $alias);
        $this->alias = $alias;

        return $this;
    }

    /**
     * Get alias
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set publishUp
     *
     * @param \DateTime $publishUp
     *
     * @return Asset
     */
    public function setPublishUp($publishUp)
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    /**
     * Get publishUp
     *
     * @return \DateTime
     */
    public function getPublishUp()
    {
        return $this->publishUp;
    }

    /**
     * Set publishDown
     *
     * @param \DateTime $publishDown
     *
     * @return Asset
     */
    public function setPublishDown($publishDown)
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    /**
     * Get publishDown
     *
     * @return \DateTime
     */
    public function getPublishDown()
    {
        return $this->publishDown;
    }

    /**
     * Set downloadCount
     *
     * @param integer $downloadCount
     *
     * @return Asset
     */
    public function setDownloadCount($downloadCount)
    {
        $this->downloadCount = $downloadCount;

        return $this;
    }

    /**
     * Get downloadCount
     *
     * @return integer
     */
    public function getDownloadCount()
    {
        return $this->downloadCount;
    }

    /**
     * Set revision
     *
     * @param integer $revision
     *
     * @return Asset
     */
    public function setRevision($revision)
    {
        $this->revision = $revision;

        return $this;
    }

    /**
     * Get revision
     *
     * @return integer
     */
    public function getRevision()
    {
        return $this->revision;
    }

    /**
     * Set language
     *
     * @param string $language
     *
     * @return Asset
     */
    public function setLanguage($language)
    {
        $this->isChanged('language', $language);
        $this->language = $language;

        return $this;
    }

    /**
     * Get language
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set category
     *
     * @param \Mautic\CategoryBundle\Entity\Category $category
     *
     * @return Asset
     */
    public function setCategory(\Mautic\CategoryBundle\Entity\Category $category = null)
    {
        $this->isChanged('category', $category);
        $this->category = $category;

        return $this;
    }

    /**
     * Get category
     *
     * @return \Mautic\CategoryBundle\Entity\Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param $prop
     * @param $val
     */
    protected function isChanged($prop, $val)
    {
        $getter  = "get" . ucfirst($prop);
        $current = $this->$getter();

        parent::isChanged($prop, $val);
    }

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Set uniqueDownloadCount
     *
     * @param integer $uniqueDownloadCount
     *
     * @return Asset
     */
    public function setUniqueDownloadCount($uniqueDownloadCount)
    {
        $this->uniqueDownloadCount = $uniqueDownloadCount;

        return $this;
    }

    /**
     * Get uniqueDownloadCount
     *
     * @return integer
     */
    public function getUniqueDownloadCount()
    {
        return $this->uniqueDownloadCount;
    }

    public function preUpload()
    {
        if (null !== $this->getFile()) {

            // set the asset title as original file name if title is missing
            if (null === $this->getTitle()) {
                $this->setTitle($this->file->getClientOriginalName());
            }

            $filename   = sha1(uniqid(mt_rand(), true));
            $extension  = $this->getFile()->guessExtension();

            if (empty($extension)) {
                //get it from the original name
                $extension = pathinfo($this->originalFileName, PATHINFO_EXTENSION);
            }
            $this->path = $filename . '.' . $extension;
        } elseif ($this->getStorageLocation() == 'remote' && $this->getRemotePath() !== null) {
            $fileName = basename($this->getRemotePath());

            $this->setOriginalFileName($fileName);

            // set the asset title as original file name if title is missing
            if (null === $this->getTitle()) {
                $this->setTitle($fileName);
            }
        }
    }

    public function upload()
    {
        // the file property can be empty if the field is not required
        if (null === $this->getFile()) {

            // check for the remote and set type data
            if ($this->getStorageLocation() == 'remote') {
                // get some basic information about the file type
                $fileInfo   = $this->getFileInfo();

                // set the mime and extension column values
                $this->setExtension($fileInfo['extension']);
                $this->setMime($fileInfo['mime']);
                $this->setSize($fileInfo['size']);
            }
            return;
        }

        // move takes the target directory and then the
        // target filename to move to
        $this->getFile()->move($this->getUploadDir(), $this->path);
        $filePath = $this->getUploadDir() . '/' . $this->temp;

        // get some basic information about the file type
        $fileInfo   = $this->getFileInfo();

        // set the mime and extension column values
        $this->setExtension($fileInfo['extension']);
        $this->setMime($fileInfo['mime']);
        $this->setSize($fileInfo['size']);

        // check if we have an old asset
        if (isset($this->temp) && file_exists($filePath)) {
            // delete the old asset
            unlink($filePath);
            // clear the temp asset path
            $this->temp = null;
        }

        // Remove temporary folder and files
        $fs = new Filesystem();
        $fs->remove($this->getAbsoluteTempDir());

        // clean up the file property as you won't need it anymore
        $this->file = null;
    }

    /**
     * Remove a file
     *
     * @param   boolean     $temp >> regular uploaded file or temporary
     * @return  void
     */
    public function removeUpload($temp = false)
    {
        if ($temp) {
            $file = $this->getAbsoluteTempPath();
        } else {
            $file = $this->getAbsolutePath();
        }

        if ($file && file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Returns absolute path to the file.
     *
     * @return string
     */
    public function getAbsolutePath()
    {
        return null === $this->path
            ? null
            : $this->getUploadDir() . '/' . $this->path;
    }

    /**
     * Returns absolute path to temporary file.
     *
     * @return string
     */
    public function getAbsoluteTempPath()
    {
        return null === $this->tempId || null === $this->tempName
            ? null
            : $this->getAbsoluteTempDir() . '/' . $this->tempName;
    }

    /**
     * Returns absolute path to temporary file.
     *
     * @return string
     */
    public function getAbsoluteTempDir()
    {
        return null === $this->tempId
            ? null
            : $this->getUploadDir() . '/tmp/' . $this->tempId;
    }

    /**
     * Returns absolute path to upload dir.
     *
     * @return string
     */
    protected function getUploadDir()
    {
        if ($this->uploadDir) {
            return $this->uploadDir;
        }

        return 'media/files';
    }

    /**
     * Set uploadDir
     *
     * @param string $uploadDir
     *
     * @return Asset
     */
    public function setUploadDir($uploadDir)
    {
        $this->uploadDir = $uploadDir;

        return $this;
    }

    /**
     * Returns maximal uploadable size in bytes.
     * If not set, 6000000 is default
     *
     * @return string
     */
    protected function getMaxSize()
    {
        if ($this->maxSize) {
            return $this->maxSize;
        }

        return 6000000;
    }

    /**
     * Set max size
     *
     * @param string $maxSize
     *
     * @return Asset
     */
    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;

        return $this;
    }

    /**
     * Returns file extension
     *
     * @return string
     */
    public function getFileType()
    {
        if ($this->getStorageLocation() == 'remote') {
            return pathinfo(parse_url($this->getRemotePath(), PHP_URL_PATH), PATHINFO_EXTENSION);
        }

        if ($this->loadFile() === null) {
            return '';
        }

        return $this->loadFile()->guessExtension();
    }

    /**
     * Returns some file info
     *
     * @return array
     */
    public function getFileInfo()
    {
        $fileInfo = array();

        if ($this->getStorageLocation() == 'remote') {
            $ch = curl_init($this->getRemotePath());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_exec($ch);

            // build an array of handy info
            $fileInfo['mime']       = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $fileInfo['extension']  = $this->getFileType();
            $fileInfo['size']       = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            return $fileInfo;
        }

        if ($this->loadFile() === null) {
            return '';
        }

        // return an array of file type info
        $fileInfo['mime']       = $this->loadFile()->getMimeType();
        $fileInfo['extension']  = $this->getFileType();
        $fileInfo['size']       = $this->getSize(false, true);

        return $fileInfo;
    }

    /**
     * Returns file mime type
     *
     * @return string
     */
    public function getFileMimeType()
    {
        if ($this->getStorageLocation() == 'remote') {
            $ch = curl_init($this->getRemotePath());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_exec($ch);
            return curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        }

        if ($this->loadFile() === null) {
            return '';
        }

        $type = $this->loadFile()->getMimeType();

        return $type;
    }

    /**
     * Returns Font Awesome icon class based on file type.
     *
     * @return string
     */
    public function getIconClass()
    {
        $fileType = $this->getFileType();

        // return missing file icon if file type is empty
        if (!$fileType) {
            return 'fa fa-ban';
        }

        $fileTypes = $this->getFileExtensions();

        // Search for icon name by file extension.
        foreach ($fileTypes as $icon => $extensions) {
            if (in_array($fileType, $extensions)) {
                return 'fa fa-file-' . $icon . '-o';
            }
        }

        // File extension is unknown, display general file icon.
        return 'fa fa-file-o';
    }

    /**
     * Decides if an asset is image displayable by browser.
     *
     * @return boolean
     */
    public function isImage()
    {
        $fileType = strtolower($this->getFileType());

        if (!$fileType) {
            return false;
        }

        $imageTypes = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($fileType, $imageTypes)) {
            return true;
        }

        return false;
    }

    /**
     * Returns array of common extensions
     *
     * @return string
     */
    public function getFileExtensions()
    {
        return array(
            'excel' => array(
                'xlsx',
                'xlsm',
                'xlsb',
                'xltx',
                'xltm',
                'xls',
                'xlt'
            ),
            'word' => array(
                'doc',
                'docx',
                'docm',
                'dotx'
            ),
            'pdf' => array(
                'pdf'
            ),
            'audio' => array(
                'mp3'
            ),
            'archive' => array(
                'zip',
                'rar',
                'iso',
                'tar',
                'gz',
                '7z'
            ),
            'image' => array(
                'jpg',
                'jpeg',
                'png',
                'gif',
                'ico',
                'bmp',
                'psd'
            ),
            'text' => array(
                'txt',
                'pub'
            ),
            'code' => array(
                'php',
                'js',
                'json',
                'yaml',
                'xml',
                'html',
                'htm',
                'sql'
            ),
            'powerpoint' => array(
                'ppt',
                'pptx',
                'pptm',
                'xps',
                'potm',
                'potx',
                'pot',
                'pps',
                'odp'
            ),
            'video' => array(
                'wmv',
                'avi',
                'mp4',
                'mkv',
                'mpeg'
            )
        );
    }

    /**
     * Load the file object from it's path.
     *
     * @return null|\Symfony\Component\HttpFoundation\File\File
     */
    public function loadFile($temp = false)
    {
        if ($temp) {
            $path = $this->getAbsoluteTempPath();
        } else {
            $path = $this->getAbsolutePath();
        }

        if (!$path || !file_exists($path)) {
            return null;
        }

        try {
            $file = new File($path);
        } catch (FileNotFoundException $e) {
            $file = null;
        }

        return $file;
    }

    /**
     * Load content of the file from it's path.
     *
     * @return string
     */
    public function getFileContents()
    {
        $path = $this->getFilePath();

        return file_get_contents($path);
    }

    /**
     * Get the path to the file; a URL if remote or full file path if local
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getStorageLocation() == 'remote' ? $this->getRemotePath() : $this->getAbsolutePath();
    }

    /**
     * @return mixed
     */
    public function getDescription ()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription ($description)
    {
        $this->description = $description;
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        // Add a constraint to manage the file upload data
        $metadata->addConstraint(new Assert\Callback(array('\\Mautic\\AssetBundle\\Entity\\Asset', 'validateFile')));
    }

    /**
     * Validator to ensure proper data for the file fields
     *
     * @param Asset                     $object  Entity object to validate
     * @param ExecutionContextInterface $context Context object
     */
    public static function validateFile($object, ExecutionContextInterface $context)
    {
        if ($object->getStorageLocation() == 'local') {
            $tempName = $object->getTempName();

            // If the object is stored locally, we should have file data
            if ($object->isNew() && $tempName === null) {
                $context->buildViolation('mautic.asset.asset.error.missing.file')
                    ->atPath('tempName')
                    ->setTranslationDomain('validators')
                    ->addViolation();
            }

            if ($object->getTitle() === null) {
                $context->buildViolation('mautic.asset.asset.error.missing.title')
                    ->atPath('title')
                    ->setTranslationDomain('validators')
                    ->addViolation();
            }

            // Unset any remote file data
            $object->setRemotePath(null);
        } elseif ($object->getStorageLocation() == 'remote') {
            // If the object is stored remotely, we should have a remote path
            if ($object->getRemotePath() === null) {
                $context->buildViolation('mautic.asset.asset.error.missing.remote.path')
                    ->atPath('remotePath')
                    ->setTranslationDomain('validators')
                    ->addViolation();
            }

            // Unset any local file data
            $object->setPath(null);
        }
    }

    /**
     * Set temporary ID
     *
     * @param string $tempId
     *
     * @return Asset
     */
    public function setTempId($tempId)
    {
        $this->tempId = $tempId;

        return $this;
    }

    /**
     * Get temporary ID
     *
     * @return string
     */
    public function getTempId()
    {
        return $this->tempId;
    }

    /**
     * Set temporary file name
     *
     * @param string $tempName
     *
     * @return Asset
     */
    public function setTempName($tempName)
    {
        $this->tempName = $tempName;

        return $this;
    }

    /**
     * Get temporary file name
     *
     * @return string
     */
    public function getTempName()
    {
        return $this->tempName;
    }

    /**
     * @param bool   $humanReadable
     * @param bool   $forceUpdate
     * @param string $inUnit
     *
     * @return float|string
     */
    public function getSize($humanReadable = true, $forceUpdate = false, $inUnit = '')
    {
        if (empty($this->size) || $forceUpdate) {
            // Try to fetch it
            if ($this->getStorageLocation() == 'remote') {
                $ch = curl_init($this->getRemotePath());
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

                curl_exec($ch);

                $this->setSize(round(curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD)));
            }

            if ($this->loadFile() === null) {
                return 0;
            }

            $this->setSize(round($this->loadFile()->getSize()));
        }

        return ($humanReadable) ? static::convertBytesToHumanReadable($this->size, $inUnit) :  $this->size;
    }

    /**
     * @param mixed $size
     *
     * @return Asset
     */
    public function setSize($size)
    {
        $this->size = $size;

        return $this;
    }


    /**
     * Borrowed from Symfony\Component\HttpFoundation\File\UploadedFile::getMaxFilesize
     *
     * @param $size
     *
     * @return int|string
     */
    static public function convertSizeToBytes($size)
    {
        if ('' === $size) {
            return PHP_INT_MAX;
        }

        $max = ltrim($size, '+');
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = intval($max);
        }

        switch (strtolower(substr($size, -1))) {
            case 't': $max *= 1024;
            case 'g': $max *= 1024;
            case 'm': $max *= 1024;
            case 'k': $max *= 1024;
        }

        return $max;
    }

    /**
     * @param        $size
     * @param string $unit
     *
     * @return string
     */
    static public function convertBytesToHumanReadable($size, $unit = "")
    {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
            return number_format($size / (1 << 30), 2). " GB";
        }
        if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
            return number_format($size / (1 << 20), 2)." MB";
        }
        if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
            return number_format($size / (1 << 10), 2)." KB";
        }

        return number_format($size) . " bytes";
    }
}
