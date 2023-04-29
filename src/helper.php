<?php

// 注册命令
\think\Console::addDefaultCommands([
    "Creatcode\\command\\CronCommand",
    "Creatcode\\command\\MakeCommand"
]);
