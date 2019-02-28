<?php
/**
 * 任务执行策略
 *
 * @author sivan
 * @description job strategy
 */
namespace Lib\Executor;

use Lib\Core\Process;
use Lib\Core\Table;

class JobStrategy
{
    // 串行
    const SERIAL = 'SERIAL_EXECUTION';
    // 丢弃后续调度请求
    const DISCARD_NEXT_SCHEDULING = 'DISCARD_LATER';
    // 关闭当前任务调度进程，启用新的调度
    const USE_NEXT_SCHEDULING = 'COVER_EARLY';

    /**
     * @param $data
     * @param $processName
     * @param $params
     * @param Table $table
     * @return false|string
     */
    public static function serial($data, $serverInfo, $params, Table $table)
    {
        // 然后执行脚本
        $process = new Process(function (Process $worker) use ($data, $serverInfo, $params, $table) {
            while ($msg = $worker->pop()) {
                if ($msg === false) {
                    continue;
                }
                $existInfo = $table->get($data['jobId']);
                if ($existInfo && !empty($existInfo['logId']) && $existInfo['logId'] != $data['logId']) {
                    $worker->push($msg);
                    continue;
                }
                // 丢弃之后的
                $params = json_decode($msg, true);
                $worker->exec($serverInfo['php'], $params);
                $worker->write('success');
            }

        }, true);
        $process->useQueue(1, 2);
        $process->start();
        $process->push(json_encode($params));
        $wait_res = Process::wait();
        if ($wait_res['code']) {
            echo  "\033[31;40m [FAIL] \033[0m" . PHP_EOL;
            exit;
        }
        $execResultMsg = $process->read();
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
    public static function discard($data)
    {

        return json_encode([
                'job_id' => $data['jobId'],
                'request_id' => $data['requestId'],
                'log_id' => $data['logId'],
                'log_date_time' => $data['logDateTim']]
        );
    }
}

