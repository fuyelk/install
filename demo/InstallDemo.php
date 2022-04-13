<?php

use fuyelk\install\Install;
use fuyelk\install\InstallException;

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

try {
    // 设置安装根目录
    Install::setRootPath(__DIR__ . '/temp');

    // 指定安装包MD5
    Install::setPackageMd5('abcdefg');

    // 指定安装包大小
    Install::setPackageSize(1021);

    // 下载
    Install::download('https://github.com/fuyelk/install/archive/refs/heads/master.zip');

    // 安装（安装会执行下载和解压操作）
    Install::install('https://github.com/fuyelk/install/archive/refs/heads/master.zip');

    // 查看路径下文件
    Install::showFilesPath(__DIR__ . 'temp');

} catch (InstallException $e) {
    echo $e->getMessage();
    exit();
}

echo "ok";