<?php
// +---------------------------------------------------
// | 文件安装程序
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2022/11/10 16:09
// +---------------------------------------------------
// | 项目依赖：nelexa/zip : composer require nelexa/zip
// +---------------------------------------------------

namespace fuyelk\install;

use Exception;
use fuyelk\db\Db;
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
    private static $packageSize = 0;

    /**
     * 数据包MD5
     * @var string
     */
    private static $packageMD5 = '';

    /**
     * @var string 数据包解压临时目录
     */
    private static $tempPackageDir = '';

    /**
     * 数据库配置
     * @var bool
     */
    private static $dbConfig = false;

    /**
     * 排除文件扩展名
     * @var array
     */
    private static $excludeExt = [];

    /**
     * 指定文件扩展名
     * @var array
     */
    private static $includeExt = [];

    /**
     * @var array[] 事务列表
     */
    private static $transList = [
        'new_file' => [],      // 新创建的文件
        'backup_file' => [],   // 备份的文件
        'backup_dir' => [],    // 备份的目录
    ];

    /**
     * 设置数据包大小
     * @param int $package_size 单位：字节（Byte）
     */
    public static function setPackageSize(int $package_size)
    {
        self::$packageSize = $package_size;
    }

    /**
     * 设置数据包MD%
     * @param string $md5
     */
    public static function setPackageMd5(string $md5)
    {
        self::$packageMD5 = strtolower($md5);
    }

    /**
     * 设置根目录
     * @param string $path
     * @throws InstallException
     */
    public static function setRootPath(string $path)
    {
        if (!is_dir($path)) {
            throw new InstallException('根目录不存在');
        }
        self::$ROOT_PATH = str_replace('\\', '/', $path);
    }

    /**
     * 设置数据库配置
     * @param array $config ['type','host','database','username','password','port','prefix']
     */
    public static function setDbConfig(array $config)
    {
        self::$dbConfig = true;
        Db::setConfig($config);
    }

    /**
     * 设置排除文件扩展名
     * @param array $exclude
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function setExcludeExt(array $exclude)
    {
        self::$excludeExt = array_map(function ($item) {
            return strtolower($item);
        }, $exclude);
    }

    /**
     * 设置允许的文件扩展名
     * @param array $include
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function setIncludeExt(array $include)
    {
        self::$includeExt = array_map(function ($item) {
            return strtolower($item);
        }, $include);
    }

    /**
     * 下载文件
     * @param string $source
     * @return string
     * @throws InstallException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function download(string $source): string
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
            throw new InstallException($error);
        }

        $info = curl_getinfo($ch);
        curl_close($ch);
        fclose($fp);
        if (filesize($tempFile) != $info['size_download']) {
            throw new InstallException('下载数据不完整，请重新下载');
        }

        if (self::$packageSize && self::$packageSize != filesize($tempFile)) {
            throw new InstallException('数据包大小与更新信息不一致');
        }

        if (self::$packageMD5 && self::$packageMD5 != strtolower(md5_file($tempFile))) {
            throw new InstallException('数据包MD5校验值与更新信息不一致');
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
     * @throws InstallException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function unpack(string $file, string $dir, bool $delPack = true): array
    {
        // 打开压缩包
        $zip = new ZipFile();
        try {
            $zip->openFile($file);
        } catch (ZipException $e) {
            $zip->close();
            if (is_file(unlink($file))) {
                @unlink($file);
            }
            throw new InstallException('压缩包打开失败：' . $e->getMessage());
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 解压
        try {
            $zip->extractTo($dir);
        } catch (ZipException $e) {
            if (is_file(unlink($file))) {
                @unlink($file);
            }
            throw new InstallException('文件解压失败：' . $e->getMessage());
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
     * @param int $removeBaseDirLen [去除路径前缀长度：-1去掉所有前缀，0不去除]
     * @return array
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function showFilesPath(string $path = '', int $removeBaseDirLen = -1): array
    {
        $list = [];
        if (-1 == $removeBaseDirLen) {
            $removeBaseDirLen = mb_strlen($path);
        }
        if (is_dir($path) && ($handle = opendir($path))) {
            if (DIRECTORY_SEPARATOR !== $path[strlen($path) - 1]) {
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
     * 递归删除目录和文件
     * @param string $dir
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function removeDir(string $dir): bool
    {
        $result = false;
        if (!is_dir($dir)) return true;
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
        return $result;
    }

    /**
     * 安装文件
     * @param string $package
     * @param string $root_path
     * @return bool
     * @throws InstallException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function install(string $package, string $root_path = ''): bool
    {
        if (!empty($root_path)) {
            self::setRootPath($root_path);
        }

        if (empty(self::$ROOT_PATH)) {
            throw new InstallException('请先设置根目录');
        }

        // 下载
        $tempFile = is_file($package) ? $package : self::download($package);

        // 设临时目录为文件名去掉后缀
        $ext = pathinfo($tempFile, PATHINFO_EXTENSION);
        self::$tempPackageDir = mb_substr($tempFile, 0, -1 - strlen($ext));
        $files = self::unpack($tempFile, self::$tempPackageDir);

        // 最先执行安装脚本
        if (in_array('/install.php', $files)) {
            $installScripts = require self::$tempPackageDir . '/install.php';
            $scriptIndex = 0;
            $changeIndex = 0;
            foreach ($installScripts as $script) {
                foreach ($script['change'] as $item) {
                    if (!in_array($item['type'] ?? '', ['before', 'after', 'replace', 'delete'])) {
                        self::throwAndRollback('Error installing script: the script operation type is incorrect');
                    }
                    $func = $item['type'];
                    $res = self::$func(self::$ROOT_PATH . $script['file'], $item['search'] ?? '', $item['content'] ?? '');
                    if (false === $res) {
                        if (!empty($item['description'])) {
                            self::throwAndRollback(sprintf('ERROR,install script error: %s', $item['description']));
                        }
                        self::throwAndRollback(sprintf('ERROR,install script error: script index:%d,change index %d', $scriptIndex, $changeIndex));
                    }
                    $changeIndex++;
                }
                $scriptIndex++;
            }
            unset($scriptIndex, $changeIndex);
        }

        // 安装
        foreach ($files as $file) {

            // 检查文件扩展名是否被允许
            if (!self::checkExtension($file)) continue;

            // 跳过安装脚本及据库脚本
            if ('/install.php' == $file || '/install.sql' == $file) continue;

            // 拷贝文件
            self::newFile(self::$tempPackageDir . '/' . $file, self::$ROOT_PATH . $file);
        }

        // 最后执行SQL脚本
        if (in_array('/install.sql', $files)) {
            if (!self::$dbConfig) {
                self::throwAndRollback('请先配置数据库');
            }
            Db::startTrans();
            try {
                $prefix = Db::getConfig('prefix');
                $installSql = file_get_contents(self::$tempPackageDir . '/install.sql');
                $installSql = str_replace('`prefix_', '`' . ($prefix ?: ''), $installSql);
                Db::query($installSql);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                self::throwAndRollback('执行Sql脚本出错：' . $e->getMessage());
            }
        }

        // 删除临时目录
        self::removeDir(self::$tempPackageDir);

        // 清除事务记录
        self::clearTrans();
        return true;
    }

    /**
     * 检查文件扩展名是否被允许
     * @param string $file
     * @return bool
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function checkExtension(string $file): bool
    {
        $extension = strtolower(strrchr($file, '.'));
        if (self::$excludeExt && in_array($extension, self::$excludeExt)) {
            return false;
        }

        if (self::$includeExt && !in_array($extension, self::$excludeExt)) {
            return false;
        }

        return true;
    }

    /**
     * 替换换行标识
     * @param string $content 原文本
     * @param false $toLF [替换为LF]
     * @return array|string|string[]|null
     */
    private static function replaceCRLF(string $content, bool $toLF = false)
    {
        if ($toLF) {
            return preg_replace("/\r\n/", "\n", $content);
        }
        return preg_replace("/\n/", "\r\n", $content);
    }

    /**
     * 拷贝文件
     * @param string $from
     * @param string $to
     * @throws InstallException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    public static function newFile(string $from, string $to)
    {
        if (!is_file($from)) {
            self::throwAndRollback('文件[' . $from . ']不存在');
        }

        if (!is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }
        copy($from, $to);

        self::addToTransList($to, 'new_file');
    }

    /**
     * 前方插入
     * @param string $file 文件绝对路径
     * @param string $search 查找字符
     * @param string $content 插入内容
     * @return bool
     */
    private static function before(string $file, string $search, string $content): bool
    {
        if (!is_file($file)) return false;
        self::addToTransList($file, 'backup_file');
        $context = file_get_contents($file);

        $before = strstr($context, $search, true);
        if (false === $before) {
            $new_search = self::replaceCRLF($search);
            $before = strstr($context, $new_search, true);
            if (false === $before) {
                $new_search = self::replaceCRLF($search, true);
                $before = strpos($context, $new_search);
            }
            $search = $new_search;
        }

        if (false === $before) return false;

        $after = strstr($context, $search);
        file_put_contents($file, $before . $content . $after);
        return true;
    }

    /**
     * 前方插入
     * @param string $file 文件绝对路径
     * @param string $search 查找字符
     * @param string $content 插入内容
     * @return bool
     */
    private static function after(string $file, string $search, string $content): bool
    {
        if (!is_file($file)) return false;
        self::addToTransList($file, 'backup_file');
        $context = file_get_contents($file);

        $index = strpos($context, $search);
        if (false === $index) {
            $new_search = self::replaceCRLF($search);
            $index = strpos($context, $new_search);
            if (false === $index) {
                $new_search = self::replaceCRLF($search, true);
                $index = strpos($context, $new_search);
            }
            $search = $new_search;
        }

        if (false === $index) return false;

        $before = substr($context, 0, $index + strlen($search));
        $after = substr($context, $index + strlen($search));
        file_put_contents($file, $before . $content . $after);
        return true;
    }

    /**
     * 文本替换
     * @param string $file 文件绝对路径
     * @param string $search 查找字符
     * @param string $replace 替换内容
     * @return bool
     */
    private static function replace(string $file, string $search, string $replace): bool
    {
        if (!is_file($file)) return false;
        self::addToTransList($file, 'backup_file');
        $context = file_get_contents($file);
        $new_context = str_replace($search, $replace, $context);
        file_put_contents($file, $new_context);
        return true;
    }

    /**
     * 删除文件
     * @param string $file 文件绝对路径
     * @return bool
     */
    private static function delete(string $file): bool
    {
        if (is_dir($file)) {
            self::addToTransList($file, 'backup_dir');
            return self::removeDir($file);
        }

        if (!is_file($file)) {
            return true;
        }
        self::addToTransList($file, 'backup_file');
        return unlink($file);
    }

    /**
     * 将变更添加到事务列表
     * @param string $file
     * @param string $event ['new_file','backup_file','backup_dir']
     * @return void
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function addToTransList(string $file, string $event)
    {
        // 记录事务
        if (!in_array($file, self::$transList[$event])) {
            // 备份文件
            if ('backup_file' == $event) {
                copy($file, $file . '_install_');
            }

            // 备份目录
            if ('backup_dir' == $event) {
                if (is_dir($file . '_install_')) {
                    self::removeDir($file . '_install_');
                }
                rename($file, $file . '_install_');
            }
            self::$transList[$event][] = $file;
        }
    }

    /**
     * 回滚变更的内容
     * @return void
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function rollback()
    {
        foreach (self::$transList['new_file'] as $file) {
            // 删除新增的文件
            @unlink($file);
        }
        unset($file);

        foreach (self::$transList['backup_file'] as $file) {
            // 删除修改的文件
            if (is_file($file)) {
                @unlink($file);
            }
            // 还原备份的文件
            is_file($file . '_install_') and rename($file . '_install_', $file);
        }
        unset($file);

        foreach (self::$transList['backup_dir'] as $dir) {
            // 还原备份的目录
            is_dir($dir . '_install_') and rename($dir . '_install_', $dir);
        }

        // 清空事务
        self::$transList = [
            'new_file' => [],
            'backup_file' => [],
            'backup_dir' => [],
        ];
    }

    /**
     * 清除事务记录
     * @return void
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function clearTrans()
    {
        // 删除备份文件
        foreach (self::$transList['backup_file'] as $file) {
            if (is_file($file . '_install_')) {
                @unlink($file . '_install_');
            }
        }
        unset($file);

        // 删除备份目录
        foreach (self::$transList['backup_dir'] as $dir) {
            if (is_dir($dir . '_install_')) {
                self::removeDir($dir . '_install_');
            }
        }

        // 清空事务
        self::$transList = [
            'new_file' => [],
            'backup_file' => [],
            'backup_dir' => [],
        ];
    }

    /**
     * 回滚变更，并抛出异常
     * @param string $message
     * @return mixed
     * @throws InstallException
     * @author fuyelk <fuyelk@fuyelk.com>
     */
    private static function throwAndRollback(string $message)
    {
        // 删除数据包临时目录
        if (is_dir(self::$tempPackageDir)) {
            self::removeDir(self::$tempPackageDir);
        }
        self::rollback();
        throw new InstallException($message);
    }
}