<?php

// SPDX-FileCopyrightText: 2026 2026 Buwl.hu
// SPDX-FileCopyrightText: 2026 Buwl.hu
//
// SPDX-License-Identifier: GPL-2.0-or-later

Session::checkLoginUser();

// No autoload when plugin is not activated
require_once(__DIR__ . '/../inc/config.class.php');

$config = new PluginCronguardConfig();
if (isset($_POST['update'])) {
    $config->check($_POST['id'], UPDATE);
    $config->update($_POST);

    Html::back();
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Html::redirect($CFG_GLPI['url_base'] . '/front/config.form.php?forcetab=' . urlencode('PluginCronguardConfig$1'));
