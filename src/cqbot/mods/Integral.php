<?php

/**
 * 积分功能
 */
class Integral extends ModBase
{
    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['*', '@', '积分', 'group.奇遇'],
        'notice' => ['group_admin.set', 'group_decrease.kick'],
        'request' => ['group.invite']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [
        '积分' => [
            'action' => 'query',
            'description' => '查看积分 / 开通积分功能'
        ],
        '奇遇' => [
            'action' => 'activeAdventure',
            'description' => '奇遇太少？你本可以主动点...',
            // 频率限制
            'limit' => [
                'bucket_name' => 'Integral:activeAdventure', // 桶名 可以共享
                'period' => 1, // 时间段 单位分钟 每几分钟
                'max' => 1, // 单个时间段内 最多几次,
                'tips' => '亲，你要控几你寂几啊。',
                'ban' => 5, // 频率快了 禁言丫
            ]
        ]
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
     * 初始化 加载数据
     *
     * @return void
     */
    static function initValues()
    {
        // 获取已经保存的列表
        $list = [];
        if (file_exists(self::$save_file)) {
            $list = json_decode(file_get_contents(self::$save_file), true);
        }

        // 初始化一个群数组
        Cache::set(self::$group_key, []);

        // 遍历列表
        foreach ($list as $key => $info) {
            // 先保存群号
            Cache::appendKey(self::$group_key, $key, $key);

            // 将积分保存进去
            Cache::set($key, $info);
        }

        // 整理奇遇事件
        $adv_list = explode("\n", (trim(file_get_contents(CONFIG_DIR . "Adventure.txt"))));
        $advens = [];
        $level_sum = 0;
        foreach ($adv_list as $adv_str) {
            $info = explode('|', $adv_str);
            $level_sum += $info['0']; // 奇遇总概率

            $advens[] = [
                'level' => $level_sum,
                'range' => explode(',', $info['1']),
                'msg' => $info['2']
            ];
        }
        // 保存到缓存
        Cache::set('Integral:Adventure', [
            'level_sum' => $level_sum,
            'advens' => $advens
        ]);
    }

    /**
     * 保存数据
     *
     * @return void
     */
    static function saveValues()
    {
        // 先获取群列表
        $group_list = Cache::get(self::$group_key) ?? [];

        // 逐个群获取记录
        $list = [];
        foreach ($group_list as $key) {
            $list[$key] = Cache::get($key);
        }

        // 保存到文件
        // $list = []; // 相当于没有积分了
        file_put_contents(CONFIG_DIR . "Integral.json", json_encode($list));
    }

    /**
     * 收到消息时的处理
     *
     * @param string $content
     * @param string $type
     * @return bool
     */
    public function message(string $content, string $type): bool
    {
        // 默认所有消息都会触发 奇遇
        if ($type == 'all') {
            // 开通积分才会有奇遇
            if ((Cache::get($this->key) ?? [])[$this->data['user_id']]) {
                // 字数也决定触发几率
                $msg_len = strlen($content);

                // 暂定 只有12-120个字符才触发奇遇 起码4个汉字
                if ($msg_len < 12 || $msg_len > 120) {
                    // 超出范围直接加分就好
                    self::change($this->data['self_id'], $this->data['user_id'], 1, $this->data['group_id']);

                    return false;
                }

                // 尝试触发奇遇
                $info = $this->adventure();
                if (!$info['active']) {
                    // 没触发奇遇 加分
                    self::change($this->data['self_id'], $this->data['user_id'], 1, $this->data['group_id']);
                }
            }
        }

        return false;
    }

    /**
     *  查询积分
     *
     * @return bool
     */
    public function query($args): bool
    {
        // 禁言频率限制的key
        $limit_key = sprintf('Limit:Integral:%s:%s', $this->data['group_id'], $this->data['user_id']);

        if (!empty($args) && $args['0'] == '禁言换开通') {
            // 随机禁言时间
            $time = rand(1 * 60, 5 * 60);
            CQAPI::set_group_ban($this->data['self_id'], [
                'group_id' => $this->data['group_id'],
                'user_id' => $this->data['user_id'],
                'duration' => $time * 60
            ]);

            // 取消限制
            Cache::unset($limit_key);

            //发送消息
            $this->reply(sprintf('[积分] %s 亲，已为您解除禁言限制呢，等下再开通下积分试试？', CQ::at($this->data['user_id'])));
        }


        // 整个群的积分
        $info = Cache::get($this->key) ?? [];


        // 是否未开通积分
        if (!(Cache::get($this->key) ?? [])[$this->data['user_id']]) {
            // 如果今天开过就不行了
            if (($date = Cache::get($limit_key)) && $date == date('Y-m-d')) {
                $msg = sprintf(
                    "[积分] %s 亲，您每天只能开通一次积分功能。\n 发送 “#积分 禁言换开通” 使用随机禁言1-5小时换取开通权限",
                    CQ::at($this->data['user_id'])
                );
                $this->reply($msg);
                return true;
            }

            // 赠送积分
            $initial_score = rand(100, 500);

            // 添加群
            Cache::appendKey(self::$group_key, $this->key, $this->key);

            // 保存积分
            Cache::appendKey($this->key, $this->data['user_id'], $initial_score);

            // 锁定今日开通
            Cache::set($limit_key, date('Y-m-d', time()));

            $this->reply("[积分] ". CQ::at($this->data['user_id']) ."首次开通，赠送您 {$initial_score} 积分。");
        } else {
            $this->reply("[积分] ". CQ::at($this->data['user_id']) ."您的积分余额为: {$info[$this->data['user_id']]}");
        }

        return true;
    }

    /**
     * 改动用户积分
     *
     * @param int      $self_id
     * @param int      $user_id 用户QQ号码
     * @param int      $value 积分变动值 +1 / -1
     * @param int|null $group_id 群号码
     * @return int|bool     变动后积分值 / 失败返回false
     */
    public static function change(int $self_id, int $user_id, int $value, int $group_id = null)
    {
        // 改动 个人积分 还是 群内积分
        if ($group_id) {
            $key = sprintf('Integral:%s:%s', $self_id, $group_id);

            // 群没开通 或者 此人没开通 都返回 false
            if (!$info = Cache::get($key)) return false;
            if (!isset($info[$user_id])) return false;

            // 对积分加减
            Cache::appendKey($key, $user_id, $info[$user_id] + $value);

            // 如果出现负分就禁言
            if (Cache::get($key)[$user_id] < 0) {
                if (Cache::get($key)[$user_id] > -10) {
                    // -10分以内 没分禁言2分钟
                    $duration = Cache::get($key)[$user_id] * -2;
                } else if (Cache::get($key)[$user_id] > -50) {
                    // -50分以内 固定禁言一小时
                    $duration = 60;
                } else {
                    $duration = 60 * 24;
                }

                // 积分清零
                Cache::removeKey($key, $user_id);

                // 设置禁言
                CQAPI::set_group_ban($self_id, [
                    'group_id' => $group_id,
                    'user_id' => $user_id,
                    'duration' => $duration * 60
                ]);

                // 发消息说他被禁言了
                $msg = sprintf(
                    '[负分禁言] %s 积分居然变成了负数，系统以将他积分清零，并禁言 %s 分钟作为惩罚。',
                    CQ::at($user_id),
                    $duration
                );
                CQAPI::send_group_msg($self_id, ["group_id" => $group_id, "message" => $msg]);
            }

            return Cache::get($key)[$user_id];
        }

        return false;
    }

    /**
     * 获取用户积分
     *
     * @param int      $self_id
     * @param int      $user_id
     * @param int|null $group_id
     * @return int
     */
    public static function get(int $self_id, int $user_id, int $group_id = null)
    {
        $key = sprintf('Integral:%s:%s', $self_id, $group_id ?? '');

        return (Cache::get($key) ?? [])[$user_id];
    }

    /**
     * 奇遇事件
     *
     * @param int  $max
     * @param bool $isProcessing 是否马上处理
     * @return array
     */
    public function adventure(int $max = 10, bool $isProcessing = true): array
    {
        // 默认返回值
        $ret_data = [
            'active' => false,
        ];

        // 有 10% 的几率触发奇遇
        if (rand(1, 100) < $max) {

            // 取出奇遇事件列表
            $adventure = Cache::get('Integral:Adventure');

            // 生成随机值
            $rand = rand(1, $adventure['level_sum']);

            // 遍历取出奇遇事件
            foreach ($adventure['advens'] as $info) {
                // 判断是否在自己的区间
                if ($rand <= $info['level']) {
                    break;
                }
            }

            // 生成积分
            $add = isset($info['range']['1']) ? rand($info['range']['0'], $info['range']['1']) : $info['range']['0'];

            // 保存奇遇事件
            $ret_data = [
                'active' => true,
                'rand' => $rand,
                'add' => $add,
                'info' => $info
            ];

            // 生成事件
            $msg = sprintf(
                '[奇遇] %s 刚刚 %s, 积分 %+d',
                CQ::at($this->data['user_id']),
                $info['msg'],
                $add
            );

            // 如果立即处理发消息加积分
            if ($isProcessing) {
                $this->reply($msg);
                self::change($this->data['self_id'], $this->data['user_id'], $add, $this->data['group_id']);
            }
        }

        return $ret_data;
    }

    /**
     * 主动触发奇遇 有惩罚机制
     */
    public function activeAdventure($args): bool
    {
        // 随机一个数
        $value = rand(-10, -50);

        // 小于这个数 不奇遇 还扣钱
        if ($value > -25) {
            // 生成事件
            $msg = sprintf(
                '[主动奇遇] %s 试图触发奇遇，可惜被运气不佳，碰到个老骗子，反被骗走 %d 积分。',
                CQ::at($this->data['user_id']),
                abs($value)
            );
        } else {
            // 正常奇遇
            $adventure = $this->adventure( -$value + 30, false);

            if (!$adventure['active']) {
                // 生成事件
                $msg = sprintf(
                    '[主动奇遇] %s 试图触发奇遇，并被老神仙索要 %d 积分，苦苦等待后，居然什么也没发生？',
                    CQ::at($this->data['user_id']),
                    abs($value)
                );
            } else {
                // 生成事件
                $msg = sprintf(
                    '[主动奇遇] %s 试图触发奇遇，并被老神仙索要 %d 积分，苦苦等待后，居然 %s，获得 %+d 积分，最终积分 %+d',
                    CQ::at($this->data['user_id']),
                    abs($value),
                    $adventure['info']['msg'],
                    $adventure['add'],
                    $value += $adventure['add']
                );
            }
        }

        // 修改积分
        self::change($this->data['self_id'], $this->data['user_id'], $value, $this->data['group_id']);

        // 发送消息
        $this->reply($msg);

        return true;
    }
}
