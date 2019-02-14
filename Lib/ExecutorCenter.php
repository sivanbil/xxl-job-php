<?php
/**
 * 提供给任务调度中心几个api 【run -> trigger, beat, idleBeat, log, kill】
 * User: sivan
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Lib;

class ExecutorCenter
{

    use JobTool;


    /**
     * 心跳检测
     *
     * @return array
     */
    public static function beat()
    {
        return ['code' => Code::SUCCESS_CODE];
    }

    /**
     * 闲时检测
     *
     * @param $job_id
     * @return array
     */
    public static function idleBeat($job_id, Table $table)
    {
        $is_running_or_has_queue = false;

        $job_status = JobExcutor::loadJob($job_id, $table);

        if ($job_status) {
            $is_running_or_has_queue = true;
        }

        if ($is_running_or_has_queue) {
            return ['code' => Code::ERROR_CODE, 'msg' => 'job thread is running or has trigger queue.'];
        }

        return ['code' => Code::SUCCESS_CODE];
    }

    /**
     * 获取执行日志
     *
     * @param $log_time
     * @param $job_id
     * @param $from_line_num
     * @return string
     */
    public static function log($log_time, $job_id, $from_line_num, $table)
    {

        $log_file_name = JobTool::makeLogFileName($log_time, $job_id);

        return JobTool::readLog($log_file_name, $from_line_num);
    }


    /**
     * 终止任务
     *
     * @param $job_id
     * @return array
     */
    public static function kill($job_id, Table $table)
    {
        $job_status = JobExcutor::loadJob($job_id, $table);

        if ($job_status) {
            JobExcutor::removeJob($job_id, $table);
        }
        return ['code' => Code::SUCCESS_CODE];
    }

    /**
     * 触发任务运行
     *
     * @param $params
     * @param Server $server
     */
    public static function run($params, Server $server)
    {
        foreach ($params as $param) {
            // 投递异步任务
            $task_id = $server->task($param);
        }

    }
}