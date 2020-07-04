<?php
/**
 * Created by PhpStorm.
 * User: liaoxianwen
 * Date: 2019/2/14
 * Time: 12:45 PM
 */

namespace Lib\Core;


class Table extends \Swoole\Table
{
    /**
     * @param $size
     * @return bool
     */
    public function checkSize($size)
    {
        return ($this->count() + 1) < $size ? true : false;
    }
}