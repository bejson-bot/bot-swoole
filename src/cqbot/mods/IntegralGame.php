<?php

/**
 * 积分游戏
 */
class IntegralGame extends ModBase
{
    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['group.烟斗']
    ];

    /**
     * 群列表 Key
     *
     * @var string
     */
    private static $group_key = 'Integral:GroupList';

    /**
     * 保存数据的文件名
     *
     * @var string
     */
    private static $save_file = CONFIG_DIR . "Integral.json";

    /**
     * 开启分词
     *
     * @var bool
     */
    public $split_execute = true;

    /**
     * 调用缓存 key
     *
     * @var string
     */
    private $key = '';

    /**
     * 构造函数 初始化
     *
     * @param CQBot $main
     * @param [type] $data
     */
    public function __construct(CQBot $main, $data)
    {
        // 调用父类
        parent::__construct($main, $data);

        // 保存查询 key
        $this->key = sprintf('Integral:%s:%s', $this->data['self_id'], $this->data['group_id']);

    }

    /**
     * 触发消息事件
     *
     * @param string $command
     * @param mixed $args
     * @return bool
     */
    public function command(string $command, $args): bool
    {
        switch ($command) {
            case '烟斗':
                $this->pipe($args);
                break;
        }

        return true;
    }

    /**
     * 烟斗
     * @param $args
     */
    private function pipe($args)
    {
        // 禁言成功率
        static $rates = [
            ''
        ];

        // 无参数 则帮助
        if (empty($args) || count($args) < 2) {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        // 解析参数一
        if (!$aims = CQ::getCQ($args['0'])) {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        // 解析参数二
        $time = intval($args['1']);
        if ($time > 10 || $time <= 0) {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        var_dump($aims);

        // 计算禁言成功率
        if (rand(0, 100) < ((10 - $time) * 10 + 5)) {
            // 禁言成功
            $price = $time * 2; // 禁言成功 费用 = 时间 * 2

            // 先扣钱
            // Integral::change($this->getRobotId(), $, $value)
            // 再加钱

            // 设置禁言

        }
        var_dump($args);


    }


}
