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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Telegramtickets\Config;
use GlpiPlugin\Telegramtickets\Field;

global $CFG_GLPI, $DB;

include("../../../inc/includes.php");

Session::checkRight('config', READ);

if(Session::getLoginUserID()) {
    if (Session::getCurrentInterface() == "helpdesk") {
        Html::displayRightError();
    } else {
        Html::header(Config::getTypeName(1), $_SERVER['PHP_SELF'], 'config', 'GlpiPlugin\Telegramtickets\Config');
    }
}

if(!empty($_POST) && (isset($_POST['update']) || isset($_POST['add']))) {
    if(isset($_POST['entities_id'])) unset($_POST['entities_id']);
    if(isset($_POST['update'])) unset($_POST['update']);
    if(isset($_POST['add'])) unset($_POST['add']);
    if(isset($_POST['id'])) unset($_POST['id']);
    if(isset($_POST['_glpi_csrf_token'])) unset($_POST['_glpi_csrf_token']);
    Config::updateConfig($_POST);
}

// add or delete fields
if(!empty($_POST) && isset($_POST['add_field'])) {
    $field = new Field;
    $field->fields['fields_id'] = $_POST['fields_id'];
    $field->fields['is_mandatory'] = $_POST['is_mandatory'];
    if(!(current($field->find(['fields_id' => $_POST['fields_id'], 'is_mandatory' => $_POST['is_mandatory']], [], 1)))) {
        $field->addToDB();
    }
}
if(!empty($_REQUEST) && isset($_REQUEST['delete_field'])) {
    $field = new Field;
    $field->deleteByCriteria(['id' => $_REQUEST['delete_field']]);
}

// add or delete users for SLA notify
if(!empty($_POST) && isset($_POST['add_sla_user'])) {
    $expiredSlaUser = new ExpiredSla;
    $expiredSlaUser->fields['users_id'] = $_POST['users_id'];
    if(!(current($expiredSlaUser->find(['users_id' => $_POST['users_id']], [], 1)))) {
        $expiredSlaUser->addToDB();
    }
}
if(!empty($_REQUEST) && isset($_REQUEST['delete_sla_user'])) {
    $expiredSlaUser = new ExpiredSla;
    $expiredSlaUser->deleteByCriteria(['id' => $_REQUEST['delete_sla_user']]);
}

// add or delete itemtypes for reporting
if(!empty($_POST) && isset($_POST['add_itemtype'])) {
    $itemtype = new Itemtype;
    $itemtype->fields['itemtypes_id'] = $_POST['itemtype'];
    if(!(current($itemtype->find(['itemtypes_id' => $_POST['itemtype']], [], 1)))) {
        $itemtype->addToDB();
    }
}
if(!empty($_REQUEST) && isset($_REQUEST['delete_itemtype'])) {
    $itemtype = new Itemtype;
    $itemtype->deleteByCriteria(['id' => $_REQUEST['delete_itemtype']]);
}

// add or delete users for new item notify
if(!empty($_POST) && isset($_POST['add_item_recipients'])) {
    $itemtype = new ItemtypeRecipients;
    $itemtype->fields['users_id'] = $_POST['users_id'];
    if(!(current($itemtype->find(['users_id' => $_POST['users_id']], [], 1)))) {
        $itemtype->addToDB();
    }
}
if(!empty($_REQUEST) && isset($_REQUEST['delete_item_recipients'])) {
    $itemtype = new ItemtypeRecipients;
    $itemtype->deleteByCriteria(['id' => $_REQUEST['delete_item_recipients']]);
}

$config = new Config();
$config->getFromDB(1);
$config->display(['withtemplate' => 1]);

if(Session::getLoginUserID()) {
    if (Session::getCurrentInterface() == "helpdesk") {
        Html::helpFooter();
    } else {
        Html::footer();
    }
}