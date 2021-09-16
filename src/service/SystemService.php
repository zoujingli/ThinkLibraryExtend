<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\Helper;
use think\admin\Service;
use think\db\exception\ModelNotFoundException;
use think\helper\Str;

/**
 * 系统参数管理服务
 * Class SystemService
 * @package think\admin\service
 */
class SystemService extends Service
{

    /**
     * 配置数据缓存
     * @var array
     */
    protected $data = [];

    /**
     * 绑定配置数据表
     * @var string
     */
    protected $table = 'SystemConfig';

    /**
     * 设置配置数据
     * @param string $name 配置名称
     * @param mixed $value 配置内容
     * @return ?integer
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws ModelNotFoundException
     */
    public function set(string $name, $value = ''): ?int
    {
        $this->data = [];
        [$type, $field] = $this->_parse($name);
        if (is_array($value)) {
            $count = 0;
            foreach ($value as $kk => $vv) {
                $count += $this->set("{$field}.{$kk}", $vv);
            }
            return $count;
        } else {
            $this->app->cache->delete($this->table);
            $map = ['type' => $type, 'name' => $field];
            $data = array_merge($map, ['value' => $value]);
            $query = $this->app->db->name($this->table)->master(true)->where($map);
            return (clone $query)->count() > 0 ? $query->update($data) : $query->insert($data);
        }
    }

    /**
     * 读取配置数据
     * @param string $name 配置名称
     * @param string $default 默认内容
     * @return array|mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws ModelNotFoundException
     */
    public function get(string $name = '', string $default = '')
    {
        if (empty($this->data)) {
            $this->app->db->name($this->table)->cache($this->table)->select()->map(function ($item) {
                $this->data[$item['type']][$item['name']] = $item['value'];
            });
        }
        [$type, $field, $outer] = $this->_parse($name);
        if (empty($name)) {
            return $this->data;
        } elseif (isset($this->data[$type])) {
            $group = $this->data[$type];
            if ($outer !== 'raw') foreach ($group as $kk => $vo) {
                $group[$kk] = htmlspecialchars($vo);
            }
            return $field ? ($group[$field] ?? $default) : $group;
        } else {
            return $default;
        }
    }

    /**
     * 数据增量保存
     * @param $query
     * @param array $data 需要保存的数据
     * @param string $key 更新条件查询主键
     * @param array $map
     * @return boolean|integer 失败返回 false, 成功返回主键值或 true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws ModelNotFoundException
     */
    public function save($query, array &$data, string $key = 'id', array $map = [])
    {
        $query = Helper::buildQuery($query)->master()->strict(false);
        if (empty($map[$key])) {
            $value = $data[$key] ?? null;
            if (is_string($value) && strpos($value, ',') !== false) {
                $query->whereIn($key, str2arr($value));
            } else {
                $query->where([$key => $value]);
            }
        }
        if (($model = $query->where($map)->find()) && !empty($model)) {
            if ($model->save($data) === false) return false;
            // 模型自定义事件回调
            if (method_exists($model, 'onAdminUpdate')) {
                $model->onAdminUpdate(strval($model[$key] ?? ''));
            }
            $data = $model->toArray();
            return $data[$key] ?? true;
        } else {
            $model = $query->getModel();
            if ($model->data($data)->save() === false) return false;
            // 模型自定义事件回调
            if (method_exists($model, 'onAdminInsert')) {
                $model->onAdminInsert(strval($model[$key] ?? ''));
            }
            $data = $model->toArray();
            return $model[$key] ?? true;
        }
    }

    /**
     * 解析缓存名称
     * @param string $rule 配置名称
     * @return array
     */
    private function _parse(string $rule): array
    {
        $type = 'base';
        if (stripos($rule, '.') !== false) {
            [$type, $rule] = explode('.', $rule, 2);
        }
        [$field, $outer] = explode('|', "{$rule}|");
        return [$type, $field, strtolower($outer)];
    }

    /**
     * 生成最短URL地址
     * @param string $url 路由地址
     * @param array $vars PATH 变量
     * @param boolean|string $suffix 后缀
     * @param boolean|string $domain 域名
     * @return string
     */
    public function sysuri(string $url = '', array $vars = [], $suffix = true, $domain = false): string
    {
        $ext = $this->app->config->get('route.url_html_suffix', 'html');
        $pre = $this->app->route->buildUrl('@')->suffix(false)->domain($domain)->build();
        $uri = $this->app->route->buildUrl($url, $vars)->suffix($suffix)->domain($domain)->build();
        // 默认节点配置数据
        $app = $this->app->config->get('route.default_app') ?: 'index';
        $act = Str::lower($this->app->config->get('route.default_action') ?: 'index');
        $ctr = Str::snake($this->app->config->get('route.default_controller') ?: 'index');
        // 替换省略链接路径
        return preg_replace([
            "#^({$pre}){$app}/{$ctr}/{$act}(\.{$ext}|^\w|\?|$)?#i",
            "#^({$pre}[\w\.]+)/{$ctr}/{$act}(\.{$ext}|^\w|\?|$)#i",
            "#^({$pre}[\w\.]+)(/[\w\.]+)/{$act}(\.{$ext}|^\w|\?|$)#i",
        ], ['$1$2', '$1$2', '$1$2$3'], $uri);
    }

    /**
     * 保存数据内容
     * @param string $name
     * @param mixed $value
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws ModelNotFoundException
     */
    public function setData(string $name, $value)
    {
        $data = ['name' => $name, 'value' => serialize($value)];
        return $this->save('SystemData', $data, 'name');
    }

    /**
     * 获取数据库所有数据表
     * @return array [table, total, count]
     */
    public function getTables(): array
    {
        $tables = [];
        foreach ($this->app->db->query("show tables") as $item) {
            $tables = array_merge($tables, array_values($item));
        }
        return [$tables, count($tables), 0];
    }

    /**
     * 读取数据内容
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getData(string $name, $default = [])
    {
        try {
            $value = $this->app->db->name('SystemData')->where(['name' => $name])->value('value');
            return is_null($value) ? $default : unserialize($value);
        } catch (\Exception $exception) {
            return $default;
        }
    }

    /**
     * 写入系统日志内容
     * @param string $action
     * @param string $content
     * @return boolean
     */
    public function setOplog(string $action, string $content): bool
    {
        $oplog = $this->getOplog($action, $content);
        return $this->app->db->name('SystemOplog')->insert($oplog) !== false;
    }

    /**
     * 获取系统日志内容
     * @param string $action
     * @param string $content
     * @return array
     */
    public function getOplog(string $action, string $content): array
    {
        return [
            'node'     => AdminNodeService::instance()->getCurrent(),
            'action'   => $action, 'content' => $content,
            'geoip'    => $this->app->request->ip() ?: '127.0.0.1',
            'username' => AdminAuthService::instance()->getUserName() ?: '-',
        ];
    }

    /**
     * 打印输出数据到文件
     * @param mixed $data 输出的数据
     * @param boolean $new 强制替换文件
     * @param string|null $file 文件名称
     * @return false|int
     */
    public function putDebug($data, bool $new = false, ?string $file = null)
    {
        if (is_null($file)) $file = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . date('Ymd') . '.log';
        $str = (is_string($data) ? $data : ((is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true))) . PHP_EOL;
        return $new ? file_put_contents($file, $str) : file_put_contents($file, $str, FILE_APPEND);
    }
}