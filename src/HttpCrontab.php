<?php

declare(strict_types=1);

namespace Creatcode\Crontask;

use think\Console;
use think\Db;
use Workerman\Connection\TcpConnection;
use Workerman\Crontab\Crontab;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 注意：定时器开始、重起，编辑都是在下一分钟开始执行  暂停、删除立即生效
 * Class HttpCrontab
 * @package Creatcode\Crontask
 */
class HttpCrontab
{
    const FORBIDDEN_STATUS = 0;

    const NORMAL_STATUS = 1;

    // tcp请求参数
    const TCP_METHOD_PATH = [
        'index' => 'crontabIndex',
        'create' => 'crontabCreate',
        'edit' => 'crontabUpdate',
        'read' => 'crontabRead',
        'modify' => 'crontabModify',
        'reload' => 'crontabReload',
        'delete' => 'crontabDelete',
        'flow' => 'crontabFlow',
        'pool' => 'crontabPool',
        'ping' => 'crontabPong',
        'runone' => 'crontabRunOne'
    ];

    // 命令任务
    public const COMMAND_CRONTAB = '1';
    // 类任务
    public const CLASS_CRONTAB = '2';
    // URL任务
    public const URL_CRONTAB = '3';
    // 执行shell
    public const SHELL_CRONTAB = '4';
    // 执行Sql
    public const SQL_CRONTAB = '5';

    /**
     * worker 实例
     * @var Worker
     */
    private $worker;


    /**
     * 进程名
     * @var string
     */
    private $workerName = "Workerman Visible Crontab";


    /**
     * 任务进程池
     * @var Crontab[] array
     */
    private $crontabPool = [];

    /**
     * 调试模式
     * @var bool
     */
    private $debug = false;

    /**
     * 日志记录
     * @var bool
     */
    private $islog = false;

    /**
     * 错误信息
     * @var
     */
    private $errorMsg = [];

    /**
     * 定时任务表
     * @var string
     */
    private $systemCrontabTable = 'crontab_task';

    /**
     * 定时任务日志表
     * @var string
     */
    private $systemCrontabLogTable = 'crontab_task_log';

    /**
     * 定时任务锁表
     * @var string
     */
    private $systemCrontabLockTable = 'crontab_task_lock';

    /**
     * 锁标记
     * @var string
     */
    private $lockTagValue = 'taskLock';

    private $lockType;

    /**
     * 任务锁驱动
     *
     * @var [type]
     */
    private $lockDriver = null;

    /**
     * 最低PHP版本
     * @var string
     */
    private $lessPhpVersion = '7.2.5';

    /**
     * 安全秘钥
     * @var string
     */
    private $safeKey;

    /**
     * @param string $socketName 不填写表示不监听任何端口,格式为 <协议>://<监听地址> 协议支持 tcp、udp、unix、http、websocket、text
     * @param array $contextOption socket 上下文选项 http://php.net/manual/zh/context.socket.php
     */
    public function __construct(string $socketName, array $contextOption = [])
    {
        $this->checkEnv();
        $this->initWorker($socketName, $contextOption);
    }

    /**
     * 初始化 worker
     * @param string $socketName
     * @param array $contextOption
     */
    private function initWorker(string $socketName, array $contextOption = [])
    {
        $this->worker       = new Worker($socketName, $contextOption);
        $this->worker->name = $this->workerName;
        if (isset($contextOption['ssl'])) {
            $this->worker->transport = 'ssl'; //设置当前Worker实例所使用的传输层协议，目前只支持3种(tcp、udp、ssl)。默认为tcp。
        }
        $this->registerCallback();
    }

    /**
     * 开启任务锁
     *
     * @param string $driver
     * @return self
     */
    public function setLock(string $driver): self
    {
        if ($driver) {
            $driver = ucwords(strtolower($driver));
            $class = "\\Creatcode\\Crontask\\driver\\" . $driver;
            $failmsg = [];
            if (class_exists($class)) {
                $locktag = $this->lockTagValue;
                if ($driver == 'Mysql') {
                    $locktag = $this->systemCrontabLockTable;
                }
                $this->lockType = $driver;
                $this->lockDriver = new $class($locktag);
            } else {
                $failmsg[] = 'Task lock driver does not exist. ' . $class;
            }
            $this->errorMsg = $failmsg;
        }

        return $this;
    }

    /**
     * 启用安全模式
     * @return $this
     */
    public function setSafeKey($key): self
    {
        $this->safeKey = $key;

        return $this;
    }

    /**
     * 是否调试模式
     * @return $this
     */
    public function setDebug(): self
    {
        $this->debug = true;

        return $this;
    }

    /**
     * 是否开启日志记录
     * @return $this
     */
    public function setLog($param): self
    {
        $this->islog = false;
        if ($param === true) {
            $this->islog = true;
        }

        return $this;
    }

    /**
     * 设置当前Worker实例的名称,方便运行status命令时识别进程
     * 默认为none
     * @param string $name
     * @return $this
     */
    public function setName(string $name = "Workerman Crontab"): self
    {
        $this->worker->name = $name;

        return $this;
    }

