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

use GlpiPlugin\Telegramtickets\Telegram;
use Longman\TelegramBot\Commands\SystemCommands\CallbackqueryCommand;
use Longman\TelegramBot\Entities\CallbackQuery;
use Longman\TelegramBot\Entities\Document;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;
use Glpi\RichText\RichText;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class Ticket extends \CommonDBTM {

    const FIELDS = [
        1   => 'name',
        21  => 'content',
        12  => 'status',
        10  => 'urgency',
        11  => 'impact',
        3   => 'priority',
        15  => 'date',
        45  => 'actiontime',
        7   => 'itilcategories_id',
        142 => 'documents_id',
        9   => 'requesttypes_id',
        14  => 'type'
    ];

    const FIELD_REQUEST_TRANSLATIONS = [
        1   => 'Введите заголовок заявки',
        21  => 'Введите описание заявки',
        12  => 'Выберите статус заявки',
        10  => 'Выберите срочность заявки',
        11  => 'Выберите влияние заявки',
        3   => 'Выберите приоритет заявки',
        15  => 'Введите дату открытия заявки',
        45  => 'Введите общую продолжительность заявки (в часах)',
        7   => 'Выберите категорию заявки (для поиска по категориям отправьте желаемый текст боту)',
        142 => 'Добавьте документы к заявке (возможно добавление документов, архивов, аудио, видео, голосовых сообщений)',
        9   => 'Выберите источник запросов заявки',
        14  => 'Выберите тип заявки'
    ];

    const FIELD_TRANSLATIONS = [
        1   => 'Заголовок',
        21  => 'Описание',
        12  => 'Статус',
        10  => 'Срочность',
        11  => 'Влияние',
        3   => 'Приоритет',
        15  => 'Дата открытия',
        45  => 'Общая продолжительность заявки (сек.)',
        7   => 'Категория',
        142 => 'Документ',
        9   => 'Источник запросов',
        14  => 'Тип'
    ];

    const URGENCY = [
        1 => 'Очень низкая',
        2 => 'Низкая',
        3 => 'Средняя',
        4 => 'Высокая',
        5 => 'Очень высокая'
    ];

    const IMPACT = [
        1 => 'Очень низкое',
        2 => 'Низкое',
        3 => 'Среднее',
        4 => 'Высокое',
        5 => 'Очень высокое'
    ];

    const PRIORITY = [
        1 => 'Очень низкий',
        2 => 'Низкий',
        3 => 'Средний',
        4 => 'Высокий',
        5 => 'Очень высокий'
    ];

    const TYPES = [
        \Ticket::INCIDENT_TYPE  => 'Инцидент',
        \Ticket::DEMAND_TYPE    => 'Запрос'
    ];

    const ACTORS = [
        \CommonITILActor::REQUESTER => 'requester',
        \CommonITILActor::ASSIGN => 'assign',
        \CommonITILActor::OBSERVER => 'observer'
    ];

    const ACTORS_TRANSLATE = [
        \CommonITILActor::REQUESTER => 'инициатор',
        \CommonITILActor::ASSIGN => 'специалист',
        \CommonITILActor::OBSERVER => 'наблюдатель'
    ];

    public static function getTypeName($nb = 0) {
        return 'TelegramTicket';
    }

    // получение следующего поля для ввода
    public function getNextInput($userID) {
        $fields = Field::getFields();
        $tickets = $this->find(['users_id' => $userID]);
        if(count($tickets) > 1) {
            // TODO: действия над множеством незаконченных заявок
            die('Много не законченных заявок');
        }
        foreach($tickets as $ticket) {
            foreach($fields as $id => $mandatory) {
                if(is_null($ticket[$this::FIELDS[$id]])) {
                    return [
                        'id'            => $id,
                        'field'         => $this::FIELDS[$id],
                        'request_translation'   => $this::FIELD_REQUEST_TRANSLATIONS[$id],
                        'is_mandatory'  => $mandatory,
                        'translation'   => $this::FIELD_TRANSLATIONS[$id]
                    ];
                }
            }
        }
        if(count($tickets) > 0) return true;
        return false;
    }

    public static function handleQuery($query) {
        $result = Request::emptyResponse();
        $hasData = true;
        $tickets = [];
        $text = '';
        if($query instanceof CallbackQuery) { // если $query - объект CallbackQuery
            $chatId = $query->getMessage()->getChat()->getId();
            $messageId = $query->getMessage()->getMessageId(); 
            $queryData = $query->getData();
            $message = $query->getMessage();
        } else { // если $query - массив
            $chatId = $query['chat_id'];
            $queryData = $query['data'];
            $text = isset($query['text']) ? $query['text'] : '';
            $message = $query['message'];
        }
        /** в params парсятся параметры, заданные в инлайн клавиатуре
        *
        * например:
        *  $data['reply_markup'] = ['inline_keyboard' => [ 
        *     [ [ "text" => "Включить звук", "callback_data" => "cmd=sound&value=1" ] ]
        *  ];
        **/
        parse_str($queryData, $params);
        $user = new User;
        $user->getFromDB($chatId);
        // TODO: обработка несуществующего user
        if(isset($params['user'])){
            $ticket = new self;
            $tickets = $ticket->find(['users_id' => $params['user']]);
        }
        $data = [
            'chat_id' => $chatId,
            'parse_mode' => 'html'
        ];
                    
        if(count($tickets)) {
            // обработка команд
            $ticket->getFromDB(current($tickets)['id']);
            switch($params['cmd']) {
                // установка поля "Срочность"
                case 'set_urgency':
                    if(is_null($ticket->fields['urgency'])) {
                        $ticket->fields['urgency'] = $params['urgency'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                    }
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // установка поля "Влияние"
                case 'set_impact':
                    if(is_null($ticket->fields['impact'])) {
                        $ticket->fields['impact'] = $params['impact'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                    }
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // установка поля "Приоритет"
                case 'set_priority':
                    if(is_null($ticket->fields['priority'])) {
                        $ticket->fields['priority'] = $params['priority'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                    }
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // установка поля "Категория"
                case 'set_category':
                    if(is_null($ticket->fields['itilcategories_id'])) {
                        $ticket->fields['itilcategories_id'] = $params['itilcategories_id'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                    }
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // навигация по списку категорий
                case 'prev_categories':
                case 'next_categories':
                    $data['text'] = 'Выберите категорию заявки';
                    $data = $ticket->getCategories($data, $user, $params['offset'], '',  $params['is_mandatory']);
                    break;
                // поиск категории
                case 'reset_categories':
                    $data['text'] = 'Выберите категорию заявки';
                    $data = $ticket->getCategories($data, $user, 0, '', $params['is_mandatory']);
                    break;
                // установка поля "Статус"
                case 'set_status':
                    $ticket->fields['status'] = $params['status'];
                    $ticket->updateInDB(array_keys($ticket->fields));
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // установка поля "Тип запроса"
                case 'set_requesttype':
                    $ticket->fields['requesttypes_id'] = $params['requesttype'];
                    $ticket->updateInDB(array_keys($ticket->fields));
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // установка поля "Тип"
                case 'set_type':
                    $ticket->fields['type'] = $params['type'];
                    $ticket->updateInDB(array_keys($ticket->fields));
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // пропуск необязательно поля
                case 'skip_field':
                    $ticket->fields[self::FIELDS[$params['field']]] = -1; // если поле пропущено, то в него записывается "-1"
                    //print_r($ticket);
                    $ticket->updateInDB(array_keys($ticket->fields));
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;
                // завершение создания заявки
                case 'finish_ticket':
                    // загрузка Email инициатора для уведомлений
                    $useNotification = 1;
                    $userEmail = current((new \UserEmail)->find(['users_id' => $params['user'], 'is_default' => 1]))['email'];
                    if(!$userEmail) $useNotification = 0;
                    $userGLPI = new \User;
                    $userGLPI->getFromDB($params['user']);
                    $ticketGLPI = new \Ticket;
                    foreach($tickets as $fields) {
                        // удаление полей со значением NULL
                        foreach($fields as $field => $value) {
                            if(is_null($fields[$field]) || $fields[$field] == '-1') unset($fields[$field]);
                        }
                        // подготовка параметров для создания заявки в GLPI
                        $tgTicketId = $fields['id']; // ID создаваемой заявке в Telegram
                        $requester['itemtype'] = 'User';
                        $requester['items_id'] = $fields['users_id'];
                        $requester['use_notification'] = $useNotification;
                        $requester['alternative_email'] = '';
                        $requester['default_email'] = $userEmail;
                        $fields['_actors']['requester'][0] = $requester;
                        $fields['add'] = 1;
                        $fields['id'] = 0;
                        $fields['users_id_lastupdater'] = $ticket->fields['users_id'];
                        $fields['_glpi_csrf_token'] = \Session::getNewCSRFToken();
                        $fields['entities_id'] = $userGLPI->fields['entities_id'];

                        // создание заявки в GLPI
                        if($id = $ticketGLPI->add($fields)) { // ID созданной заявки в GLPI  
                            $ticket->getFromDB($tgTicketId);
                            // добавление документа к созданной зявке в GLPI
                            $document = new \Document_Item;
                            $document->fields['documents_id'] = $ticket->fields['documents_id'];
                            $document->fields['items_id'] = $id;
                            $document->fields['itemtype'] = 'Ticket';
                            $document->fields['entities_id'] = $ticketGLPI->fields['entities_id'];
                            $document->fields['is_recursive'] = 1;
                            $document->fields['timeline_position'] = 1;
                            if($document->add($document->fields)) {
                                // TODO: если ошибка добавления связи документа и заявки
                            }
                            $botApiKey  = Config::getOption('bot_token');
                            $botUsername = Config::getOption('bot_username');
                            $telegram = new Telegram($botApiKey, $botUsername);
                            $data['text'] = 'Создана заявка с ID: '.$id.PHP_EOL;
                            $data['text'] .= 'Для создания новой заявки нажмите кнопку "Создать заявку" или откройте весь список'.PHP_EOL;
                            $data['reply_markup'] = new Keyboard([
                                'keyboard' => [
                                    ['Создать заявку'],
                                    ['Список заявок']
                                ], 
                                'resize_keyboard' => true,
                                'selective' => true
                            ]);
                            $ticket->deleteFromDB();
                        } else {
                            $data['text'] = 'Ошибка при создании заявки!';
                        }
                    }
                    break;
                
                // при нажатии кнопки "Продолжить создание"
                case 'continue_ticket':
                    $data = $ticket->create($data, $user, $params['cmd']);
                    break;

                // при нажатии кнопки "Создать новую"
                case 'add_ticket':
                    // удаление незаконченных заявок
                    $ticket = new self;
                    $tickets = $ticket->find(['users_id' => $params['user']]);
                    if(count($tickets)) {
                        foreach($tickets as $t) {
                            $ticket->getFromDB($t['id']);
                            $ticket->deleteFromDB();
                            $ticket = new self;
                        }
                        $data['text'] = 'Все незаконченные заявки удалены!'.PHP_EOL;
                        Request::sendMessage($data);
                    }
                    // создание новой заявки
                    $data = $ticket->create($data, $user);
                    break;
                default:
                    $hasData = false;
            }
        } else {
            switch($params['cmd']) {
                // навигация по списку заявок
                case 'next_tickets':
                case 'prev_tickets':
                    $data['text'] = 'Список заявок:';
                    $data = $ticket->getTickets($data, $user, $params['offset']);
                    break;
                // редактирование заявки
                case 'edit_ticket':
                    $data['text'] = 'Выберите поле для редактирования заявки ID '.$params['ticket'].':';
                    $data = self::getTicketEditButtons($data, $user, $params['ticket']);
                    //print_r($data);
                    break;
                // редактирование списка инициаторов заявки
                case 'edit_requesters':
                    $data['text'] = 'Инициаторы заявки ID '.$params['ticket'].'. Удалите существующего или добавьте нового';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::REQUESTER);
                    break;
                // удаление инициатора из заявки
                case 'del_requester':
                    if(self::deleteActor(\CommonITILActor::REQUESTER, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'Инициатор успешно удалён!'.PHP_EOL;
                    } else {
                        $data['text'] = 'Удаляемый инициатор не найден!'.PHP_EOL;
                    }
                    $data['text'] .= 'Инициаторы заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::REQUESTER);
                    break;
                // раздел добавления инициатора в заявку
                case 'add_requester':
                    $user->setState($queryData);
                    $data['text'] = 'Выберите инициатора для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::REQUESTER, 0, $text);
                    break;
                // добавление инициатора
                case 'set_requester':
                    if(self::addActor(\CommonITILActor::REQUESTER, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'В заявку ID '.$ticketId.' добавлен инициатор'.PHP_EOL;
                    } else {
                        $data['text'] = 'Ошибка при добавлении инициатора в заявку ID '.$ticketId.PHP_EOL;
                    }
                    $data['text'] .= 'Инициаторы заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::REQUESTER);
                    break;
                // навигация по списку пользователей при добавлении инициатора
                case 'prev_requesters':
                case 'next_requesters':
                    $data['text'] = 'Выберите инициатора для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::REQUESTER, $params['offset']);
                    break;
                // редактирование списка назначенных специалистов заявки
                case 'edit_assigns':
                    $data['text'] = 'Специалисты заявки ID '.$params['ticket'].'. Удалите существующего или добавьте нового';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::ASSIGN);
                    break;
                // раздел добавления специалиста в заявку
                case 'add_assign':
                    $user->setState($queryData);
                    $data['text'] = 'Выберите специалиста для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::ASSIGN, 0, $text);
                    break;
                // удаление специалиста из заявки
                case 'del_assign':
                    if(self::deleteActor(\CommonITILActor::ASSIGN, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'Специалист успешно удалён!'.PHP_EOL;
                    } else {
                        $data['text'] = 'Удаляемый специалист не найден!'.PHP_EOL;
                    }
                    $data['text'] .= 'Специалисты заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::ASSIGN);
                    break;
                // добавление специалиста
                case 'set_assign':
                    if(self::addActor(\CommonITILActor::ASSIGN, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'В заявку ID '.$ticketId.' добавлен специалист'.PHP_EOL;
                    } else {
                        $data['text'] = 'Ошибка при добавлении специалиста в заявку ID '.$ticketId.PHP_EOL;
                    }
                    $data['text'] .= 'Специалисты заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::ASSIGN);
                    break;
                // навигация по списку пользователей при добавлении специалиста
                case 'prev_assigns':
                case 'next_assigns':
                    $data['text'] = 'Выберите специалиста для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::ASSIGN, $params['offset']);
                    break;
                // редактирование списка наблюдателей заявки
                case 'edit_observers':
                    $data['text'] = 'Наблюдатели заявки ID '.$params['ticket'].'. Удалите существующего или добавьте нового';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::OBSERVER);
                    break;
                // раздел добавления наблюдателя в заявку
                case 'add_observer':
                    $user->setState($queryData);
                    $data['text'] = 'Выберите наблюдателя для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::OBSERVER, 0, $text);
                    break;
                // удаление наблюдателя из заявки
                case 'del_observer':
                    if(self::deleteActor(\CommonITILActor::OBSERVER, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'Наблюдатель успешно удалён!'.PHP_EOL;
                    } else {
                        $data['text'] = 'Удаляемый наблюдатель не найден!'.PHP_EOL;
                    }
                    $data['text'] .= 'Наблюдатели заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::OBSERVER);
                    break;
                // добавление специалиста
                case 'set_observer':
                    if(self::addActor(\CommonITILActor::OBSERVER, $params['ticket'], $params['actors_id'])) {
                        $data['text'] = 'В заявку ID '.$ticketId.' добавлен наблюдатель'.PHP_EOL;
                    } else {
                        $data['text'] = 'Ошибка при добавлении наблюдателя в заявку ID '.$ticketId.PHP_EOL;
                    }
                    $data['text'] .= 'Наблюдатели заявки ID '.$params['ticket'].':';
                    $data = self::getActorsEditButtons($data, $user, $params['ticket'], \CommonITILActor::OBSERVER);
                    break;
                // навигация по списку пользователей при добавлении специалиста
                case 'prev_observers':
                case 'next_observers':
                    $data['text'] = 'Выберите наблюдателя для заявки ID '.$params['ticket'].':';
                    $data = self::getUsersList($data, $user, $params['ticket'], \CommonITILActor::OBSERVER, $params['offset']);
                    break;
                // редакирование заголовка
                case 'edit_title':
                    $user->setState($queryData);
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    
                    $data['text'] = 'Заголовок заявки ID '.$params['ticket'].': '.$ticketGLPI->fields['name'].PHP_EOL;
                    $data['text'] .= 'Введите новый заголовок';
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Назад', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]
                    ]);
                    if($text) {
                        $input['name'] = $text;
                        if(self::updateTicket($params['ticket'], $input)) {
                            $data['text'] = 'Заголовок заявки ID '.$params['ticket'].' изменен на: '.$text.PHP_EOL;
                            $data['text'] .= 'Для изменения введите повторно или вернитесь к редактированию заявки';
                        }
                    }
                    break;
                // редакирование описания
                case 'edit_content':
                    $user->setState($queryData);
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    
                    $data['text'] = 'Описание заявки ID '.$params['ticket'].': '.$ticketGLPI->fields['content'].PHP_EOL;
                    $data['text'] .= 'Введите новое описание';
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Назад', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]
                    ]);
                    if($text) {
                        $input['name'] = $text;
                        if(self::updateTicket($params['ticket'], $input)) {
                            $data['text'] = 'Описание заявки ID '.$params['ticket'].' изменено на: '.$text.PHP_EOL;
                            $data['text'] .= 'Для изменения введите повторно или вернитесь к редактированию заявки';
                        }
                    }
                    break;
                // редакирование типа
                case 'edit_type':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $data['text'] = 'Тип заявки ID '.$params['ticket'].': '.$ticket->getRequestTypeName($ticketGLPI->fields['requesttypes_id']).PHP_EOL;
                    $data['text'] .= 'Выберите новый тип:';

                    $requestType = new \RequestType;
                    $requestTypes = $requestType->find(['is_active' => 1, 'is_ticketheader' => 1]);
                    $buttons = [];
                    foreach($requestTypes as $key => $value) {
                        $buttons[] = new InlineKeyboardButton([
                            'text'          => $value['name'],
                            'callback_data' => 'cmd=update_type&user='.$user->fields['users_id'].'&requesttype='.$value['id'].'&ticket='.$params['ticket'],
                        ]);
                    }
                    $buttons[] = new InlineKeyboardButton([
                        'text'          => 'Назад',
                        'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket'],
                    ]);

                    $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);
                    break;
                // установка нового типа
                case 'update_type':
                    if($params['requesttype']) {
                        $input['requesttypes_id'] = $params['requesttype'];
                        if(self::updateTicket($params['ticket'], $input)) {
                            $data['text'] = 'Тип заявки ID '.$params['ticket'].' изменен на: '.$ticket->getRequestTypeName($params['requesttype']).PHP_EOL;
                            $data['text'] .= 'Для изменения выберите повторно или вернитесь к редактированию заявки';
                        }
                    }

                    $requestType = new \RequestType;
                    $requestTypes = $requestType->find(['is_active' => 1, 'is_ticketheader' => 1]);
                    $buttons = [];
                    foreach($requestTypes as $key => $value) {
                        $buttons[] = new InlineKeyboardButton([
                            'text'          => $value['name'],
                            'callback_data' => 'cmd=update_type&user='.$user->fields['users_id'].'&requesttype='.$value['id'].'&ticket='.$params['ticket'],
                        ]);
                    }
                    $buttons[] = new InlineKeyboardButton([
                        'text'          => 'Назад',
                        'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket'],
                    ]);
                    $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);
                    break;
                // редакирование категории
                case 'edit_category':
                    $searchText = '';
                    $user->setState($queryData);
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $data['text'] = 'Категория заявки ID '.$params['ticket'].': '.$ticket->getCategoryName($ticketGLPI).PHP_EOL;
                    if($text) {
                        $searchText = $text;
                    } else {
                        $data['text'] .= 'Выберите новую категорию или введите текст для поиска:';
                    }
                    $data = self::getCategoriesForUpdate($data, $user, $params['ticket'], 0, $searchText);
                    break;
                // изменение на выбранную категорию
                case 'update_category':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $ticketGLPI->fields['itilcategories_id'] = $params['itilcategories_id'];
                    $input['itilcategories_id'] = $params['itilcategories_id'];
                    if(self::updateTicket($params['ticket'], $input)) {
                        $data['text'] = 'Категория заявки ID '.$params['ticket'].' изменена на: '.$ticket->getCategoryName($ticketGLPI).PHP_EOL;
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Выбрать другую категорию', 'callback_data' => 'cmd=edit_category&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                            [['text' => 'К редатированию заявки', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                    }
                    break;
                // навигация по списку категорий
                case 'prev_cats_upd':
                case 'next_cats_upd':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $data['text'] = 'Категория заявки ID '.$params['ticket'].': '.$ticket->getCategoryName($ticketGLPI).PHP_EOL;
                    $data['text'] .= 'Выберите новую категорию:';
                    $data = $ticket->getCategoriesForUpdate($data, $user, $params['ticket'], $params['offset']);
                    break;
                // редактирование приоритета
                case 'edit_priority':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $data['text'] = 'Приоритет заявки ID '.$params['ticket'].': '.self::PRIORITY[$ticketGLPI->fields['priority']].PHP_EOL;
                    $data['text'] .= 'Выберите новый приоритет:';
                    $buttons = [];
                    foreach(self::PRIORITY as $key => $value) {
                        $buttons[] = new InlineKeyboardButton([
                            'text'          => $value,
                            'callback_data' => 'cmd=upd_priority&user='.$user->fields['users_id'].'&ticket='.$params['ticket'].'&priority='.$key
                        ]);
                    }
                    $buttons[] = new InlineKeyboardButton([
                        'text'          => 'Назад',
                        'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']
                    ]);

                    $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);
                    break;
                // обновление приоритета
                case 'upd_priority':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $ticketGLPI->fields['priority'] = $params['priority'];
                    $input['priority'] = $params['priority'];
                    if(self::updateTicket($params['ticket'], $input)) {
                        $data['text'] = 'Приоритет заявки ID '.$params['ticket'].' изменен на: '.self::PRIORITY[$ticketGLPI->fields['priority']].PHP_EOL;
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Выбрать другой приоритет', 'callback_data' => 'cmd=edit_priority&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                            [['text' => 'К редатированию заявки', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                    }
                    break;
                // редактирование статуса
                case 'edit_status':
                    $ticketGLPI = new \Ticket;
                    $statuses = $ticketGLPI->getAllStatusArray();
                    $ticketGLPI->getFromDB($params['ticket']);
                    $data['text'] = 'Статус заявки ID '.$params['ticket'].': '.$statuses[$ticketGLPI->fields['status']].PHP_EOL;
                    $data['text'] .= 'Выберите новый статус:';
                    $ticket = new \Ticket;
                    $buttons = [];
                    foreach($statuses as $key => $value) {
                        $buttons[] = new InlineKeyboardButton([
                            'text'          => $value,
                            'callback_data' => 'cmd=upd_status&user='.$user->fields['users_id'].'&ticket='.$params['ticket'].'&status='.$key
                        ]);
                    }
                    $buttons[] = new InlineKeyboardButton([
                        'text'          => 'Назад',
                        'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']
                    ]);

                    $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);
                    break;
                // обновление статуса
                case 'upd_status':
                    $ticketGLPI = new \Ticket;
                    $statuses = $ticketGLPI->getAllStatusArray();
                    $ticketGLPI->getFromDB($params['ticket']);
                    $ticketGLPI->fields['status'] = $params['status'];
                    $input['status'] = $params['status'];
                    if(self::updateTicket($params['ticket'], $input)) {
                        $data['text'] = 'Статус заявки ID '.$params['ticket'].' изменен на: '.$statuses[$ticketGLPI->fields['status']].PHP_EOL;
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Выбрать другой статус', 'callback_data' => 'cmd=edit_status&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                            [['text' => 'К редатированию заявки', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                    }
                    break;
                // редактирование документов
                case 'edit_docs':
                    $data['text'] = 'Выберите действие для документов заявки ID '.$params['ticket'].': ';
                    $data['reply_markup'] = new InlineKeyboard(
                        [['text' => 'Список документов', 'callback_data' => 'cmd=list_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                        [['text' => 'Добавить новый', 'callback_data' => 'cmd=add_doc&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                        [['text' => 'К редатированию заявки', 'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                    );
                    break;
                // добавление документа
                case 'add_doc':
                    $user->setState($queryData);
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    if($docID = self::addFile($message, $params['ticket'], $ticketGLPI->fields['entities_id'])) {
                        $doc = new \Document;
                        $doc->getFromDB($docID);
                        $data['text'] = 'Файл '.$doc->fields['filename'].' добавлен в заявку ID '.$params['ticket'];
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Добавить новый', 'callback_data' => 'cmd=add_doc&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]],
                            [['text' => 'Назад', 'callback_data' => 'cmd=edit_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                        $user->setState('');
                    } else {
                        $data['text'] = 'Отправьте документ, аудио, видео, изображение, голосовое сообщение или вернитесь назад в меню документов';
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Назад', 'callback_data' => 'cmd=edit_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                    }
                    break;
                // список документов заявки
                case 'list_docs':
                    $user->setState($queryData);
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    $docItem = new \Document_Item;
                    $docItems = $docItem->find(['items_id' => $params['ticket'], 'Itemtype' => 'Ticket']);
                    if($docItems) {
                        $data['text'] = 'Список документов заяки ID '.$params['ticket'].':';
                        $buttons = [];
                        $doc = new \Document;
                        foreach($docItems as $docItem) {
                            $doc->getFromDB($docItem['documents_id']);
                            $buttons[] = new InlineKeyboardButton([
                                'text'          => $doc->fields['filename'],
                                'callback_data' => 'cmd=edit_doc&user='.$user->fields['users_id'].'&ticket='.$params['ticket'].'&doc='.$doc->fields['id']
                            ]);
                        }
                        $buttons[] = new InlineKeyboardButton([
                            'text'          => 'Назад',
                            'callback_data' => 'cmd=edit_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']
                        ]);
        
                        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);
                    } else {
                        $data['text'] = 'В заявке ID '.$params['ticket'].' не найдено ни одного документа';
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Назад', 'callback_data' => 'cmd=edit_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                        );
                    }
                    break;
                // редактирование существующего документа
                case 'edit_doc':
                    $doc = new \Document;
                    $doc->getFromDB($params['doc']);
                    $file = GLPI_DOC_DIR.'/'.$doc->fields['filepath'];
                    $tempFile = GLPI_TMP_DIR.'/'.$doc->fields['filename'];
                    if(file_exists($file)) {
                        if (!copy($file, $tempFile)) {
                            // TODO: если файл не скопировался во временную папку
                        }
                        $mime = mime_content_type($tempFile);
                        if(str_contains($mime, 'image')) {
                            $result = Request::sendPhoto([
                                'chat_id'   => $chatId,
                                'photo'     => $tempFile
                            ]);
                        } elseif(str_contains($mime, 'audio')) {
                            $result = Request::sendAudio([
                                'chat_id'   => $chatId,
                                'document'  => $tempFile
                            ]);
                        } elseif(str_contains($mime, 'video')) {
                            $result = Request::sendVideo([
                                'chat_id'   => $chatId,
                                'document'  => $tempFile
                            ]);
                        } else {
                            $result = Request::sendDocument([
                                'chat_id'   => $chatId,
                                'document'  => $tempFile
                            ]);
                        }
                        $data['text'] = 'Вы можете удалить документ или вернуться к списку документов';
                    } else {
                        $data['text'] = 'Ошибка: файл не найден!';
                    }
                    $data['reply_markup'] = new InlineKeyboard(
                        [['text' => 'Удалить', 'callback_data' => 'cmd=del_doc&user='.$user->fields['users_id'].'&ticket='.$params['ticket'].'&doc='.$doc->fields['id']]],
                        [['text' => 'Назад', 'callback_data' => 'cmd=list_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                    );
                    break;
                // редактирование существующего документа
                case 'del_doc':
                    $docItem = new \Document_Item;
                    $doc = current($docItem->find(['documents_id' => $params['doc'], 'items_id' => $params['ticket'], 'Itemtype' => 'Ticket']));
                    $docItem->getFromDB($doc['id']);
                    $docItem->deleteFromDB();
                    $data['text'] = 'Документ успешно удалён!';
                    $data['reply_markup'] = new InlineKeyboard(
                        [['text' => 'К списку документов', 'callback_data' => 'cmd=list_docs&user='.$user->fields['users_id'].'&ticket='.$params['ticket']]]
                    );
                    break;
                // информация по заявке
                case 'show_ticket':
                    $ticketGLPI = new \Ticket;
                    $ticketGLPI->getFromDB($params['ticket']);
                    self::addItem($ticketGLPI, false, $user->fields['users_id']);
                    $hasData = false;
                    break;
                // вывод списка заявок
                case 'list_tickets':
                    $data['text'] = 'Список заявок:';
                    $data = self::getTickets($data, $user);
                    break;    
                default:
                    $hasData = false;
            }
        }

        $result = false;
        //print_r('str ------ '.strlen('cmd=prev_requesters&user=1234&offset=500&ticket=99999999').' -----');
        //print_r('data ------ '.$hasData.' -----');
        if($hasData) $result = Request::sendMessage($data);
        //print_r('result ------ '.$result.' -----');
        return $result;
    }

    public static function addCallback() {
        CallbackqueryCommand::addCallbackHandler(function($query) {
            $chatId = $query->getMessage()->getChat()->getId();
            $user = new User();
            $user->getFromDB($chatId);
            $user->setState('');
            self::handleQuery($query);
        });
    }

    public function create($data, User $user, $cmd = '') {
        if($nextInput = $this->getNextInput($user->fields['users_id'])) {
            $fields = Field::getFields();
            $tickets = $this->find(['users_id' => $user->fields['users_id']]);
            // если незаконченных заявок более одной
            if(count($tickets) > 1) {
                $data['text'] = 'Много не законченных заявок! Обратитесь к администратору!';
                return $data;
            }

            // если есть одна незаконченная заявка
            if($tickets) {
                $this->getFromDB(current($tickets)['id']);
                if($cmd && $nextInput !== true) {
                    //$field = $ticket->getNextInput($user->fields['users_id']);
                    $data['text'] = $nextInput['request_translation'];
                    unset($data['reply_markup']);
                    if(!$nextInput['is_mandatory']) {
                        $data['reply_markup'] = new InlineKeyboard([
                            [
                                'text' => 'Пропустить', 
                                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field='.$nextInput['id']
                            ]
                        ]);
                    }
                    if($tempData = $this->getInputInfo($nextInput, $data, $user)) {
                        $data = $tempData;
                    }
                    return $data;
                } else {
                    if($nextInput === true) {
                        $data['text'] = 'Все поля заявки заполнены!'.PHP_EOL;
                    } else {
                        $data['text'] = 'У вас есть незаконченная заявка:'.PHP_EOL;
                    }
                    $data['text'] .= '------------------'.PHP_EOL;
                    foreach($fields as $id => $mandatory) {
                        if(!is_null($this->fields[$this::FIELDS[$id]]) && $this->fields[$this::FIELDS[$id]] != '-1') {
                            $data['text'] .= $this::FIELD_TRANSLATIONS[$id].': '.$this->getFieldInfo($id).PHP_EOL;
                        }
                    }
                }
                if($nextInput === true) { // если все поля в заявке заполнены
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Завершить создание', 'callback_data' => 'cmd=finish_ticket&user='.$user->fields['users_id']],
                        ['text' => 'Создать новую', 'callback_data' => 'cmd=add_ticket&user='.$user->fields['users_id']]
                    ]);
                } else { // если не все поля заполнены
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Продолжить создание', 'callback_data' => 'cmd=continue_ticket&user='.$user->fields['users_id']],
                        ['text' => 'Создать новую', 'callback_data' => 'cmd=add_ticket&user='.$user->fields['users_id']]
                    ]);
                }
            }
        } else {
            // приглашение ввести данные по первому полю новой заявки
            $data['text']= 'Для создания заявки следуйте дальнейшим указаниям';
            $result = Request::sendMessage($data);
            $this->fields['users_id'] = $user->fields['users_id'];
            $this->addToDB();
            $field = $this->getNextInput($user->fields['users_id']);
            $data['text']= $field['request_translation'];
            unset($data['reply_markup']);
            if(!$field['is_mandatory']) {
                $data['reply_markup'] = new InlineKeyboard([
                    [
                        'text' => 'Пропустить', 
                        'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field='.$field['id']
                    ]
                ]);
            }
            if($tempData = $this->getInputInfo($field, $data, $user)) {
                $data = $tempData;
            }
            
            //print_r($this->getNextInput($user->fields['users_id']));
            //print_r($data);
        }

        return $data;
    }

    public function deleteForce() {
        global $DB;
        $DB->delete(
            $this->getTable(), [
               'id' => $this->fields['id']
            ]
        );
    }

    public function getFieldInfo($id) {
        switch($id) {
            case 3: // приоритет
                return self::PRIORITY[$this->fields['priority']];
                break;
            case 10: // срочность
                return self::URGENCY[$this->fields['urgency']];
                break;
            case 11: // влияние
                return self::IMPACT[$this->fields['impact']];
                break;
            case 142: // название прикрепленного файла
                return $this->getFileName();
                break;
            case 7: // название категории
                return $this->getCategoryName($this, true);
                break;
            case 12: // название статуса
                return $this->getStatusName();
                break;
            case 9: // название типа запросов
                return $this->getRequestTypeName();
                break;
            case 14: // название типа заявки
                return self::TYPES[$this->fields['type']];
                break;
            default:
                return $this->fields[$this::FIELDS[$id]];
        }
        return false;
    }

    // формирование кнопок по срочности, влиянию и приоритету
    public function getButtons($field, $array, $data, User $user, $isMandatory = 0) {
        $buttons = [];
        foreach($array as $key => $value) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $value,
                'callback_data' => 'cmd=set_'.$field.'&user='.$user->fields['users_id'].'&'.$field.'='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field='.array_search($field, self::FIELDS),
            ]);
        }

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    /*public function getUrgency($id) {
        return 
    }*/

    public function getCategories($data, User $user, $offset = 0, $searchText = '', $isMandatory = 0) {
        global $DB;

        $categories = [];
        $buttons = [];

        // запрос общего количества доступных категорий
        $req = $DB->request([
            'COUNT' => 'cnt',
            'FROM'      => 'glpi_itilcategories',
            'WHERE'     => [
                'entities_id'           => 0,
                'is_helpdeskvisible'    => 1,
                'is_incident'           => 1,
                'is_request'            => 1,
                'is_problem'            => 1,
                'is_change'             => 1,
                'name'                  => ['LIKE' , '%'.$searchText.'%']
            ]
        ]);
        foreach($req as $id => $row) {
            $count = $row['cnt'];
        }
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_itilcategories.id AS id',
                'glpi_itilcategories.entities_id AS entities_id',
                'glpi_itilcategories.name AS name',
                'glpi_itilcategories.completename AS completename'
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_itilcategories',
            'WHERE'     => [
                'entities_id'           => 0,
                'is_helpdeskvisible'    => 1,
                'is_incident'           => 1,
                'is_request'            => 1,
                'is_problem'            => 1,
                'is_change'             => 1,
                'name'                  => ['LIKE' , '%'.$searchText.'%']
            ],
            'ORDERBY'   => 'id',
            'START'     => $offset,
            'LIMIT'     => 10
        ]);
        foreach($iterator as $id => $row) {
            $categories[$id] = $row;
            $buttons[] = new InlineKeyboardButton([
                'text'          => $row['name'],
                'callback_data' => 'cmd=set_category&user='.$user->fields['users_id'].'&itilcategories_id='.$id,
            ]);
        }
        if($offset < $count && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Следующие 10 категорий из '.$count,
                'callback_data' => 'cmd=next_categories&user='.$user->fields['users_id'].'&offset='.($offset + 10).'&is_mandatory='.$isMandatory,
            ]);
        }
        if($offset && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Предыдущие 10 категорий из '.$count,
                'callback_data' => 'cmd=prev_categories&user='.$user->fields['users_id'].'&offset='.($offset - 10).'&is_mandatory='.$isMandatory,
            ]);
        }
        if($searchText && $count) {
            $data['text'] = 'По запросу "'.$searchText.'" найдены следующие категории';
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Весь список',
                'callback_data' => 'cmd=reset_categories&user='.$user->fields['users_id'].'&is_mandatory='.$isMandatory,
            ]);
        }
        if($searchText && $count == 0) {
            $data['text'] = 'По запросу "'.$searchText.'" не найдено ни одной категории!'.PHP_EOL;
            $data['text'] .= 'Выберите категорию из общего списка или измените поисковый запрос';
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field=7',
            ]);
        }

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    public static function addFile($message, $itemId = 0, $entityId = 0) {
        if (in_array($message->getType(), ['audio', 'document', 'photo', 'video', 'voice'])) {
            // добавление документа в заявку
            if($message->getType() == 'document') {
                $document = $message->getDocument();
                $fileId = $document->getFileId();
                $fileName = $document->getFileName();
                $docID = self::downloadFile($fileId, $fileName, $itemId, $entityId);
                return $docID;
            }
            
            // добавление фото в заявку
            if($message->getType() == 'photo') {
                $photoArray = $message->getPhoto();
                $fileId = end($photoArray)->getFileId();
                return self::downloadFile($fileId, '', $itemId, $entityId);
            }
            
            // добавление видео в заявку
            if($message->getType() == 'video') {
                $video = $message->getVideo();
                $fileId = $video->getFileId();
                $fileName = $video->getFileName();
                return self::downloadFile($fileId, $fileName, $itemId, $entityId);
            }

            // добавление аудио в заявку
            if($message->getType() == 'audio') {
                $audio = $message->getAudio();
                $fileId = $audio->getFileId();
                $fileName = $audio->getFileName();
                return self::downloadFile($fileId, $fileName, $itemId, $entityId);
            }

            // добавление голосового сообщения в заявку
            if($message->getType() == 'voice') {
                $voice = $message->getVoice();
                $fileId = $voice->getFileId();
                $fileName = $voice->getFileName();
                return self::downloadFile($fileId, $fileName, $itemId, $entityId);
            }
        }
    }

    static function downloadFile($fileId, $fileName = '', $itemId = 0, $entityId = 0) {
        $ServerResponse = Request::getFile(['file_id' => $fileId]);
        if ($ServerResponse->isOk()) {
            $file = $ServerResponse->getResult();
            if(!Request::downloadFile($file)) {
                return false;
            }
            $filePath = $file->getFilePath();
            $tag = \Rule::getUuid();
            if($fileName == '') $fileName = mb_substr($filePath, strpos($filePath, '/') + 1);
            $prefix = uniqid("", true);
            rename(GLPI_TMP_DIR.'/'.$filePath, GLPI_TMP_DIR.'/'.$prefix.$fileName);

            // search and add user photo as document
            $input['_filename']         = [$prefix.$fileName];
            $input['_tag_filename']     = [$tag];
            $input['_prefix_filename']  = [$prefix];
            $input['entities_id']       = $entityId;
            if($itemId) {
                $input['itemtype'] = 'Ticket';
                $input['items_id'] = $itemId;
            }
            
            $doc = new \Document();
            $docID = $doc->add($input);
            return $docID;
            
        }
        return false;
    }

    // target необходим, даже пустой
    static function addTargets($item) {
        if(!isset($item->target)) $item->target = [];
    }

    // получение названия файла
    public function getFileName() {
        $document = new \Document;
        if($document->getFromDB($this->fields['documents_id'])) {
            return $document->getName();
        }
        return false;
    }

    // получение названия категории
    public static function getCategoryName($ticket, $full = false) {
        $category = new \ITILCategory;
        if($category->getFromDB($ticket->fields['itilcategories_id'])) {
            if($full) {
                return $category->getRawCompleteName();
            }
            return $category->getName();
        }
        return false;
    }

    // получение списка статусов заявки
    public function getStatuses($data, $user, $isMandatory = 0) {
        $ticket = new \Ticket;
        $statuses = $ticket->getAllStatusArray();
        $buttons = [];
        foreach($statuses as $key => $value) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $value,
                'callback_data' => 'cmd=set_status&user='.$user->fields['users_id'].'&status='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field=12',
            ]);
        }
        
        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    // получение вариантов ввода для поля
    public function getInputInfo($field, $data, $user, $searchText = '') {
        switch($field['id']) {
            case 3:
                return $this->getButtons('priority', self::PRIORITY, $data, $user, $field['is_mandatory']);
                break;
            case 10:
                return $this->getButtons('urgency', self::URGENCY, $data, $user, $field['is_mandatory']);
                break;
            case 11:
                return $this->getButtons('impact', self::IMPACT, $data, $user, $field['is_mandatory']);
                break;
            case 7:
                return $this->getCategories($data, $user, 0, $searchText, $field['is_mandatory']);
                break;
            case 12:
                return $this->getStatuses($data, $user, $field['is_mandatory']);
                break;
            case 9:
                return $this->getRequestTypes($data, $user, $field['is_mandatory']);
                break;
            case 14:
                return $this->getTypes($data, $user, $field['is_mandatory']);
                break;
        }
        return false;
    }

    // получение названия статуса
    public function getStatusName() {
        $ticket = new \Ticket;
        $statuses = $ticket->getAllStatusArray();
        return $statuses[$this->fields['status']];
    }

    // получение списка типа запросов
    public function getRequestTypes($data, $user, $isMandatory = 0) {
        $requestType = new \RequestType;
        $requestTypes = $requestType->find(['is_active' => 1, 'is_ticketheader' => 1]);
        $buttons = [];
        foreach($requestTypes as $key => $value) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $value['name'],
                'callback_data' => 'cmd=set_requesttype&user='.$user->fields['users_id'].'&requesttype='.$value['id'],
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field=9',
            ]);
        }

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    // получение названия типа запроса
    public function getRequestTypeName($reqtypeId = 0) {
        $requestType = new \RequestType;
        if($reqtypeId) $this->fields['requesttypes_id'] = $reqtypeId;
        if($requestType->getFromDB($this->fields['requesttypes_id'])) {
            return $requestType->getName();
        }
        return false;
    }

    // получение списка типов заявки
    public function getTypes($data, $user, $isMandatory = 0) {       
        $buttons = [];
        foreach(self::TYPES as $key => $value) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $value,
                'callback_data' => 'cmd=set_type&user='.$user->fields['users_id'].'&type='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'cmd=skip_field&user='.$user->fields['users_id'].'&field=14',
            ]);
        }

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    static function getTickets($data, User $user, $offset = 0, $searchText = '', $isMandatory = 0) {
        global $DB;

        $tickets = [];
        $buttons = [];
        $userGroups = [];
        $groups = \Group_User::getUserGroups($user->fields['users_id']);
        foreach($groups as $group) {
            array_push($userGroups, $group['id']);
        }

        // запрос общего количества доступных заявок
        $req = $DB->request([
            'SELECT' => 'glpi_tickets.id',
            'COUNT' => 'cnt',
            'DISTINCT'  => true,
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_tickets_users' => [
                    'FKEY' => [
                        'glpi_tickets'          => 'id',
                        'glpi_tickets_users'    => 'tickets_id'
                    ]
                ],
                'glpi_groups_tickets' => [
                    'FKEY' => [
                        'glpi_tickets'          => 'id',
                        'glpi_groups_tickets'    => 'tickets_id'
                    ]
                ]
            ],
            'WHERE'     => [
                'glpi_tickets.entities_id'      => 0,
                'glpi_tickets.is_deleted'       => 0,
                'glpi_tickets.status'           => ['<', 5],
                'OR' => [
                    'glpi_tickets_users.users_id'   => $user->fields['users_id'],
                    'glpi_groups_tickets.groups_id' => $userGroups
                ]
            ]
        ]);
        foreach($req as $id => $row) {
            $count = $row['cnt'];
        }
        $iterator = $DB->request([
            'SELECT' => [
                'glpi_tickets.id AS id',
                'glpi_tickets.name AS name',
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_tickets',
            'LEFT JOIN' => [
                'glpi_tickets_users' => [
                    'FKEY' => [
                        'glpi_tickets'          => 'id',
                        'glpi_tickets_users'    => 'tickets_id'
                    ]
                ],
                'glpi_groups_tickets' => [
                    'FKEY' => [
                        'glpi_tickets'          => 'id',
                        'glpi_groups_tickets'    => 'tickets_id'
                    ]
                ],
            ],
            'WHERE'     => [
                'glpi_tickets.entities_id'      => 0,
                'glpi_tickets.is_deleted'       => 0,
                'glpi_tickets.status'           => ['<', 5],
                'OR' => [
                    'glpi_tickets_users.users_id'   => $user->fields['users_id'],
                    'glpi_groups_tickets.groups_id' => $userGroups
                ]
            ],
            'ORDERBY'   => 'glpi_tickets.date_mod DESC',
            'START'     => $offset,
            'LIMIT'     => 10
        ]);
        foreach($iterator as $id => $row) {
            $categories[$id] = $row;
            $buttons[] = new InlineKeyboardButton([
                'text'          => $row['id'].': '.$row['name'],
                'callback_data' => 'cmd=show_ticket&user='.$user->fields['users_id'].'&ticket='.$id,
            ]);
        }
        if($offset < $count && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Следующие 10 заявок из '.$count,
                'callback_data' => 'cmd=next_tickets&user='.$user->fields['users_id'].'&offset='.($offset + 10).'&is_mandatory='.$isMandatory,
            ]);
        }
        if($offset && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Предыдущие 10 заявок из '.$count,
                'callback_data' => 'cmd=prev_tickets&user='.$user->fields['users_id'].'&offset='.($offset - 10).'&is_mandatory='.$isMandatory,
            ]);
        }

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    /**
     * Post add item 
     *
     * @param $item            
     *
     * @return bool
    **/
    static function addItem(\Ticket $ticket, $isNew = true, $userId = 0) {
        if(file_exists(__DIR__.'/../mode') && file_get_contents(__DIR__.'/../mode') == 1) {
            echo '<pre>';
            $config = Config::getConfig();
            $conditions = [
                'glpi_users.is_deleted' => 0,
                'glpi_users.id' => ['>', 0],
                'glpi_users.is_active' => 1
            ];

            // ID заявки
            if($isNew) {
                $text = 'Создана заявка с ID: '.$ticket->fields['id'].PHP_EOL;
            } else {
                $text = 'Информация по заявке ID: '.$ticket->fields['id'].PHP_EOL;
            }
            // организация по заявке
            $entity = new \Entity;
            $entity->getFromDB($ticket->fields['entities_id']);
            $text .= 'Организация: '.$entity->fields['name'].PHP_EOL;
            // инициаторы заявки (телефон и email)
            $requesters = [];
            $reqTitle = 'Инициатор: ';
            $class = new $ticket->userlinkclass();
            $ticketsUser = $class->getActors($ticket->fields['id']);
            $reqs = $ticketsUser[\CommonITILActor::REQUESTER];
            $u = new \User();
            foreach($reqs as $requester) {
                $user = current($u->find(['id' => $requester['users_id']]));
                if(empty($phone)) $phone = isset($user['phone']) ? $user['phone'] : ' ';
                if(empty($email)) {
                    $emails = \UserEmail::getAllForUser($requester['users_id']);
                    $email = count($emails) ? implode(', ', $emails) : ' ';
                }
                array_push($requesters, $user['realname'].' '.$user['firstname']);
            }
            if(count($reqs) > 1) $reqTitle = 'Инициаторы: ';
            $text .= $reqTitle.implode(', ', $requesters).PHP_EOL;
            $text .= 'Телефон: '.$phone.PHP_EOL;
            $text .= 'Email: '.$email.PHP_EOL;

            // заголовок заявки
            $text .= 'Заголовок: '.$ticket->fields['name'].PHP_EOL;
            // описание заявки
            $text .= 'Описание: '.\Glpi\RichText\RichText::getTextFromHtml($ticket->fields['content']).PHP_EOL;
            // тип заявки
            $text .= 'Тип: '.self::TYPES[$ticket->fields['type']].PHP_EOL;
            // категория заявки
            $text .= 'Категория: '.self::getCategoryName($ticket).PHP_EOL;
            // срочность заявки
            $text .= 'Срочность: '.self::PRIORITY[$ticket->fields['priority']].PHP_EOL;
            // статус заявки
            $text .= 'Статус: '.$ticket->getAllStatusArray()[$ticket->fields['status']].PHP_EOL;
            // документы заявки
            if(isset($ticket->input['_filename']) && isset($ticket->input['_prefix_filename'])) {
                $files = [];
                foreach($ticket->input['_filename'] as $index => $filename) {
                    $prefix = $ticket->input['_prefix_filename'][$index];
                    array_push($files, str_replace($prefix, "", $filename));
                }
                $text .= 'Документы: '.implode(', ', $files).PHP_EOL;
            }
            // TODO: дедлайн заявки 
            /*if(isset($ticket->fields['time_to_resolve']) && $ticket->fields['time_to_resolve'] > 0) {
                $text .= 'Дедлайн: '.sprintf('%02d ч. %02d м.', ($ticket->fields['time_to_resolve'] / 3600), ($ticket->fields['time_to_resolve'] / 60 % 60)).PHP_EOL;
            }*/
            
            $superTargets = [];
            $users = \Group_User::getGroupUsers($config['supervisor_groups_id'], $conditions);
            if(!$isNew) {
                $tempUsers = [];
                foreach($users as $user) {
                    if($user['id'] == $userId) {
                        $tempUsers = [$user];
                        break;
                    }
                }
                $users = $tempUsers;
            }
            
            if(count($users) == 0) {
                if($userTG = current((new User)->find(['users_id' => $userId]))) {
                    $data['chat_id'] = $userTG['id'];            
                    $data['text'] = $text;
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Вернуться к списку заявок', 'callback_data' => 'cmd=list_tickets&user='.$userId]
                    ]);
                    $telegram = new Telegram;
                    $telegram->send($data);
                }
            }
            
            foreach($users as $user) {
                $superTargets[$user['id']] = $user['id'];
                if($userTG = current((new User)->find(['users_id' => $user['id']]))) {
                    $chatId = $userTG['id'];
                    $data['chat_id'] = $chatId;
                    $data['text'] = $text;
                    if($isNew) {
                        $data['reply_markup'] = new InlineKeyboard([
                            ['text' => 'Редактировать', 'cmd=edit_ticket&user='.$user['id'].'&ticket='.$ticket->fields['id']]
                        ]);
                    } else {
                        $data['reply_markup'] = new InlineKeyboard(
                            [['text' => 'Редактировать', 'callback_data' => 'cmd=edit_ticket&user='.$user['id'].'&ticket='.$ticket->fields['id']]],
                            [['text' => 'Вернуться к списку заявок', 'callback_data' => 'cmd=list_tickets&user='.$user['id']]]
                        );
                    }
                    $telegram = new Telegram;
                    $telegram->send($data);
                }
            }
            if($isNew) {
                $class = new $ticket->userlinkclass();
                $ticketsUser = $class->getActors($ticket->fields['id']);
                $u = new \User();
                $targets = [];
                if(isset($ticketsUser[\CommonITILActor::REQUESTER])) {
                    $reqs = $ticketsUser[\CommonITILActor::REQUESTER];
                    foreach($reqs as $requester) {
                        $targets[$requester['users_id']] = $requester['users_id'];
                    }
                }
                if(isset($ticketsUser[\CommonITILActor::OBSERVER])) {
                    $obs = $ticketsUser[\CommonITILActor::OBSERVER];
                    foreach($obs as $observer) {
                        $targets[$observer['users_id']] = $observer['users_id'];
                    }
                }
                if(isset($ticketsUser[\CommonITILActor::ASSIGN])) {
                    $specs = $ticketsUser[\CommonITILActor::ASSIGN];
                    foreach($specs as $spec) {
                        $targets[$spec['users_id']] = $spec['users_id'];
                    }
                }
                $class = new $ticket->grouplinkclass();
                $ticketsGroup = $class->getActors($ticket->fields['id']);
                if(isset($ticketsGroup[\CommonITILActor::REQUESTER])) {
                    $reqsGroup = $ticketsGroup[\CommonITILActor::REQUESTER];           
                    foreach($reqsGroup as $reqGrp) {
                        $users = \Group_User::getGroupUsers($reqGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                if(isset($ticketsGroup[\CommonITILActor::OBSERVER])) {
                    $obsGroup = $ticketsGroup[\CommonITILActor::OBSERVER];
                    foreach($obsGroup as $obsGrp) {
                        $users = \Group_User::getGroupUsers($obsGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                if(isset($ticketsGroup[\CommonITILActor::ASSIGN])) {
                    $specsGroup = $ticketsGroup[\CommonITILActor::ASSIGN];
                    foreach($specsGroup as $specGrp) {
                        $users = \Group_User::getGroupUsers($specGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                $targets = array_diff($targets, $superTargets);
                foreach($targets as $id => $user) {
                    if($userTG = current((new User)->find(['users_id' => $id]))) {
                        $chatId = $userTG['id'];
                        $data = [
                            'chat_id'      => $chatId,
                            'text'         => $text,
                        ];
                        $telegram = new Telegram;
                        $telegram->send($data);
                    }
                }
            }
        }
    }

    static function getTicketEditButtons($data, User $user, $id) {

        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Инициаторы',
            'callback_data' => 'cmd=edit_requesters&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Наблюдатели',
            'callback_data' => 'cmd=edit_observers&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Исполнители',
            'callback_data' => 'cmd=edit_assigns&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Заголовок',
            'callback_data' => 'cmd=edit_title&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Описание',
            'callback_data' => 'cmd=edit_content&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Тип',
            'callback_data' => 'cmd=edit_type&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Категория',
            'callback_data' => 'cmd=edit_category&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Приоритет',
            'callback_data' => 'cmd=edit_priority&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Статус',
            'callback_data' => 'cmd=edit_status&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Документы',
            'callback_data' => 'cmd=edit_docs&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        /*$buttons[] = new InlineKeyboardButton([
            'text'          => 'Дедлайн (в разработке)',
            'callback_data' => 'cmd=edit_deadline&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);*/
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Вернуться к списку заявок',
            'callback_data' => 'cmd=list_tickets&user='.$user->fields['users_id'],
        ]);

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    static function getActorsEditButtons($data, User $user, $id, $actor) {

        $ticket = new \Ticket;
        $class = new $ticket->userlinkclass();
        $ticketsUser = $class->getActors($id);
        $actors = isset($ticketsUser[$actor]) ? $ticketsUser[$actor] : [];
        $u = new \User();
        foreach($actors as $a) {
            $userActor = current($u->find(['id' => $a['users_id']]));
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Удалить: '.$userActor['realname'].' '.$userActor['firstname'],
                'callback_data' => 'cmd=del_'.self::ACTORS[$actor].'&user='.$user->fields['users_id'].'&ticket='.$id.'&actors_id='.$userActor['id'],
            ]);
        }
        /*if(count($actors) == 0) {
            $data['text'] = 'По заявке '.$id.' нет инициатора. Выберите действие:';
        }*/

        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Добавить',
            'callback_data' => 'cmd=add_'.self::ACTORS[$actor].'&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Назад',
            'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$id,
        ]);
        
        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    /**
     * Post add item 
     *
     * @param $item            
     *
     * @return bool
    **/
    static function updateItem($ticket) {
        //echo '<pre>';
        //print_r($ticket);
        //die();
    }

    public static function deleteActor($actor, $ticketId, $userId) {
        $ticket = new \Ticket;
        $tu = new \Ticket_User;
        $ticketUser = current($tu->find(['tickets_id' => $ticketId, 'users_id' => $userId, 'type' => $actor]));
        if(($ticketUser)) {
            $input = [
                'id' => $ticketId,
                '_glpi_csrf_token' => \Session::getNewCSRFToken(),
                '_users_id_'.self::ACTORS[$actor].'_deleted' => [
                    0 => [
                        'id' => $ticketUser['id'],
                        'items_id' => $userId,
                        'itemtype' => 'User'
                    ]
                ]
            ];
            
        } else {
            return false;
        }
        //if($ticket->update($input)) print_r($input);
        $ticket->update($input);
        return true;
    }

    static function getUsersList($data, User $user, $ticketsId, $actor = 0, $offset = 0, $searchText = '') {
        global $DB;

        $categories = [];
        $buttons = [];
        $ticket = new \Ticket;
        $ticket->getFromDB($ticketsId); // TODO: если заявка не найдена

        // запрос общего количества доступных пользователей
        $sub_query = new \QuerySubQuery([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
               'tickets_id' => $ticketsId,
               'type' => $actor
            ]
         ]);
        $req = $DB->request([
            'COUNT' => 'cnt',
            'FROM'      => 'glpi_users',
            'WHERE'     => [
                'entities_id'   => $ticket->fields['entities_id'],
                'is_active'     => 1,
                'is_deleted'    => 0,
                'realname'      => ['LIKE' , '%'.$searchText.'%'],
                'NOT'           => ['id' => $sub_query]
            ]
        ]);
        foreach($req as $id => $row) {
            $count = $row['cnt'];
        }
        
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_users.id AS id',
                'glpi_users.entities_id AS entities_id',
                'glpi_users.realname AS realname',
                'glpi_users.firstname AS firstname'
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_users',
            'WHERE'     => [
                'entities_id'   => $ticket->fields['entities_id'],
                'is_active'     => 1,
                'is_deleted'    => 0,
                'realname'      => ['LIKE' , '%'.$searchText.'%'],
                'NOT'           => ['id' => $sub_query]
            ],
            'ORDERBY'   => 'id',
            'START'     => $offset,
            'LIMIT'     => 10
        ]);
        foreach($iterator as $id => $row) {
            $categories[$id] = $row;
            $buttons[] = new InlineKeyboardButton([
                'text'          => $row['realname'].' '.$row['firstname'],
                'callback_data' => 'cmd=set_'.self::ACTORS[$actor].'&user='.$user->fields['users_id'].'&actors_id='.$id.'&ticket='.$ticketsId,
            ]);
        }
        if($offset < $count && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Следующие 10 пользователей из '.$count,
                'callback_data' => 'cmd=next_'.self::ACTORS[$actor].'s&user='.$user->fields['users_id'].'&offset='.($offset + 10).'&ticket='.$ticketsId,
            ]);
        }
        if($offset && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Предыдущие 10 пользователей из '.$count,
                'callback_data' => 'cmd=prev_'.self::ACTORS[$actor].'s&user='.$user->fields['users_id'].'&offset='.($offset - 10).'&ticket='.$ticketsId,
            ]);
        }
        if($searchText && $count) {
            $data['text'] = 'По запросу "'.$searchText.'" найдены следующие пользователи';
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Весь список',
                'callback_data' => 'cmd=add_'.self::ACTORS[$actor].'&user='.$user->fields['users_id'].'&ticket='.$ticketsId,
            ]);
        }
        if($searchText && $count == 0) {
            $data['text'] = 'По запросу "'.$searchText.'" не найдено ни одного пользователя!'.PHP_EOL;
            $data['text'] .= 'Выберите пользователя из общего списка или измените поисковый запрос';
        }
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Назад',
            'callback_data' => 'cmd=edit_'.self::ACTORS[$actor].'s&user='.$user->fields['users_id'].'&ticket='.$ticketsId,
        ]);

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    static function addActor($actor, $ticketId, $userId) {
        $ticket = new \Ticket;
        //$tu = new \Ticket_User;
        //$ticketUser = current($tu->find(['tickets_id' => $ticketId, 'users_id' => $userId, 'type' => $actor]));
        $input = [
            'id' => $ticketId,
            '_glpi_csrf_token' => \Session::getNewCSRFToken(),
            '_users_id_'.self::ACTORS[$actor].'' => [
                '_actors_'.$userId => $userId
            ],
            '_users_id_'.self::ACTORS[$actor].'_notif' => [
                'use_notification' => [
                    '_actors_'.$userId => 1
                ]
            ]
        ];
        $ticket->update($input); // TODO: обработка ошибки при добавлении
        return true;
    }

    static function updateTicket($ticketId, $input) {
        $ticket = new \Ticket;
        $input['id'] = $ticketId;
        $input['_glpi_csrf_token'] = \Session::getNewCSRFToken();
        $ticket->update($input); // TODO: обработка ошибки при добавлении
        return true;
    }

    public static function getCategoriesForUpdate($data, User $user, $ticketId, $offset = 0, $searchText = '') {
        global $DB;

        $categories = [];
        $buttons = [];

        // запрос общего количества доступных категорий
        $req = $DB->request([
            'COUNT' => 'cnt',
            'FROM'      => 'glpi_itilcategories',
            'WHERE'     => [
                'entities_id'           => 0,
                'is_helpdeskvisible'    => 1,
                'is_incident'           => 1,
                'is_request'            => 1,
                'is_problem'            => 1,
                'is_change'             => 1,
                'name'                  => ['LIKE' , '%'.$searchText.'%']
            ]
        ]);
        foreach($req as $id => $row) {
            $count = $row['cnt'];
        }
        $iterator = $DB->request([
            'SELECT'    => [
                'glpi_itilcategories.id AS id',
                'glpi_itilcategories.entities_id AS entities_id',
                'glpi_itilcategories.name AS name',
                'glpi_itilcategories.completename AS completename'
            ],
            'DISTINCT'  => true,
            'FROM'      => 'glpi_itilcategories',
            'WHERE'     => [
                'entities_id'           => 0,
                'is_helpdeskvisible'    => 1,
                'is_incident'           => 1,
                'is_request'            => 1,
                'is_problem'            => 1,
                'is_change'             => 1,
                'name'                  => ['LIKE' , '%'.$searchText.'%']
            ],
            'ORDERBY'   => 'id',
            'START'     => $offset,
            'LIMIT'     => 10
        ]);
        foreach($iterator as $id => $row) {
            $categories[$id] = $row;
            $buttons[] = new InlineKeyboardButton([
                'text'          => $row['name'],
                'callback_data' => 'cmd=update_category&user='.$user->fields['users_id'].'&itilcategories_id='.$id.'&ticket='.$ticketId,
            ]);
        }
        if($offset < $count && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Следующие 10 категорий из '.$count,
                'callback_data' => 'cmd=next_cats_upd&user='.$user->fields['users_id'].'&offset='.($offset + 10).'&ticket='.$ticketId,
            ]);
        }
        if($offset && $count > 10) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Предыдущие 10 категорий из '.$count,
                'callback_data' => 'cmd=prev_cats_upd&user='.$user->fields['users_id'].'&offset='.($offset - 10).'&ticket='.$ticketId,
            ]);
        }
        if($searchText && $count) {
            $data['text'] .= 'По запросу "'.$searchText.'" найдены следующие категории';
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Весь список',
                'callback_data' => 'cmd=edit_category&user='.$user->fields['users_id'].'&ticket='.$ticketId,
            ]);
        }
        if($searchText && $count == 0) {
            $data['text'] .= 'По запросу "'.$searchText.'" не найдено ни одной категории!'.PHP_EOL;
            $data['text'] .= 'Выберите категорию из общего списка или измените поисковый запрос';
        }
        $buttons[] = new InlineKeyboardButton([
            'text'          => 'Назад',
            'callback_data' => 'cmd=edit_ticket&user='.$user->fields['users_id'].'&ticket='.$ticketId,
        ]);

        $data['reply_markup'] = Telegram::verticalInlineKeyboard($buttons);

        return $data;
    }

    /**
     * Post add item 
     *
     * @param $item            
     *
     * @return bool
    **/
    static function addFollowup(\ITILFollowup $followup) {
        if(file_exists(__DIR__.'/../mode') && file_get_contents(__DIR__.'/../mode') == 1) {
            if($followup->fields['itemtype'] == 'Ticket' && $followup->fields['is_private'] === 0) {
                //echo '<pre>';
                $config = Config::getConfig();
                $conditions = [
                    'glpi_users.is_deleted' => 0,
                    'glpi_users.id' => ['>', 0],
                    'glpi_users.is_active' => 1
                ];
                $ticket = new \Ticket;
                $ticket->getFromDB($followup->fields['items_id']);
                // ID заявки
                $text = 'Новый комментарий по заявке ID: '.$ticket->fields['id'].PHP_EOL;
                // заголовок заявки
                $text .= 'Заголовок заявки: '.$ticket->fields['name'].PHP_EOL;
                // дата комментария
                $text .= 'Время: '.$followup->fields['date_creation'].PHP_EOL;
                // автор комментария
                $user = new \User();
                $user->getFromDB($followup->fields['users_id']);
                $text .= 'Автор: '.$user->fields['realname'].' '.$user->fields['firstname'].PHP_EOL;
                // текст комментария
                $text .= 'Комментарий: '.\Glpi\RichText\RichText::getTextFromHtml($followup->fields['content']);
                
                $superTargets = [];
                $class = new $ticket->userlinkclass();
                $ticketsUser = $class->getActors($ticket->fields['id']);
                $u = new \User();
                $targets = [];
                if(isset($ticketsUser[\CommonITILActor::REQUESTER])) {
                    $reqs = $ticketsUser[\CommonITILActor::REQUESTER];
                    foreach($reqs as $requester) {
                        $targets[$requester['users_id']] = $requester['users_id'];
                    }
                }
                if(isset($ticketsUser[\CommonITILActor::OBSERVER])) {
                    $obs = $ticketsUser[\CommonITILActor::OBSERVER];
                    foreach($obs as $observer) {
                        $targets[$observer['users_id']] = $observer['users_id'];
                    }
                }
                if(isset($ticketsUser[\CommonITILActor::ASSIGN])) {
                    $specs = $ticketsUser[\CommonITILActor::ASSIGN];
                    foreach($specs as $spec) {
                        $targets[$spec['users_id']] = $spec['users_id'];
                    }
                }
                $class = new $ticket->grouplinkclass();
                $ticketsGroup = $class->getActors($ticket->fields['id']);
                if(isset($ticketsGroup[\CommonITILActor::REQUESTER])) {
                    $reqsGroup = $ticketsGroup[\CommonITILActor::REQUESTER];           
                    foreach($reqsGroup as $reqGrp) {
                        $users = \Group_User::getGroupUsers($reqGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                if(isset($ticketsGroup[\CommonITILActor::OBSERVER])) {
                    $obsGroup = $ticketsGroup[\CommonITILActor::OBSERVER];
                    foreach($obsGroup as $obsGrp) {
                        $users = \Group_User::getGroupUsers($obsGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                if(isset($ticketsGroup[\CommonITILActor::ASSIGN])) {
                    $specsGroup = $ticketsGroup[\CommonITILActor::ASSIGN];
                    foreach($specsGroup as $specGrp) {
                        $users = \Group_User::getGroupUsers($specGrp['groups_id'], $conditions);
                        foreach($users as $user) {
                            $targets[$user['id']] = $user['id'];
                        }
                    }
                }
                $targets = array_diff($targets, $superTargets);
                foreach($targets as $id => $user) {
                    if($userTG = current((new User)->find(['users_id' => $id]))) {
                        $chatId = $userTG['id'];
                        $data = [
                            'chat_id'      => $chatId,
                            'text'         => $text,
                            'reply_markup' => new InlineKeyboard([
                                [
                                    'text' => 'Просмотреть заявку', 
                                    'callback_data' => 'cmd=show_ticket&user='.$user['id'].'&ticket='.$ticket->fields['id']
                                ]
                            ])
                        ];
                        $telegram = new Telegram;
                        $telegram->send($data);
                    }
                }
            }
        }
    }
}