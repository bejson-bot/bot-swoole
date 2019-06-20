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
        '抢劫' => [
            'action' => 'loot',
            'description' => '抢劫 使用说明: \n#抢劫 @被抢劫的人。',
            'alias' => ['打劫'],
            // 频率限制
            'limit' => [
                'bucket_name' => 'Loot:Loot', // 桶名 可以共享
                'period' => 3, // 时间段 单位分钟 每几分钟
                'max' => 1, // 单个时间段内 最多几次,
                'tips' => '你丫胆子忒大~!'
            ]
        ]
    ];

    /**
     * 烟斗
     * @param $args
     * @return bool
     */
    public function loot($args): bool
    {
        // 无参数 则帮助
        if (empty($args) || count($args) < 1) {
            $this->reply("抢劫 使用说明: \n#抢劫 @被抢劫的人\n抢劫积分是0-100随机的。");
            return true;
        }

        // 解析参数
        $aims = CQ::getCQ($args['0']);
        if (!$aims || $aims['type'] != 'at') {
            $this->reply("抢劫 使用说明: \n#抢劫 @被抢劫的人\n抢劫积分是0-100随机的。");
            return true;
        }

        // 检查双方是否开通积分
        if (!(Cache::get($this->key) ?? [])[$this->data['user_id']] ||!(Cache::get($this->key) ?? [])[$aims['params']['qq']]) {
            $this->reply(sprintf(
                '[抢劫] %s 双方必须都开通积分才能参与。',
                CQ::at($this->data['user_id'])
            ));
            return true;
        }

        // 生成抢劫数量
        $rand = rand(-100, 100);

        if ($rand  > 0) {

            // 先抢劫
            Integral::change($this->getRobotId(), $aims['params']['qq'], -$rand, $this->data['group_id']);

            // 再加钱
            Integral::change($this->getRobotId(), $this->data['user_id'], $rand, $this->data['group_id']);

            // 创建消息
            $msg = sprintf(
                '[抢劫] %s 试图抢劫 %s 的积分，通过迷晕对方，成功抢劫了 %s 积分。',
                CQ::at($this->data['user_id']),
                CQ::at($aims['params']['qq']),
                $rand
            );
        } else {
            // 失败的话

            // 先扣自己的钱
            Integral::change($this->getRobotId(), $this->data['user_id'], $rand, $this->data['group_id']);

            // 再给对方加钱
            Integral::change($this->getRobotId(), $aims['params']['qq'], -$rand, $this->data['group_id']);

            // 创建消息
            $msg = sprintf(
                '[抢劫] %s 试图抢劫 %s 的积分，没能成功，反被抢劫了 %s 积分，并被对方爆了菊花。',
                CQ::at($this->data['user_id']),
                CQ::at($aims['params']['qq']),
                -$rand
            );
        }

        // 公布结果
        $this->reply($msg);

        return true;
    }
}
