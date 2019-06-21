<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/7/17
 * Time: 2:21 PM
 */

/**
 * Class Help
 * 帮助功能类 用于功能说明
 */
class Help extends ModBase
{
    /**
     * @var array 注册命令列表
     */
    protected static $hooks = [
        'message' => ['帮助', '菜单', 'help', 'group.关闭', 'group.退下', '开启']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [
        '帮助' => [
            'action' => 'helpMain',
            'description' => '查看帮助菜单',
            'alias' => ['菜单', 'help']
        ],
        '帮助 积分' => [
            'description' => '查看关于积分机制的解释'
        ],
        '关闭' => [
            'action' => 'closeBot',
            'description' => '关闭机器人, 可以指定关闭到时间 格式为 Y-m-d H:i:s 或 1天/时/分',
            'alias' => '退下'
        ],
        '开启' => [
            'action' => 'openBot',
            'description' => '打开机器人',
        ]
    ];

    /**
     * 帮助主菜单
     *
     * @param $args
     * @return bool
     */
    public function helpMain($args): bool
    {
        if (!isset($args['0'])) {
            $this->reply($this->getHelpText());
            return true;
        }

        switch ($args['0']) {
            case '积分':
                $msg = "「积分机制」\n";
                $msg .= "#积分 查询当前积分，首次发送开通积分 随机赠送100-500积分。\n";
                $msg .= "群内正常聊天，每次+1分，10%的几率触发奇遇。\n";
                $msg .= "使用 # 开头消息，被视为使用命令，不论是否成功，均-1分\n";
                $msg .= "当积分为负数时，会被禁言（负分越多，禁言越久），同时积分被清零";
                $this->reply($msg);
                return true;
        }

        return true;
    }

    /**
     * 关闭机器人
     *
     * @param $args
     * @return bool
     */
    public function closeBot($args): bool
    {
        return $this->changBotStatus($args, 'close');
    }

    /**
     * 打开
     *
     * @param $args
     * @return bool
     */
    public function openBot($args): bool
    {
        return $this->changBotStatus($args, 'open');

        // 预先获取
        $is_all = false;


        // 管理员逻辑特殊
        if ($this->isAdmin()) {
            // 打开所有群
            if (($args['0'] ?? '') == 'all') {
                $is_all = true;
            }
        }

        // 如果不是管理员 就保存并判断是否够三个人
        $key = 'Core::BotClose:Vote' . $this->data['group_id'];
        if (!$this->isAdmin()) {
            $default = ['type' => 'open', 'time' => time(), 'users' => [], 'num' => -2]; // 默认数据

            $info = Cache::get($key, $default);
            // 投票十分钟内有效
            if (((time() - $info['time']) > 60 * 10)) {
                $info = $default;
            }

            // 在列表里的就不能继续发起了
            if (in_array($this->data['user_id'], $info['users'])) {
                $this->reply(sprintf("[关闭机器人] %s 请等其他人投票。", CQ::at($this->data['user_id'])));
                return true;
            }

            // 人数不够就加一继续
            if ($info['num'] < 2) {
                Cache::appendKey($key, 'num', ++$info['num']);
                Cache::appendKey($key, 'users', ($info['users'][] = $this->data['user_id']));
                $this->reply(sprintf(
                    "[关闭机器人] %s 投票成功，当前支持 %s 的人数 %s / 3，共 %s 人。",
                    CQ::at($this->data['user_id']),
                    $info['type'] == 'close' ? '关闭' : '开启',
                    $info['num'],
                    count($info['users'])
                ));
                return true;
            }

            // 到这里说明 可以开启了，清除记录
            Cache::unset($key);
            $time = $info['time'];
        }

        // 设置开启
        if ($is_all) {
            Cache::unset('Core::BotClose');
        } else {
            Cache::unset('Core::BotClose:' . $this->data['group_id']);
        }

        $msg = sprintf("[关闭机器人] 我肥来啦~!!!");
        $this->reply($msg);
        return true;
    }

    /**
     * 修改机器人状态 开关
     * @param array  $args
     * @param string $status
     * @return bool
     */
    private function changBotStatus(array $args, string $status): bool
    {
        // 预先获取
        $is_all = false;
        $time = $args['0'] ?? '1天';

        // 管理员逻辑特殊
        if ($this->isAdmin()) {
            // 关闭所有群
            if (($args['0'] ?? '') == 'all' || ($args['1'] ?? '') == 'all') {
                $is_all = true;
            }
        }

        // 先尝试替换 年月日
        $time = str_ireplace(['年', '月', '天', '时', '分'], [' year', ' month', 'day', ' hour', ' min'], $time);
        $time = strtotime($time);
        if ($time <= time()) {
            $this->reply("[关闭机器人] 时间格式错误，不是正确的时间格式或不是未来时间\n正确格式: 2019-06-21 19:00 或者 1天");
            return true;
        }

        // 如果不是管理员 就保存关闭时间再判断是否够三个人
        $key = 'Core:BotClose:Vote:' . $this->data['group_id'];
        $info = [];
        if (!$this->isAdmin()) {
            $default = ['type' => $status, 'time' => time(), 'to_time' => $time,'users' => [], 'num' => 0]; // 默认数据
            $info = Cache::get($key, $default);

            // 投票十分钟内有效
            if (((time() - $info['time']) > 60 * 10)) {
                echo "res\n";
                $info = $default;
            }

            // 在列表里的就不能继续发起了
            if (in_array($this->data['user_id'], $info['users'])) {
                $this->reply(sprintf("[关闭机器人] %s 请等其他人投票。", CQ::at($this->data['user_id'])));
                return true;
            }

            // 人数不够就加一继续
            if ($info['num'] < 2) {
                $info['num']++;
                $info['users'][] = $this->data['user_id'];
                Cache::set($key, $info);

                $this->reply(sprintf(
                    "[关闭机器人] %s 投票成功，当前支持 %s 的人数 %s / 3，共 %s 人。",
                    CQ::at($this->data['user_id']),
                    $info['type'] == 'close' ? '关闭' : '开启',
                    $info['num'],
                    count($info['users'])
                ));
                return true;
            }

            // 到这里说明 可以修改了，清除记录
            Cache::unset($key);
            $time = $info['to_time'];
        }

        // 设置关闭
        if ($is_all) {
            if ($status == 'close') {
                Cache::set('Core::BotClose', $time);
                $msg = "[关闭机器人] 好了，臣妾先行退下了";
            } else {
                Cache::uet('Core::BotClose');
                $msg = "[关闭机器人] 我想死你们啦~!!!";
            }
        } else {
            if ($info['type'] == 'close') {
                Cache::set('Core::BotClose:' . $this->data['group_id'], $time);
                $msg = "[关闭机器人] 好了，臣妾先行退下了";
            } else {
                Cache::uet('Core::BotClose:' . $this->data['group_id']);
                $msg = "[关闭机器人] 我想死你们啦~!!!";
            }
        }

        $this->reply($msg);
        return true;
    }



    /**
     * 遍历模块 获取帮助说明
     *
     * @return string
     */
    private function getHelpText(): string
    {
        // 检查是否 管理员
        $isAdmin = $this->isAdmin();

        // 遍历所有模块
        $mods = Cache::get('mods');
        $content = "「机器人帮助」\n";
        foreach ($mods as $mod) {
            $routes = $mod::getRoutes();
            foreach ($routes as $command => $route) {
                $content .= sprintf(
                    "#%s\t\t: %s\n",
                    $command, //$this->mb_str_pad($command, 6),
                    $route['description']
                );
            }
        }

        return $content;
    }

    /**
     * 字符串中英混合
     *
     * @param $input
     * @param $pad_length
     * @param string $pad_string
     * @param int $pad_type
     * @return string
     */
    private function mb_str_pad(string $input, int $pad_length, string $pad_string = " ", int $pad_type = STR_PAD_RIGHT): string
    {
        // 计算差
        $add_len = ceil(($pad_length - strlen($input)) / 9);
        return $input . sprintf("%'\t{$add_len}s", '');

        // 先计算汉字长度
        $str_len = strlen($input);
        $mb_len = mb_strlen($input);

        // 需要补齐的长度
        $chinese_length = $str_len - ($str_len - $mb_len) / 2;
        $diff_len = $pad_length - $chinese_length;

        var_dump($chinese_length, $diff_len);

        $pad_str = sprintf("%'\t{$diff_len}s", '');

        return $input . $pad_str;
        /*
            $pad_type == STR_PAD_RIGHT ? $input . $pad_str
                : $pad_type == STR_PAD_LEFT ? $pad_str . $input
                : substr($input, $diff_len / 2) . $input . substr($input, $diff_len / 2);
        */
    }


}
