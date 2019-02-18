<?php
/**
 * 执行器执行入口文件
 * User: Sivan
 * Date: 2019/2/1
 * Time: 6:59 PM
 */

define('DEBUG', true);

define('APP_PATH', dirname(__DIR__));

define('LIB_PATH', APP_PATH . '/Lib');

define('CONF_PATH', APP_PATH . '/Conf');

define('CRON_PATH', APP_PATH . '/Cron');

define('SRC_PATH', APP_PATH . '/Src');

// 注册顶层命名空间到自动载入器
require_once(LIB_PATH . '/Loader.php');
\Lib\Loader::setRootNS('Lib', LIB_PATH);
spl_autoload_register('\\Lib\\Loader::autoload');

$conf = json_decode($argv[1], true);
// init executor server
$server = new \Lib\TcpServer($conf);
// process 名称设置 mac下安全设置
$server->setProcessName($conf['server']['process_name']);
// 启动server
if ($cmd == 'start') {
    $server->start();
}

if ($cmd == 'shutdown') {
    $server->shutdown();
}
