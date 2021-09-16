<?php

declare (strict_types=1);

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\Model;

/**
 * 数据更新管理器
 * Class SaveHelper
 * @package think\admin\helper
 */
class SaveHelper extends Helper
{

    /**
     * 逻辑器初始化
     * @param Model|BaseQuery|string $dbQuery
     * @param array $edata 表单扩展数据
     * @param string $field 数据对象主键
     * @param array $where 额外更新条件
     * @return boolean
     * @throws \think\db\exception\DbException
     */
    public function init($dbQuery, array $edata = [], string $field = '', array $where = []): bool
    {
        $query = $this->buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $edata = $edata ?: $this->app->request->post();
        $value = $this->app->request->post($field);

        // 主键限制处理
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
            if (isset($edata)) unset($edata[$field]);
        }

        // 前置回调处理
        if (false === $this->class->callback('_save_filter', $query, $edata)) {
            return false;
        }

        // 检查原始数据
        $result = $query->master()->where($where)->update($edata) !== false;

        // 模型自定义事件回调
        $model = $query->getModel();
        if ($result && method_exists($model, 'onAdminSave')) {
            $model->onAdminSave(strval($value));
        }

        // 结果回调处理
        if (false === $this->class->callback('_save_result', $result, $model)) {
            return $result;
        }

        // 回复前端结果
        if ($result !== false) {
            $this->class->success(lang('think_library_save_success'), '');
        } else {
            $this->class->error(lang('think_library_save_error'));
        }
    }
}
