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
    private $key = 'VfaOXoPVlm2Le535ctBHRBZVLGS17ix0';

    private $api = 'http://version.milinger.com/api/version/updateCheck';

    private $app_code = '';

    private $current_version = '';

    private $install_path = '';

    private $config_path = '';

    private $updateInfo = [];

    private $_error = null;

    public $new_version_found = false;

    /**
     * Update constructor.
     * @param string $options 配置參數[配置文件目录：config_path,更新安装目录：install_path,[APP编号：$app_code,当前版本：current_version]]
     * @throws \Exception
     */
    public function __construct(array $options)
    {
        if (empty($options['config_path'])) {
            throw new \Exception('未设置配置文件路径');
        }

        $this->config_path = $options['config_path'];

        if (empty($options['install_path'])) {
            throw new \Exception('未设置更新安装目录');
        }

        $this->install_path = $options['install_path'];

        if (!empty($options['app_code']) && !empty($options['current_version'])) {
            $this->app_code = $options['app_code'];
            $this->current_version = $options['current_version'];
        }
        $this->checkConfig();
    }

    /**
     * 检查配置
     * @throws \Exception
     */
    private function checkConfig()
    {
        $config = Config::get($this->config_path);
        if (empty($config)) {
            $template = [
                'app_code' => $this->app_code,
                'current_version' => $this->current_version,
            ];
            Config::set($template, $this->config_path);

            if (empty($this->app_code)) {
                throw new \Exception('首次运行请修改[' . str_replace('\\', '/', $this->config_path) . ']配置文件');
            }
        }

        if (empty($this->app_code)) {
            if (empty($config['app_code'])) {
                throw new \Exception('APP编号配置有误');
            }

            if (empty($config['current_version'])) {
                throw new \Exception('APP版本号配置有误');
            }

            $this->app_code = $config['app_code'];
            $this->current_version = $config['current_version'];
        }

        if (empty($this->app_code)) {
            throw new \Exception('APP编号配置有误');
        }

        if (empty($this->current_version)) {
            throw new \Exception('APP版本号配置有误');
        }
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

        $this->current_version = $this->updateInfo['new_version'];
        $config = [
            'app_code' => $this->app_code,
            'current_version' => $this->current_version,
            'update_time' => date('Y-m-d H:i:s'),
            'update_info' => $this->updateInfo
        ];

        Config::set($config, $this->config_path);
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
    public function getUpdateInfo()
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
    private function request($url, $method = 'GET', $data = [])
    {
        // 约定秘钥，建议每个项目独立设置
        $timestamp = time();

        $addHeader = [
            'REQT:' . $timestamp,
            'SIGN:' . md5($url . http_build_query($data) . $timestamp . $this->key)
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
        $data = [
            'app_code' => $this->app_code,
            'version' => $this->current_version
        ];

        // 请求更新接口
        $response = $this->request($this->api, 'POST', $data);
        if (empty($response)) {
            $this->_error = '未查询到版本信息';
            return false;
        }

        // 校验更新接口信息
        $res = json_decode($response, true);
        if (empty($res) || !isset($res['code']) || !isset($res['msg'])) {
            throw new \Exception('未查询到版本信息');
        }

        // 接口状态不能为0
        if (empty($res['code']) || empty($res['data']) || !isset($res['data']['new_version_found'])) {
            $this->_error = $res['msg'];
            return false;
        }

        $this->updateInfo = $res['data'];
        $this->new_version_found = !empty($res['data']['new_version_found']);

        // 强制更新
        if ($this->new_version_found && !empty($res['data']['enforce'])) {
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

        Install::setRootPath($this->install_path);

        if (isset($info['package_size'])) {
            Install::setPackageSize(intval($info['package_size']));
        }

        if (isset($info['md5'])) {
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