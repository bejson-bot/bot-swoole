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
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['帮助', '菜单']
    ];

    /**
     * @var bool 拆解参数
     */
    public $split_execute = true;

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
            case '帮助':
            case "菜单":
                return $this->helpMain($args);
        }

        return false;
    }

    /**
     * 帮助主菜单
     *
     * @param $args
     * @return bool
     */
    public function helpMain($args): bool
    {
        if (!isset($args['0'])) {
            $msg = "「机器人帮助」\n";
            $msg .= "#烟斗: 禁言斗争，消耗积分禁言别人。\n";
            $msg .= "#禁言: 管理员功能，禁言指定人。\n\n";
            $msg .= "关于积分机制，请发送 “#帮助 积分”";
            $this->reply($msg);
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

}
