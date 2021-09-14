<?php

declare (strict_types=1);

namespace think\admin\helper;

use think\admin\Helper;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Model;

/**
 * 通用删除管理器
 * Class DeleteHelper
 * @package think\admin\helper
 */
class DeleteHelper extends Helper
{
    /**
     * 逻辑器初始化
     * @param Model|BaseQuery|string $dbQuery
     * @param string $field 操作数据主键
     * @param array $where 额外更新条件
     * @return boolean|null
     * @throws DbException
     */
    public function init($dbQuery, string $field = '', array $where = []): ?bool
    {
        $query = $this->buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $this->app->request->post($field);

        // 查询限制处理
        if (!empty($where)) $query->where($where);
        if (!isset($where[$field]) && is_string($value)) {
            $query->whereIn($field, str2arr($value));
        }

        // 前置回调处理
        if (false === $this->class->callback('_delete_filter', $query, $where)) {
            return null;
        }

        // 阻止危险操作
        if (!$query->getOptions('where')) {
            $this->class->error(lang('think_library_delete_error'));
        }

        // 组装执行数据
        $data = [];
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            if (in_array('deleted', $fields)) $data['deleted'] = 1;
            if (in_array('is_deleted', $fields)) $data['is_deleted'] = 1;
            if (isset($data['deleted']) || isset($data['is_deleted'])) {
                if (in_array('deleted_at', $fields)) $data['deleted_at'] = date('Y-m-d H:i:s');
                if (in_array('deleted_time', $fields)) $data['deleted_time'] = time();
            }
        }

        // 执行删除操作
        if ($result = (empty($data) ? $query->delete() : $query->update($data)) !== false) {
            // 模型自定义事件回调
            $model = $query->getModel();
            if (method_exists($model, 'onAdminDelete')) {
                $model->onAdminDelete(strval($value));
            }
        }

        // 结果回调处理
        if (false === $this->class->callback('_delete_result', $result)) {
            return $result;
        }

        // 回复返回结果
        if ($result !== false) {
            $this->class->success(lang('think_library_delete_success'), '');
        } else {
            $this->class->error(lang('think_library_delete_error'));
        }
    }
}
