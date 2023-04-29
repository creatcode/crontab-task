<?php

// 注册命令
\think\Console::addDefaultCommands([
    "Creatcode\\Crontask\\command\\CronCommand",
    "Creatcode\\Crontask\\command\\MakeCommand"
]);
