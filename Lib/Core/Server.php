<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/13
 * Time: 2:21 PM
 */

namespace Lib\Core;

class Server extends \Swoole\Server
{
    public $runningServers = [];
}