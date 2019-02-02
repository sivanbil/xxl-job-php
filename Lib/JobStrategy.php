<?php
/**
 * @author sivan
 * @description job strategy
 */
namespace Lib;

class JobStrategy
{
    // 串行
    const SERIAL = 1;
    // 丢弃后续调度请求
    const DISCARD_NEXT_SCHEDULING = 2;
    // 关闭当前任务调度进程，启用新的调度
    const USE_NEXT_SCHEDULING = 3;
}

