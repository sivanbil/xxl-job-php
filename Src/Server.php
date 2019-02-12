<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Src;

use Lib\JobTool;
use Lib\TcpServer;

class Server extends TcpServer
{

    use JobTool;

    // 心跳检测
    public function beat()
    {

    }

    // 忙碌检测
    public function idleBeat($job_id)
    {

    }
}