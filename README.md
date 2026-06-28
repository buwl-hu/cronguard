<!--
SPDX-FileCopyrightText: 2026 2026 Buwl.hu

SPDX-License-Identifier: GPL-2.0-or-later
-->

# CronGuard GLPI Plugin

![GitHub Pre-release](https://img.shields.io/github/v/release/buwl-hu/cronguard?include_prereleases&label=pre-release)
![Status](https://img.shields.io/badge/status-beta-orange)
![GLPI](https://img.shields.io/badge/GLPI-11.x-blue)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4)
![License](https://img.shields.io/github/license/buwl-hu/cronguard)

> ⚠️ **Beta Software**
>
> CronGuard is currently in beta. Feedback, bug reports and suggestions are welcome.

> A watchdog plugin that automatically detects and recovers stuck GLPI CronTasks.

## Overview

CronGuard continuously monitors GLPI CronTasks and automatically restarts tasks that appear to be stuck.

It is designed to improve the reliability of scheduled jobs by detecting CronTasks that have stopped executing because of unexpected failures, deadlocks or interrupted processes.

For maximum reliability, CronGuard can also be executed as an independent system cron job. This allows it to recover the CronGuard GLPI CronTask itself if it ever becomes stuck.

## Features

- Automatic detection of stuck GLPI CronTasks
- Automatic recovery of affected CronTasks
- Configurable global timeout
- Email notifications
- Optional standalone watchdog script
- Compatible with GLPI 11
- Lightweight and easy to configure

## How it works

CronGuard can operate in two complementary ways.

### GLPI CronTask

The built-in CronGuard CronTask periodically checks whether other CronTasks have exceeded the configured timeout and restarts them if necessary.

This mode requires no operating system access and works on virtually every GLPI installation.

If you want to receive email notifications, the CronGuard GLPI CronTask should remain enabled, even when using the standalone cron job.

### Standalone Cron Job (Recommended)

A standalone PHP script can also be executed using the operating system scheduler (cron, Task Scheduler, etc.).

Because it runs independently from GLPI, it can even recover the CronGuard CronTask itself, providing an additional layer of protection.

The standalone script focuses on recovery and writes its results to a log file. It does not send email notifications, so it is intended to complement—not replace—the built-in GLPI CronTask.

## Installation

1. Download the latest beta release.
2. Copy the `cronguard` directory into `glpi/plugins/`.
3. Install the plugin from **Setup → Plugins**.
4. Configure the global timeout and notification settings.
5. (Optional but recommended) Configure the standalone CronGuard PHP script as a system cron job.

## Requirements

- GLPI 11.x
- PHP 8.2 or newer

## Screenshots

*(Screenshots will be added.)*

## Bug Reports

If you discover a bug or have a feature request, please open an issue on GitHub.

https://github.com/buwl-hu/cronguard/issues

## Contributing

Contributions, bug reports and suggestions are welcome.

Please open an issue before submitting large changes so they can be discussed first.

## License

CronGuard is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later).

See the LICENSE file for details.

## Disclaimer

CronGuard is an independent community plugin for GLPI.

It is not affiliated with, endorsed by or supported by the GLPI Project.