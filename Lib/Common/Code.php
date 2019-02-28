<?php
/**
 * 返回状态码说明
 *
 * User: sivan
 * Date: 2019/2/12
 * Time: 6:22 PM
 */
namespace Lib\Common;

class Code
{
    // 执行成功
    const SUCCESS_CODE = 200;
    // 执行失败
    const ERROR_CODE = 500;
    // 信息提示
    const MSG_MAPPING = [
        'script_exec_failed' => '脚本执行失败',
        'script_exec_success' => '脚本执行完成，结束脚本运行',
    ];

    /**
     * @param $str
     * @return mixed
     */
    public static function getMsg($str)
    {
        $mapping = self::MSG_MAPPING;
        return isset($mapping[$str]) ? $mapping[$str] : $str;
    }
}