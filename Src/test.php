<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/2
 * Time: 3:10 PM
 */
$server = new swoole_server('127.0.0.1', 9504);
$server->on('receive', function() {

});
$server->start();
