<?php
/**
 * 提供给任务调度中心几个api 【run -> trigger, beat, idleBeat, log, kill】
 * User: sivan
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Lib;

class ExecutorServer
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
     * 忙碌检测
     *
     * @param $job_id
     * @return array
     */
    public static function idleBeat($job_id)
    {
        $is_running_or_has_queue = false;

        $job_status = JobExcutor::loadJob($job_id);

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
    public static function log($log_time, $job_id, $from_line_num)
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
    public static function kill($job_id)
    {
        $job_status = JobExcutor::loadJob($job_id);

        if ($job_status) {
            JobExcutor::removeJob($job_id);
        }
        return ['code' => Code::SUCCESS_CODE];
    }

    // 触发任务运行
    public static function run($params)
    {

    }
}