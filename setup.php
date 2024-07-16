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

define('PLUGIN_TELEGRAMTICKETS_VERSION', '0.0.1');

// Minimal GLPI version, inclusive
define("PLUGIN_TELEGRAMTICKETS_MIN_GLPI_VERSION", "10.0.1");
// Maximum GLPI version, exclusive
define("PLUGIN_TELEGRAMTICKETS_MAX_GLPI_VERSION", "10.0.99");

use Glpi\Plugin\Hooks;

require_once 'vendor/autoload.php';

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_telegramtickets()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;
    
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['telegramtickets'] = true;

    $menu = [];
    if(\Session::haveRight('config', READ)) $menu['config'] = 'GlpiPlugin\Telegramtickets\Config';
    $PLUGIN_HOOKS['menu_toadd']['telegramtickets'] = $menu;

    $PLUGIN_HOOKS[Hooks::ITEM_ACTION_TARGETS]['telegramtickets'] = ['NotificationTargetTicket' => ['GlpiPlugin\Telegramtickets\Ticket', 'addTargets']];
    $PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['telegramtickets'] = ['GlpiPlugin\Telegramtickets\User', 'showUsernameField'];
    $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['telegramtickets'] = 'plugin_telegramtickets_hook_post_item_form';
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['telegramtickets'] = [
        'User' => ['GlpiPlugin\Telegramtickets\User', 'cleanUsername'], 
        'TicketValidation' => ['GlpiPlugin\Telegramtickets\Validation', 'addItem'],
        'Ticket' => ['GlpiPlugin\Telegramtickets\Ticket', 'updateItem'],
        'Ticket_User' => ['GlpiPlugin\Telegramtickets\Ticket', 'updateItem']
    ];
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['telegramtickets'] = ['TicketValidation' => ['GlpiPlugin\Telegramtickets\Validation', 'addItem']];
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['telegramtickets'] += ['Ticket' => ['GlpiPlugin\Telegramtickets\Ticket', 'addItem']];
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['telegramtickets'] += ['ITILFollowup' => ['GlpiPlugin\Telegramtickets\Ticket', 'addFollowup']];
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_telegramtickets()
{
    return [
        'name'           => 'Telegram Tickets',
        'version'        => PLUGIN_TELEGRAMTICKETS_VERSION,
        'author'         => '<a href="https://www.linkedin.com/in/oleg-kapeshko-webdev-admin/">Oleg Kapeshko</a>',
        'license'        => 'GPL-2.0-or-later',
        'homepage'       => 'https://github.com/exilitywork/telegramtickets',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_TELEGRAMTICKETS_MIN_GLPI_VERSION,
                'max' => PLUGIN_TELEGRAMTICKETS_MAX_GLPI_VERSION,
            ]
        ]
    ];
}
