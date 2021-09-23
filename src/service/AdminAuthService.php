<?php

declare (strict_types=1);

namespace think\admin\service;

use think\admin\extend\DataExtend;
use think\admin\model\SystemAuth;
use think\admin\model\SystemNode;
use think\admin\model\SystemUser;
use think\admin\Service;

/**
 * 系统权限管理服务
 * Class AdminAuthService
 * @package think\admin\service
 */
class AdminAuthService extends Service
{

    /**
     * 是否已经登录
     * @return boolean
     */
    public function isLogin(): bool
    {
        return $this->getUserId() > 0;
    }

    /**
     * 是否为超级用户
     * @return boolean
     */
    public function isSuper(): bool
    {
        return $this->getUserName() === 'admin';
    }

    /**
     * 获取后台用户ID
     * @return integer
     */
    public function getUserId(): int
    {
        return intval($this->app->session->get('user.id', 0));
    }

    /**
     * 获取后台用户名称
     * @return string
     */
    public function getUserName(): string
    {
        return $this->app->session->get('user.username', '');
    }

    /**
     * 检查指定节点授权
     * --- 需要读取缓存或扫描所有节点
     * @param null|string $node
     * @return boolean
     * @throws \ReflectionException
     */
    public function check(?string $node = ''): bool
    {
        if ($this->isSuper()) return true;
        $service = AdminNodeService::instance();
        [$real, $nodes] = [$service->fullnode($node), $service->getMethods()];
        // 以下代码为兼容 win 控制器不区分大小写的验证问题
        foreach ($nodes as $key => $rule) {
            if (strpos($key, '_') !== false && strpos($key, '/') !== false) {
                $attr = explode('/', $key);
                $attr[1] = strtr($attr[1], ['_' => '']);
                $nodes[join('/', $attr)] = $rule;
            }
        }
        if (!empty($nodes[$real]['isauth'])) {
            return in_array($real, $this->app->session->get('user.nodes', []));
        } else {
            return !(!empty($nodes[$real]['islogin']) && !$this->isLogin());
        }
    }

    /**
     * 获取授权节点列表
     * @param array $checkeds
     * @return array
     * @throws \ReflectionException
     */
    public function tree(array $checkeds = []): array
    {
        [$nodes, $pnodes, $methods] = [[], [], array_reverse(AdminNodeService::instance()->getMethods())];
        foreach ($methods as $node => $method) {
            [$count, $pnode] = [substr_count($node, '/'), substr($node, 0, strripos($node, '/'))];
            if ($count === 2 && !empty($method['isauth'])) {
                in_array($pnode, $pnodes) or array_push($pnodes, $pnode);
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            } elseif ($count === 1 && in_array($pnode, $pnodes)) {
                $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            }
        }
        foreach (array_keys($nodes) as $key) foreach ($methods as $node => $method) if (stripos($key, $node . '/') !== false) {
            $pnode = substr($node, 0, strripos($node, '/'));
            $nodes[$node] = ['node' => $node, 'title' => $method['title'], 'pnode' => $pnode, 'checked' => in_array($node, $checkeds)];
            $nodes[$pnode] = ['node' => $pnode, 'title' => ucfirst($pnode), 'pnode' => '', 'checked' => in_array($pnode, $checkeds)];
        }
        return DataExtend::arr2tree(array_reverse($nodes), 'node', 'pnode', '_sub_');
    }

    /**
     * 初始化用户权限
     * @param boolean $force 强刷权限
     * @return $this
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function apply(bool $force = false): AdminAuthService
    {
        if ($force) $this->clear();
        if (($uid = $this->app->session->get('user.id'))) {
            $user = SystemUser::mk()->where(['id' => $uid])->find();
            if (!empty($user['authorize']) && !$this->isSuper()) {
                $db = SystemAuth::mk()->field('id')->where(['status' => 1])->whereIn('id', str2arr($user['authorize']));
                $user['nodes'] = array_unique(SystemNode::mk()->whereRaw("auth in {$db->buildSql()}")->column('node'));
            } else {
                $user['nodes'] = [];
            }
            $this->app->session->set('user', $user);
        }
        return $this;
    }

    /**
     * 清理节点缓存
     * @return $this
     */
    public function clear(): AdminAuthService
    {
        AdminTokenService::instance()->clear();
        $this->app->cache->delete('SystemAuthNode');
        return $this;
    }

}