<?php
// +---------------------------------------------------
// | 配置管理
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/07/01 16:51
// +---------------------------------------------------

namespace fuyelk\install;

Class Config
{
    /**
     * 读取配置信息
     * @param string $path
     * @return bool|mixed
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/07/01 17:13
     */
    public static function get(string $path = '')
    {
        if (empty($path)) {
            $path = __DIR__ . '/conf';
        }

        if (!is_file($path)) return false;

        // PHP文件直接返回执行结果
        if ('.php' == strtolower(self::getExtName($path))) {
            return require $path;
        }

        $data = file_get_contents($path);
        if (empty($data)) return false;

        // JSON文件解析后返回
        if ('.json' == strtolower(self::getExtName($path))) {
            return json_decode($data, true);
        }

        try {
            $config = eval($data);
        } catch (\Exception $e) {
            return false;
        }

        return $config;
    }

    /**
     * 创建配置文件
     * @param array $content 配置内容,$$:开头的值原样输出
     * @param string $path 文件路径,可以是PHP或JSON文件,缺省为conf
     * @param int $tabLevel [左侧制表符数量（建议缺省）]
     * @return string|bool
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/07/01 13:02
     */
    public static function set(array $content, string $path = '', int &$tabLevel = 1)
    {
        if (!is_string($path)) {
            throw new \Exception('配置文件路径有误');
        }

        $str = "";

        if (empty($path)) {
            $path = __DIR__ . '/conf';
        }

        // 原级调用则创建文件头
        if ('false' != $path) {

            // JSON文件直接写入即可
            if ('.json' == strtolower(self::getExtName($path))) {
                $fp = fopen($path, 'w');
                fwrite($fp, json_encode($content, JSON_UNESCAPED_UNICODE));
                fclose($fp);
                return true;
            }

            // PHP文件加文件头
            if ('.php' == strtolower(self::getExtName($path))) {
                $str .= "<?php\n\n";
            }

            $str .= "return\t[\n";
        }

        // 遍历配置信息，逐行拼接
        if (is_array($content)) {
            foreach ($content as $key => $value) {

                // 创建左侧制表符
                $tabLeft = str_pad("\t", $tabLevel, "\t");

                // 定义键
                if (is_string($key)) {
                    $confKey = "'{$key}'";
                } else {
                    $confKey = $key;
                }

                // 值为数组则递归检查
                if (is_array($value) && !empty($value)) {

                    // 创建数组的键及左括号并换行
                    $str .= "{$tabLeft}{$confKey}\t=>\t[\n";

                    // 增加一级左侧缩进
                    $tabLevel++;

                    // 递归获取数组内容
                    $str .= self::set($value, 'false', $tabLevel);

                    // 数组结束回退一级左侧缩进
                    $tabLevel--;

                    // 创建数组右括号并换行
                    $str .= str_pad("\t", $tabLevel, "\t") . "],\n";
                    continue;
                }

                // 值不是数组，则根据值的类型创建值
                if (is_string($value) && preg_match('/^\$\$:/', $value)) {
                    $confValue = mb_substr($value, 3);
                } elseif (is_string($value)) {
                    $value = str_replace('\'', '\\\'', $value);
                    $confValue = "'{$value}'";
                } elseif (is_bool($value)) {
                    $confValue = $value ? "true" : "false";
                } elseif (is_null($value)) {
                    $confValue = "null";
                } elseif (is_array($value) && empty($value)) {
                    $confValue = "[]";
                } else {
                    $confValue = $value;
                }

                // 拼接这一行的值
                $str .= "{$tabLeft}{$confKey}\t=>\t{$confValue},\n";
            }
        }

        // 原级调用结束，则结束文件
        if ('false' != $path) {
            $str .= "];";
            $fp = fopen($path, 'w');
            fwrite($fp, $str);
            fclose($fp);
            return true;
        }

        return $str;
    }

    /**
     * 获取文件扩展名
     * @param string $file
     * @return string
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/07/01 17:56
     */
    private static function getExtName(string $file): string
    {
        $arrExt = explode('.', basename($file));
        if (count($arrExt) > 1) {
            $ext = end($arrExt);
            return '.' . $ext;
        }
        return basename($file);
    }
}