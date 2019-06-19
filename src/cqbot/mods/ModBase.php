<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/4/12
 * Time: 10:39
 */

/**
 * Class ModBase
 * @method static onRequest($req)
 * @method static onNotice($req)
 */
abstract class ModBase
{
    protected $main;
    protected $data;

    /**
     * 注册的钩子列表
     *
     * @var array
     */
    protected static $hooks = [];

    /**
     * 控制模块是否调用分割函数的变量
     * 当split 为FALSE时，表明CQBot主实例不需要调用execute函数
     * 当为TRUE时，CQBot在实例化模块对象后会执行execute函数
     * @var bool
     */
    public $split_execute = false;

    public function __construct(CQBot $main, $data) {
        $this->main = $main;
        $this->data = $data;
    }

    /**
     * 获取钩子
     *
     * @return void
     */
    public static function getHooks()
    {
        return static::$hooks;
    }

    /**
     * 命令被调用时触发
     *
     * @param string $it
     * @param string|array $args
     * @return bool 是否拦截消息
     */
    public function command(string $command, $args): bool
    {
        return false;
    }

    /**
     * 收到消息
     *
     * @param string $content 消息内容
     * @param string $type 消息类型 at / all
     * @return bool 是否拦截
     */
    public function message(string $content, string $type): bool
    {
        return false;
    }

    public function getUser($data = null) { return CQUtil::getUser($data === null ? $this->data["user_id"] : $data["user_id"]); }

    public function getUserId($data = null) { return $data === null ? strval($this->data["user_id"]) : strval($data["user_id"]); }

    public function reply($msg, callable $callback = null) { return $this->main->reply($msg, $callback); }

    public function getMessageType() { return $this->data["message_type"]; }

    public function getRobotId() { return $this->data["self_id"]; }

    /**
     * 判断是否是机器人管理员
     * @param int|null $user_id
     * @return bool
     */
    public function isAdmin(int $user_id = null): bool
    {
        $user_id = $user_id ?? $this->data['user_id'];

        return in_array($user_id, settings()['admin']);
    }
}
