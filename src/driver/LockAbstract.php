<?php

declare(strict_types=1);

namespace Creatcode\Crontask\driver;

abstract class LockAbstract
{
    protected $locktable;

    protected $handler;

    public function __construct(string $locktable)
    {
        $this->locktable = $locktable;
        $this->_init();
    }

    /**
     * 添加锁数据
     *
     * @param string $crontab_id
     * @param integer $isLock
     */
    abstract public function insert(string $crontab_id, $isLock = 0);

    /**
     * 获取锁信息
     *
     * @param string $crontab_id
     */
    abstract public function get(string $crontab_id);

    /**
     * 任务加锁
     *
     * @param string $crontab_id
     */
    abstract public function lock(string $crontab_id): bool;

    /**
     * 任务解锁
     *
     * @param string $crontab_id
     */
    abstract public function unlock(string $crontab_id): bool;

    /**
     * 重置锁
     *
     */
    abstract public function resetLock(): bool;

    public function gethandler()
    {
        return $this->handler;
    }

    public function __call($method, $args)
    {
    }
}
