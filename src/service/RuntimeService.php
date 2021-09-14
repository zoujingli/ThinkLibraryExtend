<?php

namespace think\admin\service;

use think\admin\Service;

/**
 * 系统初始化运行服务
 * Class RuntimeService
 * @package think\admin\service
 */
class RuntimeService extends Service
{

    /**
     * 运行数据对象
     * @var array
     */
    protected $data = [];

    /**
     * 配置文件路径
     * @var string
     */
    protected $file = '';

    /**
     * 服务初始化
     */
    public function initialize()
    {
        $this->file = $this->app->getRootPath() . 'runtime/.env';
    }

    /**
     * 启动系统服务
     */
    public function start()
    {
        $this->app->debug($this->debug());
        $response = $this->app->http->run();
        $response->send();
        $this->app->http->end($response);
    }


    /**
     * 初始化命令行主程序
     * @throws \Exception
     */
    public function command()
    {
        $this->app->debug($this->debug());
        $this->app->console->run();
    }

    /**
     * 当前运行模式
     * @return boolean
     */
    public function debug(): bool
    {
        return $this->get('mode') !== 'product';
    }

    /**
     * 设置实时运行配置
     * @param null|mixed $mode 支持模式
     * @param null|array $appmap 应用映射
     * @param null|array $domain 域名映射
     * @return boolean 是否调试模式
     */
    public function set(?string $mode = null, ?array $appmap = [], ?array $domain = []): bool
    {
        $data = $this->get();
        $this->data['mode'] = $mode ?: $data['mode'];
        $this->data['appmap'] = $this->uniqueArray($data['appmap'], $appmap);
        $this->data['domain'] = $this->uniqueArray($data['domain'], $domain);
        // 组装配置内容
        $rows[] = "[RUNTIME]\r\nmode = {$this->data['mode']}";
        foreach ($this->data['appmap'] as $key => $item) $rows[] = "appmap[{$key}] = {$item}";
        foreach ($this->data['domain'] as $key => $item) $rows[] = "domain[{$key}] = {$item}";
        // 写入配置参数
        file_put_contents($this->file, join("\r\n", $rows));
        return $this->apply($this->data);
    }

    /**
     * 获取实时运行配置
     * @param null|string $name 配置名称
     * @param array $default 配置内容
     * @return array|string
     */
    public function get(?string $name = null, array $default = [])
    {
        if (empty($this->data)) {
            if (file_exists($this->file)) {
                $this->app->env->load($this->file);
            }
            $this->data = [
                'mode'   => $this->app->env->get('RUNTIME_MODE') ?: 'development',
                'appmap' => $this->app->env->get('RUNTIME_APPMAP') ?: [],
                'domain' => $this->app->env->get('RUNTIME_DOMAIN') ?: [],
            ];
        }
        return is_null($name) ? $this->data : ($this->data[$name] ?? $default);
    }

    /**
     * 清理运行缓存
     */
    public function clear(): void
    {
        $data = $this->get();
        $this->app->cache->clear();
        $this->app->console->call('clear', ['--dir']);
        $this->set($data['mode'], $data['appmap'], $data['domain']);
    }

    /**
     * 判断运行环境
     * @param string $type 运行模式（dev|demo|local）
     * @return boolean
     */
    public function mode(string $type = 'dev'): bool
    {
        $domain = $this->app->request->host(true);
        $isDemo = is_numeric(stripos($domain, 'thinkadmin.top'));
        $isLocal = in_array($domain, ['127.0.0.1', 'localhost']);
        if ($type === 'dev') return $isLocal || $isDemo;
        if ($type === 'demo') return $isDemo;
        if ($type === 'local') return $isLocal;
        return true;
    }

    /**
     * 绑定应用实时配置
     * @param array $data 配置数据
     * @return boolean 是否调试模式
     */
    public function apply(array $data = []): bool
    {
        if (empty($data)) $data = $this->get();
        $bind['app_map'] = $this->uniqueArray($this->app->config->get('app.app_map', []), $data['appmap']);
        $bind['domain_bind'] = $this->uniqueArray($this->app->config->get('app.domain_bind', []), $data['domain']);
        $this->app->config->set($bind, 'app');
        return $this->app->debug($data['mode'] !== 'product')->isDebug();
    }

    /**
     * 压缩发布项目
     */
    public function optimize(): void
    {
        $connection = $this->app->db->getConfig('default');
        $this->app->console->call("optimize:schema", ["--connection={$connection}"]);
        foreach (AdminNodeService::instance()->getModules() as $module) {
            $path = $this->app->getRootPath() . 'runtime' . DIRECTORY_SEPARATOR . $module;
            file_exists($path) && is_dir($path) or mkdir($path, 0755, true);
            $this->app->console->call("optimize:route", [$module]);
        }
    }

    /**
     * 获取唯一数组参数
     * @param array ...$args
     * @return array
     */
    private function uniqueArray(...$args): array
    {
        return array_unique(array_reverse(array_merge(...$args)));
    }
}