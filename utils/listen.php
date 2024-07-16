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

ini_set('display_errors', 'On');
error_reporting(E_ALL);

use GlpiPlugin\Telegramtickets\Telegram;

echo('<pre>');
$homepage = file_get_contents(__DIR__.'/../mode');
if($homepage == 2) echo 4;
echo $homepage;
if(file_exists(__DIR__.'/../mode1') && file_get_contents(__DIR__.'/../mode1') == 2) {
    echo file_exists(__DIR__.'/../mode');
}

require_once __DIR__.'/../vendor/autoload.php';
include (__DIR__."/../../../inc/includes.php");

Telegram::listen();

/*$iterator = $DB->request('SHOW COLUMNS from `glpi_plugin_telegramtickets_users` LIKE `authtype`');
if(count($iterator) == 0) {
    $DB->request('ALTER TABLE `glpi_plugin_telegramtickets_users` ADD `authtype` VARCHAR(255) NULL AFTER `is_authorized`');
}
print_r(count($iterator));*/

//ALTER TABLE `glpi_plugin_telegramtickets_users` ADD `authtype` VARCHAR(255) NULL AFTER `is_authorized`;
$auth = new \Auth;
print_r($auth->getLoginAuthMethods());

$tt = new \TicketTemplate;
            $fields = $tt->getAllowedFields();print_r($fields);