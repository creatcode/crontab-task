<?php

declare(strict_types=1);

namespace Creatcode\Crontask\command;

use think\App;
use think\console\command\Make;
use think\console\input\Argument;

class MakeCommand extends Make
{
    protected $type = "Command";

    protected function configure()
    {
        parent::configure();
        $this->setName('crontask:makecommand')
            ->addArgument('commandName', Argument::OPTIONAL, "The name of the command")
            ->setDescription('Create a new command class');
    }

    protected function buildClass($name)
    {
        $commandName = $this->input->getArgument('commandName') ?: strtolower(basename($name));
        $namespace   = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');

        $class = str_replace($namespace . '\\', '', $name);
        $stub  = file_get_contents($this->getStub());

        return str_replace(['{%commandName%}', '{%className%}', '{%namespace%}', '{%app_namespace%}'], [
            $commandName,
            $class,
            $namespace,
            App::$namespace,
        ], $stub);
    }

    protected function getStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'command.stub';
    }

    // protected function getClassName($name)
    // {
    //     return parent::getClassName(ucfirst($name));
    // }

    protected function getNamespace($app, $module)
    {
        return parent::getNamespace($app, $module) . '\\command';
    }
}
