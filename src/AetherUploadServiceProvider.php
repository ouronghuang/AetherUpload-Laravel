<?php

namespace AetherUpload;

use Illuminate\Support\ServiceProvider;
use AetherUpload\Console\BuildRedisHashesCommand;
use AetherUpload\Console\CleanUpDirectoryCommand;
use AetherUpload\Console\CreateGroupDirectoryCommand;
use AetherUpload\Console\PublishCommand;

class AetherUploadServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function register()
    {
        $this->configure();

        $this->bindCommands();
    }

    /**
     * 合并配置信息
     *
     * @return void
     */
    protected function configure()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/aetherupload.php', 'aetherupload');
    }

    /**
     * 注册命令
     *
     * @return void
     */
    protected function bindCommands()
    {
        $this->app->bind('command.aetherupload.publish', function () {
            return new PublishCommand();
        });

        $this->app->bind('command.aetherupload.build', function () {
            return new BuildRedisHashesCommand();
        });

        $this->app->bind('command.aetherupload.clean', function () {
            return new CleanUpDirectoryCommand();
        });

        $this->app->bind('command.aetherupload.groups', function () {
            return new CreateGroupDirectoryCommand();
        });

        $this->commands('command.aetherupload.publish', 'command.aetherupload.build', 'command.aetherupload.clean', 'command.aetherupload.groups');
    }

    /**
     * 引导服务
     *
     * @return void
     */
    public function boot()
    {
        $this->registerViews();

        $this->offerPublishing();

        $this->registerRoutes();
    }

    /**
     * 加载视图
     *
     * @return void
     */
    protected function registerViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../views', 'aetherupload');
    }

    /**
     * 加载发布资源
     *
     * @return void
     */
    protected function offerPublishing()
    {
        /*$publishes = [
            // 配置文件
            __DIR__ . '/../config/aetherupload.php' => config_path('aetherupload.php'),

            // 前端资源文件
            __DIR__ . '/../assets/aetherupload.js' => public_path('js/aetherupload.js'),
            __DIR__ . '/../assets/spark-md5.min.js' => public_path('js/spark-md5.min.js'),

            // 默认文件夹
            __DIR__ . '/../uploads/aetherupload_file' => config('aetherupload.upload_path') . '/file',
            __DIR__ . '/../uploads/aetherupload_head' => config('aetherupload.upload_path') . '/_head',

            // 语言文件
            __DIR__ . '/../translations/zh-CN/aetherupload.php' => base_path('resources/lang/zh-CN/aetherupload.php'),
            __DIR__ . '/../translations/en/aetherupload.php' => base_path('resources/lang/en/aetherupload.php'),
        ];*/

        $publishes = [
            // 配置文件
            __DIR__ . '/../config/aetherupload.php' => config_path('aetherupload.php')
        ];

        $this->publishes($publishes, 'aetherupload');
    }

    /**
     * 加载路由
     *
     * @return void
     */
    protected function registerRoutes()
    {
        if (!$this->app->routesAreCached()) {
            require __DIR__ . '/../routes/routes.php';
        }
    }
}
