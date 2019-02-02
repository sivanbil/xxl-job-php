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
    public function heartBeat()
    {

    }

    // 忙碌检测
    public function idleBeat()
    {

    }

    // 关闭
    public function kill()
    {

    }

    // 记录日志
    public function log()
    {

    }

    // 注册到任务调度中心
    public function registerToJobCenter()
    {

    }

    // 运行任务
    public function run()
    {

    }
}