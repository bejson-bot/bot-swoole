<?php
/**
 * Created by PhpStorm.
 * User: jerry
 * Date: 2018/3/29
 * Time: 11:31
 */

class ErrorStatus
{
    static $error = [
        1400 => "POST 请求的正文格式不正确",
        1404 => "API 不存在",
        100 => "参数缺失或参数无效",
        102 => "酷q操作权限不足",
        103 => "用户权限不足或文件系统异常",
        201 => "工作线程池未正确初始化",
        -1 => "请求发送失败",
        -2 => "未收到服务器回复，可能未发送成功",
        -3 => "消息过长或为空",
        -4 => "消息解析过程异常",
        -5 => "日志功能未启用",
        -6 => "日志优先级错误",
        -7 => "数据入库失败",
        -8 => "不支持对系统帐号操作",
        -9 => "帐号不在该群内，消息无法发送",
        -10 => "该用户不存在/不在群内",
        -11 => "数据错误，无法请求发送",
        -12 => "不支持对匿名成员解除禁言",
        -13 => "无法解析要禁言的匿名成员数据",
        -14 => "由于未知原因，操作失败",
        -15 => "群未开启匿名发言功能，或匿名帐号被禁言",
        -16 => "帐号不在群内或网络错误，无法退出/解散该群",
        -17 => "帐号为群主，无法退出该群",
        -18 => "帐号非群主，无法解散该群",
        -19 => "临时消息已失效或未建立",
        -20 => "参数错误",
        -21 => "临时消息已失效或未建立",
        -22 => "获取QQ信息失败",
        -23 => "找不到与目标QQ的关系，消息无法发送",
        -26 => "消息过长"
    ];

    static function getMessage($retcode){
        return self::$error[$retcode] ?? "未知错误";
    }
}