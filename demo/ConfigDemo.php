<?php
// +---------------------------------------------------
// | 配置管理程序示例
// +---------------------------------------------------
// | @author fuyelk@fuyelk.com
// +---------------------------------------------------
// | @date 2021/06/28 21:30
// +---------------------------------------------------

use fuyelk\install\Config;

require __DIR__ . '/../src/Config.php';

$config = [
    'composer_name' => 'fuyelk/install',
    'demo_func' => "$$:date('Y-m-d H:i:s')",
    'demo_combine' => "$$:'php version is ' . PHP_VERSION",
    'demo_integer' => 100,
    'demo_integer_zero' => 0,
    'demo_float' => 3.1415,
    'demo_bool_true' => true,
    'demo_bool_false' => false,
    'demo_empty_string' => '',
    'demo_null' => null,
    'demo_array' => [
        'demo_func' => "$$:date('Y-m-d H:i:s')",
        'demo_combine' => "$$:'php version is ' . PHP_VERSION",
        'demo_integer' => 100,
        'demo_integer_zero' => 0,
        'demo_float' => 3.1415,
        'demo_bool_true' => true,
        'demo_bool_false' => false,
        'demo_empty_string' => '',
        'demo_null' => null,
        'demo_array_empty' => [],
    ],
    'demo_array_empty' => [],
];

Config::set($config, __DIR__ . '/temp/config_demo.php');
$got_php = Config::get(__DIR__ . '/temp/config_demo.php');
echo var_export($got_php, true);

Config::set($config, __DIR__ . '/temp/config_demo.json');
$got_json = Config::get(__DIR__ . '/temp/config_demo.json');
echo var_export($got_json, true);

Config::set($config, __DIR__ . '/temp/conf');
$got_default = Config::get(__DIR__ . '/temp/conf');
echo var_export($got_default, true);