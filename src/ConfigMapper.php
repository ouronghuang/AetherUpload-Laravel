<?php

namespace AetherUpload;

class ConfigMapper
{
    private static $_instance = null;
    private $upload_path;
    private $file_dir;
    private $file_sub_dir;
    private $head_dir;
    private $chunk_size;
    private $file_maxsize;
    private $file_extensions;
    private $middleware_preprocess;
    private $middleware_save_chunk;
    private $middleware_display;
    private $middleware_download;
    private $event_before_upload_complete;
    private $event_upload_complete;

    private function __construct()
    {
        //disallow new instance
    }

    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = (new self())->applyCommonConfig();
        }

        return self::$_instance;
    }

    private function applyCommonConfig()
    {
        $this->upload_path = $this->getConfig('upload_path');
        $this->chunk_size = $this->getConfig('chunk_size');
        $this->head_dir = $this->getConfig('head_dir');
        $this->file_sub_dir = $this->getConfig('file_sub_dir');

        return $this;
    }

    public function applyGroupConfig($group)
    {
        $this->file_dir = $group;
        $this->file_maxsize = $this->getGroupConfig('file_maxsize');
        $this->file_extensions = $this->getGroupConfig('file_extensions');
        $this->middleware_preprocess = $this->getGroupConfig('middleware_preprocess');
        $this->middleware_save_chunk = $this->getGroupConfig('middleware_save_chunk');
        $this->middleware_display = $this->getGroupConfig('middleware_display');
        $this->middleware_download = $this->getGroupConfig('middleware_download');
        $this->event_before_upload_complete = $this->getGroupConfig('event_before_upload_complete');
        $this->event_upload_complete = $this->getGroupConfig('event_upload_complete');

        return $this;
    }

    public static function get($property)
    {
        return self::getInstance()->{$property};
    }

    protected function getConfig($key)
    {
        return config("aetherupload.{$key}");
    }

    protected function getGroupConfig($key)
    {
        return config("aetherupload.groups.{$this->file_dir}.{$key}");
    }
}