    /**
     * 设置当前Worker实例启动多少个进程
     * Worker主进程会 fork出 count个子进程同时监听相同的端口，并行的接收客户端连接，处理连接上的事件
     * 默认为1
     * windows系统不支持此特性
     * @param int $count
     * @return $this
     */
    public function setCount(int $count = 1): self
    {
        $this->worker->count = $count;

        return $this;
    }

    /**
     * 设置当前Worker实例以哪个用户运行
     * 此属性只有当前用户为root时才能生效，建议$user设置权限较低的用户
     * 默认以当前用户运行
     * windows系统不支持此特性
     * @param string $user
     * @return $this
     */
    public function setUser(string $user = "root"): self
    {
        $this->worker->user = $user;

        return $this;
    }

    /**
     * 设置当前Worker实例的协议类
     * 协议处理类可以直接在实例化Worker时在监听参数直接指定
     * @param string $protocol
     * @return $this
     */
    public function setProtocol(string $protocol): self
    {
        $this->worker->protocol = $protocol;

        return $this;
    }

    /**
     * 以daemon(守护进程)方式运行
     * windows系统不支持此特性
     * @return $this
     */
    public function setDaemon(): self
    {
        Worker::$daemonize = true;

        return $this;
    }

    /**
     * 设置所有连接的默认应用层发送缓冲区大小。默认1M。可以动态设置
     * @param float|int $size
     * @return $this
     */
    public function setMaxSendBufferSize($size = 1024 * 1024): self
    {
        TcpConnection::$defaultMaxSendBufferSize = $size;

        return $this;
    }

    /**
     * 设置每个连接接收的数据包。默认10M。超包视为非法数据，连接会断开
     * @param float|int $size
     * @return $this
     */
    public function setMaxPackageSize($size = 10 * 1024 * 1024): self
    {
        TcpConnection::$defaultMaxPackageSize = $size;

        return $this;
    }

    /**
     * 指定日志文件
     * 默认为位于workerman下的 workerman.log
     * 日志文件中仅仅记录workerman自身相关启动停止等日志，不包含任何业务日志
     * @param string $path
     * @return $this
     */
    public function setLogFile(string $path = "./workerman.log"): self
    {
        Worker::$logFile = $path;

        return $this;
    }

    /**
     * 指定打印输出文件
     * 以守护进程方式(-d启动)运行时，所有向终端的输出(echo var_dump等)都会被重定向到 stdoutFile指定的文件中
     * 默认为/dev/null,也就是在守护模式时默认丢弃所有输出
     * windows系统不支持此特性
     * @param string $path
     * @return $this
     */
    public function setStdoutFile(string $path = "./workerman_debug.log"): self
    {
        Worker::$stdoutFile = $path;

        return $this;
    }

    /**
     * 设置数据库链接信息
     * @param array $config
     * @return $this
     */
    public function setDbConfig(array $config = []): self
    {
        $dbConfig = array_change_key_case($config);
        if ($dbConfig['prefix']) {
            $this->systemCrontabTable     = $dbConfig['prefix'] . $this->systemCrontabTable;
            $this->systemCrontabLogTable  = $dbConfig['prefix'] . $this->systemCrontabLogTable;
            $this->systemCrontabLockTable = $dbConfig['prefix'] . $this->systemCrontabLockTable;
        }
        return $this;
    }

    /**
     * 注册子进程回调函数
     */
    private function registerCallback()
    {
        $this->worker->onWorkerStart  = [$this, 'onWorkerStart'];
        $this->worker->onWorkerReload = [$this, 'onWorkerReload'];
        $this->worker->onWorkerStop   = [$this, 'onWorkerStop'];
        $this->worker->onConnect      = [$this, 'onConnect'];
        $this->worker->onMessage      = [$this, 'onMessage'];
        $this->worker->onClose        = [$this, 'onClose'];
        $this->worker->onBufferFull   = [$this, 'onBufferFull'];
        $this->worker->onBufferDrain  = [$this, 'onBufferDrain'];
        $this->worker->onError        = [$this, 'onError'];
    }

    /**
     * 设置Worker子进程启动时的回调函数，每个子进程启动时都会执行
     * @param Worker $worker
     */
    public function onWorkerStart(Worker $worker)
    {
        $this->checkCrontabTables();
        $this->crontabInit();
        $this->keepActive();
    }

    /**
     * 保持活跃
     */
    private function keepActive()
    {
        Timer::add(50, function () {
            Db::query('select 1');
        });

        if ($this->lockType == 'Redis') {
            Timer::add(30, function () {
                $this->lockDriver->gethandler()->ping();
            });
        }
    }

    /**
     * @param Worker $worker
     */
    public function onWorkerStop(Worker $worker)
    {
    }

    /**
     * 设置Worker收到reload信号后执行的回调
     * 如果在收到reload信号后只想让子进程执行onWorkerReload，不想退出，可以在初始化Worker实例时设置对应的Worker实例的reloadable属性为false
     * @param Worker $worker
     */
    public function onWorkerReload(Worker $worker)
    {
    }

    /**
     * 当客户端与Workerman建立连接时(TCP三次握手完成后)触发的回调函数
     * 每个连接只会触发一次onConnect回调
     * 此时客户端还没有发来任何数据
     * 由于udp是无连接的，所以当使用udp时不会触发onConnect回调，也不会触发onClose回调
     * @param TcpConnection $connection
     */
    public function onConnect(TcpConnection $connection)
    {
    }

