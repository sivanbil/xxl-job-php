<?php
/**
 * 提供给任务调度中心几个api 【run -> trigger, beat, idleBeat, log, kill】
 * User: sivan
 * Date: 2019/2/1
 * Time: 7:10 PM
 */
namespace Lib\Executor;

use Lib\Common\Code;
use Lib\Common\JobTool;
use Lib\Core\Server;
use Lib\Core\Table;

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

        $job_status = JobExecutor::loadJob($job_id, $table);

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
     * @return array
     */
    public static function log($log_time, $job_id, $from_line_num)
    {

        $log_file_name = JobTool::makeLogFileName($log_time, $job_id);

        $log_content = JobTool::readLog($log_file_name, $from_line_num);

        return ['code' => Code::SUCCESS_CODE, 'msg' => '', 'content' => $log_content];
    }


    /**
     * 终止任务
     *
     * @param $job_id
     * @return array
     */
    public static function kill($job_id, Table $table)
    {
        $job_info = JobExecutor::loadJob($job_id, $table);
        $code = Code::SUCCESS_CODE;
        // 内存表里还有key
        if ($job_info) {
            $process_name = $job_info['process_name'];
            if (self::killScriptProcess($process_name)) {
                JobExecutor::removeJob($job_id, $table);
            } else {
                $code = Code::ERROR_CODE;
            }
        }
        return ['code' => $code];
    }

    /**
     * 触发任务运行
     *
     * @param $params
     * @param Server $server
     */
    public static function run($params, $request_id, Server $server)
    {
        foreach ($params as $param) {
            // 投递异步任务
            $param['requestId'] = $request_id;
            self::appendLog($param['logDateTim'], $param['logId'], '调度中心执行任务参数：' . json_encode($params));

            $server->task($param);
        }
    }
}