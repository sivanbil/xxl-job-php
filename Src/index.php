<?php
/**
 * 执行器执行入口文件
 * User: Sivan
 * Date: 2019/2/1
 * Time: 6:59 PM
 */

define('APP_PATH', dirname(__DIR__));

define('DEBUG', true);

// 注册顶层命名空间到自动载入器
require_once(LIB_PATH . '/Loader.php');
\Lib\Loader::setRootNS('Lib', LIB_PATH);
spl_autoload_register('\\Lib\\Loader::autoload');

$conf = json_decode($argv[1], true);
// init tcp server
$server = new \Lib\TcpServer($conf);
$server->setProcessName($conf['server']['process_name']);

$server->run();
