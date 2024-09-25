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

require_once __DIR__.'/../vendor/autoload.php';

use DateTimeImmutable;
use GlpiPlugin\Telegramtickets\Field;
use GlpiPlugin\Telegramtickets\Ticket;
use GlpiPlugin\Telegramtickets\User;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram as TelegramBot;

class Telegram extends \CommonDBTM
{
    protected TelegramBot $telegram;

    function __construct() {
        try {
            $config = Config::getConfig();
            $telegram = new TelegramBot($config['bot_token'], $config['bot_username']);
        } catch (TelegramException $e) {
            // log telegram errors
            echo $e->getMessage();
            die();
        }
    }

    /**
     * Get typename
     *
     * @param $nb            integer
     *
     * @return string
    **/
    static function getTypeName($nb = 0) {
        return __('Telegram', 'telegramtickets');
    }

    public function send($data) {
        try {
            Request::sendMessage($data);
        } catch (TelegramException $e) {
            // log telegram errors
            echo $e->getMessage();
            die();
        }
    }

    static function listen() {
        global $DB;
        $config = Config::getConfig();

        $mysql_credentials = [
            'host'     => $DB->dbhost,
            'port'     => 3306, // optional
            'user'     => $DB->dbuser,
            'password' => $DB->dbpassword,
            'database' => $DB->dbdefault,
        ];

        //print_r('<pre>');

        try {
            // Create Telegram API object
            $telegram = new TelegramBot($config['bot_token'], $config['bot_username']);
            $telegram->setDownloadPath(GLPI_TMP_DIR);
            // Enable MySQL
            $telegram->enableMySql($mysql_credentials, 'glpi_plugin_telegramtickets_');

            // добавление обработчиков команд
            User::addCallback();
            Ticket::addCallback();
            
            // Handle telegram getUpdates request
            $serverResponse = $telegram->handleGetUpdates();
            if ($serverResponse->isOk()) {
                $chatIDs = [];
                $updates = $serverResponse->getResult();
                foreach($updates as $update) {
                    //print_r('-------- Telegram.php 220<br>');
                    if($message = $update->getMessage()){
                        //print_r($update->getMessage());
                        //print_r('-------- Telegram.php<br>');
                        $text = $update->getMessage()->text;
                        //print_r($text);
                        //print_r('-------- Telegram.php<br>');

                        // не обрабатывать здесь команду /start
                        if($text == '/start') continue;
                        
                        $chatId = $update->getMessage()->getChat()->getId();

                        // прерывание цикла для обработки только первого поступившего сообщения от пользователя
                        if(in_array($chatId, $chatIDs)) break;
                        array_push($chatIDs, $chatId);

                        $ticket = new Ticket;
                        $user = new User;
                        $user->getFromDB($chatId);
                        $isNewUser = $user->isNewItem();

                        // обработка сохраненных комманд
                        if(!empty($user->fields['state'] && $text != 'Создать заявку' && $text != 'Список заявок')) {
                            $query['chat_id'] = $chatId;
                            $query['data'] = $user->fields['state'];
                            $query['text'] = $text;
                            $query['message'] = $message;
                            Ticket::handleQuery($query);
                            continue;
                        }

                        $data = [
                            'chat_id'      => $chatId,
                            'text'         => 'Здравствуйте! Для того, чтобы создать новую заявку, нажмите кнопку "Создать заявку", или просмотрите список заявок. Если кнопки нет, то нажмите /start',
                            'reply_markup' => new Keyboard([
                                'keyboard' => [
                                    ['Создать заявку'],
                                    ['Список заявок']
                                ], 
                                'resize_keyboard' => true,
                                'selective' => true
                            ])
                        ];
                        
                        // проверка авторизации пользователя
                        if(empty($user->fields['is_authorized'])) { 
                            unset($data['reply_markup']);
                            if(file_exists(__DIR__.'/../mode') && file_get_contents(__DIR__.'/../mode') == 1) { // авторизация по единому паролю
                                $data['text'] = 'Для доступа к заявкам наберите пароль';
                                if($text == $config['bot_password']) {
                                    $user->fields['id'] = $chatId;
                                    $user->fields['is_authorized'] = 1;
                                    //print_r('+++++++++++ Telegram.php 273<br>');
                                    //print_r($isNewUser);
                                    //print_r('<br>+++++++++++++ Telegram.php<br>');
                                    if(!$isNewUser) {
                                        $user->updateInDB(array_keys($user->fields));
                                        //print_r("+++++++++++++++++++++++++++++++++++++++++++++++++++++ Telegram.php");
                                    } else {
                                        $user->addToDB();
                                        //print_r("******************************************************* Telegram.php");
                                    }
                                    $data['text'] = 'Пароль верный! Введите вашу фамилию';
                                } else {
                                    $data['text'] = 'Пароль неверный! Для доступа к заявкам введите правильный пароль';
                                    if($text == 'Создать заявку') $data['text'] = 'Для доступа к заявкам введите правильный пароль';
                                }
                                //unset($data['reply_markup']);
                            } else { // авторизация по логину и паролю
                                if(empty($user->fields['users_id'])) {
                                    $data['text'] = 'Введите имя пользователя';
                                    $userGLPI = new \User;
                                    //print_r($user->getAuthTypeAndId($user->fields['authtype']));
                                    $auth = $user->getAuthTypeAndId($user->fields['authtype']);
                                    if($userGLPI->getFromDBbyNameAndAuth($text, $auth['authtype'], $auth['auth_id']) && $userGLPI->fields['is_active'] && !$userGLPI->fields['is_deleted']) {
                                        //echo '<pre>';
                                        //print_r($userGLPI);
                                        $user->fields['id'] = $chatId;
                                        $user->fields['users_id'] = $userGLPI->fields['id'];
                                        $user->fields['is_authorized'] = 0;
                                        if(!$isNewUser) {
                                            $user->updateInDB(array_keys($user->fields));
                                        } else {
                                            $user->addToDB();
                                        }
                                        $data['text'] = 'Введите пароль';
                                    } else {
                                        if(isset($userGLPI->fields['is_deleted']) && $userGLPI->fields['is_deleted']) {
                                            $data['text'] = 'Пользователь с таким именем удален! Введите повторно имя пользователя';
                                        } elseif(isset($userGLPI->fields['is_active']) && !$userGLPI->fields['is_active']) {
                                            $data['text'] = 'Пользователь с таким именем отключен! Введите повторно имя пользователя';
                                        } else {
                                            $data['text'] = 'Пользователь с таким именем не найден! Введите повторно имя пользователя';
                                        }                                       
                                    }
                                    //unset($data['reply_markup']);
                                } else {
                                    //$data['text'] = 'Введите пароль';
                                    if($user->fields['is_authorized']) {
                                        $data['text'] = '+++++';
                                        //unset($data['reply_markup']);
                                    } else {
                                        //$data['text'] = '-----';
                                        //$userGLPI = new \User;
                                        //$userGLPI->getFromDB($user->fields['users_id']);
                                        $userGLPI = User::getUser($user->fields['users_id']);
                                        $auth = new \Auth;
                                        $auth->login($userGLPI['name'], $text, 0, 0, $user->fields['authtype']);
                                        //$data['text'] .= $auth->auth_succeded;
                                        if($auth->auth_succeded) {
                                            $data['text'] = 'Подтвердите авторизацию пользователя: '.$userGLPI['realname'].' '.$userGLPI['firstname'].' ('.$userGLPI['entity'].')';
                                            $data['reply_markup'] = new InlineKeyboard([
                                                ['text' => 'Подтвердить', 'callback_data' => 'cmd=confirm_user&user='.$user->fields['users_id']],
                                                ['text' => 'Отменить', 'callback_data' => 'cmd=cancel_user']
                                            ]);
                                        } else {
                                            $data['text'] = 'Пароль не верен. Попробуйте ещё раз';
                                            unset($data['reply_markup']);
                                        }
                                        //print_r($auth->auth_succeded);
                                    }
                                    //print_r($user);
                                    /*if($userGLPI->getFromDBbyName($text)) {
                                        $user->fields['id'] = $chatId;
                                        $user->fields['users_id'] = $userGLPI->fields['id'];
                                        $user->fields['is_authorized'] = 0;
                                        if(!$isNewUser) {
                                            $user->updateInDB(array_keys($user->fields));
                                        } else {
                                            $user->addToDB();
                                        }
                                    } else {
                                        $data['text'] = 'Пользователь с таким именем не найден! Введите повторно имя пользователя';
                                    }*/
                                }
                                
                                
                            }
                            
                        // определение пользователя GLPI
                        } elseif(empty($user->fields['users_id'])) {
                            unset($data['reply_markup']);
                            $data['text'] = 'Введите вашу фамилию';
                            if(!empty($text)) {
                                $users = User::getUsers($text);
                                if(!count($users)) {
                                    $data['text'] = 'Пользователь с такой фамилией не найден! Введите вашу фамилию повторно';
                                } else {
                                    $data['text'] = 'Найдено пользователей - '.count($users);
                                    $keyboardButtons = [];
                                
                                    foreach($users as $user) {
                                        $keyboardButtons[] = new InlineKeyboardButton([
                                            'text'          => $user['realname'].' '.$user['firstname'].' ('.$user['entity'].')',
                                            'callback_data' => 'cmd=set_user_id&user='.$user['id'],
                                        ]);
                                    }
                                    $keyboardButtons[] = new InlineKeyboardButton([
                                        'text'          => 'Ввести фамилию повторно',
                                        'callback_data' => 'cmd=incorrect_family&user='.$user['id'],
                                    ]);

                                    $data['reply_markup'] = self::verticalInlineKeyboard($keyboardButtons);
                                }
                            }
                        // обработка нажатия кнопки "Создать заявку"
                        } elseif($text == 'Создать заявку') {
                            $data = $ticket->create($data, $user);
                        // обработка нажатия кнопки "Список заявок"
                        } elseif($text == 'Список заявок') {
                            $ticket = new Ticket;
                            $tickets = $ticket->find(['users_id' => $user->fields['users_id']]);
                            if(count($tickets)) {
                                foreach($tickets as $t) {
                                    $ticket->getFromDB($t['id']);
                                    $ticket->deleteFromDB();
                                    $ticket = new Ticket;
                                }
                            }
                            $data['text'] = 'Список заявок:';
                            $data = Ticket::getTickets($data, $user);
                            //$data['text'] = 34656546456;
                            //$data = $ticket->create($data, $user);
                        // обработка ввода данных заявки
                        } elseif($field = $ticket->getNextInput($user->fields['users_id'])) {
                            //print_r('<br> ************************** ');
                            //print_r($field);
                            //print_r(' ************************** <br>');
                            if($field === true) { // если все поля в заявке заполнены
                                $data['text'] = 'Для того, чтобы cоздать новую заявку нажмите кнопку "Создать заявку". Если кнопки нет, то нажмите /start';
                            } else {
                                $tickets = $ticket->find(['users_id' => $user->fields['users_id']]);
                                if(count($tickets) > 1) {
                                    $data['text'] = 'Много не законченных заявок! Обратитесь к администратору!';
                                } elseif($tickets) {
                                    $validInput = true;
                                    // если текущее поле - "Документы", то загружаем файл и получаем его ID
                                    if($field['id'] == 142) {
                                        if(is_null($ticket->fields['documents_id'])) {
                                            if($docID = $ticket->addFile($message)) {
                                                $text = $docID;
                                            }
                                        }
                                    }
                                    // если текущее поле - "Дата открытия", то преобразуем введенную дату в формат "Y-m-d H:i:s"
                                    if($field['id'] == 15) {
                                        try {
                                            $date = new DateTimeImmutable($text);
                                            $text = $date->format('Y-m-d H:i:s');
                                        } catch (\Exception $e) {
                                            $validInput = false;
                                        }
                                    }
                                    // если текущее поле - "Общая продолжительность", то преобразуем часы в секунды
                                    if($field['id'] == 45) {
                                        if(is_numeric($text)) {
                                            $text = $text * 3600;
                                        } else {
                                            $validInput = false;
                                        }
                                    }
                                    // обновление полей создаваемой заявки
                                    if($field['id'] != 7 && $validInput) { // если текущее поле "Категория", то его не обновляем здесь
                                        $ticket->getFromDB(current($tickets)['id']);
                                        $ticket->fields[$field['field']] = $text;
                                        $ticket->updateInDB(array_keys($ticket->fields));
                                    }
                                }
                                // если текущее поле "Категория", то извлекаем текст для поиска
                                $searchText = '';
                                if($field['id'] == 7) {
                                    $searchText = $text;
                                }
                                // получение следующего поля для заполнения
                                if($field = $ticket->getNextInput($user->fields['users_id'])) {
                                    if($field === true) { // если все поля заполнены
                                        $data['text'] = 'Все поля заявки заполнены!'.PHP_EOL;
                                        $data['text'] .= '------------------'.PHP_EOL;
                                        $fields = Field::getFields();
                                        foreach($fields as $id => $mandatory) {
                                            if(!is_null($ticket->fields[$ticket::FIELDS[$id]]) && $ticket->fields[$ticket::FIELDS[$id]] != '-1') {
                                                $data['text'] .= $ticket::FIELD_TRANSLATIONS[$id].': '.$ticket->getFieldInfo($id).PHP_EOL;
                                            }
                                        }
                                        $data['reply_markup'] = new InlineKeyboard([
                                            ['text' => 'Завершить создание', 'callback_data' => 'cmd=finish_ticket&user='.$user->fields['users_id']],
                                            ['text' => 'Создать новую', 'callback_data' => 'cmd=add_ticket&user='.$user->fields['users_id']]
                                        ]);
                                    } else { // вывод предложения для ввода данных по следующему полю
                                        $data['text'] = $field['request_translation'];
                                        if(!$validInput && $field['id'] == 15) $data['text'] = 'Некорректная дата! Повторите ввод (пример: 22.02.2024 15:00)';
                                        if(!$validInput && $field['id'] == 45) $data['text'] = 'Некорректное количество часов! Повторите ввод в часах (пример: 22)';
                                        unset($data['reply_markup']);
                                        if(!$field['is_mandatory']) {
                                            $data['reply_markup'] = new InlineKeyboard([
                                                [
                                                    'text' => 'Пропустить', 
                                                    'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field='.$field['id']
                                                ]
                                            ]);
                                        }//print_r($searchText);
                                        if($tempData = $ticket->getInputInfo($field, $data, $user, $searchText)) {
                                            $data = $tempData;
                                        }
                                    }
                                } else {
                                    // недостижимо???
                                    $data['text'] = 'Заявка успешно добавлена';
                                }
                            }
                            
                            //$ticket->fields[$field['field']] = $text;
                        }

                        Request::sendMessage($data);
                    }
                }
            } else {
                // TODO: действия, если сервер не отвечает
                print_r('Сервер не отвечает!'.PHP_EOL);
                die();
            }
        } catch (TelegramException $e) {
            // log telegram errors
            echo $e->getMessage();
            die();
        }
    }

    static function verticalInlineKeyboard($keyboardButtons) {
        $inline_keyboard = new InlineKeyboard($keyboardButtons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }
        $inline_keyboard->__set('inline_keyboard', $keysV);
        return $inline_keyboard;
    }

}
?>