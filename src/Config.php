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

class Config extends \CommonDBTM
{

    /**
     * Get typename
     *
     * @param $nb            integer
     *
     * @return string
    **/
    static function getTypeName($nb = 0) {
        return __('TT Config', 'telegramtickets');
    }

    /**
     * Get headername
     *
     * @return string
    **/
    public function getHeaderName(): string {
        return __('Настройки', 'telegramtickets');
    }

    static function getMenuContent() {

        $menu          = [];
        $menu['title'] = self::getMenuName();
        $menu['icon']   = 'far fa-envelope';
        $menu['page']   = '/plugins/telegramtickets/front/config.php';

        return $menu;
    }

    /**
    * Define tabs to display on form page
    *
    * @param array $options
    * @return array containing the tabs name
    */
   function defineTabs($options = []) {

    $ong = [];
    $this->addStandardTab('GlpiPlugin\Telegramtickets\Config', $ong, $options);
    //$this->addStandardTab('GlpiPlugin\Telegramtickets\Validation', $ong, $options);
    //$this->addStandardTab('Log', $ong, $options);

    return $ong;
 }

    /**
     * Get the tab name used for item
     *
     * @param object $item the item object
     * @param integer $withtemplate 1 if is a template form
     * @return string|array name of the tab
     */
    function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0) {
        switch ($item->getType()) {
            case __CLASS__:
                $ong = [];

                $ong[1] = __('Общие');
                if(file_get_contents(__DIR__.'/../mode') == 2) {
                    $ong[2] = __('Согласования');
                }

                /*if ($item->getField('is_itemgroup')) {
                    $ong[1] = __('Used items');
                }
                if ($item->getField('is_assign')) {
                    $ong[2] = __('Managed items');
                }
                if (
                    $item->getField('is_usergroup')
                    && Group::canUpdate()
                    && Session::haveRight("user", User::UPDATEAUTHENT)
                    && AuthLDAP::useAuthLdap()
                ) {
                    $ong[3] = __('LDAP directory link');
                }*/
                return $ong;
        }
        /*if ($item->getType() == __CLASS__) {
            return [
                __('Общие')
            ];
        }*/
        return '';
    }

    /**
    * Display the content of the tab
    *
    * @param object $item
    * @param integer $tabnum number of the tab to display
    * @param integer $withtemplate 1 if is a template form
    * @return boolean
    */
    static function displayTabContentForItem($item, $tabnum = 0, $withtemplate = 0) {
        $opt = current($item->find([], [], 1));
        $item->getFromDB($opt['id']);
        switch ($tabnum) {
            case 1:
                $item->showCommonForm();
                return true;
            case 2:
                $item->showValidationConfigForm();
                return true;
        }
        return false;
    }

    /**
     * Show tabs
     *
     * @param array $options parameters to add to URLs and ajax
     *     - withtemplate is a template view ?
     *
     * @return void
     **/
    public function showNavigationHeader($options = []) {
    }

    /**
     * Display common form
     *
     * @param integer   $ID
     * @param array     $options
     * 
     * @return true
     */
    function showCommonForm($ID = 1, $options = []) {
        global $CFG_GLPI;

        $config = self::getConfig();

        $options['formtitle']       = __('Telegram Tickets', 'telegramtickets');
        $options['no_header']       = true;
        $options['colspan']         = 4;
        $options['withtemplate']    = 0;
        $options['target']          = $CFG_GLPI["root_doc"].'/plugins/telegramtickets/front/config.php';
        $this->showFormHeader($options);

        echo '<tr class="tab_bg_1"><th colspan="5">'.__('Настройки Telegram', 'telegramtickets') . '</th></tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td>';
        echo __('Telegram Bot Token');
        echo '</td>';
        echo '<td class="center">';
        echo \Html::input(
            'bot_token',
            [
                'value' => isset($config['bot_token']) ? $config['bot_token'] : '',
                'id'    => 'bot_token'
            ]
        );
        echo '</td>';
        echo '<td>';
        echo __('Telegram Bot Username');
        echo '</td>';
        echo '<td class="center">';
        echo \Html::input(
            'bot_username',
            [
                'value' => isset($config['bot_username']) ? $config['bot_username'] : '',
                'id'    => 'bot_token'
            ]
        );
        echo '</td>';
        if(isset($config['bot_token']) && isset($config['bot_username'])) {
            echo '<td>';
            echo '<a href="https://t.me/'.$config['bot_username'].'" style="font-weight: bold">https://t.me/'.$config['bot_username'].'</a>';
            echo '</td>';
        }
        echo '</tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td>';
        echo __('Пароль доступа к боту');
        echo '</td>';
        echo '<td class="center">';
        echo \Html::input(
            'bot_password',
            [
                'value' => isset($config['bot_password']) ? $config['bot_password'] : '',
                'id'    => 'bot_password'
            ]
        );
        echo '</td>';
        echo '</tr>';

        echo '<tr class="tab_bg_1"><th colspan="5">'.__('Настройки уведомлений', 'telegramtickets') . '</th></tr>';
        echo "<tr class='tab_bg_2'><td class='center'>";
        echo __('Группа supervisor', 'telegramtickets')."</td><td>";
        \Group::dropdown(['name' => 'supervisor_groups_id', 'value' => isset($config['supervisor_groups_id']) ? $config['supervisor_groups_id'] : '']);
        echo "</td><td class='center'>".__('Группа tech', 'telegramtickets')."</td><td>";
        \Group::dropdown(['name' => 'tech_groups_id', 'value' => isset($config['tech_groups_id']) ? $config['tech_groups_id'] : '',]);
        echo "</td><td class='center'>";
        //echo "<input type='submit' name='add' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
        echo "</td></tr>";

        echo '</table>';

        $options['candel'] = false;
        $this->showFormButtons($options);

        if(file_get_contents(__DIR__.'/../mode') == 1) {

            $tt = new \TicketTemplate;
            $fields = $tt->getAllowedFieldsNames(true);
            $fields[4] = $fields[4].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Инициатор запроса
            $fields[71] = $fields[71].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Группа инициатора запроса
            $fields[5] = $fields[5].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Специалист 
            $fields[8] = $fields[8].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Группа специалистов
            $fields[6] = $fields[6].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Назначенный поставщик
            $fields[66] = $fields[66].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Наблюдатель
            $fields[65] = $fields[65].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Группа наблюдателей
            $fields[13] = $fields[13].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Связанные элементы
            $fields[-2] = $fields[-2].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Запрос на согласование
            $fields[175] = $fields[175].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Задачи 
            $fields[37] = $fields[37].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // SLA Время реакции
            $fields[30] = $fields[30].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // SLA Время до решения
            $fields[190] = $fields[190].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // OLA Внутреннее время реакции
            $fields[191] = $fields[37].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // OLA Внутреннее время для решения
            $fields[180] = $fields[180].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Внутреннее время для решения
            $fields[185] = $fields[185].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Внутреннее время реакции
            $fields[52] = $fields[52].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Согласование 
            $fields[193] = $fields[193].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Договор 
            $fields[155] = $fields[155].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Время реакции
            $fields[18] = $fields[18].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Время до решения
            $fields[83] = $fields[83].' (НЕ ПОДДЕРЖИВАЕТСЯ)'; // Местоположение
            $fieldsFiltered = $fields;
            $iterator = (new Field)->find();
            foreach ($iterator as $data) {
                unset($fieldsFiltered[$data['fields_id']]);
            }

            $rand = mt_rand();

            echo "<div class='firstbloc'>";
            echo "<form name='fields_form$rand' id='fields_form$rand' method='post' action=''>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='6'>" . __('Настройка необходимых полей при создании заявки') . "</tr>";

            echo "<tr class='tab_bg_2'><td class='center'>";
            echo __('Field')."</td><td>";
            \Dropdown::showFromArray('fields_id', $fieldsFiltered);
            echo "</td><td class='center'>".__('Обязательное')."</td><td>";
            \HTML::showCheckbox(['name' => 'is_mandatory']);
            echo "</td><td class='center'>";
            echo "<input type='submit' name='add_field' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
            echo "</td></tr>";

            echo "</table>";
            \Html::closeForm();
            echo "</div>";

            echo "<div class='spaced'>";
            echo "<form name='fields_table$rand' id='fields_table$rand' method='post' action=''>";
            if (count($iterator) > 0) {
                echo "<table class='tab_cadre_fixehov'>";
                $header = "<tr><th>".__('Field')."</th><th>".__('Обязательное')."</th><th></th></tr>";
                echo $header;
                foreach ($iterator as $data) {
                    echo "<tr class='tab_bg_1'>";
                    echo "<td>".$fields[$data['fields_id']]."</td>";
                    echo "<td>";
                    \HTML::showCheckbox([
                        'name'      => 'mandatory', 
                        'checked'   => $data['is_mandatory'], 
                        'id'        => $data['id']
                    ]);
                    echo "</td>";
                    echo '<td class="center"><a class="btn btn-sm btn-danger" href="?delete_field='.$data['id'].'"><span>Удалить</span></a></td>';
                    echo "</tr>";
                }
                echo $header;
                echo "</table>";
                echo '<script>
                    $(\'input[name="mandatory"]\').on("change", function(event) { 
                        $.ajax({
                            type: "POST",
                            url: "../../../plugins/telegramtickets/ajax/changemandatory.php",
                            data: {
                                id: $(this).attr("id")
                            },
                            datatype: "json"
                        }).done(function(response) {
                            if(response) {
                                //console.log(response);
                            } else {
                                alert("'.__('Ошибка!', 'telegramtickets').'");
                            }
                        });
                    } );
                </script>';
            } else {
                echo "<table class='tab_cadre_fixe'>";
                echo "<tr><th>" . __('No item found') . "</th></tr>";
                echo "</table>\n";
            }

            \Html::closeForm();
            echo "</div>";
        }

        return true;
    }

    /**
     * Display common form
     *
     * @param integer   $ID
     * @param array     $options
     * 
     * @return true
     */
    function showValidationConfigForm($ID = 1, $options = []) {
        global $CFG_GLPI;

        $config = self::getConfig();

        $rand = mt_rand();

        //$options['formtitle']       = __('Telegram Tickets', 'telegramtickets');
        //$options['no_header']       = true;
        //$options['colspan']         = 4;
        //$options['withtemplate']    = 0;
        $options['target']          = $CFG_GLPI["root_doc"].'/plugins/telegramtickets/front/config.php';
        $this->showFormHeader($options);

        echo '<tr class="tab_bg_1"><th>'.__('Настройка отправки согласований в Telegram Bot', 'telegramtickets') . '</th></tr>';
        /*echo '<tr class="tab_bg_1"><td>';
        $options['title'] = 'Согласования';
        $columns = [
            'name' => 'label',
            'name1' => 'label2',
        ];
        $rows = [
            'n' => [
                'label' => 'test',
                'columns' => ['name' => ['checked' => true]]
            ],
            'n1' => [
                'label' => 'test1',
                'columns' => []
            ]
        ];
        \Html::showCheckboxMatrix($columns, $rows, $options);
        $changeTemplate = new \ChangeTemplate;
        print_r($changeTemplate->getAllowedFieldsNames(true));
        echo '</td></tr>';
        echo '<tr class="tab_bg_1" aria-grabbed="false"><td draggable="true">TEST</td></tr>';
        echo '<tr class="tab_bg_1"><td>TEST</td></tr>';

        echo '</table>';
        $problemTemplate = new \ProblemTemplate;
        print_r($problemTemplate->getAllowedFieldsNames(true));

        $options['candel'] = false;
        $this->showFormButtons($options);*/
        echo '</table>';
        \Html::closeForm(true);
        echo '</div>';
        echo '</div>';

        // Выводимые поля при согласовании заявок
        $tt = new \TicketTemplate;
        $fields = $tt->getAllowedFieldsNames(true);
        $fieldsFiltered = $fields;
        $iterator = (new Validation_Field)->find(['itemtype' => 'Ticket']);
        foreach ($iterator as $data) {
            unset($fieldsFiltered[$data['fields_id']]);
        }

        $this->showFormHeader($options);
        echo "<tr class='tab_bg_1'><th colspan='3'>" . __('Выбор показываемых полей при согласовании заявки') . "</tr>";
        echo "<tr class='tab_bg_2'><td class='center'>";
        echo __('Field')."</td><td>";
        \Dropdown::showFromArray('fields_id', $fieldsFiltered);
        echo "</td><td class='center'>";
        echo '<input type="hidden" name="itemtype" value="Ticket"></input>';
        echo "<input type='submit' name='add_validation_field' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
        echo "</td></tr>";
        echo "</table>";
        \Html::closeForm(true);
        echo '</div>';
        echo '</div>';

        echo "<div class='spaced'>";
        if (count($iterator) > 0) {
            echo "<table class='tab_cadre_fixehov'>";
            $header = "<tr><th>".__('Field')."</th><th></th></tr>";
            echo $header;
            foreach ($iterator as $data) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>".$fields[$data['fields_id']]."</td>";
                echo '<td class="center"><a class="btn btn-sm btn-danger" href="?delete_validation_field='.$data['id'].'"><span>Удалить</span></a></td>';
                echo "</tr>";
            }
            echo $header;
            echo "</table>";
        } else {
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>" . __('No item found') . "</th></tr>";
            echo "</table>\n";
        }
        echo "</div>";

        // Выводимые поля при согласовании проблем
        /*$tt = new \ProblemTemplate;
        $fields = $tt->getAllowedFieldsNames(true);
        $fieldsFiltered = $fields;
        $iterator = (new Validation_Field)->find(['itemtype' => 'Problem']);
        foreach ($iterator as $data) {
            unset($fieldsFiltered[$data['fields_id']]);
        }

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='3'>" . __('Выбор показываемых полей при согласовании заявки') . "</tr>";

        echo "<tr class='tab_bg_2'><td class='center'>";
        echo __('Field')."</td><td>";
        \Dropdown::showFromArray('fields_id', $fieldsFiltered);
        echo "</td><td class='center'>";
        echo '<input type="hidden" name="itemtype" value="Problem"></input>';
        echo "<input type='submit' name='add_validation_field' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
        echo "</td></tr>";
        echo "</table>";
        \Html::closeForm();
        echo "</div>";
        echo "<div class='spaced'>";
        if (count($iterator) > 0) {
            echo "<table class='tab_cadre_fixehov'>";
            $header = "<tr><th>".__('Field')."</th><th></th></tr>";
            echo $header;
            foreach ($iterator as $data) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>".$fields[$data['fields_id']]."</td>";
                echo '<td class="center"><a class="btn btn-sm btn-danger" href="?delete_validation_field='.$data['id'].'"><span>Удалить</span></a></td>';
                echo "</tr>";
            }
            echo $header;
            echo "</table>";
        } else {
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>" . __('No item found') . "</th></tr>";
            echo "</table>\n";
        }*/

        // Выводимые поля при согласовании изменений
        $tt = new \ChangeTemplate;
        $fields = $tt->getAllowedFieldsNames(true);
        $fieldsFiltered = $fields;
        $iterator = (new Validation_Field)->find(['itemtype' => 'Change']);
        foreach ($iterator as $data) {
            unset($fieldsFiltered[$data['fields_id']]);
        }

        $this->showFormHeader($options);
        echo "<tr class='tab_bg_1'><th colspan='3'>" . __('Выбор показываемых полей при согласовании изменений') . "</tr>";
        echo "<tr class='tab_bg_2'><td class='center'>";
        echo __('Field')."</td><td>";
        \Dropdown::showFromArray('fields_id', $fieldsFiltered);
        echo "</td><td class='center'>";
        echo '<input type="hidden" name="itemtype" value="Change"></input>';
        echo "<input type='submit' name='add_validation_field' value=\"" . _sx('button', 'Add') . "\" class='btn btn-primary'>";
        echo "</td></tr>";
        echo "</table>";
        \Html::closeForm(true);
        echo '</div>';
        echo '</div>';
        echo "<div class='spaced'>";
        if (count($iterator) > 0) {
            echo "<table class='tab_cadre_fixehov'>";
            $header = "<tr><th>".__('Field')."</th><th></th></tr>";
            echo $header;
            foreach ($iterator as $data) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>".$fields[$data['fields_id']]."</td>";
                echo '<td class="center"><a class="btn btn-sm btn-danger" href="?delete_validation_field='.$data['id'].'"><span>Удалить</span></a></td>';
                echo "</tr>";
            }
            echo $header;
            echo "</table>";
        } else {
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>" . __('No item found') . "</th></tr>";
            echo "</table>\n";
        }
        echo "</div>";

        return true;
    }

    /**
     * Update config of plugin
     *
     * @param array     $options
     * 
     * @return bool
    **/
    static function updateConfig($options = []) {
        try {
            foreach($options as $option => $value) {
                $cfg = new self;
                $cfg->fields['option'] = $option;
                $cfg->fields['value'] = $value;
                if($config = current($cfg->find(['option' => $option], [], 1))) {
                    $cfg->fields['id'] = $config['id'];
                    $cfg->updateInDB(array_keys($cfg->fields));
                } else {
                    $cfg->addToDB();
                }
            }
        } catch (Exception $e) {
            $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * Get config of plugin
     *
     * @param array     $options
     * 
     * @return array|false
    **/
    static function getConfig($options = []) {
        $out = [];
        $cfg = new self;
        try {
            if($options){
                foreach($options as $option) {
                    $config = current($cfg->find(['option' => $option], [], 1));
                    $out[$option] = $config['value'];
                }
            } else {
                foreach($cfg->find() as $config) {
                    $out[$config['option']] = $config['value'];
                }
            }
        } catch (Exception $e) {
            $e->getMessage();
            return false;
        }
        return $out;
    }

    /**
     * Get option value
     *
     * @param string     $options
     * 
     * @return string|false
    **/
    static function getOption($option) {
        $cfg = new self;
        try {
            if($config = current($cfg->find(['option' => $option], [], 1))){
                return $config['value'];
            }
        } catch (Exception $e) {
            $e->getMessage();
            return false;
        }
        return false;
    }

    function can($ID, $right, ?array &$input = NULL) {
        if(\Session::haveRight('config', READ)) return true;
        return false;
    }
}
?>