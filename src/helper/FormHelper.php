<?php

declare (strict_types=1);

namespace think\admin\helper;

use think\admin\Helper;
use think\admin\service\SystemService;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 表单视图管理器
 * Class FormHelper
 * @package think\admin\helper
 */
class FormHelper extends Helper
{

    /**
     * 逻辑器初始化
     * @param Model|BaseQuery|string $dbQuery
     * @param string $template 视图模板名称
     * @param string $field 指定数据主键
     * @param array $where 额外更新条件
     * @param array $edata 表单扩展数据
     * @return array|boolean|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function init($dbQuery, string $template = '', string $field = '', array $where = [], array $edata = [])
    {
        $query = $this->buildQuery($dbQuery);
        $field = $field ?: ($query->getPk() ?: 'id');
        $value = $edata[$field] ?? input($field);
        if ($this->app->request->isGet()) {
            if ($value !== null) {
                $exist = $query->where([$field => $value])->where($where)->find();
                if ($exist instanceof Model) $exist = $exist->toArray();
                $edata = array_merge($edata, $exist ?: []);
            }
            if (false !== $this->class->callback('_form_filter', $edata)) {
                $this->class->fetch($template, ['vo' => $edata]);
            } else {
                return $edata;
            }
        }
        if ($this->app->request->isPost()) {
            $edata = array_merge($this->app->request->post(), $edata);
            if (false !== $this->class->callback('_form_filter', $edata, $where)) {
                $result = SystemService::instance()->save($query, $edata, $field, $where) !== false;
                if (false !== $this->class->callback('_form_result', $result, $edata)) {
                    if ($result !== false) {
                        $this->class->success(lang('think_library_form_success'));
                    } else {
                        $this->class->error(lang('think_library_form_error'));
                    }
                }
                return $result;
            }
        }
    }
}