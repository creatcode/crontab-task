<?php

declare(strict_types=1);

namespace Creatcode\Crontask;

class Client
{

    private $client;
    protected static $instance = null;

    protected function __construct()
    {
        $this->client = stream_socket_client('tcp://' . config('crontabtask.base_url'));
    }

    /**
     * 获取实例
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * 获取定时任务列表
     *
     * @param array $where
     * @param integer $page
     * @param integer $limit
     * @return array
     */
    public function crontabIndex(array $where = [], int $page = 1, int $limit = 10): array
    {
        return $this->request([
            'method' => 'index',
            'args' => [
                'page' => $page,
                'limit' => $limit,
                'where' => $where
            ]
        ]);
    }

    /**
     * 添加定时任务
     *
     * @param array $params
     */
    public function crontabCreate(array $params)
    {
        return $this->request([
            'method' => 'create',
            'args' => $params
        ]);
    }

    /**
     * 更新定时任务
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function crontabUpdate(int $id, array $data)
    {
        $data['id'] = $id;
        return $this->request([
            'method' => 'edit',
            'args' => $data
        ]);
    }

    /**
     * 定时任务详情
     *
     * @param integer $id
     * @return array
     */
    public function crontabRead(int $id)
    {
        return $this->request([
            'method' => 'read',
            'args' => [
                'id' => $id
            ]
        ]);
    }

    /**
     * 修改定时器状态
     *
     * @param integer $id
     * @param integer $status
     */
    public function crontabModify(int $id, int $status)
    {
        return $this->request([
            'method' => 'modify',
            'args' => [
                'id' => $id,
                'status' => $status
            ]
        ]);
    }

    /**
     * 重启定时任务
     *
     * @param string $ids
     */
    public function crontabReload(string $ids)
    {
        return $this->request([
            'method' => 'reload',
            'args' => [
                'id' => $ids
            ]
        ]);
    }

    /**
     * 删除定时任务
     *
     * @param string $id
     */
    public function crontabDelete(string $id)
    {
        return $this->request([
            'method' => 'delete',
            'args' => [
                'id' => $id
            ]
        ]);
    }

    /**
     * 执行日志列表
     *
     * @param integer $crontab_id
     * @param array $where
     * @param integer $page
     * @param integer $limit
     */
    public function crontabFlow(int $crontab_id, int $page = 1, int $limit = 10): array
    {
        return $this->request([
            'method' => 'flow',
            'args' => [
                'page' => $page,
                'limit' => $limit,
                'where' => ['crontab_id' => $crontab_id]
            ]
        ]);
    }

    /**
     * 获取定时器池数据
     */
    public function crontabPool()
    {
        return $this->request([
            'method' => 'pool',
            'args' => []
        ]);
    }

    /**
     * 执行一次定时任务（状态正常才可执行）
     * 
     * @param integer $id
     */
    public function crontabRunOne(int $id)
    {
        return $this->request([
            'method' => 'runone',
            'args' => [
                'id' => $id
            ]
        ]);
    }

    /**
     * 客户端调用
     *
     * @param array $param
     */
    protected function request(array $param): array
    {
        fwrite($this->client, json_encode($param) . "\n");
        $result = fgets($this->client, 10240000);
        fclose($this->client);
        $result = json_decode($result, true);
        $result['msg'] == '' && $result['msg'] = 'success';
        return $result;
    }

    public function __call($name, $arguments)
    {
        return $this->request(['method' => 'ping']);
    }
}
