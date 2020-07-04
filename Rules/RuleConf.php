<?php
/**
 * 项目执行路径规则
 * User: sivan
 * Date: 2019/3/7
 * Time: 3:59 PM
 */
namespace Rules;


class RuleConf
{
    const ARTISAN_MODE = 'artisan';
    // 项目名称_Artisan_Console_signature，参数根据不同的任务来设置不同的格式
    const LARAVEL_COMMAND_NAME_IDENTIFIER = 'console';

    const REPLACE_NEEDLE_STR = ':string';

    protected static $mapping = [
        'platform' => [
            'router_index' => '/ecs/public/index.php',
            'file_real_path' => ':string/ecs/application/:string/controller/:string.php',
            'identifier' => 'p'
        ],
        'abc360' => [
            'realDir' => 'abc360.com',
            'router_index' => '/cli.php',
            'file_real_path' => ':string/Application/:string/Controller/:stringController.class.php',
            'identifier' => 'a'
        ],
        'teen' => [
            'router_index' => '/index.php',
            'file_real_path' => ':string/Application/:string/Controller/:stringController.class.php',
            'identifier' => 't'
        ],
        'crm' => [
            'run_mode' => self::ARTISAN_MODE,
            'router_index' => self::ARTISAN_MODE,
            'param_identify' => ' ',
            'file_real_path' => ':string/Console/Kernel.php',
            'identifier' => 'c'
        ],
        'local' => [
            'identifier' => 'l'
        ],
        'sdk' => [
            'realDir' => 'sdkdispatcher',
            'router_index' => '/public/index.php',
            'file_real_path' => ':string/application/:string/controller/:string.php',
            'identifier' => 'd'
        ]
    ];

    /**
     * 项目配置信息
     *
     * @param $projectName
     * @return array|mixed
     */
    public static function info($projectName)
    {
        $map = self::$mapping;
        return isset($map[$projectName]) ? $map[$projectName] : [];
    }

    /**
     * 支持laravel类框架
     *
     * @param $handlerInfoArr
     * @param $projectName
     * @param $conf
     * @param $projectInfo
     * @return array
     */
    public static function supportLaravelFramework($handlerInfoArr, $projectName, $conf, &$projectInfo)
    {
        // 支持别名和真实的目录映射
        if (!empty($projectInfo['realDir'])) {
            $projectName = $projectInfo['realDir'];
        }
        $indexPath = self::getIndexCommand($conf, $projectName);

        $indexRouterPath = $indexPath . '/' . $projectInfo['router_index'];
        $classPath = $handlerInfoArr[3];
        $projectInfo['file_real_path'] = $indexRouterPath;

        return [$indexRouterPath, $classPath];
    }

    /**
     * 支持本地脚本
     *
     * @param $handlerInfoArr
     * @param $projectName
     * @param $projectInfo
     * @return array
     */
    public static function supportLocal($handlerInfoArr, $projectName, &$projectInfo, &$isShell)
    {
        $classPath = '';

        if (!is_bool(stripos('shell', $handlerInfoArr[0]))) {
            $dirName = SHELL_SCRIPT_DIR;
            $projectName = $handlerInfoArr[1];
            $fileExt = '.sh';
            $isShell = true;
        } else {
            $dirName = empty($handlerInfoArr[1]) ? PHP_TEST_DIR : $handlerInfoArr[1];
            $fileExt = '.php';
        }
        $targetFilename = empty($handlerInfoArr[2]) ? $projectName : $handlerInfoArr[2];
        $indexRouterPath = $dirName . '/' . $targetFilename . $fileExt;
        $projectInfo = RuleConf::info('local');
        $projectInfo['file_real_path'] = $indexRouterPath;

        return [$indexRouterPath, $classPath];
    }

    /**
     * 支持tp等使用php cli模式执行的脚本框架
     *
     * @param $handlerInfoArr
     * @param $projectName
     * @param $projectInfo
     * @param $conf
     * @return mixed
     */
    public static function supportCommonFramework($handlerInfoArr, $projectName, $conf, &$projectInfo)
    {
        // 入口文件地址 /project/platform
        $indexPath = self::getIndexCommand($conf, $projectName);
        // /project/platform/ecs/public/index.php
        $indexRouterPath = $indexPath . $projectInfo['router_index'];
        // /project/platform/ecs/application/Module/controller/
        $classPath = $handlerInfoArr[1] . '/' . $handlerInfoArr[2] . '/' . $handlerInfoArr[3];
        // project_module_class
        $formatValues = [$indexPath, $handlerInfoArr[1], $handlerInfoArr[2]];
        foreach ($formatValues as $formatValue) {
            $pos = strpos($projectInfo['file_real_path'], self::REPLACE_NEEDLE_STR);

            $projectInfo['file_real_path'] = substr_replace($projectInfo['file_real_path'], $formatValue, $pos, strlen(self::REPLACE_NEEDLE_STR));
        }
        return [$indexRouterPath, $classPath];
    }

    /**
     * @param $conf
     * @param $projectName
     * @return string
     */
    protected static function getIndexCommand($conf, $projectName)
    {
        $indexPath = rtrim($conf['project']['root_path'], '/')  . '/' . $projectName;
        return $indexPath;
    }
}
