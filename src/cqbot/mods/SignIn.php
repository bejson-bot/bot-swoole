<?php

/**
 * 积分排名
 */
class SignIn extends ModBase
{
    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['签到','打卡']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [
        '签到' => [
            'action' => 'signInBoot',
            'description' => '签到 使用说明: 每天签到首次加分。',
            'alias' => ['打卡']
        ]
    ];

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
     * 签到
     * @param $args
     * @return bool
     */
    public function signInBoot($args): bool
    {

        // 检查是否开通积分
        if (!(Cache::get($this->key) ?? [])[$this->data['user_id']]) {
            $this->reply(sprintf(
                '[签到] %s 需先开通积分才能参与。',
                CQ::at($this->data['user_id'])
            ));
            return true;
        }
        
        // 添加限制
        $key = sprintf('SingIn:%s', $this->data['group_id']);
        if (($time = Cache::get($key, [])[$this->data['user_id']]) && $time >= strtotime(date('Y-m-d'))) {
            $this->reply(sprintf(
                '[签到] %s 今天已经签过到了呢。',
                CQ::at($this->data['user_id'])
            ));
            return true;
        }

        // 生成抢劫数量
        $rand = rand(1, 30);

        // 加钱
        Integral::change($this->getRobotId(), $this->data['user_id'], $rand, $this->data['group_id']);

        // 记录签到
        if (Cache::get($key, flase) === false) Cache::set($key, []);
        Cache::appendKey($key, $this->data['user_id'], time());

        // 创建消息
        $msg = sprintf(
            '[签到] %s 签到成功，奖励 %s 积分。',
            CQ::at($this->data['user_id']),
            $rand
        );

        // 公布结果
        $this->reply($msg);

        return true;
    }
}
