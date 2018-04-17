<?php

namespace AetherUpload;

class Receiver
{
    public $uploadHead;
    public $uploadPartialFile;
    public $chunkIndex;
    public $chunkTotalCount;
    public $file;
    public $uploadExt;
    public $uploadBaseName;
    public $savedPath;

    /**
     * filter and create the file
     * @param $uploadBaseName
     * @param $uploadExt
     * @return array|bool|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function createFile($uploadBaseName, $uploadExt)
    {
        $uploadPartialFile = $this->generateUploadPartialFilePath($uploadBaseName, $uploadExt);
        $uploadHead = $this->generateUploadHeadPath($uploadBaseName);

        if (!(@touch($uploadPartialFile) && @touch($uploadHead))) {
            return trans('aetherupload.create_file_fail');
        }

        return false;
    }

    /**
     * write data to the existing file
     */
    public function writeFile()
    {
        # 写入上传文件内容
        if (@file_put_contents($this->uploadPartialFile, @file_get_contents($this->file->getRealPath()), FILE_APPEND) === false) {
            return trans('aetherupload.write_file_fail');
        }
        # 写入头文件内容
        if (@file_put_contents($this->uploadHead, $this->chunkIndex) === false) {
            return trans('aetherupload.write_head_fail');
        }

        return false;
    }

    public function renameTempFile($fileName = '')
    {
        $savedFileHash = $this->generateSavedFileHash($this->uploadPartialFile);

        if (RedisHandler::hashExists($savedFileHash)) {
            $this->savedPath = RedisHandler::getFilePathByHash($savedFileHash);
        } else {
            $fileName = $fileName ? date('His') . '_' . $fileName : $savedFileHash . '.' . $this->uploadExt;

            $this->savedPath = ConfigMapper::get('file_dir') . '/' . ConfigMapper::get('file_sub_dir') . '/' . $fileName;

            if (!@rename($this->uploadPartialFile, ConfigMapper::get('upload_path') . '/' . $this->savedPath)) {
                return false;
            }
        }

        return $this->savedPath;
    }

    public function generateUploadPartialFilePath($uploadBaseName, $uploadExt)
    {
        return ConfigMapper::get('upload_path') . '/' . ConfigMapper::get('file_dir') . '/' . ConfigMapper::get('file_sub_dir') . '/' . $uploadBaseName . '.' . $uploadExt . '.part';
    }

    public function getUploadPartialFilePath($subDir)
    {
        return ConfigMapper::get('upload_path') . '/' . ConfigMapper::get('file_dir') . '/' . $subDir . '/' . $this->uploadBaseName . '.' . $this->uploadExt . '.part';
    }

    public function generateUploadHeadPath($uploadBaseName)
    {
        return ConfigMapper::get('upload_path') . '/' . ConfigMapper::get('head_dir') . '/' . $uploadBaseName . '.head';
    }

    public function getUploadHeadPath()
    {
        return ConfigMapper::get('upload_path') . '/' . ConfigMapper::get('head_dir') . '/' . $this->uploadBaseName . '.head';
    }

    public function getUploadFileSubFolderPath()
    {
        return ConfigMapper::get('upload_path') . '/' . ConfigMapper::get('file_dir') . '/' . ConfigMapper::get('file_sub_dir');
    }

    protected function generateSavedFileHash($filePath)
    {
        return md5_file($filePath);
    }

    public function generateTempFileName()
    {
        return time() . mt_rand(100, 999);
    }
}
