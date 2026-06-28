<?php

// SPDX-FileCopyrightText: 2026 2026 Buwl.hu
// SPDX-FileCopyrightText: 2026 Buwl.hu
//
// SPDX-License-Identifier: GPL-2.0-or-later

class PluginCronguardCron
{
    const int CRONGUARD_STATE_SLEEP = 0;
    const int CRONGUARD_STATE_RUN = 1;
    const int CRONGUARD_STATE_NEED_NOTIFICATION = 2;

    const int DEFAULT_TIMEOUT = 1800;
    const string CACHE_FILE = GLPI_CACHE_DIR . '/plugins/cronguard/report.json';

    static ?array $cache = null;

    public static function cronCronguard(CronTask $task): bool
    {
        try {
            return self::check($task);
        } catch (\Throwable $e) {
            \Toolbox::logInFile(
                'cronguard',
                get_class($e) . "[{$e->getCode()}]: {$e->getMessage()}"
            );
        }
        return false;
    }

    protected static function check(CronTask $task): bool
    {
        $cache = self::getCache();
        $state = $cache['state'];
        if ($state == self::CRONGUARD_STATE_RUN) return true;
        self::setState(self::CRONGUARD_STATE_RUN);

        if ($state == self::CRONGUARD_STATE_NEED_NOTIFICATION && !empty($cache['report'])) {
            self::raiseNotificationEvents($cache['report']);
        }

        return self::watchdog();
    }

    protected static function raiseNotificationEvents($report): bool
    {
        foreach ($report as $data) {
            try {
                $cron_task = new CronTask();
                if (!$cron_task->getFromDB($data['id'])) continue;
                NotificationEvent::raiseEvent(\GlpiPlugin\CronGuard\CronTask::EVENT_RESTARTED, $cron_task);
            } catch (Throwable) {
            }
        }
        return true;
    }

    protected static function watchdog(): bool
    {
        self::$cache['report'] = [];
        try {
            $cron_task = new CronTask();
            $timeout = self::getTimeout();

            $tasks = $cron_task->find([
                'state' => CronTask::STATE_RUNNING,
                'NOT' => ['lastrun' => null],
                'lastrun' => ['<', new \Glpi\DBAL\QueryExpression("NOW() - INTERVAL {$timeout} SECOND")]
            ], ['id', 'lastrun']);

            if (empty($tasks)) {
                self::setState(self::CRONGUARD_STATE_SLEEP);
                return true;
            }

            foreach ($tasks as $task) {
                $success = $cron_task->update([
                    'id' => $task['id'],
                    'state' => CronTask::STATE_WAITING
                ]);

                self::$cache['report'][$task['id']] = [
                    'id' => $task['id'],
                    'lastrun' => $task['lastrun'],
                    'restarted' => $success
                ];
                self::saveCache();
            }

            self::setState(self::CRONGUARD_STATE_NEED_NOTIFICATION);
            self::raiseNotificationEvents(self::$cache['report']);
            self::setState(self::CRONGUARD_STATE_SLEEP);

            return true;
        } catch (Throwable $e) {
            Toolbox::logInFile(PluginCronguardConfig::cronLogFile(true), "DB error: " . $e->getMessage() . PHP_EOL);
            return false;
        }
    }

    static function getState(): int
    {
        $cache = self::getCache();
        return $cache['state'] ?? self::CRONGUARD_STATE_SLEEP;
    }

    protected static function setState(int $state): void
    {
        self::$cache['state'] = $state;
        self::saveCache();
    }

    /**
     * @return array{
     *     state: string,
     *     timeout: int
     * }
     */
    static function getCache(): array
    {
        if (!is_null(self::$cache)) return self::$cache;
        try {
            $cache_file = self::CACHE_FILE;
            $dirname = dirname($cache_file);
            if (!is_dir($dirname)) mkdir($dirname, 0777, true);
            $cache_exists = file_exists($cache_file);
            $cache = $cache_exists ? json_decode(file_get_contents($cache_file), true) : [];
            if (!is_array($cache)) $cache = [];
            $cache['timeout'] = PluginCronguardConfig::get(PluginCronguardConfig::FIELD_STUCK_TIMEOUT);
            $cache['state'] ??= self::CRONGUARD_STATE_SLEEP;

            $cache_is_old = false;
            if ($cache_exists) {
                $file_time = filemtime($cache_file);
                $current_time = time();
                $cache_is_old = ($current_time - $file_time) > $cache['timeout'];
            }
            if ($cache_is_old && $cache['state'] == self::CRONGUARD_STATE_RUN) {
                $cache['state'] = self::CRONGUARD_STATE_SLEEP;
            }
            self::$cache = $cache;
        } catch (Throwable $e) {
            self::$cache = [];
        }

        if (empty(self::$cache['timeout'])) self::$cache['timeout'] = self::DEFAULT_TIMEOUT;
        $cache['state'] ??= self::CRONGUARD_STATE_SLEEP;

        return self::$cache;
    }

    protected static function saveCache(): void
    {
        self::$cache ??= ['state' => self::CRONGUARD_STATE_SLEEP];
        self::$cache['timeout'] = self::getTimeout();

        try {
            file_put_contents(self::CACHE_FILE, json_encode(self::$cache));
        } catch (Throwable) {
        }
    }

    protected static function getTimeout()
    {
        try {
            $timeout = PluginCronguardConfig::get(PluginCronguardConfig::FIELD_STUCK_TIMEOUT);
        } catch (Throwable) {
            $timeout = self::getCache()['timeout'] ?? 0;
        }
        if (empty($timeout)) $timeout = self::DEFAULT_TIMEOUT;
        return $timeout;
    }
}
