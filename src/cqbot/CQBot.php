<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/4/12
 * Time: 10:43
 */

class CQBot
{
    /** @var Framework */
    public $framework;

    //传入数据
    public $data = null;

    //检测有没有回复过消息
    private $function_called = false;

    public $starttime;
    public $endtime;
    public $self_id;

    /**
     * 谁能告诉我 这个变量是干嘛的
     *
     * @var int
     */
    public $circle;

    public function __construct(Framework $framework, $circle, $package) {
        $this->circle = $circle;
        $this->starttime = microtime(true);
        $this->framework = $framework;
        $this->data = $package;
        $this->self_id = $this->data["self_id"];
    }

    /**
     * 处理消息
     *
     * @return bool
     */
    public function execute() {
        if ($this->circle >= 5) return false;
        if ($this->data === null) return false;

        // 如果是 机器人的消息 则不处理
        if (isset($this->data["user_id"]) && CQUtil::isRobot($this->data["user_id"])) return false;

        // 看不懂 暂时注释
        // 如果是 管理群 且 -> 机器人不是机器人管理员 则 不处理?
//        if (isset($this->data["group_id"]) && $this->data["group_id"] == Cache::get("admin_group")) {
//            // 原来是 Cache::get("admin_active")
//            if ($this->getRobotId() != Cache::get("admin")) {
//                // return false;
//            }
//        }

        // 空消息不回
        if ($this->data["message"] == "")
            return false;

        // 如果消息是 # 开头就表示命令 则取出消息内容
        $regex = '/^#(?<cmd>[^\s]+)(?:\s+(?<args>[^$]+))?/';
        $matches = $this->data["message"];
        $types = []; // 运行的命令列表
        if ($this->data["message"]['0'] == '#') {
            if (!preg_match($regex, $this->data["message"], $matches)) {
                // 获取失败则作为 文本处理
                $matches = $this->data["message"];
            } else {
                $types[] = 'command';
            }
        }

        // 判断是否被 at
        if (stripos($this->data['message'], CQ::at($this->getRobotId())) !== false) {
            $types[] = 'at';
        }

        // 所有消息的钩子
        $types[] = 'all';

        // 遍历所有钩子类型
        foreach ($types as $type) {
            // 整理
            $hookd = [
                $this->data['message_type'],
                $this->data['sub_type'],
                $type
            ];

            // 遍历每一层
            $send_ret = false; // 消息是否被拦截(取消继续冒泡)
            do {
                $hook_name = implode('.', $hookd);

                // 如果有 定义这个钩子则遍历每个 mod
                if (!empty(Cache::$reg_hooks['message'][$hook_name])) {
                    $hooks = $type == 'command' ? Cache::$reg_hooks['message'][$hook_name][$matches['cmd']] ?? [] : Cache::$reg_hooks['message'][$hook_name];
                    foreach ($hooks as $mod_name) {
                        $mod_obj = new $mod_name($this, $this->data);
                        if ($type == 'command') {
                            if ($mod_obj->split_execute) {
                                $matches["args"] = explodeMsg($matches["args"]);
                            }
                            $send_ret = $mod_obj->command($matches['cmd'], $matches["args"]);
                            // echo "{$hook_name}.{$matches['cmd']} = ". ($send_ret ? 'true' : 'false') ."\n";
                        } else {
                            $send_ret = $mod_obj->message($this->data['message'], $type);
                            // echo "{$hook_name}.message = ". ($send_ret ? 'true' : 'false') ."\n";
                        }

                        // 拦截消息 阻止其他钩子
                        if ($send_ret) break 3;
                    }
                }

                // 向上一节冒泡
                array_splice($hookd, -2, 1);
            } while (count($hookd) >= 1);
        }

        $this->endtime = microtime(true);
        return $this->function_called;
    }

    /**
     * 快速回复消息
     * @param $msg
     * @param callable|null $callback
     * @param bool $async
     * @return bool
     */
    public function reply($msg, callable $callback = null, $async = false) {
        $this->function_called = true;
        switch ($this->data["message_type"]) {
            case "group":
                $this->function_called = true;
                if (!$async) return CQAPI::send_group_msg($this->getRobotId(), ["group_id" => $this->data["group_id"], "message" => $msg], $callback);
                else return CQAPI::send_group_msg_async($this->getRobotId(), ["group_id" => $this->data["group_id"], "message" => $msg], $callback);
            case "private":
                $this->function_called = true;
                if (!$async) return CQAPI::send_private_msg($this->getRobotId(), ["user_id" => $this->data["user_id"], "message" => $msg], $callback);
                else return CQAPI::send_private_msg_async($this->getRobotId(), ["user_id" => $this->data["user_id"], "message" => $msg], $callback);
            case "discuss":
                $this->function_called = true;
                if (!$async) return CQAPI::send_discuss_msg($this->getRobotId(), ["discuss_id" => $this->data["discuss_id"], "message" => $msg], $callback);
                else return CQAPI::send_discuss_msg_async($this->getRobotId(), ["discuss_id" => $this->data["discuss_id"], "message" => $msg], $callback);
            case "wechat":
                //TODO: add wechat account support in the future
                break;
        }
        return false;
    }

    public function isAdmin($user) {
        if (in_array($user, Cache::get("admin"))) return true;
        else return false;
    }

    public function replace($msg, $dat) {
        $msg = str_replace("{at}", '[CQ:at,qq=' . $dat["user_id"] . ']', $msg);
        $msg = str_replace("{and}", '&', $msg);
        while (strpos($msg, '{') !== false && strpos($msg, '}') !== false) {
            if (strpos($msg, '{') > strpos($msg, '}')) return $msg;
            $start = strpos($msg, '{');
            $end = strpos($msg, '}');
            $sub = explode("=", substr($msg, $start + 1, $end - $start - 1));
            switch ($sub[0]) {
                case "at":
                    $qq = $sub[1];
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), '[CQ:at,qq=' . $qq . ']', $msg);
                    break;
                case "image":
                case "record":
                    $pictFile = $sub[1];
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), '[CQ:' . $sub[0] . ',file=' . $pictFile . ']', $msg);
                    break;
                case "dice":
                    $file = $sub[1];
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), '[CQ:dice,type=' . $file . ']', $msg);
                    break;
                case "shake":
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), '[CQ:shake]', $msg);
                    break;
                case "music":
                    $id = $sub[1];
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), '[CQ:music,type=163,id=' . $id . ']', $msg);
                    break;
                case "internet":
                    array_shift($sub);
                    $id = implode("=", $sub);
                    if (substr($id, 0, 7) != "http://") $id = "http://" . $id;
                    $is = file_get_contents($id, false, NULL, 0, 1024);
                    if ($is == false) $is = "[请求时发生了错误] 如有疑问，请联系管理员";
                    $msg = str_replace(substr($msg, $start, $end - $start + 1), $is, $msg);
                    break 2;
                default:
                    break 2;
            }
        }
        return $msg;
    }

    /**
     * 返回当前机器人的id
     * @return string|null
     */
    public function getRobotId() {
        return $this->data["self_id"] ?? null;
    }
}
