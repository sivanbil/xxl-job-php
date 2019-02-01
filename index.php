<?php
/**
 * 第一阶段的实现所有api，在第一阶段时，要考虑之后的扩展性
 * 第二阶段的实现阻塞策略
 * 第三阶段的实现多机部署方案可行性，可能涉及升级
 *
 * @author sivan
 * @description tcp server start
 */
define('DEBUG', true);

define('APP_PATH', __DIR__);

define('CONF_PATH', APP_PATH . '/Conf');

define('LIB_PATH', APP_PATH . '/Lib');

define('CRON_PATH', APP_PATH . '/Cron');

if (PHP_OS == 'WINNT') {
    define("NL", "\r\n");
} else {
    define("NL", "\n");
}

define("BL", "<br />" . NL);

// 注册顶层命名空间到自动载入器
require_once(LIB_PATH . '/Loader.php');
\Lib\Loader::setRootNS('Lib', LIB_PATH);
spl_autoload_register('\\Lib\\Loader::autoload');

// cli模式参数执行
if (\Lib\Cmd::checkArgvValid($argv)) {
    // 命令执行
    \Lib\Cmd::exec($argv);
} else {
    // 操作提示
    \Lib\Cmd::tips();
}
