<?php

use Lib\Loader;
use Lib\TcpServer;
/**
 * 执行器执行入口文件
 * User: Sivan
 * Date: 2019/2/1
 * Time: 6:59 PM
 */
date_default_timezone_set("PRC");

define('DEBUG', true);
// php 文件根据不同框架的命令行执行模式进行扩展
define('PHP_RUN_MODE', 'cli');
// 参数传输格式不一样
define('CLI_PARAM_IDENTIFY', '-');

define('APP_PATH', dirname(__DIR__));
// php test脚本目录
define('PHP_TEST_DIR', APP_PATH . '/' . 'Tests');
// 支持shell脚本目录
define('SHELL_SCRIPT_DIR', APP_PATH . '/' . 'Shells');

define('LIB_PATH', APP_PATH . '/Lib');

define('CONF_PATH', APP_PATH . '/Conf');

define('CRON_PATH', APP_PATH . '/Cron');

define('SRC_PATH', APP_PATH . '/Src');

define('RULES_PATH', APP_PATH . '/Rules');

// 注册顶层命名空间到自动载入器
require_once(LIB_PATH . '/Loader.php');
Loader::setRootNS('Lib', LIB_PATH);
Loader::setRootNS('Rules', RULES_PATH);
spl_autoload_register('\\Lib\\Loader::autoload');

$conf = json_decode($argv[1], true);
$cmd = $argv[2];
// init executor server
$server = new TcpServer($conf);
// process 名称设置 mac下安全设置
$server->setProcessName($conf['server']['process_name']);
// 启动server
$server->run($cmd);
