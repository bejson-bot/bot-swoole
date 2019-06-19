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
        'message' => ['帮助', '菜单', 'help']
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
