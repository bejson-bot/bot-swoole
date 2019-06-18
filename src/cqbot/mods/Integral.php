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
        'MessageEvent' => ['*', '积分']
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
        }

        return true;
    }


    /**
     *  查询积分
     *
     * @param array $it
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
     * 收到消息时的处理
     *
     * @param string $content
     * @return bool
     */
    public function message(string $content): bool
    {
        Console::info($content);
        if ($value = (Cache::get($this->key) ?? [])[$this->data['user_id']]) {
            // 给用户加分
            $this->add($value);
        }

        return false;
    }

    /**
     * 给用户添加积分
     *
     * @param int $value
     * @return void
     */
    public function add(int $value)
    {
        // 触发随机事件 10%
        $add = 1; // 默认是正常的 加一分

        // 字数也决定触发几率
        // $rule = strlen()

        if (rand(1, 100) < 10) {

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

            // 生成事件
            $msg = sprintf(
                '[奇遇] %s 刚刚 %s, 积分 %+d',
                CQ::at($this->data['user_id']),
                $info['msg'],
                $add
            );

            // 发送消息
            $this->reply($msg);
        }

        // 保存积分
        Cache::appendKey($this->key, $this->data['user_id'], $value + $add);
    }
}
