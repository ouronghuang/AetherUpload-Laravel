## Laravel upload

Forked from [peinhu/AetherUpload-Laravel](https://github.com/peinhu/AetherUpload-Laravel)

## 说明
    
提供 **超大文件** 上传的 Laravel 扩展包

* 支持分组配置
* 支持断线续传
* 支持秒传
* 支持 Laravel 5.1~5.5

> 我们知道，在以前，文件上传采用的是直接传整个文件的方式，这种方式对付一些小文件是没有问题的。而当需要上传大文件时，此种方式不仅操作繁琐，需要修改 WEB 服务器和后端语言的配置，而且会大量占用服务器的内存，导致服务器内存吃紧，严重的甚至传输超时或文件过大无法上传。很显然，普通的文件上传方式已无法满足现在越来越高的要求。  

> 随着技术的发展，如今我们可以利用 HTML5 的分块上传技术来轻松解决这个困扰，通过将大文件分割成小块逐个上传再拼合，来降低服务器内存的占用，突破服务器及后端语言配置中的上传大小限制，可上传任意大小的文件，同时也简化了操作，提供了直观的进度显示。 

![示例页面](http://wx2.sinaimg.cn/mw690/69e23056gy1fho6ymepjlg20go0aknar.gif) 

## 功能特性
- [x] 百分比进度条  
- [x] 文件类型和大小限制  
- [x] 分组配置  
- [x] 自定义中间件   
- [x] 上传完成事件   
- [x] 同步上传 *①*  
- [x] 断线续传 *②*  
- [x] 文件秒传 *③* 

*①：同步上传相比异步上传，在上传带宽足够大的情况下速度稍慢，但同步可在上传同时进行文件的拼合，而异步因文件块上传完成的先后顺序不确定，需要在所有文件块都完成时才能拼合，将会导致异步上传在接近完成时需等待较长时间。同步上传每次只有一个文件块在上传，在单位时间内占用服务器的内存较少，相比异步方式可支持更多人同时上传。*  

*②：断线续传和断点续传不同，断线续传是指遇到断网或无线网络不稳定时，在不关闭页面的情况下，上传组件会定时自动重试，一旦网络恢复，文件会从未上传成功的那个文件块开始继续上传。断线续传在刷新页面或关闭后重开是无法续传的，之前上传的部分已成为无效文件。*  

*③：文件秒传需服务端Redis和客户端浏览器支持(FileReader、File.slice())，两者缺一则秒传功能无法生效。* 

## 安装

1. 安装扩展包

```shell
composer require orh/laravel-upload ~2.0
```

2. Laravel 版本小于 5.5 者需要手动添加服务提供者

```php
// app/config/app.php

'providers' => [
...

AetherUpload\AetherUploadServiceProvider::class,
];
```

3. 发布配置文件

```shell
php artisan vendor:publish --tag=aetherupload-config
```

4. 编辑配置文件，可根据需要配置 `upload_path` 参数

```php
// app/config/aetherupload.php
...

'upload_path' => public_path() . '/attachment',
```

> 注意：`file_extensions` 参数必须配置

5. 发布资源文件

```shell
php artisan vendor:publish --tag=aetherupload
```

* 包含前端资源文件
* 默认文件夹
* 语言文件

6. 在 Linux 相关文件夹必须有相关权限

```shell
chmo 755 -R public/attachment/
```

7. 至此，安装完成

## 用法

### 基本用法

* 示例页面

访问：{host}/aetherupload 可直达示例页面，`debug` 参数必须为 `true`。

* 文件上传
  
参考示例文件注释的部分，在需要上传大文件的页面引入相应文件和代码。可使用自定义中间件来对文件上传进行额外过滤，还可使用上传完成事件对上传的文件进一步处理。

* 分组配置  

在配置文件的 groups 下新增分组，其配置可参照默认组。  

* 自定义中间件

参考 Laravel 文档中间件部分，创建你的中间件并在 `Kernel.php` 中注册，将你注册的中间件名称填入配置文件对应部分，如 `['middleware1','middleware2']`。  

* 上传完成事件

分为上传完成前和上传完成后事件，参考 Laravel 文档事件系统部分，在 `EventServiceProvider` 中注册你的事件和监听器，运行 `php artisan event:generate` 生成事件和监听器，将你注册的事件完整类名填入配置文件对应部分，如 `'App\Events\OrderShipped'`。

### 添加秒传功能（需 Redis 及浏览器支持）

安装 Redis 并启动服务端。

安装 predis 包

```shell
composer require predis/predis
```

在 `.env` 文件中配置 Redis 的相关参数。确保上传页面引入了 `spark-md5.min.js` 文件。

> 提示：在 Redis 中维护了一份与实际资源文件对应的 hash 清单，文件的 md5 哈希值为资源文件的唯一标识符，实际资源文件的增删造成的变化均需要同步到 hash 清单中，否则会产生脏数据，扩展包已包含新增部分，删除（deleteOneHash）则需要使用者自行调用相关方法处理，详情参考 RedisHandler 类。

### 定义的 Artisan 命令

```shell
# 列出所有分组并自动创建对应目录
php artisan aetherupload:groups

# 在 Redis 中重建资源文件的 hash 清单 
php artisan aetherupload:build

# 清除几天前的无效临时文件
php artisan aetherupload:clean
```

## 优化建议

* （推荐）设置每天自动清除无效的临时文件。  

由于上传流程存在意外终止的情况，如在传输过程中强行关闭页面或浏览器，将会导致已产生的文件部分成为无效文件，占据大量的存储空间，我们可以使用 Laravel 的任务调度功能来定期清除它们。  

在 Linux 中运行以下命令

```shell
export EDITOR=vi && crontab -e
```

随后加入以下代码

```
* * * * * php /var/www/project/artisan schedule:run >> /dev/null 2>&1
```

添加调度

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    ...
    
    $schedule->call(function () {
        \AetherUpload\ResourceHandler::cleanUpDir();
    })->daily();
}
```

* 设置每天自动重建 Redis 中的 hash 清单

不恰当的处理和某些极端情况可能使hash清单中出现脏数据，从而影响到秒传功能的准确性，重建hash清单可消除脏数据，恢复与实际资源文件的同步。

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    ...
    
    $schedule->call(function () {
        \AetherUpload\RedisHandler::build();
    })->daily();
}
```

* 提高临时文件读写速度

利用 Linux 的 tmpfs 文件系统，来达到将临时文件放到内存中快速读写的目的。执行以下命令  

```bash
mkdir /dev/shm/tmp
chmod 1777 /dev/shm/tmp
mount --bind /dev/shm/tmp /tmp
```

## 兼容性

<table>
  <th></th>
  <th>IE</th>
  <th>Edge</th>
  <th>Firefox</th>
  <th>Chrome</th>
  <th>Safari</th>
  <tr>
      <td>上传</td>
      <td>10+</td>
      <td>12+</td>
      <td>3.6+</td>
      <td>6+</td>
      <td>5.1+</td>
  </tr>
  <tr>
      <td>秒传</td>
      <td>10+</td>
      <td>12+</td>
      <td>3.6+</td>
      <td>6+</td>
      <td>6+</td>
  </tr>
</table>

## 安全性

AetherUpload 并未使用 Content-Type(Mime-Type) 来检测上传文件类型，而是以白名单的形式直接限制了保存文件扩展名，来阻止上传可执行文件(默认屏蔽了常见的可执行文件扩展名)，因为 Content-Type(Mime-Type) 也可伪造，无法起到应有的作用，安全起见白名单一栏不应留空。  

虽然做了诸多安全工作，但恶意文件上传是防不胜防的，建议确保所有上传目录权限为 755，文件权限为 644。  

## 更新日志  

请参考 [CHANGELOG](./CHANGELOG.md)

## 许可证

使用 GPLv2 许可证, 查看 [LICENCE](./LICENSE) 文件以获得更多信息。
