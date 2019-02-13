<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Lib;

class Server extends TcpServer
{

    use JobTool;

    public function __construct($conf)
    {
        parent::__construct($conf);
    }

    // 心跳检测
    public function beat()
    {
        return Code::SUCCESS_CODE;
    }

    // 忙碌检测
    public function idleBeat($job_id)
    {

    }
}