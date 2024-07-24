<?php

/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use GlpiPlugin\Telegramtickets\User;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;

/**
 * Start command
 */
class StartCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'start';

    /**
     * @var string
     */
    protected $description = 'Start command';

    /**
     * @var string
     */
    protected $usage = '/start';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * Command execute method
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $user = new \GlpiPlugin\Telegramtickets\User;
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();

        // стандартное приглашение
        $data = [
            'chat_id'      => $chat_id,
            'text'         => 'Здравствуйте! Для того, чтобы создать новую заявку, нажмите кнопку "Создать заявку", или просмотрите список заявок. Если кнопок нет, то нажмите /start',
            'reply_markup' => new Keyboard([
                'keyboard' => [
                    ['Создать заявку'],
                    ['Список заявок']
                ],
                'resize_keyboard' => true, 
                //'one_time_keyboard' => true, 
                'selective' => true
            ])
        ];

        // проверка авторизации пользователя
        //$user = $user->checkAuth($chat_id);
        //$user->getFromDB($chat_id);
        if(file_exists(__DIR__.'/../../../../../../mode') && file_get_contents(__DIR__.'/../../../../../../mode') == 1) {
            if(!$user->getFromDB($chat_id)) {
                $data['text'] = 'Для доступа к заявкам наберите пароль';
                unset($data['reply_markup']);
            } elseif(is_null($user->fields['users_id']) && $user->fields['is_authorized']) {
                $data['text'] = 'Введите вашу фамилию';
                unset($data['reply_markup']);
            }
        } else {
            $data['text'] = 'test';
            if(!$user->getFromDB($chat_id)) {echo 333;
                $data = User::getAuthTypesButtons($data);
                /*$inline_keyboard = new InlineKeyboard([
                    ['text' => 'Подтвердить', 'callback_data' => 'action=confirm_user&users_id='.$user->fields['users_id']],
                                                ['text' => 'Отменить', 'callback_data' => 'action=cancel_user']
                    ['text' => 'callback', 'callback_data' => 'identifier222'],
                ], [
                    ['text' => 'callback', 'callback_data' => 'identifier222'],
                ]);
                $data['text'] = 'Введите имя пользователя';
                //unset($data['reply_markup']);
                $data['reply_markup'] = $inline_keyboard;*/
            } elseif(is_null($user->fields['users_id'])) {
                $data['text'] = 'Введите имя пользователя';
                unset($data['reply_markup']);
            } elseif(empty($user->fields['is_authorized'])) {
                $data['text'] = 'Введите пароль';
                unset($data['reply_markup']);
            }
        }
        //$switch_element = mt_rand(0, 9) < 5 ? 'true' : 'false';
        /*$inline_keyboard = new InlineKeyboard([
            ['text' => 'inline', 'switch_inline_query' => $switch_element],
            ['text' => 'inline current chat', 'switch_inline_query_current_chat' => $switch_element],
        ], [
            ['text' => 'callback', 'callback_data' => 'identifier222'],
            ['text' => 'open url', 'url' => 'https://github.com/php-telegram-bot/core'],
        ]);*/
        //$data['reply_markup'] = new Keyboard(['keyboard' => [['Подать заявку']], 'resize_keyboard' => true, 'one_time_keyboard' => true, 'selective' => true]);
        return Request::sendMessage($data);
    }
}
