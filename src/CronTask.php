<?php

namespace GlpiPlugin\CronGuard;

use NotificationTargetCronTask;
use Plugin;

class CronTask
{
    const string EVENT_RESTARTED = 'plugin_cronguard_cron_task_restarted';

    public static function addEvents(NotificationTargetCronTask $target): void
    {
        Plugin::loadLang('cronguard');
        $target->events[self::EVENT_RESTARTED]
            = sprintf(
            __('%1$s (%2$s)'),
            __('Cron Task Restart Attempt', 'cronguard'),
            __('CronGuard', 'cronguard')
        );
    }

    public static function addData(NotificationTargetCronTask $target): void
    {
        global $CFG_GLPI;

        $cron_task = $target->obj;
        if (!$cron_task instanceof \CronTask) return;

        $cache = \PluginCronguardCron::getCache();
        $report = $cache['report'][$id = $cron_task->getID()] ?? [];

        $target->data['##crontask.restarted##'] = $report['restarted'] ?? false;
        $target->data['##crontask.lastrun##'] = $report['lastrun'] ?? '-';
        $target->data['##crontask.name##'] = '';

        if ($is_plug = isPluginItemType($cron_task->getField('itemtype'))) {
            $target->data['##crontask.name##'] = $is_plug['plugin'] . " - ";
        }

        $target->data['##crontask.name##'] .= $cron_task->getName();
        $target->data['##crontask.description##'] = $cron_task->getDescription($id);
        $target->data['##crontask.url##'] = $target->formatURL(
            \NotificationTarget::GLPI_USER,
            "CronTask_" . $id
        );

        $base_url = $CFG_GLPI['url_base'];
        $target->data['##glpi.logo##'] = "$base_url/pics/logos/logo-GLPI-250-white.png";
        $target->data = array_merge(self::paletteData(), $target->data);
    }

    public static function paletteData(): array
    {
        global $CFG_GLPI;

        $palette = $CFG_GLPI['palette'];
        $palette_css_file = realpath(__DIR__ . "/../../../css/palettes/_{$palette}.scss");

        $data = [// aerialgreen
            '##palette.tblr_primary_rgb##' => '142, 197, 71',
            '##palette.tblr_secondary##' => '#768363',
            '##palette.tblr_secondary_fg##' => '#fcfcfc',
            '##palette.tblr_link_color_rgb##' => '69, 148, 54',
            '##palette.glpi_mainmenu_bg##' => '#459436',
            '##palette.glpi_helpdesk_header##' => 'hsl(111deg, 41%, 85%)',
            '##palette.glpi_mainmenu_fg##' => '#f4f6fa',
            '##palette.glpi_palette_color_1##' => '#459436',
            '##palette.glpi_palette_color_2##' => '#365731',
            '##palette.glpi_palette_color_3##' => '#8ec547',
            '##palette.glpi_palette_color_4##' => '#fec95c',
            '##palette.glpi_illustrations_gradient_1##' => 'hsl(110deg, 47%, 92%)',
            '##palette.glpi_illustrations_gradient_2##' => 'hsl(110deg, 47%, 72%)',
            '##palette.glpi_illustrations_gradient_3##' => 'hsl(110deg, 47%, 45%)',
        ];

        if (!file_exists($palette_css_file)) return $data;

        $palette_css = file_get_contents($palette_css_file);

        $re = '/--([a-z0-9-]+):\s+([^;]+)/m';
        preg_match_all($re, $palette_css, $matches, PREG_SET_ORDER, 0);

        foreach ($matches as $match) {
            $variable = str_replace('-', '_', $match[1]);
            $data["##palette.$variable##"] = $match[2];
        }
        return $data;
    }
}
