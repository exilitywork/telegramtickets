<?php

/**
 * -------------------------------------------------------------------------
 * Telegram Tickets plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Telegram Tickets.
 *
 * Telegram Tickets is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * any later version.
 *
 * Telegram Tickets is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Telegram Tickets. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2023-2024 by Oleg Кapeshko
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/exilitywork/telegramtickets
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_telegramtickets_install() {
    global $DB;

    if(!$DB->runFile(GLPI_ROOT . "/plugins/telegramtickets/sql/structure.sql")) die("SQL error");

    $cron = new \CronTask();
    if (!$cron->getFromDBbyName('GlpiPlugin\Telegramtickets\Cron', 'TTListenMessageTelegram')) {
        \CronTask::Register('GlpiPlugin\Telegramtickets\Cron', 'TTListenMessageTelegram', 60,
                            ['state' => \CronTask::STATE_WAITING, 'mode' => 2]);
    }

   return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_telegramtickets_uninstall() {
    global $DB;

    //if(!$DB->runFile(GLPI_ROOT . "/plugins/etn/sql/uninstall.sql")) die("SQL error");  

    return true;
}
