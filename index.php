<?php
/**
 * @author sivan
 * @description tcp server start
 */
namespace Job;

define('DEBUG', true);

define('APP_PATH', __DIR__);

define('CONF_PATH', APP_PATH . '/Conf');

define('Lib_PATH', APP_PATH . '/Lib');

define('CRON_PATH', APP_PATH . '/Cron');

/**
 * 第一阶段的实现所有api，在第一阶段时，要考虑之后的扩展性
 * 第二阶段的实现阻塞策略
 * 第三阶段的实现多机部署方案可行性，可能涉及升级
 */

