<?php

declare(strict_types=1);

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
        $this->setName('crontask:crontab')
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
        if (!config('crontabtask.base_url')) {
            $this->output->writeln('base_url不能为空');
            return false;
        }

        $url = "tcp://" . config('crontabtask.base_url');
        $server   = new HttpCrontab($url);
        $database = config('database');
        $server->setName(config('crontabtask.name') ?: 'Crontab Server')
            ->setDbConfig($database ?? [])
            ->setLog(config('crontabtask.log'))
            ->setLock(config('crontabtask.driver'));

        if (config('crontabtask.safe_key') !== null && config('crontabtask.safe_key')) {
            $server->setSafeKey(config('crontabtask.safe_key'));
        }

        $options['debug'] && $server->setDebug();

        $server->run();
    }
}
