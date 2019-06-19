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
        // 无参数 则帮助
        if (empty($args) || count($args) < 2) {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        // 解析参数一
        $aims = CQ::getCQ($args['0']);
        if (!$aims || $aims['type'] != 'at') {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        // 解析参数二
        $time = intval($args['1']);
        if ($time > 10 || $time <= 0) {
            $this->reply("烟斗 使用说明: \n#烟斗 @被禁言的人 [禁言时长(1-10)分钟]\n禁言越久失败率越高，且扣分越多。");
            return;
        }

        // 检查双方是否开通积分
        if (!(Cache::get($this->key) ?? [])[$this->data['user_id']] ||!(Cache::get($this->key) ?? [])[$aims['params']['qq']]) {
            $this->reply(sprintf(
                '[烟斗] %s 双方必须都开通积分才能参与。',
                CQ::at($this->data['user_id'])
            ));
            return;
        }

        // 计算禁言成功率
        $rand = rand(0, 100);
        $rate = (10 - $time) * 10 + 5;
        if ($rand < $rate) {
            // 禁言成功
            $price = $time * 2; // 禁言成功 费用 = 时间 * 2

            // 先扣钱
            Integral::change($this->getRobotId(), $this->data['user_id'], -$price, $this->data['group_id']);

            // 再加钱 先检查
            Integral::change($this->getRobotId(), $aims['params']['qq'], $price, $this->data['group_id']);

            // 设置禁言
            CQAPI::set_group_ban($this->getRobotId(), [
                'group_id' => $this->data['group_id'],
                'user_id' => $aims['params']['qq'],
                'duration' => $time * 60
            ]);

            // 创建消息
            $msg = sprintf(
                '[烟斗] %s 试图禁言 %s %s分钟，并掷出 %s，成功率 %s，最终如愿以偿，消耗积分 %s。',
                CQ::at($this->data['user_id']),
                CQ::at($aims['params']['qq']),
                $time,
                $rand,
                $rate,
                $price
            );


        } else {
            // 失败的话
            $price = $time; // 禁言失败 费用 = 时间;

            // 先扣钱
            Integral::change($this->getRobotId(), $this->data['user_id'], $price, $this->data['group_id']);

            // 创建消息
            $msg = sprintf(
                '[烟斗] %s 试图禁言 %s %s分钟，并掷出 %s，成功率 %s，非常可惜，未能如愿，损失积分 %s。',
                CQ::at($this->data['user_id']),
                CQ::at($aims['params']['qq']),
                $time,
                $rand,
                $rate,
                $price
            );
        }

        // 公布结果
        $this->reply($msg);
    }


}
