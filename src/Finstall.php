<?php
// +---------------------------------------------------
// | F平台代码库下载程序
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2022/04/15 14:12
// +---------------------------------------------------

namespace fuyelk\install;

require_once 'InstallException.php';

function dump($content = '', $new_line = true)
{
    echo $content;
    if ($new_line) echo PHP_EOL;
}

class Finstall
{
    private $_error = null;

    /**
     * Cookie文件
     */
    private static $COOKIE_FILE = __DIR__ . '/cookie.cookies';

    /**
     * @var string HOST
     */
    private static $HOST = 'http://version.milinger.com';

    /**
     * 打印欢迎词
     * @return void
     */
    private function welcome()
    {
        dump('欢迎使用');
        dump('请选择操作');
        dump('1. 查看代码库列表');
        dump('2. 登录');
        dump('3. 退出登录');
        dump("请选择：", 0);
    }

    public function __construct()
    {
        $this->welcome();

        $operate = fgets(STDIN);
        if (1 == $operate) {
            $this->libraryList();
            exit();
        }

        if (2 == $operate) {
            $this->login();
            exit();
        }

        if (3 == $operate) {
            $this->logout();
            exit();
        }
    }

    /**
     * http请求
     * @param string $api http地址
     * @param string $method 请求方式
     * @param array $data 请求数据：
     * <pre>
     *  $data = [
     *      'image' => new \CURLFile($filePath),
     *      'access_token' => 'this-is-access-token'
     *       ...
     *  ]
     * </pre>
     * @param bool $checkLogin 检查登录
     * @return array|bool|string
     * @throws InstallException
     */
    public function apiRequest($api, $method = 'GET', $data = [], $checkLogin = true)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_ACCEPT_ENCODING => 'gzip,deflate',
            CURLOPT_URL => $api,
            CURLOPT_CUSTOMREQUEST => strtoupper($method), // 请求方式
            CURLOPT_USERAGENT => 'Mozilla / 5.0 (Windows NT 10.0; Win64; x64)',// 模拟常用浏览器的useragent
            CURLOPT_RETURNTRANSFER => true,     // 获取的信息以文件流的形式返回，而不是直接输出
            CURLOPT_SSL_VERIFYPEER => false,    // https请求不验证证书
            CURLOPT_SSL_VERIFYHOST => false,    // https请求不验证hosts
            CURLOPT_MAXREDIRS => 10,            // 最深允许重定向级数
            CURLOPT_CONNECTTIMEOUT => 10,       // 最长等待连接成功时间
            CURLOPT_TIMEOUT => 50,              // 最长等待响应完成时间
            CURLOPT_COOKIEJAR => self::$COOKIE_FILE, // 设置cookie存储文件
            CURLOPT_COOKIEFILE => self::$COOKIE_FILE // 设置cookie上传文件
        ]);

        $addHeader = [];
        // 发送请求数据
        if ($data) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            array_push($addHeader, 'Content-type:application/json');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $addHeader); // 设置请求头
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new InstallException($err);

        // 解析接口数据
        $responseArr = json_decode($response, true);

        if (false == $responseArr || !isset($responseArr['code'])) {
            throw new InstallException('不支持的数据结构:' . $response);
        }

        if ($checkLogin) {
            // 接口返回未登录标识，则尝试登录一次
            if (401 == intval($responseArr['code']) || 403 == intval($responseArr['code'])) {
                $login_res = $this->login();
                if (!$login_res) {
                    throw new InstallException('身份验证失败');
                }
                // 重发一次当前请求，不再检查登录
                return $this->apiRequest($api, $method, $data, false);
            }
        }

        if (1 != $responseArr['code']) {
            throw new InstallException($responseArr['msg']);
        }

        return $responseArr['data'] ?? '';
    }

    /**
     * 代码库列表
     * @return array|bool|string
     */
    private function libraryList()
    {
        $api = self::$HOST . '/api/Finstall/libraryList';

        try {
            $libraries = self::apiRequest($api, 'POST', []);
        } catch (InstallException $e) {
            dump($e->getMessage());
            exit();
        }

        if (false == $libraries) {
            dump($libraries->getError());
            exit();
        }
        $i = 1;

        dump("+==================================+");
        dump("| 欢迎使用云端库，可使用库列表如下 |");
        dump("+==================================+");
        dump("| 输入对应序号即可下载对应代码库   |");
        dump("+==================================+");

        // 构建可输入项
        $operates = [];
        foreach ($libraries as $library) {
            dump(sprintf("%d. %s", $i, $library['title']));
            $operates[$i++] = [
                'url' => $library['download_url'],
                'need_database' => $library['need_database']
            ];
        }

        // 检查用户输入
        dump('请选择：', false);
        $select = trim(fgets(STDIN));
        while (!is_numeric($select) || !isset($operates[$select])) {
            dump('输入有误，请重新输入');
            dump('请选择：', false);
            $select = trim(fgets(STDIN));
        }

        // 检查安装目录
        dump('请输入安装项目的根目录');
        dump(':', false);
        $root_path = trim(fgets(STDIN));
        while (!is_dir($root_path)) {
            dump('目录不存在，请重新输入');
            dump(':', false);
            $root_path = trim(fgets(STDIN));
        }

        // 检查数据库配置
        $database = [];
        if ('yes' == $operates[$select]['need_database']) {
            dump('请完成数据库配置：');
            $db_conf_list = ['host', 'database', 'username', 'password', 'prefix'];
            foreach ($db_conf_list as $db_conf) {
                echo $db_conf . ':';
                $database[$db_conf] = trim(fgets(STDIN));
            }
            Install::setDbConfig($database);
        }

        // 执行安装
        try {
            Install::install($operates[$select]['url'], $root_path);
        } catch (InstallException $e) {
            dump('安装失败!');
            dump($e->getMessage());
            exit();
        }
        dump('安装完成！');
        exit();
    }

    /**
     * 登录
     * @return bool
     */
    public function login()
    {
        $api = self::$HOST . '/api/Finstall/login';

        echo PHP_EOL . '请登录:' . PHP_EOL;

        echo 'username:';
        $username = trim(fgets(STDIN));

        echo 'password:';
        $password = trim(fgets(STDIN));

        $data = [
            'account' => $username,
            'password' => $password
        ];

        try {
            self::apiRequest($api, 'POST', $data, false);
        } catch (InstallException $e) {
            dump($e->getMessage());
            exit();
        }

        dump('登录成功');
        exit();
    }

    /**
     * 退出登录
     * @return bool
     */
    public function logout()
    {
        $api = self::$HOST . '/api/finstall/logout';

        try {
            $res = self::apiRequest($api, 'GET', [], false);
        } catch (InstallException $e) {
            dump($e->getMessage());
            exit();
        }

        echo "登出结果";
        var_dump($res);

        dump('退出成功');
        exit();
    }

    /**
     * @return string|null
     * @author fuyelk <fuyelk@fuyelk.com>
     * @date 2022/4/15 14:27
     */
    public function getError()
    {
        return $this->_error;
    }
}


new Finstall();
