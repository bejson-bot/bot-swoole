<?php

/**
 * 这是一个示例模块php文件，你可以直接复制此文件中的代码
 * 然后修改其class的名字（注意要和.php文件的文件名相同）
 * 例如，新建一个Mailer模块，则Mailer模块的文件名字为
 * Mailer.php
 * 如果要开启框架的切割函数激活，请在__construct构造函数中
 * 添加一句：$this->split_execute = true;
 * 默认不会执行execute函数
 */
class Loot extends ModBase
{
    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['抢劫','打劫']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [

    ];


}