    /**
     * 当客户端连接与Workerman断开时触发的回调函数
     * 不管连接是如何断开的，只要断开就会触发onClose
     * 每个连接只会触发一次onClose
     * 由于udp是无连接的，所以当使用udp时不会触发onConnect回调，也不会触发onClose回调
     * @param TcpConnection $connection
     */
    public function onClose(TcpConnection $connection)
    {
    }

    /**
     * 当客户端通过连接发来数据时(Workerman收到数据时)触发的回调函数
     * @param TcpConnection $connection
     * @param $request
     */
    public function onMessage(TcpConnection $connection, $request)
    {
        $data = json_decode($request, true);
        if (!is_null($this->safeKey) && ($data['key'] ?? '') !== $this->safeKey) {
            $return = ['status' => false, 'msg' => '非法请求'];
        } else {
            $method = $data['method'] ?? '';
            $args   = $data['args'] ?? [];
            if (!in_array($method, array_keys(self::TCP_METHOD_PATH))) {
                $return = ['status' => false, 'msg' => '方法不存在'];
            } else {
                try {
                    $return = call_user_func([$this, self::TCP_METHOD_PATH[$method]], $args);
                    if (is_bool($return)) {
                        $return = [
                            'status' => $return,
                            'msg' => $return ? 'Exec Success' : 'Exec Fail'
                        ];
                    }
                    if (!isset($return['status'])) {
                        $return = array_merge(['status' => true], $return);
                    }
                } catch (\Throwable $th) {
                    $return = ['status' => false, 'msg' => '方法调用异常 ' . $th->getMessage()];
                }
            }
        }

        $connection->send(json_encode($return) . "\n");
    }

    /**
     * 缓冲区满则会触发onBufferFull回调
     * 每个连接都有一个单独的应用层发送缓冲区，如果客户端接收速度小于服务端发送速度，数据会在应用层缓冲区暂存
     * 只要发送缓冲区还没满，哪怕只有一个字节的空间，调用Connection::send($A)肯定会把$A放入发送缓冲区,
     * 但是如果已经没有空间了，还继续Connection::send($B)数据，则这次send的$B数据不会放入发送缓冲区，而是被丢弃掉，并触发onError回调
     * @param TcpConnection $connection
     */
    public function onBufferFull(TcpConnection $connection)
    {
    }

    /**
     * 在应用层发送缓冲区数据全部发送完毕后触发
     * @param TcpConnection $connection
     */
    public function onBufferDrain(TcpConnection $connection)
    {
    }

    /**
     * 客户端的连接上发生错误时触发
     * @param TcpConnection $connection
     * @param $code
     * @param $msg
     */
    public function onError(TcpConnection $connection, $code, $msg)
    {
    }

