<?php

namespace think\admin\command;

use think\admin\Command;
use think\admin\Exception;
use think\admin\Library;
use think\admin\service\SystemService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 扩展工具集合
 * Class Develop
 * @package think\admin\command
 */
class Develop extends Command
{
    protected function configure()
    {
        $this->setName('xadmin:develop');
        $this->addArgument('action', Argument::OPTIONAL, 'ActionName', '');
        $this->setDescription("Custom extension collection, Argument: database|version");
    }

    /**
     * @param \think\console\Input $input
     * @param \think\console\Output $output
     * @return void
     */
    protected function execute(Input $input, Output $output)
    {
        $action = $input->hasOption('action') ? 'database' : $input->getArgument('action');
        if (method_exists($this, $method = "{$action}Action")) return $this->$method();
        $this->output->error("># Wrong operation, Allow database|version");
    }

    /**
     * 优化所有数据表
     * @throws Exception
     */
    protected function databaseAction(): void
    {
        $this->setQueueProgress("正在获取需要优化的数据表", 0);
        [$tables, $total, $count] = SystemService::instance()->getTables();
        $this->setQueueProgress("总共需要优化 {$total} 张数据表", 0);
        foreach ($tables as $table) {
            $this->setQueueMessage($total, ++$count, "正在优化数据表 {$table}");
            $this->app->db->query("REPAIR TABLE `{$table}`");
            $this->app->db->query("OPTIMIZE TABLE `{$table}`");
            $this->setQueueMessage($total, $count, "完成优化数据表 {$table}", 1);
        }
        $this->setQueueSuccess("已完成对 {$total} 张数据表优化操作");
    }

    /**
     * 显示版本号
     */
    protected function versionAction(): void
    {
        $this->output->writeln('ThinkPHPCore ' . $this->app->version());
        $this->output->writeln('ThinkLibVers ' . Library::VERSION);
    }
}