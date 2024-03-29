<?php
/**
 * 注册自动加载
 * User: sivan
 * Date: 2019/2/1
 * Time: 5:02 PM
 */
namespace Lib;

class Loader
{
    /**
     * 命名空间的路径
     */
    static $nsPath;

    /**
     * 自动载入类
     * @param $class
     */
    public static function autoload($class)
    {
        $root = explode('\\', trim($class, '\\'), 2);
        if (count($root) > 1 and isset(self::$nsPath[$root[0]])) {
            include_once self::$nsPath[$root[0]] . '/' . str_replace('\\', '/', $root[1]) . '.php';
        }
    }
    /**
     * 设置根命名空间
     * @param $root
     * @param $path
     */
    public static function setRootNS($root, $path)
    {
        self::$nsPath[$root] = $path;
    }
}