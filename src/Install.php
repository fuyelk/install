<?php
// +---------------------------------------------------
// | 文件安装程序
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/06/28 21:30
// +---------------------------------------------------
// | 项目依赖：nelexa/zip : composer require nelexa/zip
// +---------------------------------------------------

namespace fuyelk\install;

use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;

class Install
{
    /**
     * 根目录
     * @var string
     */
    private static $ROOT_PATH = '';

    /**
     * 数据包大小
     * @var int
     */
    private static $package_size = 0;

    /**
     * 数据包MD5
     * @var string
     */
    private static $package_md5 = '';

    /**
     * 设置数据包大小
     * @param int $package_size 单位：字节（Byte）
     */
    public static function setPackageSize(int $package_size)
    {
        self::$package_size = $package_size;
    }

    /**
     * 设置数据包MD%
     * @param string $md5
     */
    public static function setPackageMd5(string $md5)
    {
        self::$package_md5 = strtolower($md5);
    }

    /**
     * 设置根目录
     * @param string $path
     * @throws \Exception
     */
    public static function setRootPath(string $path)
    {
        if (!is_dir($path)) {
            throw new \Exception('根目录不存在');
        }
        self::$ROOT_PATH = str_replace('\\', '/', $path);
    }

    /**
     * 下载文件
     * @param string $source
     * @return string
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 17:39
     */
    public static function download(string $source)
    {
        // 创建临时文件
        $tempFile = self::$ROOT_PATH . '/installtemp/temp_' . time();
        $tempFile = str_replace('//', '/', $tempFile);
        if (!is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0755, true);
        }
        $fp = fopen($tempFile, 'w');

        // 初始化 cURL 会话
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source); // 远程地址
        curl_setopt($ch, CURLOPT_POST, 0); // 非POST请求
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3000); // 最长等待连接成功时间
        curl_setopt($ch, CURLOPT_FILE, $fp); // 本地路径
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // https请求 不验证证书
        curl_exec($ch);
        if (curl_error($ch)) {
            $error = curl_error($ch);
            fclose($fp);
            curl_close($ch);
            throw new \Exception($error);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);
        fclose($fp);
        if (filesize($tempFile) != $info['size_download']) {
            throw new \Exception('下载数据不完整，请重新下载');
        }

        if (self::$package_size && self::$package_size != filesize($tempFile)) {
            throw new \Exception('数据包大小与更新信息不一致');
        }

        if (self::$package_md5 && self::$package_md5 != strtolower(md5_file($tempFile))) {
            throw new \Exception('数据包MD5校验值与更新信息不一致');
        }

        $content_type = explode('/', $info['content_type']);
        $fileName = $tempFile . '.' . end($content_type);
        rename($tempFile, $fileName);
        return $fileName;
    }

    /**
     * 解压文件
     * @param string $file 压缩包文件
     * @param string $dir 解压目录
     * @param bool $delPack 删除压缩包
     * @return array
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 21:14
     */
    public static function unpack(string $file, string $dir, $delPack = true)
    {
        // 打开压缩包
        $zip = new ZipFile();
        try {
            $zip->openFile($file);
        } catch (ZipException $e) {
            $zip->close();
            throw new \Exception('压缩打开失败：' . $e->getMessage());
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 解压
        try {
            $zip->extractTo($dir);
        } catch (ZipException $e) {
            throw new \Exception('文件解压失败：' . $e->getMessage());
        } finally {
            $zip->close();
        }

        // 删除压缩包
        if ($delPack) unlink($file);

        // 返回文件夹内全部文件列表
        return self::showFilesPath($dir);
    }

    /**
     * 递归获取指定路径下的所有文件路径
     * @param string $path 绝对路径
     * @param int $removeBaseDirLen [移除初始路径长度]
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 19:51
     */
    public static function showFilesPath($path = '', $removeBaseDirLen = -1)
    {
        $list = [];
        if (-1 == $removeBaseDirLen) {
            $removeBaseDirLen = mb_strlen($path);
        }
        if (is_dir($path) && ($handle = opendir($path))) {
            if (DIRECTORY_SEPARATOR !== $path{-1}) {
                $path .= DIRECTORY_SEPARATOR;
            }
            while (false !== ($file = readdir($handle))) {
                if ('.' !== $file && '..' !== $file) {
                    if (is_dir($path . $file)) {
                        $list = array_merge($list, self::showFilesPath($path . $file, $removeBaseDirLen));
                    } else {
                        $list[] = str_replace('\\', '/', mb_substr($path, $removeBaseDirLen) . $file);
                    }
                }
            }
            closedir($handle);
        }
        return $list;
    }

    /**
     * 拷贝文件
     * @param $from
     * @param $to
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 21:10
     */
    public static function copy($from, $to)
    {
        if (!is_file($from)) {
            throw new \Exception('文件[' . $from . ']不存在');
        }

        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }
        copy($from, $to);
    }

    /**
     * 递归删除目录和文件
     * @param string $dir
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 21:25
     */
    public static function removeDir($dir)
    {
        $result = false;
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while ($item = readdir($handle)) {
                    if ($item != '.' && $item != '..') {
                        if (is_dir($dir . '/' . $item)) {
                            self::removeDir($dir . '/' . $item);
                        } else {
                            unlink($dir . '/' . $item);
                        }
                    }
                }
                closedir($handle);
                if (rmdir($dir)) {
                    $result = true;
                }
            }
        }
        return $result;
    }

    /**
     * 安装文件
     * @param string $url
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 18:11
     */
    public static function install(string $url, string $root_path = '')
    {
        if (!empty($root_path)) {
            self::setRootPath($root_path);
        }

        if (empty(self::$ROOT_PATH)) {
            throw new \Exception('请先设置根目录');
        }

        // 下载
        $tempFile = self::download($url);

        // 解压
        $pathinfo = pathinfo($tempFile);
        $tempPack = $pathinfo['dirname'] . '/' . $pathinfo['filename'];
        $files = self::unpack($tempFile, $tempPack);

        // 安装
        foreach ($files as $file) {
            self::copy($tempPack . '/' . $file, self::$ROOT_PATH . $file);
        }

        // 删除临时目录
        self::removeDir($tempPack);

        if (empty(self::showFilesPath($pathinfo['dirname']))) {
            self::removeDir($pathinfo['dirname']);
        }

        return true;
    }
}