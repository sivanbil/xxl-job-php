<?php
/**
 * 提供给任务调度中心几个api 【run -> trigger, beat, idleBeat, log, kill】
 * User: sivan
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Lib;

class ExecutorServer extends TcpServer
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

    // 获取执行日志
    public function log($job_id)
    {

    }

    // 终止任务
    public function kill($job_id)
    {

    }

    // 触发任务运行
    public function trigger($params)
    {

    }
}