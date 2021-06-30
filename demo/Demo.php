<?php

use fuyelk\install\Update;

$update = new Update();
$update->setRootPath(__DIR__);
$result = false;
try {
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