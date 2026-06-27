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

use Glpi\Plugin\Hooks;

const PLUGIN_CRONGUARD_VERSION = '0.0.1';
const PLUGIN_CRONGUARD_MIN_GLPI = '11.0.0';
const PLUGIN_CRONGUARD_MAX_GLPI = '11.0.99';

if (!defined('PLUGIN_CRONGUARD_DIR')) {
    define('PLUGIN_CRONGUARD_DIR', Plugin::getPhpDir('cronguard'));
}

function plugin_init_cronguard(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['cronguard'] = true;

    Plugin::registerClass(PluginCronguardCron::class);
    Plugin::registerClass(PluginCronguardConfig::class, ['addtabon' => 'Config']);

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['cronguard'] = 'front/config.form.php';
    include_once(PLUGIN_CRONGUARD_DIR . '/inc/config.class.php');
    include_once(PLUGIN_CRONGUARD_DIR . '/src/CronTask.php');

    $PLUGIN_HOOKS[Hooks::ITEM_GET_EVENTS]['cronguard'] = [
        NotificationTargetCronTask::class => [\GlpiPlugin\CronGuard\CronTask::class, 'addEvents']
    ];

    $PLUGIN_HOOKS[Hooks::ITEM_ADD_TARGETS]['cronguard'] = [
        NotificationTargetCronTask::class => [\GlpiPlugin\CronGuard\CronTask::class, 'addTargets']
    ];

    $PLUGIN_HOOKS[Hooks::ITEM_ACTION_TARGETS]['cronguard'] = [
        NotificationTargetCronTask::class => [\GlpiPlugin\CronGuard\CronTask::class, 'addActionTargets']
    ];

    $PLUGIN_HOOKS[Hooks::ITEM_GET_DATA]['sysinfo'] = [
        NotificationTargetCronTask::class => [\GlpiPlugin\CronGuard\CronTask::class, 'addData']
    ];
}

function plugin_version_cronguard(): array
{
    return ['name' => __s('CronGuard', 'cronguard'),
        'version' => PLUGIN_CRONGUARD_VERSION,
        'author' => 'Peter Hegedűs, Buwl.hu',
        'license' => 'GPLv3+',
        'homepage' => 'https://buwl.hu',
        'minGlpiVersion' => PLUGIN_CRONGUARD_MIN_GLPI,
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_CRONGUARD_MIN_GLPI,
                'max' => PLUGIN_CRONGUARD_MAX_GLPI,
            ],
        ],
    ];
}
