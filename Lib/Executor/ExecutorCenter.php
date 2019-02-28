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
     * @param $jobId
     * @param Table $table
     * @return array
     */
    public static function idleBeat($jobId, Table $table)
    {
        $isRunningOrHasQueue = false;

        $jobStatus = JobExecutor::loadJob($jobId, $table);

        if ($jobStatus) {
            $isRunningOrHasQueue = true;
        }

        if ($isRunningOrHasQueue) {
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
    public static function log($logTime, $jobId, $fromLineNum)
    {

        $logFileName = JobTool::makeLogFileName($logTime, $jobId);

        $logContent = JobTool::readLog($logFileName, $fromLineNum);

        return ['code' => Code::SUCCESS_CODE, 'msg' => '', 'content' => $logContent];
    }


    /**
     * 终止任务
     *
     * @param $jobId
     * @param Table $table
     * @return array
     */
    public static function kill($jobId, Table $table)
    {
        $jobInfo = JobExecutor::loadJob($jobId, $table);
        $code = Code::SUCCESS_CODE;
        // 内存表里还有key
        if ($jobInfo) {
            $processName = $jobInfo['process_name'];
            if (self::killScriptProcess($processName)) {
                JobExecutor::removeJob($jobId, $table);
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
     * @param $requestId
     * @param Server $server
     */
    public static function run($params, $requestId, Server $server)
    {
        foreach ($params as $param) {
            // 投递异步任务
            $param['requestId'] = $requestId;
            self::appendLog($param['logDateTim'], $param['logId'], '调度中心执行任务参数：' . json_encode($params));

            $server->task($param);
        }
    }
}