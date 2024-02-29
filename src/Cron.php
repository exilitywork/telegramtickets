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

namespace GlpiPlugin\TelegramTickets;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

use GlpiPlugin\Telegramtickets\Telegram;

class Cron extends \CommonDBTM
{

    /**
     * Get typename
     *
     * @param $nb            integer
     *
     * @return string
    **/
    static function getTypeName($nb = 0) {
        return __('Cron', 'etn');
    }

    static function cronInfo($name) {
        switch ($name) {
            case 'TTListenMessageTelegram':
                return array('description' => __('Telegram Tickets: работа с входящими сообщениями от бота', 'etn'));
        }
  
        return array();
    }

    static function cronTTListenMessageTelegram($task) {
        global $DB;
        Telegram::listen();
        $task->addVolume(1);
        return true;
    }

}
?>