<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/4/19
 * Time: 14:55
 */

/**
 * Class Manage
 * 框架管理模块，里面已经附带了一些查看状态、重载和停止的功能
 */
class Manage extends ModBase
{

    /**
     * 注册命令列表
     *
     * @var array
     */
    protected static $hooks = [
        'message' => ['group.normal.禁言']
    ];

    /**
     * @var array 路由
     */
    protected static $routes = [
        '禁言' => [
            'action' => 'group_ban',
            'description' => '禁言指定用户',
            'isAdmin' => true
        ]
    ];

    /**
     * 禁言某人
     *
     * @param $args
     * @return bool
     */
    public function group_ban($args): bool
    {
        // 无参数 则帮助
        if (empty($args) || count($args) < 2) {
            $this->reply("禁言 使用说明: \n#禁言 @被禁言的人 [禁言分钟数 0取消禁言]");
            return true;
        }

        // 解析参数一
        $aims = CQ::getCQ($args['0']);
        if (!$aims || $aims['type'] != 'at') {
            $this->reply("禁言 使用说明: \n#禁言 @被禁言的人 [禁言分钟数 0取消禁言]");
            return true;
        }

        // 解析参数二
        $time = intval($args['1']);

        // 设置禁言
        CQAPI::set_group_ban($this->getRobotId(), [
            'group_id' => $this->data['group_id'],
            'user_id' => $aims['params']['qq'],
            'duration' => $time * 60
        ]);

        // 补回积分
        Integral::change($this->data['self_id'], $this->data['user_id'], 10);

        return true;
    }


}
