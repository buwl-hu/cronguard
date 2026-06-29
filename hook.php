<?php

// SPDX-FileCopyrightText: 2026 2026 Buwl.hu
// SPDX-FileCopyrightText: 2026 Buwl.hu
//
// SPDX-License-Identifier: GPL-2.0-or-later

if (!defined('PLUGIN_CRONGUARD_VERSION')) define('PLUGIN_CRONGUARD_VERSION', '0.0.3');

function plugin_cronguard_install(): true
{
    $migration = new Migration(PLUGIN_CRONGUARD_VERSION);

    include_once(Plugin::getPhpDir('cronguard') . '/inc/config.class.php');
    PluginCronguardConfig::install($migration);
    $migration->executeMigration();

    CronTask::register(
        PluginCronguardCron::class,
        'cronguard',
        60,
        [
            'mode' => CronTask::MODE_EXTERNAL,
            'comment' => 'CronGuard task'
        ]
    );

    return true;
}

function plugin_cronguard_uninstall(): true
{
    $migration = new Migration(PLUGIN_CRONGUARD_VERSION);

    $tables = ['glpi_plugin_cronguard_configs'];

    foreach ($tables as $table) {
        try {
            $migration->dropTable($table);
        } catch (Throwable) {
        }
    }

    $migration->executeMigration();

    return true;
}
