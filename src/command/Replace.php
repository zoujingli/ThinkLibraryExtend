<?php

declare (strict_types=1);

namespace think\admin\command;

use Exception;
use think\admin\Command;
use think\admin\service\SystemService;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\helper\Str;

/**
 * 数据库字符替换
 * Class Replace
 * @package think\admin\command
 */
class Replace extends Command
{
    /**
     * 指令任务配置
     */
    protected function configure()
    {
        $this->setName('xadmin:replace');
        $this->addArgument('search', Argument::OPTIONAL, '查找替换的字符内容', '');
        $this->addArgument('replace', Argument::OPTIONAL, '目标替换的字符内容', '');
        $this->setDescription('Database Field chars Replace, Argument: SearchText ReplaceText');
    }

    /**
     * 任务执行入口
     * @param Input $input
     * @param Output $output
     * @return Replace|Command
     * @throws Exception
     */
    protected function execute(Input $input, Output $output)
    {
        if (($search = $input->getArgument('search')) === '') {
            return $this->setQueueError('查找替换字符内容不能为空！');
        }
        if (($repalce = $input->getArgument('replace')) === '') {
            return $this->setQueueError('目标替换字符内容不能为空！');
        }
        [$tables, $total, $count] = SystemService::instance()->getTables();
        foreach ($tables as $table) {
            $data = [];
            $this->setQueueMessage($total, ++$count, sprintf("准备替换数据表 %s", Str::studly($table)));
            foreach ($this->app->db->table($table)->getFields() as $field => $attrs) {
                if (preg_match('/char|text/', $attrs['type'])) {
                    $data[$field] = $this->app->db->raw(sprintf('REPLACE(`%s`,"%s","%s")', $field, $search, $repalce));
                }
            }
            if (count($data) > 0) {
                if ($this->app->db->table($table)->where('1=1')->update($data) !== false) {
                    $this->setQueueMessage($total, $count, sprintf("成功替换数据表 %s", Str::studly($table)), 1);
                } else {
                    $this->setQueueMessage($total, $count, sprintf("失败替换数据表 %s", Str::studly($table)), 1);
                }
            } else {
                $this->setQueueMessage($total, $count, sprintf("无需替换数据表 %s", Str::studly($table)), 1);
            }
        }
        return $this->setQueueSuccess('批量替换成功');
    }
}