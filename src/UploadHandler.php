<?php

namespace AetherUpload;

class UploadHandler extends \Illuminate\Routing\Controller
{
    private $receiver;

    public function __construct(Receiver $receiver)
    {
        $this->receiver = $receiver;

        ConfigMapper::getInstance()->applyGroupConfig(request('group'));

        $preprocess = ConfigMapper::get('middleware_preprocess');

        if (count($preprocess)) {
            $this->middleware($preprocess)->only('preprocess');
        }

        $saveChunk = ConfigMapper::get('middleware_save_chunk');

        if (count($saveChunk)) {
            $this->middleware($saveChunk)->only('saveChunk');
        }
    }

    /**
     * preprocess the upload request
     * @return \Illuminate\Http\JsonResponse
     */
    public function preprocess()
    {
        $fileName = request('file_name', 0);
        $fileSize = request('file_size', 0);
        $fileHash = request('file_hash', 0);

        $result = [
            'error' => 0,
            'chunkSize' => ConfigMapper::get('chunk_size'),
            'subDir' => ConfigMapper::get('file_sub_dir'),
            'uploadBaseName' => '',
            'uploadExt' => '',
            'savedPath' => '',
        ];

        if (!($fileName && $fileSize)) {
            return Responser::reportError(trans('aetherupload.invalid_file_params'));
        }

        if ($error = $this->filterBySize($fileSize)) {
            return Responser::reportError($error);
        }

        if ($error = $this->filterByExt($uploadExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)))) {
            return Responser::reportError($error);
        }
        # 检测是否可以秒传
        if ($fileHash && RedisHandler::hashExists($fileHash)) {
            $result['savedPath'] = RedisHandler::getFilePathByHash($fileHash);

            return Responser::returnResult($result);
        }
        # 创建子目录
        if (!is_dir($uploadFileSubFolderPath = $this->receiver->getUploadFileSubFolderPath())) {
            @mkdir($uploadFileSubFolderPath, 0755, true);
        }
        # 预创建文件
        if ($error = $this->receiver->createFile($uploadBaseName = $this->receiver->generateTempFileName(), $uploadExt)) {
            return Responser::reportError($error);
        }

        $result['uploadExt'] = $uploadExt;
        $result['uploadBaseName'] = $uploadBaseName;

        return Responser::returnResult($result);
    }

    /**
     * handle and save the uploaded data
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveChunk()
    {
        $this->receiver->chunkTotalCount = request('chunk_total', 0);# 分片总数
        $this->receiver->chunkIndex = request('chunk_index', 0);# 当前分片号
        $this->receiver->uploadBaseName = request('upload_basename', 0);# 文件临时名
        $this->receiver->uploadExt = request('upload_ext', 0); # 文件扩展名
        $this->receiver->file = request()->file('file', 0);# 文件
        $subDir = request('sub_dir', 0);# 子目录名
        $fileName = request('file_name', 0);
        $this->receiver->uploadHead = $this->receiver->getUploadHeadPath();
        $this->receiver->uploadPartialFile = $this->receiver->getUploadPartialFilePath($subDir);
        $result = [
            'error' => 0,
            'savedPath' => '',
        ];

        if (!($this->receiver->chunkTotalCount && $this->receiver->chunkIndex && $this->receiver->uploadExt && $this->receiver->uploadBaseName && $subDir)) {
            return Responser::reportError(trans('aetherupload.invalid_chunk_params'), true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
        }
        # 防止被人为跳过验证过程直接调用保存方法，从而上传恶意文件
        if (!is_file($this->receiver->uploadPartialFile)) {
            return Responser::reportError(trans('aetherupload.invalid_operation'), true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
        }

        if ($this->receiver->file->getError() > 0) {
            return Responser::reportError($this->receiver->file->getErrorMessage(), true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
        }

        if (!$this->receiver->file->isValid()) {
            return Responser::reportError(trans('aetherupload.http_post_only'), true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
        }
        # 头文件指针验证，防止断线造成的重复传输某个文件块
        if (@file_get_contents($this->receiver->uploadHead) != $this->receiver->chunkIndex - 1) {
            return Responser::returnResult($result);
        }
        # 写入数据到预创建的文件
        if ($error = $this->receiver->writeFile()) {
            return Responser::reportError($error, true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
        }
        # 判断文件传输完成
        if ($this->receiver->chunkIndex === $this->receiver->chunkTotalCount) {
            @unlink($this->receiver->uploadHead);
            # 触发上传完成前事件
            if (!empty($beforeUploadCompleteEvent = ConfigMapper::get('event_before_upload_complete'))) {
                event(new $beforeUploadCompleteEvent($this->receiver));
            }

            if (!($result['savedPath'] = $this->receiver->renameTempFile($fileName))) {
                return Responser::reportError(trans('aetherupload.rename_file_fail'), true, $this->receiver->uploadHead, $this->receiver->uploadPartialFile);
            }

            RedisHandler::setOneHash(pathinfo($this->receiver->savedPath, PATHINFO_FILENAME), $this->receiver->savedPath);
            # 触发上传完成事件
            if (!empty($uploadCompleteEvent = ConfigMapper::get('event_upload_complete'))) {
                event(new $uploadCompleteEvent($this->receiver));
            }

        }

        return Responser::returnResult($result);
    }

    /**
     * @param $fileSize
     * @return bool|string
     */
    public function filterBySize($fileSize)
    {
        $MAXSIZE = ConfigMapper::get('file_maxsize') * 1000 * 1000;
        # 文件大小过滤
        if ($MAXSIZE != 0 && $fileSize > $MAXSIZE) {
            return trans('aetherupload.invalid_file_size');
        }

        return false;
    }

    /**
     * @param $uploadExt
     * @return bool|string
     */
    public function filterByExt($uploadExt)
    {
        $EXTENSIONS = ConfigMapper::get('file_extensions');
        # 文件类型过滤
        if (($EXTENSIONS != '' && !in_array($uploadExt, explode(',', $EXTENSIONS))) || in_array($uploadExt, static::getDangerousExtList())) {
            return trans('aetherupload.invalid_file_type');
        }

        return false;
    }

    /**
     * get the extensions that may harm a server
     * @return array
     */
    private static function getDangerousExtList()
    {
        return ['php', 'part', 'html', 'shtml', 'htm', 'shtm', 'js', 'jsp', 'asp', 'java', 'py', 'sh', 'bat', 'exe', 'dll', 'cgi', 'htaccess', 'reg', 'aspx', 'vbs'];
    }


}
