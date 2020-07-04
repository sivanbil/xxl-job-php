<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/13
 * Time: 3:57 PM
 */

namespace Lib\Executor;

use Lib\Core\Table;

class JobExecutor
{
    /**
     * @param $jobId
     * @param Table $table
     * @return mixed
     */
    public static function loadJob($jobId, Table $table)
    {

        return $table->get($jobId);
    }


    /**
     * @param $jobId
     * @param Table $table
     * @return mixed
     */
    public static function removeJob($jobId, Table $table)
    {
        return $table->del($jobId);
    }
}