    /**
     * 初始化定时任务
     * @return void
     */
    private function crontabInit(): void
    {
        $ids = Db::table($this->systemCrontabTable)
            ->where('status', '=', self::NORMAL_STATUS)
            ->order(['sort' => 'desc'])
            ->column('id');

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $this->crontabRun($id);
            }
        }
    }

    /**
     * 定时任务列表
     * @param $params
     * @return array
     */
    private function crontabIndex($params): array
    {
        [$page, $limit, $where] = $this->buildParames($params);
        $data = Db::table($this->systemCrontabTable)
            ->where($where)
            ->order('sort desc,id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);

        return ['total' => $data->total(), 'list' => $data->items()];
    }

    /**
     * 创建定时任务
     * @param $params
     * @return bool
     */
    public function crontabCreate($params)
    {
        $id = 0;
        $require = ['title', 'type', 'rule', 'target'];
        $msg = '';
        foreach ($require as $key => $value) {
            if (!isset($params[$value])) {
                $msg = "{$value}不能为空";
                break;
            }
        }

        if (!$msg) {
            Db::startTrans();
            try {
                $params['create_time'] = $params['update_time'] = time();
                $id = Db::table($this->systemCrontabTable)->field(true)->insertGetId($params);
                $id && $this->crontabRun($id);
                Db::commit();
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                Db::rollback();
            }
        }

        return ['status' => (bool)$id, 'msg' => $msg];
    }

    /**
     * 读取定时任务
     * @param $params
     * @return array
     */
    private function crontabRead($params): array
    {
        $id = $params['id'] ?? 0;
        $row = Db::table($this->systemCrontabTable)
            ->find($id) ?: [];
        return ['data' => $row];
    }

    /**
     * 定时器池
     * @return array
     */
    private function crontabPool(): array
    {
        $data = [];
        foreach ($this->crontabPool as $row) {
            unset($row['crontab']);
            $data[] = $row;
        }

        return $data;
    }

    /**
     * 编辑定时任务
     * @param  $params
     * @return bool
     */
    private function crontabUpdate($params)
    {
        $id = $params['id'] ?? 0;
        $row = Db::table($this->systemCrontabTable)
            ->where('id', $id)
            ->find();
        if (empty($row)) {
            return false;
        }
        $result = Db::table($this->systemCrontabTable)
            ->field(true)
            ->update($params);

        $newroop = array_merge($row, $params);
        if ($row['rule'] != $newroop['rule'] || $row['target'] != $newroop['target'] || $row['singleton'] != $newroop['singleton'] || $row['status'] != $newroop['status']) {
            if (isset($this->crontabPool[$id])) {
                $this->writeln('移除');
                $this->crontabPool[$id]['crontab']->destroy();
                unset($this->crontabPool[$id]);
            }

            if ($newroop['status'] == self::NORMAL_STATUS) {
                $this->writeln('重新启动');
                $this->crontabRun($id);
            }
        }

        return (bool)$result;
    }

    /**
     * 修改定时器状态
     * @param  $params
     * @return bool
     */
    private function crontabModify($params): bool
    {
        $row = Db::table($this->systemCrontabTable)
            ->where('id', $params['id'])
            ->update(['status' => $params['status']]);

        if (isset($this->crontabPool[$params['id']])) {
            $this->crontabPool[$params['id']]['crontab']->destroy();
            unset($this->crontabPool[$params['id']]);
        }
        if ($params['status'] == self::NORMAL_STATUS) {
            $this->crontabRun($params['id']);
        }

        return (bool)$row;
    }

    /**
     * 清除定时任务
     * @param  $params
     * @return bool
     */
    private function crontabDelete($params): bool
    {
        $resid = $params['id'] ?? '';
        $ids = explode(',', $resid);

        if ($resid) {
            foreach ($ids as $item) {
                if (isset($this->crontabPool[$item])) {
                    $this->crontabPool[$item]['crontab']->destroy();
                    unset($this->crontabPool[$item]);
                }
            }

            $rows = Db::table($this->systemCrontabTable)
                ->whereIn('id', $resid)
                ->delete();

            return (bool)$rows;
        }

        return true;
    }

    /**
     * 重启定时任务
     * @param $params
     * @return bool
     */
    private function crontabReload($params): bool
    {
        $resid = $params['id'] ?? '';
        $ids = explode(',', $resid);

        foreach ($ids as $id) {
            if (isset($this->crontabPool[$id])) {
                $this->crontabPool[$id]['crontab']->destroy();
                unset($this->crontabPool[$id]);
            }
            Db::table($this->systemCrontabTable)
                ->where('id', $id)
                ->update(['status' => self::NORMAL_STATUS]);
            $this->crontabRun($id);
        }

        return true;
    }

    /**
     * 执行一次
     * @param  $params
     * @return bool
     */
    private function crontabRunOne($params): bool
    {
        $id = $params['id'] ?? 0;
        $item = Db::table($this->systemCrontabTable)
            ->where('id', $id)
            ->where('status', self::NORMAL_STATUS)
            ->find();
        if (!empty($item)) {
            $this->debug && $this->writeln("立即运行一次");
            $this->crontabRun($id, true);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 创建定时器
     * 0   1   2   3   4   5
     * |   |   |   |   |   |
     * |   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
     * |   |   |   |   +------ month (1 - 12)
     * |   |   |   +-------- day of month (1 - 31)
     * |   |   +---------- hour (0 - 23)
     * |   +------------ min (0 - 59)
     * +-------------- sec (0-59)[可省略，如果没有0位,则最小时间粒度是分钟]
     * @param $id
     * @param bool $run
     */
    private function crontabRun($id, bool $run = false)
    {
        $data = Db::table($this->systemCrontabTable)
            ->where('id', $id)
            ->where('status', self::NORMAL_STATUS)
            ->find();
        if (!empty($data)) {
            switch ($data['type']) {
                case self::COMMAND_CRONTAB:
                    if ($run === true) {
                        $this->runCommandCrontab($data);
                    } else {
                        $this->crontabPool[$data['id']] = [
                            'id'          => $data['id'],
                            'target'      => $data['target'],
                            'rule'        => $data['rule'],
                            'parameter'   => $data['parameter'],
                            'singleton'   => $data['singleton'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'crontab'     => new Crontab($data['rule'], function () use ($data) {
                                $this->runCommandCrontab($data);
                            })
                        ];
                    }
                    break;
                case self::CLASS_CRONTAB:
                    if ($run === true) {
                        $this->runClassCrontab($data);
                    } else {
                        $this->crontabPool[$data['id']] = [
                            'id'          => $data['id'],
                            'target'      => $data['target'],
                            'rule'        => $data['rule'],
                            'parameter'   => $data['parameter'],
                            'singleton'   => $data['singleton'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'crontab'     => new Crontab($data['rule'], function () use ($data) {
                                $this->runClassCrontab($data);
                            })
                        ];
                    }
                    break;
                case self::URL_CRONTAB:
                    if ($run === true) {
                        $this->runUrlCrontab($data);
                    } else {
                        $this->crontabPool[$data['id']] = [
                            'id'          => $data['id'],
                            'target'      => $data['target'],
                            'rule'        => $data['rule'],
                            'parameter'   => $data['parameter'],
                            'singleton'   => $data['singleton'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'crontab'     => new Crontab($data['rule'], function () use ($data) {
                                $this->runUrlCrontab($data);
                            })
                        ];
                    }
                    break;
                case self::SHELL_CRONTAB:
                    if ($run === true) {
                        $this->runShellCrontab($data);
                    } else {
                        $this->crontabPool[$data['id']] = [
                            'id'          => $data['id'],
                            'target'      => $data['target'],
                            'rule'        => $data['rule'],
                            'parameter'   => $data['parameter'],
                            'singleton'   => $data['singleton'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'crontab'     => new Crontab($data['rule'], function () use ($data) {
                                $this->runShellCrontab($data);
                            })
                        ];
                    }
                    break;
                case self::SQL_CRONTAB:
                    if ($run === true) {
                        $this->runSqlCrontab($data);
                    } else {
                        $this->crontabPool[$data['id']] = [
                            'id'          => $data['id'],
                            'target'      => $data['target'],
                            'rule'        => $data['rule'],
                            'parameter'   => $data['parameter'],
                            'singleton'   => $data['singleton'],
                            'create_time' => date('Y-m-d H:i:s'),
                            'crontab'     => new Crontab($data['rule'], function () use ($data) {
                                $this->runSqlCrontab($data);
                            })
                        ];
                    }
                    break;
            }
        }
    }

    /**
     * 执行console命令任务
     *
     * @param [type] $data
     */
    private function runCommandCrontab($data)
    {
        $crontab_id = $data['id'];
        if (!$this->isCanrun($crontab_id)) {
            return false;
        }
        if (!$this->checkCrontabLock($crontab_id)) {
            //加锁
            $this->crontabLock($crontab_id);

            $time      = time();
            $startTime = microtime(true);
            $code      = 0;
            $result    = true;
            try {
                $parameter = [];
                if (!empty($data['parameter'])) {
                    $parameter = explode(' ', $data['parameter']);
                }
                $command = Console::call($data['target'], $parameter);
                $exception = $command->fetch();
            } catch (\Throwable $e) {
                $result    = false;
                $code      = 1;
                $exception = $e->getMessage();
            }
            $this->debug && $this->writeln('执行定时器任务(console)#' . $data['id'] . ' ' . $data['rule'] . ' ' . $data['target'], $result);

            $this->runInSingleton($data);

            $endTime = microtime(true);

            $this->cronUpdateTask(['id' => $data['id'], 'time' => $time]);

            $this->crontabRunLog([
                'crontab_id'   => $data['id'],
                'target'       => $data['target'],
                'parameter'    => $parameter ?? '',
                'exception'    => $exception ?? '',
                'return_code'  => $code,
                'running_time' => round($endTime - $startTime, 6),
                'create_time'  => $time,
                'update_time'  => $time,
            ]);
            //解锁
            $this->crontabUnlock($crontab_id);
        }
    }

    /**
     * 执行调用Class任务
     *
     * @param [type] $data
     */
    private function runClassCrontab($data)
    {
        $crontab_id = $data['id'];
        if (!$this->isCanrun($crontab_id)) {
            return false;
        }
        if (!$this->checkCrontabLock($crontab_id)) {
            //加锁
            $this->crontabLock($crontab_id);

            $time      = time();
            $class     = trim($data['target']);
            $startTime = microtime(true);
            if ($class) {
                if (strpos($class, '@') !== false) {
                    $class  = explode('@', $class);
                    $method = end($class);
                    array_pop($class);
                    $class = implode('@', $class);
                } else {
                    $method = 'execute';
                }
                if (class_exists($class) && method_exists($class, $method)) {
                    try {
                        $result     = true;
                        $code       = 0;
                        $parameters = !empty($data['parameter']) ? json_decode($data['parameter'], true) : [];

                        // $reflect     = new \ReflectionClass($class);
                        // $instance     = $reflect->newInstanceArgs();

                        $instance = new $class();
                        $res = $instance->{$method}(...array_values($parameters));
                    } catch (\Throwable $throwable) {
                        $result = false;
                        $code   = 1;
                    }
                    $exception = isset($throwable) ? $throwable->getMessage() . $throwable->getFile() . $throwable->getLine() : $res;
                } else {
                    $result    = false;
                    $code      = 1;
                    $exception = "方法或类不存在或者错误";
                }
            }
            $this->debug && $this->writeln('执行定时器任务(class)#' . $data['id'] . ' ' . $data['rule'] . ' ' . $data['target'], $result);

            $this->runInSingleton($data);

            $endTime = microtime(true);

            $this->cronUpdateTask(['id' => $data['id'], 'time' => $time]);

            $this->crontabRunLog([
                'crontab_id'   => $data['id'],
                'target'       => $data['target'],
                'parameter'    => $data['parameter'] ?? '',
                'exception'    => $exception ?? '',
                'return_code'  => $code,
                'running_time' => round($endTime - $startTime, 6),
                'create_time'  => $time,
                'update_time'  => $time,
            ]);
            //解锁
            $this->crontabUnlock($crontab_id);
        }
    }

    /**
     * 执行请求url任务
     *
     * @param [type] $data
     */
    private function runUrlCrontab($data)
    {
        $crontab_id = $data['id'];
        if (!$this->isCanrun($crontab_id)) {
            return false;
        }
        if (!$this->checkCrontabLock($crontab_id)) {
            //加锁
            $this->crontabLock($crontab_id);

            $time      = time();
            $url       = trim($data['target']);
            $startTime = microtime(true);
            try {
                $parameters = !empty($data['parameter']) ? json_decode($data['parameter'], true) : [];
                $response = $this->sendRequest($url, $parameters);
                $result   = $response['ret'];
                $code     = 0;
                if (!$result) {
                    $exception = $response['msg'];
                }
            } catch (\Throwable $throwable) {
                $result    = false;
                $code      = 1;
                $exception = $throwable->getMessage();
            }
            $this->debug && $this->writeln('执行定时器任务(url)#' . $data['id'] . ' ' . $data['rule'] . ' ' . $data['target'], $result);

            $this->runInSingleton($data);

            $endTime = microtime(true);

            $this->cronUpdateTask(['id' => $data['id'], 'time' => $time]);

            $this->crontabRunLog([
                'crontab_id'   => $data['id'],
                'target'       => $data['target'],
                'parameter'    => $data['parameter'] ?? '',
                'exception'    => $exception ?? '',
                'return_code'  => $code,
                'running_time' => round($endTime - $startTime, 6),
                'create_time'  => $time,
                'update_time'  => $time,
            ]);
            //解锁
            $this->crontabUnlock($crontab_id);
        }
    }

    /**
     * 执行shell命令任务
     *
     * @param [type] $data
     */
    private function runShellCrontab($data)
    {
        $crontab_id = $data['id'];
        if (!$this->isCanrun($crontab_id)) {
            return false;
        }
        if (!$this->checkCrontabLock($crontab_id)) {
            //加锁
            $this->crontabLock($crontab_id);

            $time      = time();
            $parameter = $data['parameter'] ?: '';
            $startTime = microtime(true);
            $code      = 0;
            $result    = true;
            try {
                $exception = shell_exec($data['target']);
            } catch (\Throwable $e) {
                $result    = false;
                $code      = 1;
                $exception = $e->getMessage();
            }
            $this->debug && $this->writeln('执行定时器任务(shell)#' . $data['id'] . ' ' . $data['rule'] . ' ' . $data['target'], $result);

            $this->runInSingleton($data);

            $endTime = microtime(true);

            $this->cronUpdateTask(['id' => $data['id'], 'time' => $time]);

            $this->crontabRunLog([
                'crontab_id'   => $data['id'],
                'target'       => $data['target'],
                'parameter'    => $parameter,
                'exception'    => $exception ?? '',
                'return_code'  => $code,
                'running_time' => round($endTime - $startTime, 6),
                'create_time'  => $time,
                'update_time'  => $time,
            ]);
            //解锁
            $this->crontabUnlock($crontab_id);
        }
    }

    /**
     * 执行Sql语句任务
     *
     * @param [type] $data
     */
    private function runSqlCrontab($data)
    {
        $crontab_id = $data['id'];
        if (!$this->isCanrun($crontab_id)) {
            return false;
        }
        if (!$this->checkCrontabLock($crontab_id)) {
            //加锁
            $this->crontabLock($crontab_id);

            $time      = time();
            $parameter = $data['parameter'];
            $startTime = microtime(true);
            $code      = 0;
            $result    = true;
            try {
                $exception = json_encode(Db::query("{$data['target']}"));
            } catch (\Throwable $e) {
                $result    = false;
                $code      = 1;
                $exception = $e->getMessage();
            }
            $this->debug && $this->writeln('执行定时器任务(sql)#' . $data['id'] . ' ' . $data['rule'] . ' ' . $data['target'], $result);

            $this->runInSingleton($data);

            $endTime = microtime(true);

            $this->cronUpdateTask(['id' => $data['id'], 'time' => $time]);

            $this->crontabRunLog([
                'crontab_id'   => $data['id'],
                'target'       => $data['target'],
                'parameter'    => $parameter ?? '',
                'exception'    => $exception ?? '',
                'return_code'  => $code,
                'running_time' => round($endTime - $startTime, 6),
                'create_time'  => $time,
                'update_time'  => $time,
            ]);
            //解锁
            $this->crontabUnlock($crontab_id);
        }
    }

    /**
     * 是否单次
     * @param $crontab
     * @return void
     */
    private function runInSingleton($crontab)
    {
        if ($crontab['singleton'] === 0 && isset($this->crontabPool[$crontab['id']])) {
            $this->debug && $this->writeln("定时器销毁");
            $this->crontabPool[$crontab['id']]['crontab']->destroy();
        }
    }

    /**
     * 心跳
     * @return array
     */
    private function crontabPong(): array
    {
        return ['msg' => 'pong'];
    }

    /**
     * 执行日志
     * @param $params
     * @return array
     */
    private function crontabFlow($params): array
    {
        [$page, $limit, $where] = $this->buildParames($params);
        $data = Db::table($this->systemCrontabLogTable)
            ->where($where)
            ->order(['id' => 'desc'])
            ->paginate(['list_rows' => $limit, 'page' => $page]);
        return ['total' => $data->total(), 'list' => $data->items()];
    }

    /**
     * 记录执行日志
     * @param array $data
     * @return void
     */
    private function crontabRunLog(array $data): void
    {
        if ($this->islog === true) {
            Db::table($this->systemCrontabLogTable)->insert($data);
        }
    }

    /**
     * 更新任务信息
     * @param $task
     * @return void
     */
    private function cronUpdateTask($task)
    {
        Db::table($this->systemCrontabTable)
            ->where('id', $task['id'])
            ->update([
                'running_times'     => Db::raw('running_times+1'),
                'last_running_time' => $task['time']
            ]);
    }


    /**
     * 检查任务锁
     * @param $taskId
     * @return bool
     */
    public function checkCrontabLock($crontab_id): bool
    {
        if (!$this->lockDriver) {
            return false;
        }

        $crontabLock = $this->getCrontabLock($crontab_id);
        if (!$crontabLock) {
            $this->insertCrontabLock($crontab_id);
            return false;
        } else {
            return $this->isCrontabLocked($crontabLock['is_lock']);
        }
    }

    /**
     * 任务是否可执行
     * @param $taskId
     * @return bool
     */
    public function isCanrun($crontab_id): bool
    {
        return isset($this->crontabPool[$crontab_id]);
    }


    /**
     * 获取任务锁信息
     * @param $crontab_id
     * @return array|mixed
     */
    public function getCrontabLock($crontab_id)
    {
        if ($this->lockDriver) {
            return $this->lockDriver->get((string)$crontab_id);
        }
        return [];
    }


    /**
     * 插入任务锁数据
     * @param $crontab_id
     * @param $isLock
     */
    public function insertCrontabLock($crontab_id, $isLock = 0)
    {
        if ($this->lockDriver) {
            $this->lockDriver->insert((string)$crontab_id, $isLock = 0);
        }
        return true;
    }


    /**
     * 是否加锁
     * @param $isLock
     * @return bool
     */
    private function isCrontabLocked($isLock): bool
    {
        return $isLock == 1;
    }

    /**
     * 加锁
     * @param $crontab_id
     * @return bool
     */
    private function crontabLock($crontab_id): bool
    {
        if ($this->lockDriver) {
            return $this->lockDriver->lock((string)$crontab_id);
        }
        return true;
    }

    /**
     * 解锁
     * @param $crontab_id
     * @return bool
     */
    private function crontabUnlock($crontab_id): bool
    {
        if ($this->lockDriver) {
            return $this->lockDriver->unlock((string)$crontab_id);
        }
        return true;
    }

    /**
     * 重置锁
     */
    private function crontabLockReset(): bool
    {
        if ($this->lockDriver) {
            return $this->lockDriver->resetLock();
        }
        return true;
    }

    /**
     * 函数是否被禁用
     * @param $method
     * @return bool
     */
    private function functionDisabled($method): bool
    {
        return in_array($method, explode(',', ini_get('disable_functions')));
    }

    /**
     * 扩展是否加载
     * @param $extension
     * @return bool
     */
    private function extensionLoaded($extension): bool
    {
        return in_array($extension, get_loaded_extensions());
    }

    /**
     * 是否是Linux操作系统
     * @return bool
     */
    private function isLinux(): bool
    {
        return strpos(PHP_OS, "Linux") !== false;
    }

    /**
     * 版本比较
     * @param $version
     * @param string $operator
     * @return bool
     */
    private function versionCompare($version, string $operator = ">="): bool
    {
        return version_compare(phpversion(), $version, $operator);
    }

    /**
     * 检测运行环境
     */
    private function checkEnv()
    {
        $errorMsg = [];
        $this->functionDisabled('exec') && $errorMsg[] = 'exec函数被禁用';
        $this->functionDisabled('shell_exec') && $errorMsg[] = 'shell_exec函数被禁用';
        if ($this->isLinux()) {
            $this->versionCompare($this->lessPhpVersion, '<') && $errorMsg[] = 'PHP版本必须≥' . $this->lessPhpVersion;
            $checkExt = ["pcntl", "posix"];
            foreach ($checkExt as $ext) {
                !$this->extensionLoaded($ext) && $errorMsg[] = $ext . '扩展没有安装';
            }
            $checkFunc = [
                "stream_socket_server",
                "stream_socket_client",
                "pcntl_signal_dispatch",
                "pcntl_signal",
                "pcntl_alarm",
                "pcntl_fork",
                "posix_getuid",
                "posix_getpwuid",
                "posix_kill",
                "posix_setsid",
                "posix_getpid",
                "posix_getpwnam",
                "posix_getgrnam",
                "posix_getgid",
                "posix_setgid",
                "posix_initgroups",
                "posix_setuid",
                "posix_isatty",
            ];
            foreach ($checkFunc as $func) {
                $this->functionDisabled($func) && $errorMsg[] = $func . '函数被禁用';
            }
        }

        if (!empty($errorMsg)) {
            $this->errorMsg = array_merge($this->errorMsg, $errorMsg);
        }
    }

    /**
     * 输出日志
     * @param $msg
     * @param bool $ok
     */
    private function writeln($msg, bool $ok = true)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . ($ok ? " [Ok] " : " [Fail] ") . PHP_EOL;
    }

    /**
     * 检测表是否存在
     */
    private function checkCrontabTables()
    {
        $allTables = $this->getDbTables();
        !in_array($this->systemCrontabTable, $allTables) && $this->createSystemCrontabTable();
        !in_array($this->systemCrontabLogTable, $allTables) && $this->createSystemCrontabLogTable();
        if (in_array($this->systemCrontabLockTable, $allTables)) {
            $this->crontabLockReset();
        } else {
            $this->createSystemCrontabLockTable();
        }
    }

    /**
     * 创建定时器任务表
     */
    private function createSystemCrontabTable()
    {
        $sql = <<<SQL
 CREATE TABLE IF NOT EXISTS `{$this->systemCrontabTable}`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '任务标题',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '任务类型 (1 command, 2 class, 3 url 4 shell 5 sql)',
  `rule` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '任务Cron规则',
  `target` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '任务调用目标',
  `parameter` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT '' COMMENT '任务调用参数(shell/sql无效)',
  `running_times` int(11) NOT NULL DEFAULT '0' COMMENT '已运行次数',
  `last_running_time` int(11) NOT NULL DEFAULT '0' COMMENT '上次运行时间',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '备注',
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序，越大越前',
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '任务状态状态[0:禁用;1启用]',
  `singleton` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否单次执行 (0 是 1 不是)',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `title`(`title`) USING BTREE,
  INDEX `type`(`type`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '定时器任务表' ROW_FORMAT = DYNAMIC
SQL;

        return Db::query($sql);
    }

    /**
     * 定时器任务流水表
     */
    private function createSystemCrontabLogTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->systemCrontabLogTable}`  (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `crontab_id` bigint UNSIGNED NOT NULL COMMENT '任务id',
  `target` varchar(255) NOT NULL COMMENT '调用目标',
  `parameter` varchar(500)  COMMENT '调用参数', 
  `exception` text  COMMENT '任务执行或者异常信息输出',
  `return_code` tinyint(1) NOT NULL DEFAULT 0 COMMENT '执行返回状态[0成功; 1失败]',
  `running_time` varchar(10) NOT NULL COMMENT '执行所用时间',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE,
  INDEX `crontab_id`(`crontab_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '定时器任务流水表' ROW_FORMAT = DYNAMIC
SQL;

        return Db::query($sql);
    }

    /**
     * 定时器任务锁表
     */
    private function createSystemCrontabLockTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->systemCrontabLockTable}`  (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `crontab_id` bigint UNSIGNED NOT NULL COMMENT '任务id',
  `is_lock` tinyint(4) NOT NULL DEFAULT 0 COMMENT '是否锁定(0:否,1是)',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `crontab_id`(`crontab_id`) USING BTREE,
  INDEX `create_time`(`create_time`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '定时器任务锁表' ROW_FORMAT = DYNAMIC
SQL;

        return Db::query($sql);
    }

    /**
     * 获取数据库表名
     * @return array
     */
    private function getDbTables(): array
    {
        return Db::getTables();
    }

    /**
     * 构建请求参数
     * @param array $get
     * @return array
     */
    public function buildParames($get)
    {
        $page    = isset($get['page']) && !empty($get['page']) ? (int)$get['page'] : 1;
        $limit   = isset($get['limit']) && !empty($get['limit']) ? (int)$get['limit'] : 10;
        $where = isset($get['where']) && !empty($get['where']) ? $get['where'] : [];
        return [$page, $limit, $where];
    }

    /**
     * CURL发送Request请求,含POST和REQUEST
     * @param string $url     请求的链接
     * @param mixed  $params  传递的参数
     * @param string $method  请求的方法
     * @param mixed  $options CURL的参数
     * @return array
     */
    protected static function sendRequest($url, $params = [], $method = 'POST', $options = [])
    {
        $method = strtoupper($method);
        $protocol = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch = curl_init();
        $defaults = [];
        if ('GET' == $method) {
            $geturl = $query_string ? $url . (stripos($url, "?") !== false ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
        } else {
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST') {
                $defaults[CURLOPT_POST] = 1;
            } else {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $params;
        }

        $defaults[CURLOPT_HEADER] = false;
        $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = true;
        $defaults[CURLOPT_RETURNTRANSFER] = true;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 3;
        $defaults[CURLOPT_TIMEOUT] = 3;

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

        if ('https' == $protocol) {
            $defaults[CURLOPT_SSL_VERIFYPEER] = false;
            $defaults[CURLOPT_SSL_VERIFYHOST] = false;
        }

        curl_setopt_array($ch, (array)$options + $defaults);

        $ret = curl_exec($ch);
        $err = curl_error($ch);

        if (false === $ret || !empty($err)) {
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            return [
                'ret'   => false,
                'errno' => $errno,
                'msg'   => $err,
                'info'  => $info,
            ];
        }
        curl_close($ch);
        return [
            'ret' => true,
            'msg' => $ret,
        ];
    }


    /**
     * 运行所有Worker实例
     * Worker::runAll()执行后将永久阻塞
     * Worker::runAll()调用前运行的代码都是在主进程运行的，onXXX回调运行的代码都属于子进程
     * windows版本的workerman不支持在同一个文件中实例化多个Worker
     * windows版本的workerman需要将多个Worker实例初始化放在不同的文件中
     */
    public function run()
    {
        if (empty($this->errorMsg)) {
            $this->writeln("启动系统定时任务");
            Worker::runAll();
        } else {
            foreach ($this->errorMsg as $v) {
                $this->writeln($v, false);
            }
        }
    }
}
