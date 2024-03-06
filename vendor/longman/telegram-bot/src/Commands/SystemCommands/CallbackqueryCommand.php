<?php

/**
 * This file is part of the TelegramBot package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;

/**
 * Callback query command
 */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var callable[]
     */
    protected static $callbacks = [];

    /**
     * @var string
     */
    protected $name = 'callbackquery1';

    /**
     * @var string
     */
    protected $description = 'Reply to callback query';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Command execute method
     *
     * @return ServerResponse
     */
    public function execute(): ServerResponse
    {
        $callback_query = $this->getCallbackQuery();
        $user_id        = $callback_query->getFrom()->getId();
        $query_id       = $callback_query->getId();
        $query_data     = $callback_query->getData();

        $answer         = null;

        // Call all registered callbacks.
        foreach (self::$callbacks as $callback) {
            $answer = $callback($callback_query);
        }
        $data = [];
        /*$data = [
            'chat_id'      => $user_id,
            'text'         => 'Здравствуйте! Для того, чтобы подать новую заявку нажмите кнопку "Создать заявку". Если кнопки нет, то нажмите /start'
        ];*/
        return ($answer instanceof ServerResponse) ? $answer : $callback_query->answer($data);
        return new ServerResponse();
    }

    /**
     * Add a new callback handler for callback queries.
     *
     * @param $callback
     */
    public static function addCallbackHandler($callback): void
    {
        self::$callbacks[] = $callback;
    }
}
