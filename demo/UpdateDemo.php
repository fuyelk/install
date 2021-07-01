<?php
// +---------------------------------------------------
// | 版本更新程序示例
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/06/28 21:30
// +---------------------------------------------------

use fuyelk\install\Update;

require __DIR__ . '/../src/Update.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Install.php';

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

$result = false;
try {
    $update = new Update();
    $result = $update->updateCheck();
} catch (\Exception $e) {
    var_dump($e->getMessage());
    exit();
}

if ($result) {
    if ($update->new_version_found) {
        try {
            $update->install();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        var_dump('更新成功');
        exit();
    }
    var_dump(['msg' => '当前已是最新版本', 'info' => $update->getUpdateInfo()]);
    exit();
}

var_dump($update->getError());
exit();