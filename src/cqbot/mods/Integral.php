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
        'message' => ['*', '@', '积分', 'group.奇遇', 'private.状态', 'group.@', 'group.normal.帮助'],
        'notice' => ['group_admin.set', 'group_decrease.kick'],
        'request' => ['group.invite']
    ];

    /**
     * 开启分词(解析参数)
     *
     * @var bool
     */
    public $split_execute = true;

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

    // 定时保存数据
    static function onTick($tick)
    {
        if ($tick % 600 == 0) {
            CQUtil::saveAllFiles();
        }
    }

    /**
     * 读取积分数据
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
     * 保存积分
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
     * 触发消息事件
     *
     * @param string $command
     * @param mixed $args
     * @return bool
     */
    public function command(string $command, $args): bool
    {
        switch ($command) {
            case '积分':
                $this->query();
                break;
            case '奇遇':
                $this->activeAdventure();
                break;
        }

        return true;
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
                $this->adventure();
            }
        }

        return false;
    }

    /**
     *  查询积分
     *
     * @return void
     */
    public function query()
    {
        // 整个群的积分
        $info = Cache::get($this->key) ?? [];

        // 是否未开通积分
        if (!(Cache::get($this->key) ?? [])[$this->data['user_id']]) {
            // 赠送积分
            $initial_score = rand(100, 500);

            // 添加群
            Cache::appendKey(self::$group_key, $this->key, $this->key);

            // 保存积分
            Cache::appendKey($this->key, $this->data['user_id'], $initial_score);

            $this->reply("首次开通，赠送您 {$initial_score} 积分。");
        } else {
            $this->reply("您的积分余额为: {$info[$this->data['user_id']]}");
        }
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
                    $duration = Cache::get($key)[$user_id] * 2;
                } else if (Cache::get($key)[$user_id] > 50) {
                    // 50分以内 固定禁言一小时
                    $duration = 60;
                } else {
                    $duration = 60 * 24;
                }

                // 积分清零
                Cache::removeKey($key, $user_id);
var_dump(Cache::get($key)[$user_id]);
                // 设置禁言
                CQAPI::set_group_ban($self_id, [
                    'group_id' => $group_id,
                    'user_id' => $user_id,
                    'duration' => $duration * 60
                ]);

                // 表示他被禁言了
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
    public function activeAdventure()
    {
        // 随机一个数
        $value = rand(10, 50);

        // 小于这个数 不奇遇 还扣钱
        if ($value < 25) {
            // 生成事件
            $msg = sprintf(
                '[主动奇遇] %s 试图触发奇遇，可惜被运气不佳，碰到个老骗子，反被骗走 %d 积分。',
                CQ::at($this->data['user_id']),
                -$value
            );

            // 修改积分
            self::change($this->data['self_id'], $this->data['user_id'], -$value, $this->data['group_id']);

            // 发送消息
            $this->reply($msg);
        } else {
            // 正常奇遇
            $adventure = $this->adventure(30 + $value, false);

            if (!$adventure['active']) {
                // 生成事件
                $msg = sprintf(
                    '[主动奇遇] %s 试图触发奇遇，并被老神仙索要 %d 积分，苦苦等待后，居然什么也没发生？',
                    CQ::at($this->data['user_id']),
                    -$value
                );

                // 修改积分
                self::change($this->data['self_id'], $this->data['user_id'], -$value, $this->data['group_id']);

                // 发送消息
                $this->reply($msg);
            } else {
                // 生成事件
                $msg = sprintf(
                    '[主动奇遇] %s 试图触发奇遇，并被老神仙索要 %d 积分，苦苦等待后，居然 %s，获得 %+d 积分，最终积分 %+d',
                    CQ::at($this->data['user_id']),
                    -$value,
                    $adventure['info']['msg'],
                    $adventure['add'],
                    -$value + $adventure['add']
                );

                // 修改积分
                self::change($this->data['self_id'], $this->data['user_id'], -$value + $adventure['add'], $this->data['group_id']);

                // 发送消息
                $this->reply($msg);
            }
        }
    }
}
