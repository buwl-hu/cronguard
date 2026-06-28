<?php

use Glpi\Application\View\TemplateRenderer;

if (!defined('PLUGIN_CRONGUARD_DIR')) {
    define('PLUGIN_CRONGUARD_DIR', Plugin::getPhpDir('cronguard'));
}

class PluginCronguardConfig extends CommonDBTM
{
    const string FIELD_STUCK_TIMEOUT = 'stuck_timeout';

    static protected ?self $_instance = null;
    static public $rightname = 'config';
    static protected ?CronTask $cron_task = null;
    static protected ?int $notification_id = null;
    static protected ?int $group_id = null;
    static protected ?array $targets = null;

    public static function getInstance(): self
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }

        return self::$_instance;
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function getTypeName($nb = 0): ?string
    {
        return __s('CronGuard', 'cronguard');
    }

    public function getName($options = []): ?string
    {
        return __s('CronGuard', 'cronguard');
    }

    public static function getIcon(): string
    {
        return "ti ti-clock-shield";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): array|string
    {
        if ($item->getType() == 'Config') {
            return self::createTabEntry(self::getTypeName(), 0, $item::getType(), self::getIcon());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): true
    {
        if ($item->getType() == 'Config') self::showConfigForm($item);
        return true;
    }

    public static function install(Migration $mig): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $table = 'glpi_plugin_cronguard_configs';
        if (!$DB->tableExists($table)) { //not installed
            $default_charset = DBConnection::getDefaultCharset();
            $default_collation = DBConnection::getDefaultCollation();
            $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

            $field_stuck_timeout = self::FIELD_STUCK_TIMEOUT;

            $query = <<<SQL
                CREATE TABLE `$table` (
                    `id` int $default_key_sign NOT NULL,
                    `$field_stuck_timeout` INT UNSIGNED DEFAULT 1800,

                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET= {$default_charset}
                 COLLATE = {$default_collation} ROW_FORMAT=DYNAMIC
SQL;
            $DB->doQuery($query);

            $query = "INSERT INTO `$table` (id, $field_stuck_timeout) VALUES (1, 1800)";
            $DB->doQuery($query);
        }

        self::createUserGroup();
        self::createNotification();

        if (!is_dir($dirname = dirname(self::cronLogFile()))) mkdir($dirname, recursive: true);
    }

    public static function createUserGroup(): int
    {
        if (!is_null(self::$group_id)) return self::$group_id;
        $group = new Group();
        $input = ['code' => 'cronguard-group'];
        if ($found = $group->find($input)) {
            return (self::$group_id = (int)array_values($found)[0]['id']);
        }
        return (self::$group_id = $group->add(array_merge($input, [
            'is_recursive' => 1,
            'name' => 'CronGuard Recipients',
            'comment' => 'Automatically created group by CronGuard plugin'
        ])));
    }

    public static function createNotification(): int
    {
        if (!is_null(self::$notification_id)) return self::$notification_id;
        $notification = new Notification();
        $event = \GlpiPlugin\CronGuard\CronTask::EVENT_RESTARTED;
        $input = ['event' => $event, 'itemtype' => CronTask::class];
        if ($found = $notification->find($input)) {
            return (self::$notification_id = (int)array_values($found)[0]['id']);
        }

        $notification_id = $notification->add(array_merge($input, [
            'name' => 'CronTask Restarted (CronGuard)',
            'is_recursive' => 1,
            'is_active' => 1
        ]));

        $notification_template = new NotificationTemplate();
        $notification_template_id = $notification_template->add([
            'name' => 'CronTask Restarted (CronGuard)',
            'itemtype' => CronTask::class
        ]);

        $html = file_get_contents(__DIR__ . '/../templates/notification.html');

        $ntt = new NotificationTemplateTranslation();
        $ntt->add([
            'notificationtemplates_id' => $notification_template_id,
            'language' => '',
            'subject' => 'CronGuard Report',
            'content_text' => strip_tags($html),
            'content_html' => $html
        ]);

        $nnt = new Notification_NotificationTemplate();
        $nnt->add([
            'notifications_id' => $notification_id,
            'mode' => 'mailing',
            'notificationtemplates_id' => $notification_template_id
        ]);

        $notification_target = new NotificationTarget();

        $notification_target->add([
            'items_id' => Notification::GLOBAL_ADMINISTRATOR,
            'type' => Notification::USER_TYPE,
            'notifications_id' => $notification_id,
            'is_exclusion' => 0
        ]);

        $group_id = self::createUserGroup();
        $notification_target->add([
            'items_id' => $group_id,
            'type' => Notification::GROUP_TYPE,
            'notifications_id' => $notification_id,
            'is_exclusion' => 0
        ]);

        return (self::$notification_id = $notification_id);
    }

    public static function showConfigForm($item): false
    {
        global $CFG_GLPI;

        $config = self::getInstance();

        $config->showFormHeader();
        $group_id = self::createUserGroup();
        $group_url = "{$CFG_GLPI['url_base']}/front/group.form.php?" . http_build_query([
                'id' => $group_id,
                'forcetab' => 'Group_User$1'
            ]);

        $notification_id = self::createNotification();
        $notification_url = fn(array $query = []) => "{$CFG_GLPI['url_base']}/front/notification.form.php?" . http_build_query(array_merge($query, ['id' => $notification_id]));
        $notification = new Notification();
        $notification->getFromDB($notification_id);

        TemplateRenderer::getInstance()->display(
            '@cronguard/config.html.twig',
            [
                self::FIELD_STUCK_TIMEOUT => $config->fields[self::FIELD_STUCK_TIMEOUT] ?? 1800,
                'recipients' => self::getRecipients($has_original_group),
                'has_original_group' => $has_original_group,

                'notification' => [
                    'id' => $notification_id,
                    'name' => $notification->getName(),
                    'url' => $notification_url(['forcetab' => 'Notification$main'])
                ],

                'recipients_url' => $has_original_group ? $group_url : $notification_url(['forcetab' => 'NotificationTarget$1']),
                'template_url' => $notification_url(['forcetab' => 'Notification_NotificationTemplate$1']),

                'cfg_glpi' => $CFG_GLPI,
                'cron_jobs' => self::getCronJobExamples(),
                'cron_php_file' => self::cronPhpFile(),
                'cron_log_file' => self::cronLogFile()
            ]
        );

        $config->showFormButtons(['candel' => false]);

        return false;
    }

    static function getCronTask(): ?CronTask
    {
        if (is_null(self::$cron_task)) {
            $cron_task = new CronTask();
            if (!$found = $cron_task->find(['itemtype' => PluginCronguardCron::class, 'name' => 'cronguard'])) return null;
            $cron_task->getFromDB(array_values($found)[0]['id']);
            self::$cron_task = $cron_task;
        }
        return self::$cron_task;
    }

    static function getRecipients(&$has_original_group = false): array
    {
        global $CFG_GLPI;

        $recipients = [];
        foreach (self::getTargets() as $target) {
            if ($target['type'] == Notification::USER_TYPE && $target['items_id'] == Notification::GLOBAL_ADMINISTRATOR) {
                $recipients[] = [
                    'id' => 0,
                    'name' => $CFG_GLPI['admin_email_name'] ?? 'Admin',
                    'email' => $CFG_GLPI['admin_email'],
                ];
            }
            if ($target['type'] == Notification::GROUP_TYPE && $target['items_id'] = self::createUserGroup()) {
                $has_original_group = true;
                $recipients = array_merge($recipients, self::getGroupUsersWithDefaultEmail());
            }
        }
        return $recipients;
    }

    static function getTargets(): array
    {
        if (!is_null(self::$targets)) return self::$targets;
        $notification_id = self::createNotification();
        $target = new NotificationTarget();
        return (self::$targets = $target->find(['notifications_id' => $notification_id]));
    }

    static function get($key)
    {
        $config = self::getInstance();
        $value = $config->getField($key);
        return $value;
    }

    static function set($key, $value)
    {
        $config = new self();
        $config->update([
            'id' => 1,
            $key => $value
        ]);
    }

    static function getGroupUsersWithDefaultEmail(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $group_id = self::createUserGroup();

        $sql = "
        SELECT
            u.id AS user_id,
            COALESCE(u.realname, u.name) AS user_name,
            ue.email AS email
        FROM glpi_groups_users gu
        INNER JOIN glpi_users u
            ON u.id = gu.users_id
        INNER JOIN glpi_useremails ue
            ON ue.users_id = u.id
            AND ue.is_default = 1
        WHERE gu.groups_id = {$group_id}
        ORDER BY u.name ASC
    ";

        if (!($result = $DB->doQuery($sql))) return [];

        $users = [];
        while ($row = $DB->fetchAssoc($result)) {
            $users[] = [
                'id' => (int)$row['user_id'],
                'name' => $row['user_name'],
                'email' => $row['email'],
            ];
        }

        return $users;
    }

    protected static function getCronJobExamples(): array
    {
        $cron_jobs = [];

        $php_binary = PHP_BINARY;
        $php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $php_file = self::cronPhpFile();
        $log_file = self::cronLogFile();
        $timeout = round((int)(self::get(self::FIELD_STUCK_TIMEOUT) ?? 1800) / 60);

        if (!is_dir($dirname = dirname($log_file))) mkdir($dirname, recursive: true);

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * {$php_binary} {$php_file} >> $log_file 2>&1",
            'logo' => 'https://www.php.net/favicon.svg?v=2',
            'text' => __('Detected PHP', 'cronguard'),
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * /usr/bin/php {$php_file} >> $log_file 2>&1",
            'logo' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/Tux.svg/960px-Tux.svg.png',
            'text' => __('Generic Linux', 'cronguard'),
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * php {$php_file} >> $log_file 2>&1",
            'logo' => 'https://www.php.net/favicon.svg?v=2',
            'text' => __('Using PATH', 'cronguard'),
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * /usr/local/bin/ea-php{$php_version} {$php_file} >> $log_file 2>&1",
            'logo' => 'https://www.cpanel.net/wp-content/uploads/2025/06/logo-cPanel-header.svg',
            'text' => 'EasyApache',
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * /opt/plesk/php/{$php_version}/bin/php {$php_file} >> $log_file 2>&1",
            'logo' => 'https://cdn-ilemdpo.nitrocdn.com/WNsKLnoDoQZLOuGzkBIktYCgzMstmhPp/assets/images/optimized/rev-44c8c7d/cdn1.plesk.com/wp-content/uploads/2020/11/02120824/cropped-Logo_Plesk-192x192.png',
            'text' => 'Plesk',
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * /usr/local/bin/php {$php_file} >> $log_file 2>&1",
            'logo' => 'https://www.directadmin.com/favicon.png',
            'text' => 'CustomBuild',
        ];

        $cron_jobs[] = [
            'value' => "*/$timeout * * * * {SITE_PHP} {$php_file} >> $log_file 2>&1",
            'logo' => 'https://www.ispconfig.org/wp-content/themes/ispconfig/images/ispconfig_logo.png',
            'text' => 'ISPConfig',
        ];

        return $cron_jobs;
    }

    protected static function cronPhpFile(): string
    {
        return realpath(GLPI_ROOT) . '/plugins/cronguard/cron_watchdog.php';
    }

    static function cronLogFile($just_name = false): string
    {
        $name = 'plugins/cronguard/cron_watchdog';
        if ($just_name) return $name;
        return realpath(GLPI_LOG_DIR) . "/$name.log";
    }
}
