<?php

declare(strict_types=1);

namespace Creatcode\Crontask\driver;

use think\Db;

/**
 * mysql
 */
class Mysql extends LockAbstract
{
    public function insert(string $crontab_id, $isLock = 0)
    {
        $now = time();
        Db::table($this->locktable)
            ->insert([
                'crontab_id'  => $crontab_id,
                'is_lock'     => $isLock,
                'create_time' => $now,
                'update_time' => $now
            ]);
    }

    public function get(string $crontab_id)
    {
        return Db::table($this->locktable)
            ->where(['crontab_id' => $crontab_id])
            ->lock(true)
            ->find();
    }

    public function lock(string $crontab_id): bool
    {
        return (bool)Db::table($this->locktable)
            ->where('crontab_id', $crontab_id)
            ->update(['is_lock' => 1, 'update_time' => time()]);
    }

    public function unlock(string $crontab_id): bool
    {
        return (bool)Db::table($this->locktable)
            ->where('crontab_id', $crontab_id)
            ->update(['is_lock' => 0, 'update_time' => time()]);
    }

    public function resetLock(): bool
    {
        $ids = Db::table($this->locktable)->column('id');
        return (bool)Db::table($this->locktable)
            ->whereIn('id', $ids)
            ->update(['is_lock' => 0, 'update_time' => time()]);
    }
}
