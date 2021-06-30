<?php
// +---------------------------------------------------
// | 版本更新程序
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/06/28 21:30
// +---------------------------------------------------

namespace fuyelk\install;

class Update
{
    private static $key = 'VfaOXoPVlm2Le535ctBHRBZVLGS17ix0';

    private $api = 'http://version.milinger.com/api/version/updateCheck';

    private static $app_code = '';

    private static $current_version = '';

    private static $root_path = '';

    private static $config_path = '';

    private $updateInfo = [];

    private $_error = null;

    public $new_version_found = false;

    /**
     * Update constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        self::$config_path = __DIR__ . '/config.php';
        $this->getConfig();
    }

    /**
     * @param string $root_path
     */
    public function setRootPath(string $root_path): void
    {
        self::$root_path = $root_path;
    }

    /**
     * 读取配置
     * @throws \Exception
     */
    private function getConfig()
    {
        $config = require self::$config_path;
        if (empty($config['app_code']) || empty($config['current_version'])) {
            throw new \Exception('配置信息有误');
        }

        self::$app_code = $config['app_code'];
        self::$current_version = $config['current_version'];
    }

    /**
     * 写入配置
     * @throws \Exception
     */
    private function setConfig()
    {
        if (empty($this->updateInfo) || empty($this->updateInfo['new_version'])) {
            throw new \Exception('请先检查更新');
        }

        self::$current_version = $this->updateInfo['new_version'];
        $config = [
            'app_code' => self::$app_code,
            'current_version' => self::$current_version,
            'update_info' => $this->updateInfo
        ];
        $config = var_export($config, true);
        $time = date('Y-m-d H:i:s');
        $content = <<<EOF
<?php

// Update Time {$time}

return {$config};
EOF;
        $fp = fopen(self::$config_path, 'w');
        fwrite($fp, $content);
        fclose($fp);
    }

    /**
     * @return null|string
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * @return array
     */
    public function getUpdateInfo(): array
    {
        return $this->updateInfo;
    }

    /**
     * 网络请求
     * @param string $url 请求地址
     * @param string $method 请求方式：GET/POST
     * @param array $data 请求数据
     * @return bool|string
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/20 08:44
     */
    public static function request($url, $method = 'GET', $data = [])
    {
        // 约定秘钥，建议每个项目独立设置
        $timestamp = time();

        $addHeader = [
            'REQT:' . $timestamp,
            'SIGN:' . md5($url . http_build_query($data) . $timestamp . self::$key)
        ];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_ACCEPT_ENCODING => 'gzip,deflate',
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_REFERER => '',
            CURLOPT_USERAGENT => "Mozilla / 5.0 (Windows NT 10.0; Win64; x64)",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 50,
        ]);

        if ($data) {
            $data = http_build_query($data);
            array_push($addHeader, 'Content-type:application/x-www-form-urlencoded');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $addHeader);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new \Exception($err);
        }
        return $response;
    }

    /**
     * 检查更新
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/28 22:51
     */
    public function updateCheck()
    {
        if (empty(self::$app_code)) {
            throw new \Exception('未设置APP编号');
        }

        if (empty(self::$current_version)) {
            throw new \Exception('未设置当前版本');
        }

        $data = [
            'app_code' => self::$app_code,
            'version' => self::$current_version
        ];

        // 请求更新接口
        $res = self::request($this->api, 'POST', $data);
        if (empty($res)) {
            $this->_error = '未查询到版本信息';
            return false;
        }

        // 校验更新接口信息
        $json = json_decode($res, true);
        if (empty($json) || !isset($json['code']) || !isset($json['msg'])) {
            throw new \Exception('未查询到版本信息');
        }

        // 接口状态不能为0
        if (0 == $json['code'] || empty(json(['data'])) || !isset($json['data']['new_version_found'])) {
            $this->_error = $json['msg'];
            return false;
        }

        $this->updateInfo = $json['data'];
        $this->new_version_found = !empty($json['data']['new_version_found']);

        // 强制更新
        if ($this->new_version_found && !empty($json['data']['enforce'])) {
            return $this->install(true);
        }
        return true;
    }

    /**
     * 安装
     * @param bool $recheck 完成好是否再次检查更新
     * @return bool
     * @throws \Exception
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2021/06/29 13:33
     */
    public function install(bool $recheck = false)
    {
        $info = $this->updateInfo;
        if (empty($info)) {
            $this->_error = '请先检查更新';
            return false;
        }

        if (empty($info['new_version_found'])) {
            $this->_error = '已是最新版本无需更新';
            return false;
        }

        if (empty($info['download_url'])) {
            $this->_error = '没有找到下载地址';
            return false;
        }

        if (empty(self::$root_path)) {
            $this->_error = '请先设置根目录';
            return false;
        }

        Install::setRootPath(self::$root_path);

        if (isset($json['data']['package_size'])) {
            Install::setPackageSize(intval($info['package_size']));
        }

        if (isset($json['data']['md5'])) {
            Install::setPackageMd5($info['md5']);
        }

        Install::install($info['download_url']);

        $this->setConfig();

        if ($recheck) {
            return $this->updateCheck();
        }

        return true;
    }
}