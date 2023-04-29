<?php

namespace Creatcode\Crontask\command;

use Creatcode\Crontask\HttpCrontab;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class CronCommand extends Command
{

    protected function configure()
    {
        $this->setName('secron:crontab')
            ->addArgument('action', Argument::OPTIONAL, 'start|stop|restart|reload|status|connections', 'start')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the http crontab server in daemon mode.')
            ->addOption('debug', null, Option::VALUE_NONE, 'Print log')
            ->setDescription('Run http crontab server');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = trim($input->getArgument('action'));
        if (!in_array($action, ['start', 'stop', 'restart', 'reload', 'status', 'connections'])) {
            $this->output->writeln('action参数值非法');
            return false;
        }
        $options = $input->getOptions();
        $url     = '';
        if (config('crontask.base_url') !== null && config('crontask.base_url')) {
            // if (!preg_match('/https?:\/\//', config('crontask.base_url'))) {
            //     $this->output->writeln('crontab base_url 配置值非法');
            //     return false;
            // }
            $url = config('crontask.base_url');
        }

        $server   = new HttpCrontab($url);
        $database = config('database');
        $server->setName(config('crontask.name') ?: 'Crontab Server')
            ->setDbConfig($database ?? []);
        if (config('crontask.safe_key') !== null && config('crontask.safe_key')) {
            $server->setSafeKey(config('crontask.safe_key'));
        }
        $options['debug'] && $server->setDebug();
        $server->run();
    }
}
