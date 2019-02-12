<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/12
 * Time: 3:24 PM
 */

$client = new \swoole_client(SWOOLE_SOCK_TCP);

if ($client->connect('127.0.0.1', '9501', SWOOLE_KEEP)) {
    echo 1;
} else {
    echo 0;
}

$client1 = new \swoole_client(SWOOLE_SOCK_TCP);

if ($client1->connect('127.0.0.1', '8888', SWOOLE_KEEP)) {
    echo 1;
} else {
    echo 0;
}