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

use GlpiPlugin\Telegramtickets\Field;

include ("../../../inc/includes.php");

Session::checkLoginUser();

if(!isset($_POST['id'])) die();

try {
    $field = new Field();
    $f = current($field->find(['id' => $_POST['id']], [], 1));
    $field->fields['id'] = $_POST['id'];
    $field->fields['is_mandatory'] = $f['is_mandatory'] ? 0 : 1;
    $field->updateInDB(array_keys($field->fields));
    print(true);
} catch (Exception $e) {
    $e->getMessage();
    print_r($e->getMessage());
}
die();