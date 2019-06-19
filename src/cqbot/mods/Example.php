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
class Example extends ModBase
{
    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['ping', '你好', 'hello', '随机数']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [
        'ping' => [
            'action' => 'ping',
            'description' => 'ping 测试是否在线',
            'alias' => ['你好', 'hello']
        ],
        '随机数' => [
            'action' => 'rand',
            'description' => '生成一个随机数'
        ]
    ];

    /**
     * ping 连通性/在线测试
     *
     * @param $args
     * @return bool
     */
    public function ping($args):bool
    {
        $this->reply($this->command == 'ping' ? "pong!" : '你好，我是如花。');
        return true;
    }

    /**
     * 生成一个随机数
     *
     * @param $args
     * @return bool
     */
    public function rand($args):bool
    {
        if (!isset($args['0']) || !isset($args['1'])) {
            $this->reply("用法： 随机数 开始整数 结束整数");
            return true;
        }
        $c1 = intval($args['0']);
        $c2 = intval($args['1']);
        if ($c1 > $c2) {
            $this->reply("随机数范围错误！应该从小的一方到大的一方！例如：\n随机数 1 99");
            return true;
        }
        $this->reply("生成的随机数是 " . mt_rand($c1, $c2));
        return true;
    }
}
