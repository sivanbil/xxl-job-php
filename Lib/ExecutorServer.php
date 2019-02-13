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
        return ['code' => Code::SUCCESS_CODE];
    }

    // 忙碌检测
    public function idleBeat($job_id)
    {
        $is_running_or_has_queue = false;

        $job_status = [];

        if ($job_status) {
            $is_running_or_has_queue = true;
        }

        if ($is_running_or_has_queue) {
            return ['code' => Code::ERROR_CODE, 'msg' => 'job thread is running or has trigger queue.'];
        }

        return ['code' => Code::SUCCESS_CODE];
    }

    // 获取执行日志
    public function log($job_id)
    {

        return [];
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