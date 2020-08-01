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
        $code = Code::SUCCESS_CODE;

        try {
            $jobInfo = JobExecutor::loadJob($jobId, $table);
            // 内存表里还有key
            if (!empty($jobInfo['log_id'])) {
                $processName = self::getTaskTag($jobInfo['log_id']);
                self::appendLog($jobInfo['log_id'], '即将被杀掉....');
                if (self::killScriptProcess($processName, ['logDateTime' => self::genLogTime(), 'logId' => $jobInfo['log_id']])) {
                    self::appendLog( $jobInfo['log_id'], '被杀掉....done');
                    JobExecutor::removeJob($jobId, $table);
                } else {
                    $code = Code::ERROR_CODE;
                }
            }
        } catch (\Exception $exception) {

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
    public static function run($params, $requestId, Server $server, $conf, Table $table, Table $cacheTable)
    {
        foreach ($params as $param) {
            // 投递异步任务
            $param['requestId'] = $requestId;
            $settingWorkersNum = $conf['setting']['task_worker_num'];
            $left = $param['jobId'] % $settingWorkersNum;
            $dstWorkerNum = $left + $conf['setting']['worker_num'] - 1;
            self::appendLog($param['logId'],  $settingWorkersNum.',dst:'.$dstWorkerNum.' ,调度中心执行任务参数：' . json_encode($params));
            $handleParams = self::getHandlerParams($param, $conf);
            self::appendLog($param['logId'],  ' server stats:' . json_encode($server->stats()));

            $exist = true;
            $existInfo = $table->get($param['jobId']);
            if ($existInfo) {
                self::appendLog($param['logId'], '当前exist:' . json_encode([$existInfo]));
            } else {
                $exist = false;
            }

            // 丢弃下一个
            if ($exist && $param['executorBlockStrategy'] == JobStrategy::DISCARD_NEXT_SCHEDULING) {
                self::appendLog($param['logId'], '【'.JobStrategy::DISCARD_NEXT_SCHEDULING.'】此task因策略需要被丢弃：' . json_encode($params));
                return JobStrategy::discard($param, $cacheTable, self::$retryTimes);
            }

            // 丢弃之前的使用新的
            if ($exist && $param['executorBlockStrategy'] == JobStrategy::USE_NEXT_SCHEDULING) {
                self::appendLog($existInfo['log_id'], '【'.JobStrategy::USE_NEXT_SCHEDULING.'】因下一个任务丢弃:' . json_encode($param));
                JobStrategy::coverEarly($existInfo, $param, $table, $cacheTable, self::$retryTimes);
            }

            self::appendLog($param['logId'], '开始投递任务，task_worker开始处理');
            $table->set($param['jobId'], ['log_id' => $param['logId'], 'request_id' => $param['requestId'], 'log_date_time' => strval($param['logDateTim'])]);
            // 指定task_worker 并直接触发onFinish
            $server->task(['job_data' => $param, 'params' => $handleParams], $dstWorkerNum);
        }
    }
}
