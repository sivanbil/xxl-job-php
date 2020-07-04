<?php
/**
 * 任务执行策略
 *
 * @author sivan
 * @description job strategy
 */
namespace Lib\Executor;

use Lib\Common\Code;
use Lib\Common\JobTool;
use Lib\Core\Process;
use Lib\Core\Table;

class JobStrategy
{
    use JobTool;
    // 串行
    const SERIAL = 'SERIAL_EXECUTION';
    // 丢弃后续调度请求
    const DISCARD_NEXT_SCHEDULING = 'DISCARD_LATER';
    // 关闭当前任务调度进程，启用新的调度
    const USE_NEXT_SCHEDULING = 'COVER_EARLY';

    const DEFAULT_QUEUE_KEY = 2;

    /**
     * @param $data
     * @param $processName
     * @param $params
     * @param Table $table
     * @return false|string
     */
    public static function serial($data, $serverInfo, $params, Table $table)
    {
        $key = self::DEFAULT_QUEUE_KEY;
        if (!empty($params['queue_key'])) {
            $key = $params['queue_key'];
            unset($params['queue_key']);
        }
        // 然后执行脚本
        $process = new Process(function (Process $worker) use ($data, $serverInfo, $params, $table) {
            while ($msg = $worker->pop()) {
                if ($msg === false) {
                    break;
                }
                $existInfo = $table->get($data['jobId']);
                if ($existInfo && !empty($existInfo['logId']) && $existInfo['logId'] != $data['logId']) {
                    $worker->push($msg);
                    break;
                }

                $compilerPath = !empty($params['is_shell']) ? $serverInfo['shell'] : $serverInfo['php'];
                $params = json_decode($msg, true);
                $params[] = self::getTaskTag($data['logId']);
                $worker->exec($compilerPath, $params);
            }

            // 确认结束
            while(true){
                $c = $worker->statQueue();
                $n = $c['queue_num'];
                if ($n === 0) {
                    break;
                }
            }
            $worker->freeQueue();
        }, true);

        $process->useQueue($key, 2);
        $process->start();
        $process->push(json_encode($params['params']));
        $wait_res = Process::wait();
        if ($wait_res['code']) {
            $execResultMsg = "\033[31;40m [FAIL] \033[0m" . PHP_EOL;

            return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim'],
                'exec_result_msg' => $execResultMsg
            ]);
        }

        // 超时处理
        if (!empty($data['executorTimeout']) && version_compare(SWOOLE_VERSION, '1.9.21', '>=')) {
            $timeout = doubleval($data['executorTimeout']);
            $timeout && $process->setTimeout($timeout);
        }
        $execResultMsg = $process->read();
        self::appendLog($data['logId'], 'task_worker处理完成，触发回调');

        return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim'],
                'exec_result_msg' => $execResultMsg
        ]);
    }

    /**
     * @param $data
     * @return false|string
     */
    public static function discard($data, Table $cacheTable, $retryTimes = 5)
    {
        // 被kill完回调
        while($retryTimes > 0) {
            $hostInfo = self::getCurrentServer($cacheTable);
            $bizCenter = self::getBizCenterByHostInfo($hostInfo);
            $res = $bizCenter->callback(self::convertSecondToMicroS(), $data['logId'], $data['requestId'], $data['logDateTim'], [
                'code' => Code::SUCCESS_CODE,
                'msg' => '脚本被强杀，结束脚本运行',
            ]);
            if ($res) {
                self::appendLog($data['logId'], 'callback回调:' . json_encode([$res]));

                self::appendLog($data['logId'], '任务因策略，没有进入执行');
                $retryTimes = 0;
            } else {
                self::appendLog($data['logId'], 'callback回调 retry:' . $retryTimes);
                $retryTimes--;
            }
        }
        return true;
    }

    public static function coverEarly($existInfo, $data, Table $table, Table $cacheTable, $retryTimes = 5)
    {
        $process = new Process(function (Process $process) use ($data, $existInfo, $table, $cacheTable, $retryTimes) {
            // 杀掉之前
            ExecutorCenter::kill($existInfo['jobId'], $table);
            // 被kill完回调
            while($retryTimes > 0) {
                $hostInfo = self::getCurrentServer($cacheTable);
                self::appendLog($existInfo['log_id'], $hostInfo. 'callback回调:' . json_encode($existInfo));
                $bizCenter = self::getBizCenterByHostInfo($hostInfo);
                $res = $bizCenter->callback(self::convertSecondToMicroS(), $existInfo['log_id'], $existInfo['request_id'], $existInfo['log_date_time'], [
                    'code' => Code::SUCCESS_CODE,
                    'msg' => '脚本被强杀，结束脚本运行',
                ]);
                if ($res) {
                    self::appendLog($existInfo['log_id'], 'callback回调:' . json_encode([$res]));

                    self::appendLog($existInfo['log_id'], '任务因策略，没有继续执行，已中断');
                    $retryTimes = 0;
                } else {
                    $retryTimes--;
                }
            }
        }, true);
        $process->start();
        $process->name('kill-'.$existInfo['log_id'] . '-process');
    }
}

