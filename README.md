# xxl-job-php-executor
> 为xxl-job订制的基于Swoole框架RPC通信协议的PHP执行器框架

## 自研背景

- xxl-job任务调度中心PHP glue运行模式难以满足我们的脚本运行需求
- 目前的php定时任务难以做到任务调度的详细监控与管理

以上两点为大概自研的一个背景，部分想法参考自腾讯的tsf框架设计而实现的

## 运行注意事项
- Linux or Unix
- Mac系统因安全因素不支持修改进程名
- 服务器需要安装Swoole扩展,版本1.8+

## 调度中心提供的给执行器api
- 任务结果回调服务；
- 执行器注册服务；
- 执行器注册摘除服务；

## 对调度中心提供的api
- 心跳检测
- 忙碌检测
- 触发任务执行
- 获取Rolling Log
- 终止任务


## 目录结构
- Bin 
  - index.php 命令行启动入口文件

- Conf 服务器配置

- Rules 项目目录规则配置

- Lib  核心类库
  - Common   工具通用类
  - Core     基于swoole 的核心类
  - Executor 执行器类

- Log  运行时日志 如果没有需要创建且可写的目录

- Src  业务逻辑代码
  - index.php  启动服务的tcp server 入口文件
  
  
  
## 命令行

### 演示
<pre>
[root@iz8vb25ut078wb3d93fcvmz xxl-job-swoole]# php Bin/index.php JobServer status
xxl-job-executor-server is not running, please check it [FAIL]
[root@iz8vb25ut078wb3d93fcvmz xxl-job-swoole]# php Bin/index.php JobServer start
[root@iz8vb25ut078wb3d93fcvmz xxl-job-swoole]# ps -ef | grep xxl-job
root     23527     1  0 12:30 ?        00:00:00 xxl-job-php-executor: master process
root     23528 23527  0 12:30 ?        00:00:00 xxl-job-php-executor: manager process
root     23531     1  0 12:30 ?        00:00:00 xxl-job-executor-server
root     23533 23528  0 12:30 ?        00:00:00 xxl-job-php-executor
root     23534 23528  0 12:30 ?        00:00:00 xxl-job-php-executor
root     23535 23528  0 12:30 ?        00:00:00 xxl-job-php-executor
root     23536 23528  0 12:30 ?        00:00:00 xxl-job-php-executor
root     23537 23528  0 12:30 ?        00:00:00 xxl-job-php-executor
root     23539 23485  0 12:30 pts/0    00:00:00 grep --color=auto xxl-job
[root@iz8vb25ut078wb3d93fcvmz xxl-job-swoole]# php Bin/index.php JobServer stop
root     23531  0.0  0.5 296148  5464 ?        Ss   12:30   0:00 xxl-job-executor-server
root     23531  0.0  0.5 296148  6036 ?        Ss   12:30   0:00 xxl-job-executor-server
stop xxl-job-executor-server [SUCCESS]
</pre>


### 支持的命令
`status` `start` `list` `reload` `stop` `restart`

### 命令示例
- php Bin/index.php Xxx start             `Xxx server配置文件名称`
- php Bin/index.php Xxx status 
- php Bin/index.php Xxx stop
- php Bin/index.php Xxx restart
- php Bin/index.php Xxx reload
- php Bin/index.php list

## 服务器配置详解

<pre>
[server]
; server type:tcp udp http
type = tcp
; host
host = 127.0.0.1
; port
port = 9506
; process name
process_name = xxl-job-php-executor
; registry
app_name = xxl-job-php
; server name 暂未使用
server_name = 'http://xxl-job-php.test.com'
; php
php = /usr/bin/php

[setting]
; worker process num
worker_num = 3
; Reactor num 
reactor_num = 2
; task process num
task_worker_num = 2
; dispatch mode
dispatch_mode = 2
; daemonize
daemonize = 1
; heartbeat
heartbeat_check_interval = 60
; idle
heartbeat_idle_time = 600
; system log
log_file = '/data/wwwroot/xxl-job-swoole/Log/runtime.log'
; mac process_name

[table]
size = 1024

[project]
; 执行脚本项目的目录
root_path = '/data/wwwroot/xxl-job-swoole/Tests'

[xxljob]
; site domain
host = 127.0.0.1
; netty server port
port = 8789
; 向注册中心发起注册的定时周期
registry_interval_ms = 20000
; 线上要设置为1，主动去向注册中心发起注册请求
open_registry = 0
</pre>