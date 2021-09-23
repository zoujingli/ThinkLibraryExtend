<?php

namespace think\admin;

use think\admin\helper\DeleteHelper;
use think\admin\helper\FormHelper;
use think\admin\helper\QueryHelper;
use think\admin\helper\SaveHelper;
use think\Container;

/**
 * 基础模型类
 * Class Model
 * @see \think\db\Query
 * @mixin \think\db\Query
 * @package think\admin
 *
 * @method void onAdminSave(string $ids) 记录状态变更日志
 * @method void onAdminUpdate(string $ids) 记录更新数据日志
 * @method void onAdminInsert(string $ids) 记录新增数据日志
 * @method void onAdminDelete(string $ids) 记录删除数据日志
 *
 * @method bool mSave(array $data = [], string $field = '', array $where = []) static 快捷更新逻辑器
 * @method bool|null mDelete(string $field = '', array $where = []) static 快捷删除逻辑器
 * @method bool|array mForm(string $temp = '', string $field = '', array $where = [], array $data = []) static 快捷表单逻辑器
 * @method QueryHelper mQuery($input = null, callable $callable = null) static 快捷查询逻辑器
 */
abstract class Model extends \think\Model
{
    /**
     * 日志类型
     * @var string
     */
    protected $oplogType;

    /**
     * 日志名称
     * @var string
     */
    protected $oplogName;

    /**
     * 日志过滤
     * @var callable
     */
    public static $oplogCall;

    /**
     * 创建模型实例
     * @return static
     */
    public static function mk($data = [])
    {
        return new static($data);
    }

    /**
     * 调用魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return $this|false|mixed
     */
    public function __call($method, $args)
    {
        $oplogs = [
            'onAdminSave'   => "修改{$this->oplogName}[%s]状态",
            'onAdminUpdate' => "更新{$this->oplogName}[%s]记录",
            'onAdminInsert' => "增加{$this->oplogName}[%s]成功",
            "onAdminDelete" => "删除{$this->oplogName}[%s]成功",
        ];
        if (isset($oplogs[$method])) {
            if ($this->oplogType && $this->oplogName) {
                $changeIds = $args[0] ?? '';
                if (is_callable(static::$oplogCall)) {
                    $changeIds = call_user_func(static::$oplogCall, $method, $changeIds, $this);
                }
                sysoplog($this->oplogType, sprintf($oplogs[$method], $changeIds));
            }
            return $this;
        } else {
            return parent::__call($method, $args);
        }
    }

    /**
     * 静态魔术方法
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed|FormHelper|SaveHelper|QueryHelper|DeleteHelper
     */
    public static function __callStatic($method, $args)
    {
        $helpers = [
            'mForm'   => FormHelper::class,
            'mSave'   => SaveHelper::class,
            'mQuery'  => QueryHelper::class,
            'mDelete' => DeleteHelper::class,
        ];
        if (isset($helpers[$method])) {
            return Container::getInstance()->invokeClass($helpers[$method])->init(static::class, ...$args);
        } else {
            return parent::__callStatic($method, $args);
        }
    }
}