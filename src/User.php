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

use Longman\TelegramBot\Commands\SystemCommands\CallbackqueryCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class User extends \CommonDBTM {

    public static function getTypeName($nb = 0) {
        return 'User';
    }

    public static function getUser($id) {
        global $DB;
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_users.id AS id',
                'glpi_users.name AS name',
                'glpi_users.realname AS realname',
                'glpi_users.firstname AS firstname',
                'glpi_users.entities_id',
                'glpi_entities.completename AS entity'
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_users',
            'LEFT JOIN' => [
                'glpi_entities' => [
                    'FKEY' => [
                        'glpi_entities' => 'id',
                        'glpi_users' => 'entities_id',
                    ]
                ],
            ],
            'WHERE'     => [
                'glpi_users.is_deleted' => 0,
                'glpi_users.id' => $id
            ]
        ]);
        foreach($iterator as $id => $user) {
            return $user;
        }
        return false;
    }

    public function checkAuth($chatId) {
        $user = current((new self)->find(['id' => $chatId, 'is_authorized' => 1]));
        //if(is_null(current($users)['users_id'])) return false;
        return $user;
    }

    public static function getUsers($name = '') {
        global $DB;
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_users.id AS id',
                'glpi_users.realname AS realname',
                'glpi_users.firstname AS firstname',
                'glpi_users.entities_id',
                'glpi_entities.completename AS entity'
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_users',
            'LEFT JOIN' => [
                'glpi_entities' => [
                    'FKEY' => [
                        'glpi_entities' => 'id',
                        'glpi_users' => 'entities_id',
                    ]
                ],
            ],
            'WHERE'     => [
                'glpi_users.is_deleted' => 0,
                'glpi_users.realname' => ['LIKE', '%'.$name.'%']
            ]
        ]);
        $users = [];
        foreach($iterator as $id => $user) {
            array_push($users, $user);
        }
        return $users;
    }

    public static function addCallback() {
        CallbackqueryCommand::addCallbackHandler(function($query) {
     
            $result = Request::emptyResponse();
            $chatId = $query->getMessage()->getChat()->getId();
            $messageId = $query->getMessage()->getMessageId();

            $hasData = true;
            
             /** в params парсятся параметры, заданные в инлайн клавиатуре
              *
              * например:
              *  $data['reply_markup'] = ['inline_keyboard' => [ 
              *     [ [ "text" => "Включить звук", "callback_data" => "cmd=sound&value=1" ] ]
              *  ];
              **/    
            //print_r($query->getData());
            parse_str($query->getData(), $params);
         
            $data = [
                'chat_id' => $chatId,
                'parse_mode' => 'html'
            ];

            // загрузка информации о пользователе из БД
            $userTg = new self;
            $userTg->getFromDB($chatId);
            //print_r(!$userTg->isNewItem());
            if(isset($params['user'])) {
                $user = self::getUser($params['user']);
                    if($user) {
                        $userTg->fields['id'] = $chatId;
                        $userTg->fields['users_id'] = $params['user'];
                    } else {
                        $data['text'] = 'Пользователь не найден! Попробуйте ещё раз';
                    }
            }

            // обработка комманд
            switch($params['cmd']) {
                case 'set_user_id':
                    $data['text'] = 'Выбран пользователь: '.$user['realname'].' '.$user['firstname'].' ('.$user['entity'].')';
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Подтвердить', 'callback_data' => 'cmd=confirm_user&user='.$params['user']],
                        ['text' => 'Отменить', 'callback_data' => 'cmd=cancel_user']
                    ]);
                    break;
                case 'incorrect_family':
                case 'cancel_user':
                    $data['text'] = 'Введите вашу фамилию';
                    break;
                case 'confirm_user':
                    $userTg->fields['is_authorized'] = 1;
                    if(!$userTg->isNewItem()) {
                        $userTg->updateInDB(array_keys($userTg->fields));
                    } else {
                        $userTg->addToDB();
                    }
                    $data['text'] = 'Пользователь успешно сохранён: '.$user['realname'].' '.$user['firstname'].' ('.$user['entity'].').';
                    if(file_exists(__DIR__.'/../mode') && file_get_contents(__DIR__.'/../mode') == 1) {
                        $data['text'] .= PHP_EOL.'Теперь вы можете работать с заявками: создать новую или просмотреть список';
                        $data['reply_markup'] = new Keyboard([
                            'keyboard' => [
                                ['Создать заявку'],
                                ['Список заявок']
                            ], 
                            'resize_keyboard' => true,
                            'selective' => true
                        ]);
                    } else {

                    }
                    // TODO: обработка неудачного сохранения
                    break;
                case 'set_authtype':
                    if(isset($params['authtype'])) {
                        $userTg->fields['authtype'] = $params['authtype'];
                        if(!$userTg->isNewItem()) {
                            $userTg->updateInDB(array_keys($userTg->fields));
                        } else {
                            $userTg->fields['id'] = $chatId;
                            $userTg->addToDB();
                        }
                    }
                    $data['text'] = 'Введите имя пользователя';
                    break;
                default:
                    $hasData = false;
                    break;
            }

            $result = false;
            
            if($hasData) $result = Request::sendMessage($data);
            
            return $result;
        });
    }

    static function showUsernameField($params) {
        global $DB;

        $item = $params['item'];
        $options = $params['options'];

        if(isset($_REQUEST['_glpi_tab']) && ($_REQUEST['_glpi_tab'] == 'User$1' || $_REQUEST['_glpi_tab'] == 'User$main')) {
            $username = '';
            $tgID = '';
            $id = null;
            $isPref = false;
            if ($item->getType() == 'Preference' && $options['itemtype'] == 'User') {
                $id = \Session::getLoginUserID();
                $isPref = true;
            }
            if ($item->getType() == 'User' && $item->fields['id']) $id = $item->fields['id'];
            if($id) {
                $user = new self();
                if($u = current($user->find(['users_id' => $id], [], 1))) {
                    $tgID = $u['id'];
                    $iterator = $DB->request([
                        'SELECT'    => [
                            'glpi_plugin_telegramtickets_user.id AS id',
                            'glpi_plugin_telegramtickets_user.username AS username'
                        ],
                        'DISTINCT'  => true,
                        'FROM'      => 'glpi_plugin_telegramtickets_user',
                        'WHERE'     => [
                            'glpi_plugin_telegramtickets_user.id' => $u['id']
                        ]
                    ]);
                    foreach($iterator as $tgUser) {
                        $username = $tgUser['username'];
                    }
                }
                
                $out = '';
                if($isPref) $out .= '<form method="post" name="user_manager" enctype="multipart/form-data" action="/front/preference.php" autocomplete="off">';
                $out .= '<table class="tab_cadre_fixe" style="width: auto;">';
                $out .= '<tr class="tab_bg_1" style="border: 2px rgb(135, 170, 138) solid; border-radius: 4px; display: block;">';
                $out .= '<td><strong>Telegram Tickets: </strong>'.__('Имя пользователя Telegram', 'telegramtickets').'</td>';
                $out .= '<td>'.($username ? $username : '<отсутствует>').'</td>';
                if($username) {
                    $out .= '<td>
                        <input type="hidden" name="delete_username" value="'.$tgID.'">
                        <input type="hidden" name="date_mod" value="'.date('Y-m-d H:i:s').'">
                        <input type="hidden" name="_glpi_csrf_token" value="'.\Session::getNewCSRFToken().'">
                        <input type="hidden" name="id" value="'.$id.'">
                        <button class="btn btn-danger me-2" type="submit" name="update" value="2">Очистить</button></td>
                    ';
                }
                $out .= '</tr>';
                $out .= '</table>';
                if($isPref) $out .= '</form>';

                echo $out;
            }
        }
    }

    static function cleanUsername(\User $item) {
        //die();
        if(isset($item->input['_update']) && $item->input['_update'] == 2) {
            $user = new self();
            $user->getFromDB($item->input['delete_username']);
            $user->deleteFromDB();
        };
    }

    static function getAuthTypesButtons($data) {
        $buttons = [];
        $auth = new \Auth;
        $authtypes = $auth->getLoginAuthMethods();
        unset($authtypes['_default']);
        foreach($authtypes as $authtype => $authName) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $authName,
                'callback_data' => 'cmd=set_authtype&authtype='.$authtype,
            ]);
        }
        
        $data['text'] = 'Выберите тип авторизации';
        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    static function getAuthTypeAndId($login_auth) {
        $auth = [];
        $auth['auth_id'] = 0;
        if ($login_auth == 'local') {
            $auth['authtype'] = \Auth::DB_GLPI;
        } else if (preg_match('/^(?<type>ldap|mail|external)-(?<id>\d+)$/', $login_auth, $auth_matches)) {
            $auth['auth_id'] = (int)$auth_matches['id'];
            if ($auth_matches['type'] == 'ldap') {
                $auth['authtype'] = \Auth::LDAP;
            } else if ($auth_matches['type'] == 'mail') {
                $auth['authtype'] = \Auth::MAIL;
            } else if ($auth_matches['type'] == 'external') {
                $auth['authtype'] = \Auth::EXTERNAL;
            }
        }
        return $auth;
    }

    public function setState($state) {
        $this->fields['state'] = $state;
        return $this->updateInDB(array_keys($this->fields));
    }

}