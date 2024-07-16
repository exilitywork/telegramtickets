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

namespace GlpiPlugin\Telegramtickets;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Validation extends \CommonDBTM {

    public $deduplicate_queued_notifications = false;

    static function addRecipient($item) {
        
        unset($item->target);

        $recipients = $item->options['recipients'];

        foreach($recipients as $id) {
            $email = current((new \UserEmail)->find(['users_id' => $id, 'is_default' => 1], [], 1))['email'];
            $user = current((new \User)->find(['id' => $id], [], 1));

            if ($item->getType() == 'GlpiPlugin\Etn\NotificationTargetItemtype') {
                $item->target[$email]['language'] = 'ru_RU';
                $item->target[$email]['additionnaloption']['usertype'] = 2;
                $item->target[$email]['username'] = $user['realname'].' '.$user['firstname'];
                $item->target[$email]['users_id'] = $id;
                $item->target[$email]['email'] = $email;
            }
        }
        //error_log(date('Y-m-d H:i:s')."TEST\n", 3, '/var/www/glpi/files/_log/test.log');
    }

    /**
     * Dropdown of itemtypes
     *
     * @param $value    integer / preselected value (default 0)
     * 
     * @return string id of the select
     **/
    static function getItemtypeDropdown($value = 0) {
        global $CFG_GLPI;
        
        $params['value']       = $value;
        $params['toadd']       = [];
        $params['on_change']   = '';
        $params['display']     = true;
        foreach($CFG_GLPI['asset_types'] as $key => $type) { 
            $params['toadd'] += [$type => getItemForItemtype($type)->getTypeName(1)];
        }
        foreach($CFG_GLPI['device_types'] as $key => $type) {
            $params['toadd'] += [$type => getItemForItemtype($type)->getTypeName(1)];
        }

        $items = [];
        if (count($params['toadd']) > 0) {
            $items = $params['toadd'];
        }

        $itemtypes = (new Itemtype)->find();
        foreach($itemtypes as $itemtype) {
            unset($items[$itemtype['itemtypes_id']]);
        }

        return \Dropdown::showFromArray('itemtype', $items, $params);
    }

    /**
     * Post add item 
     *
     * @param $item            
     *
     * @return bool
    **/
    static function addItem(\TicketValidation $item) {
        if(file_exists(__DIR__.'/../mode') && file_get_contents(__DIR__.'/../mode') == 2) {
            echo '<pre>';
            $ticket = new \Ticket;
            if($ticket->getFromDB($item->fields['tickets_id'])) {
                $tt = new \TicketTemplate;
                $fields = $tt->getAllowedFields(true, true);
                $fieldNames = $tt->getAllowedFieldsNames(true);
                $validFields = (new Validation_Field)->find(['itemtype' => 'Ticket']);
                foreach ($validFields as $id => $field) {
                    $id = str_starts_with($fields[$field['fields_id']], '_') ? $ticket->fields['id'] : 0;
                    $value = self::getFieldInfo($field['fields_id'], ($id ? $id : $ticket->fields[$fields[$field['fields_id']]]));
                    echo $fieldNames[$field['fields_id']].': '.($value ? $value : $ticket->fields[$fields[$field['fields_id']]]).'<br>';

                    $field['name'] = $fields[$field['fields_id']];
                    $validFields[$id] = $field;
                }

                print_r($fields);
                print_r($tt->getExtraAllowedFields());
                print_r($validFields);
                print_r($item);
                die();
            }
        }
    }

    /**
     * Get the tab name used for item
     *
     * @param object $item the item object
     * @param integer $withtemplate 1 if is a template form
     * @return string|array name of the tab
     */
    function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
        return __('Согласования');
    }

    static function getFieldInfo($id, $value) {
        switch($id) {
            case 3: // приоритет
                return Ticket::PRIORITY[$value];
                break;
            case 10: // срочность
                return Ticket::URGENCY[$value];
                break;
            case 11: // влияние
                return Ticket::IMPACT[$value];
                break;
            case 142: // название прикрепленного файла
                //return $this->getFileName();
                break;
            case 7: // название категории
                $category = new \ITILCategory;
                if($category->getFromDB($value)) {
                    /*if($full) {
                        return $category->getRawCompleteName();
                    }*/
                    return $category->getName();
                } else {
                    return '';
                }
                //return $this->getCategoryName(true);
                break;
            case 12: // название статуса
                $ticket = new \Ticket;
                $statuses = $ticket->getAllStatusArray();
                return $statuses[$value];
                //return $this->getStatusName();
                break;
            case 9: // название типа запросов
                //return $this->getRequestTypeName();
                break;
            case 14: // название типа заявки
                //return self::TYPES[$this->fields['type']];
                break;
            case 45: // продолжительность
                return $value;
                break;
            case 5: // назначенные специалисты
                return self::getActors($value, \CommonITILActor::ASSIGN);
                break;
            case 66: // наблюдатели
                return self::getActors($value, \CommonITILActor::OBSERVER);
                break;
        }
        return false;
    }

    static function getActors($ticketID, $role) {
        $actors = [];

        $ticket = new \Ticket();
        $ticket->getFromDB($ticketID);
        $class = new $ticket->userlinkclass();
        $ticketsUser = $class->getActors($ticketID);
        if(isset($ticketsUser[$role])) {
            $acts = $ticketsUser[$role];
            $user = new \User();
            foreach($acts as $actor) {
                if($user->getFromDB($actor['users_id'])) {
                    array_push($actors, $user->fields['realname'].' '.$user->fields['firstname']);
                }
            }

            return implode(', ', $actors);
        }
        return '';
    }

}