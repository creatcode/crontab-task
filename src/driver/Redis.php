<?php

declare(strict_types=1);

namespace Creatcode\Crontask\driver;

use think\Cache;

/**
 * Redis
 */
class Redis extends LockAbstract
{
    protected $handler = null;

    public function _init()
    {
        $this->handler = Cache::store('redis')->handler();
    }

    public function insert(string $crontab_id, $isLock = 0)
    {
        $now = time();
        return $this->handler->hset($this->locktable, $crontab_id, json_encode([
            'crontab_id'  => $crontab_id,
            'is_lock'     => $isLock,
            'create_time' => $now,
            'update_time' => $now
        ]));
    }

    public function get(string $crontab_id)
    {
        $res = $this->handler->hget($this->locktable, $crontab_id);
        return $res ? json_decode($res, true) : [];
    }

    public function lock(string $crontab_id): bool
    {
        $list = $this->handler->hget($this->locktable, $crontab_id);
        $list = json_decode($list, true);
        $list['is_lock'] = 1;
        $list['update_time'] = time();
        return (bool)$this->handler->hset($this->locktable, $crontab_id, json_encode($list));
    }

    public function unlock(string $crontab_id): bool
    {
        $list = $this->handler->hget($this->locktable, $crontab_id);
        $list = json_decode($list, true);
        $list['is_lock'] = 0;
        $list['update_time'] = time();
        return (bool)$this->handler->hset($this->locktable, $crontab_id, json_encode($list));
    }

    public function resetLock(): bool
    {
        $list = $this->handler->hgetAll($this->locktable);
        $paramData = [];
        foreach ($list as $key => $value) {
            $paramData[$key] = json_decode($value, true);
            $paramData[$key]['is_lock'] = 0;
            $paramData[$key]['update_time'] = time();
            $paramData[$key] = json_encode($paramData[$key]);
        }

        return (bool)$this->handler->hmset($this->locktable, $paramData);
    }
}
