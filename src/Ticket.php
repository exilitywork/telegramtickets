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
use Longman\TelegramBot\Entities\Document;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Telegram;

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
              *     [ [ "text" => "Включить звук", "callback_data" => "action=sound&value=1" ] ]
              *  ];
              **/    
            parse_str($query->getData(), $params);
            if(isset($params['users_id'])){
                $ticket = new self;
                $tickets = $ticket->find(['users_id' => $params['users_id']]);
                $user = new User;
                // TODO: обработка несуществующего user
                $user->getFromDB($chatId);
            }
            $data = [
                'chat_id' => $chatId,
                'parse_mode' => 'html'
            ];
                        
            // проверка количества найденных незаконченных заявок
            if(count($tickets) > 1) {
                $data['text'] = 'Найдено несколько незаконченных заявок! Обратитесь к администратору!';
            } elseif(count($tickets)) {
                // обработка команд
                $ticket->getFromDB(current($tickets)['id']);
                switch($params['action']) {
                    // установка поля "Срочность"
                    case 'set_urgency':
                        if(is_null($ticket->fields['urgency'])) {
                            $ticket->fields['urgency'] = $params['urgency'];
                            $ticket->updateInDB(array_keys($ticket->fields));
                        }
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // установка поля "Влияние"
                    case 'set_impact':
                        if(is_null($ticket->fields['impact'])) {
                            $ticket->fields['impact'] = $params['impact'];
                            $ticket->updateInDB(array_keys($ticket->fields));
                        }
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // установка поля "Приоритет"
                    case 'set_priority':
                        if(is_null($ticket->fields['priority'])) {
                            $ticket->fields['priority'] = $params['priority'];
                            $ticket->updateInDB(array_keys($ticket->fields));
                        }
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // установка поля "Категория"
                    case 'set_category':
                        if(is_null($ticket->fields['itilcategories_id'])) {
                            $ticket->fields['itilcategories_id'] = $params['itilcategories_id'];
                            $ticket->updateInDB(array_keys($ticket->fields));
                        }
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // навигация по списку категорий
                    case 'prev_categories':
                    case 'next_categories':
                        $data['text'] = 'Выберите категорию заявки';
                        $data = $ticket->getCategories($data, $user, $params['offset']);
                        break;
                    // поиск категории
                    case 'reset_categories':
                        $data['text'] = 'Выберите категорию заявки';
                        $data = $ticket->getCategories($data, $user);
                        break;
                    // установка поля "Статус"
                    case 'set_status':
                        $ticket->fields['status'] = $params['status'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // установка поля "Тип запроса"
                    case 'set_requesttype':
                        $ticket->fields['requesttypes_id'] = $params['requesttype'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // установка поля "Тип"
                    case 'set_type':
                        $ticket->fields['type'] = $params['type'];
                        $ticket->updateInDB(array_keys($ticket->fields));
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // пропуск необязательно поля
                    case 'skip_field':
                        $ticket->fields[self::FIELDS[$params['field']]] = -1; // если поле пропущено, то в него записывается "-1"
                        print_r($ticket);
                        $ticket->updateInDB(array_keys($ticket->fields));
                        $data = $ticket->create($data, $user, $params['action']);
                        break;
                    // завершение создания заявки
                    case 'finish_ticket':
                        // загрузка Email инициатора для уведомлений
                        $useNotification = 1;
                        $userEmail = current((new \UserEmail)->find(['users_id' => $params['users_id'], 'is_default' => 1]))['email'];
                        if(!$userEmail) $useNotification = 0;
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
                            //print_r($fields);

                            // создание заявки в GLPI
                            if($id = $ticketGLPI->add($fields)) { // ID созданной заявки в GLPI  
                                //print_r($ticketGLPI);
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
                                $data['text'] .= 'Для создания новой заявки нажмите кнопку "Создать заявку"'.PHP_EOL;
                                $data['reply_markup'] = new Keyboard([
                                    'keyboard' => [
                                        ['Создать заявку']
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
                        //$ticket = new self;
                        $data = $ticket->create($data, $user, $params['action']);
                        //$data['text'] = 'ТЕСТ!';
                        break;

                    // при нажатии кнопки "Создать новую"
                    case 'add_ticket':
                        // удаление незаконченных заявок
                        $ticket = new self;
                        $tickets = $ticket->find(['users_id' => $params['users_id']]);
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
                $hasData = false;
            }

            $result = false;
            
            if($hasData) $result = Request::sendMessage($data);
            
            return $result;
        });
    }

    public function create($data, User $user, $action = '') {
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
                if($action && $nextInput !== true) {
                    //$field = $ticket->getNextInput($user->fields['users_id']);
                    $data['text'] = $nextInput['request_translation'];
                    unset($data['reply_markup']);
                    if(!$nextInput['is_mandatory']) {
                        $data['reply_markup'] = new InlineKeyboard([
                            [
                                'text' => 'Пропустить', 
                                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field='.$nextInput['id']
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
                            //$data['text'] .= $this::FIELD_TRANSLATIONS[$id].': '.$this->fields[$this::FIELDS[$id]].PHP_EOL;
                        }
                    }
                }
                if($nextInput === true) { // если все поля в заявке заполнены
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Завершить создание', 'callback_data' => 'action=finish_ticket&users_id='.$user->fields['users_id']],
                        ['text' => 'Создать новую', 'callback_data' => 'action=add_ticket&users_id='.$user->fields['users_id']]
                    ]);
                } else { // если не все поля заполнены
                    $data['reply_markup'] = new InlineKeyboard([
                        ['text' => 'Продолжить создание', 'callback_data' => 'action=continue_ticket&users_id='.$user->fields['users_id']],
                        ['text' => 'Создать новую', 'callback_data' => 'action=add_ticket&users_id='.$user->fields['users_id']]
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
            //if($field['is_mandatory'])
            $data['text']= $field['request_translation'];
            unset($data['reply_markup']);
            if(!$field['is_mandatory']) {
                $data['reply_markup'] = new InlineKeyboard([
                    [
                        'text' => 'Пропустить', 
                        'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field='.$field['id']
                    ]
                ]);
            }
            if($tempData = $this->getInputInfo($field, $data, $user)) {
                $data = $tempData;
            }
            
            //print_r($this->getNextInput($user->fields['users_id']));
            //print_r('Ticket.php: 481 line');
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
                return $this->getCategoryName(true);
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
                'callback_data' => 'action=set_'.$field.'&users_id='.$user->fields['users_id'].'&'.$field.'='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field='.array_search($field, self::FIELDS),
            ]);
        }
        $inline_keyboard = new InlineKeyboard($buttons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }

        $inline_keyboard->__set('inline_keyboard', $keysV);
        
        $data['reply_markup'] = $inline_keyboard;

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
            'LIMIT'     => 5
        ]);
        foreach($iterator as $id => $row) {
            $categories[$id] = $row;
            $buttons[] = new InlineKeyboardButton([
                'text'          => $row['name'],
                'callback_data' => 'action=set_category&users_id='.$user->fields['users_id'].'&itilcategories_id='.$id,
            ]);
            //$row['number'] = $id + 1;
            //array_push($categories, $row);
        }
        if($offset < $count && $count > 5) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Следующие 5 категорий из '.$count,
                'callback_data' => 'action=next_categories&users_id='.$user->fields['users_id'].'&offset='.($offset + 5),
            ]);
        }
        if($offset && $count > 5) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Предыдущие 5 категорий из '.$count,
                'callback_data' => 'action=prev_categories&users_id='.$user->fields['users_id'].'&offset='.($offset - 5),
            ]);
        }
        if($searchText && $count) {
            $data['text'] = 'По запросу "'.$searchText.'" найдены следующие категории Ticket.php 549';
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Весь список',
                'callback_data' => 'action=reset_categories&users_id='.$user->fields['users_id'],
            ]);
        }
        if($searchText && $count == 0) {
            $data['text'] = 'По запросу "'.$searchText.'" не найдено ни одной категории!'.PHP_EOL;
            $data['text'] .= 'Выберите категорию из общего списка или измените поисковый запрос Ticket.php 549';
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field=7',
            ]);
        }
        $inline_keyboard = new InlineKeyboard($buttons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }

        $inline_keyboard->__set('inline_keyboard', $keysV);
        
        $data['reply_markup'] = $inline_keyboard;

        return $data;
    }

    public function addFile($message) {
        if (in_array($message->getType(), ['audio', 'document', 'photo', 'video', 'voice'])) {
            // добавление документа в заявку
            if($message->getType() == 'document') {
                $document = $message->getDocument();
                $fileId = $document->getFileId();
                $fileName = $document->getFileName();
                $docID = self::downloadFile($fileId, $fileName);
                return $docID;
            }
            
            // добавление фото в заявку
            if($message->getType() == 'photo') {
                $photoArray = $message->getPhoto();
                $fileId = end($photoArray)->getFileId();
                return self::downloadFile($fileId);
            }
            
            // добавление видео в заявку
            if($message->getType() == 'video') {
                $video = $message->getVideo();
                $fileId = $video->getFileId();
                $fileName = $video->getFileName();
                return self::downloadFile($fileId, $fileName);
            }

            // добавление аудио в заявку
            if($message->getType() == 'audio') {
                $audio = $message->getAudio();
                $fileId = $audio->getFileId();
                $fileName = $audio->getFileName();
                return self::downloadFile($fileId, $fileName);
            }

            // добавление голосового сообщения в заявку
            if($message->getType() == 'voice') {
                $voice = $message->getVoice();
                $fileId = $voice->getFileId();
                $fileName = $voice->getFileName();
                return self::downloadFile($fileId, $fileName);
            }
        }
    }

    static function downloadFile($fileId, $fileName = '') {
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
            $input['_filename']        = [$prefix.$fileName];
            $input['_tag_filename']    = [$tag];
            $input['_prefix_filename'] = [$prefix];
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
    public function getCategoryName($full = false) {
        $category = new \ITILCategory;
        if($category->getFromDB($this->fields['itilcategories_id'])) {
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
                'callback_data' => 'action=set_status&users_id='.$user->fields['users_id'].'&status='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field=12',
            ]);
        }
        $inline_keyboard = new InlineKeyboard($buttons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }

        $inline_keyboard->__set('inline_keyboard', $keysV);
        
        $data['reply_markup'] = $inline_keyboard;

        return $data;
    }

    // получение вариантов ввода для поля
    public function getInputInfo($field, $data, $user) {
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
                return $this->getCategories($data, $user, 0, '', $field['is_mandatory']);
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
                'callback_data' => 'action=set_requesttype&users_id='.$user->fields['users_id'].'&requesttype='.$value['id'],
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field=9',
            ]);
        }
        $inline_keyboard = new InlineKeyboard($buttons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }

        $inline_keyboard->__set('inline_keyboard', $keysV);
        
        $data['reply_markup'] = $inline_keyboard;

        return $data;
    }

    // получение названия типа запроса
    public function getRequestTypeName() {
        $requestType = new \RequestType;
        if($requestType->getFromDB($this->fields['requesttypes_id'])) {
            return $requestType->getName();
        }
        return false;
    }

    // получение списка типа заявки
    public function getTypes($data, $user, $isMandatory = 0) {       
        $buttons = [];
        foreach(self::TYPES as $key => $value) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => $value,
                'callback_data' => 'action=set_type&users_id='.$user->fields['users_id'].'&type='.$key,
            ]);
        }
        if(!$isMandatory) {
            $buttons[] = new InlineKeyboardButton([
                'text'          => 'Пропустить',
                'callback_data' => 'action=skip_field&users_id='.$user->fields['users_id'].'&field=14',
            ]);
        }
        $inline_keyboard = new InlineKeyboard($buttons);
        $keysV = [];
        $keysH = $inline_keyboard->__get('inline_keyboard')[0];
        foreach($keysH as $id => $key) {
            $keysV[$id][0] = $key;
        }

        $inline_keyboard->__set('inline_keyboard', $keysV);
        
        $data['reply_markup'] = $inline_keyboard;

        return $data;
    }

}