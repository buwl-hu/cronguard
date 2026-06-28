<?php

// SPDX-FileCopyrightText: 2026 2026 Buwl.hu
// SPDX-FileCopyrightText: 2026 Buwl.hu
//
// SPDX-License-Identifier: GPL-2.0-or-later

$config_file = __DIR__ . '/../../config/config_db.php';

if (!file_exists($config_file)) {
    die("Config file not found: " . $config_file . PHP_EOL);
} else {
    echo 'Config found' . PHP_EOL;
}

$content = file_get_contents($config_file);

function extractValue(string $content, string $key): ?string
{
    if (preg_match("/\\$$key\s*=\s*'([^']*)'/", $content, $matches)) {
        return $matches[1];
    }

    return null;
}

$db_host = extractValue($content, 'dbhost');
$db_user = extractValue($content, 'dbuser');
$db_pass = extractValue($content, 'dbpassword');
$db_name = extractValue($content, 'dbdefault');

if (!$db_host || !$db_user || !$db_name) {
    die("Missing DB config values" . PHP_EOL);
} else {
    echo 'DB config found' . PHP_EOL;
}

const CRONGUARD_STATE_SLEEP = 0;
const CRONGUARD_STATE_RUN = 1;
const CRONGUARD_STATE_NEED_NOTIFICATION = 2;

try {
    $cache_file = __DIR__ . '/../../files/_cache/plugins/cronguard/report.json';
    $dirname = dirname($cache_file);

    if (!is_dir($dirname)) mkdir($dirname, 0777, true);

    $cache_exists = file_exists($cache_file);
    $cache = $cache_exists ? json_decode(file_get_contents($cache_file), true) : [];
    if (!is_array($cache)) $cache = [];

    $cache['timeout'] = empty($cache['timeout']) ? 1800 : $cache['timeout'];
    $cache['state'] ??= CRONGUARD_STATE_SLEEP;

    $cache_is_old = false;
    if ($cache_exists) {
        $file_time = filemtime($cache_file);
        $current_time = time();
        $cache_is_old = ($current_time - $file_time) > $cache['timeout'];
    }

    $invalid_state = $cache['state'] != CRONGUARD_STATE_SLEEP;

    if (!$cache_is_old && $invalid_state) die('CronGuard CronTask is running' . PHP_EOL);
} catch (Throwable $e) {
    $cache = ['timeout' => 1800];
}

try {
    $cache['state'] = CRONGUARD_STATE_RUN;
    file_put_contents($cache_file, json_encode($cache));
} catch (Throwable $e) {
    // Silent
}

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass ?? '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $select_sql = "
        SELECT id, lastrun
        FROM glpi_crontasks
        WHERE state = 2
          AND lastrun IS NOT NULL
          AND lastrun < (NOW() - INTERVAL {$cache['timeout']} SECOND)
    ";

    $stmt = $pdo->query($select_sql);
    $rows = $stmt->fetchAll();
    echo count($rows) . ' stuck found' . PHP_EOL;

    $cache['report'] = [];

    foreach ($rows as $row) {
        $success = false;

        try {
            $update_stmt = $pdo->prepare("UPDATE glpi_crontasks SET state = 1 WHERE id = :id");
            $update_stmt->execute([':id' => $row['id']]);
            $success = true;
        } catch (Throwable $e) {
            $success = false;
        }

        $cache['report'][$row['id']] = [
            'id' => $row['id'],
            'lastrun' => $row['lastrun'],
            'restarted' => $success
        ];
    }

    $cache['state'] = !empty($cache['report']) ? CRONGUARD_STATE_NEED_NOTIFICATION : CRONGUARD_STATE_SLEEP;
    echo 'Stucks reported' . PHP_EOL;
} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    file_put_contents(
        $cache_file,
        json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    echo 'Cache saved' . PHP_EOL;
} catch (Throwable $e) {
    die('Cache save error: ' . $e->getMessage() . PHP_EOL);
}
