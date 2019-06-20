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
    /**
     * 注册的钩子列表
     *
     * @var array
     */
    protected static $hooks = [];

    /**
     * @var array 路由与说明
     */
    protected static $routes = [];

    /**
     * 控制模块是否调用分割函数的变量
     * 当split 为FALSE时，表明CQBot主实例不需要调用execute函数
     * 当为TRUE时，CQBot在实例化模块对象后会执行execute函数
     * @var bool
     */
    public $split_execute = true;

    /**
     * @var CQBot
     */
    protected $main;

    /**
     * @var array 本次请求的数据
     */
    protected $data;

    /**
     * @var string 触发的命令名
     */
    protected $command;

    /**
     * @var array|string 本次请求的参数
     */
    private $args;

    public function __construct(CQBot $main, $data)
    {
        $this->main = $main;
        $this->data = $data;
    }

    /**
     * 获取钩子
     *
     * @return array
     */
    public static function getHooks(): array
    {
        return static::$hooks;
    }

    /**
     * 获取路由定义
     *
     * @return array
     */
    public static function getRoutes()
    {
        return static::$routes;
    }

    /**
     * 命令被调用时触发
     *
     * @param string $command 命令名称
     * @param string|array $args 相关参数
     * @return bool                 是否拦截消息(阻止继续冒泡)
     */
    public function command(string $command, $args): bool
    {
        // 保存数据
        $this->command = $command;
        $this->args = $args;

        // 判断路由是否存在
        $route = null;
        if (is_array(static::$routes[$command])) {
            $route = static::$routes[$command];
        } else {
            // 可能是别名 遍历每一个
            foreach (static::$routes as $key => $info) {
                var_dump($key, $info);
                if (is_array($info['alias']) && in_array($command, $info['alias'])) {
                    $route = $info;
                    break;
                }
            }
        }

        // 如果没找到 就不玩了
        if (!$route) return false;

        // 判断是否需要 Admin 权限
        if ($route['isAdmin'] && !$this->isAdmin()) {
            $this->reply("[提示] 你无权使用此命令.");
            return false;
        }

        // 判断是否有限流
        if (isset($route['limit'])) {
            $key = sprintf(
                'Limit:%s:%s:%s',
                $route['limit']['bucket_name'],
                $this->data['group_id'] ?? '',
                $this->data['user_id']
            );

            // 查询限流记录
            if (!($limit = Cache::get($key)) || ($limit['time'] + $route['limit']['period'] * 60) < time()) {
                Cache::set($key, ['time' => time(), 'count' => 1]);
            } else {
                // 次数是否超了
                if ($limit['time'] < $route['limit']['max']) {
                    // 没超次数+1
                    Cache::appendKey($key, 'count', $route['limit']['max'] + 1);
                } else {
                    // 超了 不让发
                    $msg = sprintf(
                        '[频率限制] %s %s。',
                        CQ::at($this->data['user_id']),
                        $route['limit']['tips'] ?? '亲，你要控几你寂几啊。'
                    );
                    $this->reply($msg);

                    // 是否禁言
                    if (!empty($route['limit']['ban'])) {
                        if (is_array($route['limit']['ban'])) {
                            $time = rand($route['limit']['ban']['0'], $route['limit']['ban']['1']);
                        } else {
                            $time = $route['limit']['ban'];
                        }
                        // 发送禁言
                        CQAPI::set_group_ban($this->data['self_id'], [
                            'group_id' => $this->data['group_id'],
                            'user_id' => $this->data['user_id'],
                            'duration' => $time * 60
                        ]);
                    }
                }
            }
        }

        // 调用
        return $this->{$route['action']}($args);
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

    public function getUser($data = null)
    {
        return CQUtil::getUser($data === null ? $this->data["user_id"] : $data["user_id"]);
    }

    public function getUserId($data = null)
    {
        return $data === null ? strval($this->data["user_id"]) : strval($data["user_id"]);
    }

    public function reply($msg, callable $callback = null)
    {
        return $this->main->reply($msg, $callback);
    }

    public function getMessageType()
    {
        return $this->data["message_type"];
    }

    public function getRobotId()
    {
        return $this->data["self_id"];
    }

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
