<?php
/**
 *
 *
 * Date: 29.07.14
 * Time: 21:36
 */

namespace Spark\Upload;


use Spark\Common\Data\ContentType;
use Spark\Utils\FilterUtils;
use Spark\Utils\FileUtils;
use Spark\Utils\StringUtils;

class FileObject {

    private $fileName;
    private $extension;
    private $contentType;
    private $filePath;
    private $size;

    /**
     * @param mixed $contentType
     */
    public function setContentType($contentType) {
        $this->contentType = $contentType;
    }

    /**
     * @return string
     */
    public function getContentType() {
        return $this->contentType;
    }

    /**
     * @param mixed $fileName
     */
    public function setFileName($fileName) {
        $this->fileName = $fileName;
    }

    /**
     * @return mixed
     */
    public function getFileName() {
        return $this->fileName;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size) {
        $this->size = $size;
    }

    /**
     *
     * size in bytes
     * @return mixed
     */
    public function getSize() {
        return $this->size;
    }

    /**
     * @param mixed $extension
     */
    public function setExtension($extension) {
        $this->extension = $extension;
    }

    /**
     * @return mixed
     */
    public function getExtension() {
        return $this->extension;
    }

    /**
     * @param mixed $filePath
     */
    public function setFilePath($filePath) {
        $this->filePath = $filePath;
    }


    public function rename($newFileName) {
        $filePath = str_replace($this->fileName, "", $this->filePath);
        FileUtils::moveFile($this, $filePath . $newFileName);
        $this->fileName = $newFileName;
    }

    /**
     * @return mixed contains file path with its name
     */
    public function getFilePath() {
        return $this->filePath;
    }

    public function isContentType(string $type): bool {
        return StringUtils::equalsIgnoreCase($type, $this->contentType);
    }

    public function moveTo($directoryPath) {
        FileUtils::createFolderIfNotExist($directoryPath);
        FileUtils::moveFileToDir($this, $directoryPath);
        return $this;
    }
}