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

use Longman\TelegramBot\Commands\UserCommand;
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
            'text'         => 'Здравствуйте! Для того, чтобы создать новую заявку нажмите кнопку "Создать заявку". Если кнопки нет, то нажмите /start',
            'reply_markup' => new Keyboard([
                'keyboard' => [
                    ['Создать заявку']
                ],
                'resize_keyboard' => true, 
                //'one_time_keyboard' => true, 
                'selective' => true
            ])
        ];

        // проверка авторизации пользователя
        $user = $user->checkAuth($chat_id);
        if(!$user) {
            $data['text'] = 'Для доступа к заявкам наберите пароль';
            unset($data['reply_markup']);
        } elseif(is_null($user['users_id'])) {
            $data['text'] = 'Введите вашу фамилию';
            unset($data['reply_markup']);
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
