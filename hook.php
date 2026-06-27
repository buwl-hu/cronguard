<?php

/**
 * -------------------------------------------------------------------------
 * CronGuard plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of CronGuard.
 *
 * CronGuard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * CronGuard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CronGuard. If not, see <https://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 Buwl.
 * @author    Peter Hegedűs
 * @license   GPL-2.0-or-later
 * @link      https://github.com/buwl-hu/cronguard
 * -------------------------------------------------------------------------
 */

if (!defined('PLUGIN_CRONGUARD_VERSION')) define('PLUGIN_CRONGUARD_VERSION', '0.0.1');

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
