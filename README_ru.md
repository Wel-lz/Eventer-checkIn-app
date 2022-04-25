# [📘 English](https://github.com/Wel-lz/Eventer-checkIn-app/blob/main/README.md)

# Eventer checkin app

## _Мобильное приложение для WordPress плагина Eventer_
#### &emsp; [Разработано на Thunkable](https://thunkable.com)
#### &emsp; Автор плагина [Imithemes](https://eventer.imithemes.com/) 


## Функции

- Получить информацию о бронировании по номеру бронирования
- Получить информацию о каждом билете в бронировании
- Регистрация отдельных билетов
- Возможность самостоятельной кастомизации приложения (Thunkable)
- Английская/Русская локализация

## В будущих версиях
- Регистрация всего бронирования целиком
- Возможность работы с серверным API по незащищенному протоколу http (опционально)
- Возможность отменить Checkin для отдельного билета
- Разбиение строки с типом билета в бронировании на строки с отдельными билетами (по 1 шт.) для полностью отдельного Checkin'а

## Установка

#### Изменить файл `wp-content/plugins/eventer/front/eventer_rest_endpoints.php`

> Измененные строки заключены в region mod/mod_*

<details>
   <summary>Функция eventer_application_scan_events($request = null)</summary>

    function eventer_application_scan_events($request = null)
    {
        $parameters = $request->get_json_params();
        $event = (isset($parameters['event'])) ? $parameters['event'] : '';
        $date = (isset($parameters['date'])) ? $parameters['date'] : '';
        $code = (isset($parameters['code'])) ? $parameters['code'] : '';
        if ($code != '') {
            $codes = explode("-", $code);
            $code = $codes[0];
        }
        $message = '';
        if ($event == "") {
            $message = "Sorry, there are no events to show here.";
        }
        if (date_i18n('Y-m-d', strtotime($date)) < date_i18n('Y-m-d')) {
            $message = "please select date in future";
        }
        if ($code == "") {
            $message = "No barcode found";
        }
        $registrant = eventer_get_registrant_details("id", $code);
        $eventers = array('ID' => "", 'Title' => "", 'Date' => "", 'name' => "", 'email' => "", "status" => "", "amount" => "");
        //$message = "Sorry, no details found";
        if ($registrant) {
            $registrant_email = $registrant->email;
            $ticket_id = $registrant->id;
            $amount = $registrant->amount;
            $username = $registrant->username;
            $status = $registrant->status;
            $event_date = $registrant->eventer_date;
            $event_id = $registrant->eventer;
            $user = unserialize($registrant->user_system);
            $tickets = (isset($user['tickets'])) ? $user['tickets'] : '';
            $woo = "";
            if (!empty($tickets)) {
                foreach ($tickets as $ticket) {
                    $event_woo = $ticket['event'];
                    $date_woo = $ticket['date'];
    
                    if ($event_woo == $event && date_i18n("Y-m-d", strtotime($date)) == date_i18n("Y-m-d", $date_woo)) {
                        $woo = "1";
                        break;
                    }
                    $test = (isset($parameters['test'])) ? $parameters['test'] : '';
                    if ($test != "") {
                        $woo = "2";
                        break;
                    }
                }
            }
            if ($woo == "2") {
                $tickets_info = [];
                foreach ($tickets as $ticket) {
                    if (!in_array($ticket['event'], array_keys($tickets_info))) {
                        $tickets_info[$ticket['event']] = [];
                    }
                    //
                    //$get_key = get_option('eventer-android-app-api-key');
                    //
                    $tickets_info[$ticket['event']][] = [
                        'ticket_id' => $ticket['id'],
                        'event_name' => get_the_title($ticket['event']),
                        'ticket_type' => $ticket['type'],
                        'ticket_name' => $ticket['ticket'],
                        'ticket_date' => date('d-m-Y', $ticket['date']),
                        'quantity' => $ticket['quantity'],
                        'ckechin' => $ticket['checkin']
                    ];
                }
                $eventers = array(
                    'ID' => $ticket_id,
                    'name' => $username,
                    'email' => $registrant_email,
                    "status" => $status,
                    "amount" => $amount,
                    'tickets_data' => $tickets_info
                );
            } elseif ($woo == "1") {
                $eventers = array('ID' => $ticket_id, 'Title' => get_the_title($event), 'Date' => date_i18n("Y-m-d", strtotime($date)), 'name' => $username, 'email' => $registrant_email, "status" => $status, "amount" => $amount);
            } elseif ($event_date == date_i18n('Y-m-d', strtotime($date)) && $event_id == $event) {
                $eventers = array('ID' => $ticket_id, 'Title' => get_the_title($event), 'Date' => date_i18n("Y-m-d", strtotime($date)), 'name' => $username, 'email' => $registrant_email, "status" => $status, "amount" => $amount);
            } else {
                $eventers = array('ID' => "", 'Title' => "", 'Date' => "", 'name' => "", 'email' => "", "status" => "", "amount" => "");
                $message = "Sorry, ticket do not mach with the selected event";
            }
        } else {
            $eventers = array('ID' => "abcd", 'Title' => "", 'Date' => "", 'name' => "", 'email' => "", "status" => "", "amount" => "");
            $message = "Sorry, no details found";
        }
        $response = array("scan" => $eventers, "msg" => $message);
    
        return rest_ensure_response($response);
    }
</details>

<details>
  <summary>Функция eventer_application_checkin_events($request = null)</summary>

    function eventer_application_checkin_events($request = null)
    {
        $parameters = $request->get_json_params();
        $registrant = (isset($parameters['registrant'])) ? $parameters['registrant'] : '';
        $woocommerce_events = eventer_get_settings('eventer_enable_woocommerce_ticketing');
        $registrants = eventer_get_registrant_details('id', $registrant);
        if ($woocommerce_events == 'on') {
            $tickets_updated = array();
            $ticket_exist = $date_verify = $proceed_further = '';
            $user_system = unserialize($registrants->user_system);
            $tickets = (isset($user_system['tickets'])) ? $user_system['tickets'] : array();
            if (!empty($tickets)) {
    #region mod
                if (isset($parameters['mod'])) {
                    $id_tickets_to_checkin = (isset($parameters['id_tickets_to_checkin'])) ? $parameters['id_tickets_to_checkin'] : '';
                    if ($id_tickets_to_checkin != '') {
                        foreach ($tickets as $ticket) {
                            if (in_array($ticket['id'], $id_tickets_to_checkin)) {
                                $check_checkin_status = (isset($ticket['checkin'])) ? $ticket['checkin'] : '';
                                $ticket['checkin'] = $ticket['checkin_date'] = '';
                                $ticket_exist = '1';
                                $date_verify = '1';
                                if ($ticket_exist != '' && $date_verify != '') {
                                    $proceed_further = '1';
                                    $ticket['checkin'] = '1';
                                    $ticket['checkin_date'] = date_i18n('Y-m-d H:i:s');
                                    $tickets_updated[] = $ticket;
                                }
    
                            } else {
                                $tickets_updated[] = $ticket;
                            }
                        }
                        if ($proceed_further != '' && $check_checkin_status == '') {
                            $user_system['tickets'] = $tickets_updated;
                            eventer_update_registrant_details(array('user_system' => serialize($user_system)), $registrant, array("%s", "%s"));
                            $msg = "Successfully check-in";
                        } elseif ($check_checkin_status != '') {
                            $msg = "This ticket is already checked in";
                        }
                    }
                } else {
    #endregion
                    foreach ($tickets as $ticket) {
                        $check_checkin_status = (isset($ticket['checkin'])) ? $ticket['checkin'] : '';
                        $ticket['checkin'] = $ticket['checkin_date'] = '';
                        $ticket_exist = '1';
                        $date_verify = '1';
                        if ($ticket_exist != '' && $date_verify != '') {
                            $proceed_further = '1';
                            $ticket['checkin'] = '1';
                            $ticket['checkin_date'] = date_i18n('Y-m-d H:i:s');
                            $tickets_updated[] = $ticket;
                        }
                    }
                    if ($proceed_further != '' && $check_checkin_status == '') {
                        $user_system['tickets'] = $tickets_updated;
                        eventer_update_registrant_details(array('user_system' => serialize($user_system)), $registrant, array("%s", "%s"));
                        $msg = "Successfully check-in";
                    } elseif ($check_checkin_status != '') {
                        $msg = "This ticket is already checked in";
                    }
    # region mod end bracket
                }
    # endregion
            } else {
                $msg = "It seems the ticket is not related to the details you submiited above.";
            }
        } else {
            $user_system = unserialize($registrants->user_system);
            if (isset($user_system['checkin']) && $user_system['checkin'] == '1') {
                $msg = "This ticket is already checked in";
            } else {
                $user_system['checkin'] = "1";
                $user_system['checkin_date'] = date_i18n('Y-m-d H:i:s');
                eventer_update_registrant_details(array('user_system' => serialize($user_system)), $registrant, array("%s", "%s"));
                $msg = "Successfully check-in";
            }
        }
        $response = array("scan" => "", "msg" => $msg);
    
        return rest_ensure_response($response);
    }

</details>

### &emsp;Android
#### &emsp;&emsp;Скачать [последний релиз](https://github.com/Wel-lz/Eventer-checkIn-app/releases/latest)

&emsp;&emsp;**или**
[**Установка из исходника**](#исходник)

### &emsp;iOS
> Из-за ограничений iOS и Thunkable для получения приложения требуется самостоятельно запросить и получить ссылку для скачивания на сайте Thunkable

&emsp;&emsp;[**Установка из исходника**](#исходник)


## Исходник
1. Откройте [исходник](https://x.thunkable.com/projectPage/626118094be1eb00112ea4e8) на Thunkable
1. Настройте проект
   1. Название/описание/иконка/тд...
      1. Кликнуть на проект<br>![img.png](img/img.png)
      2. Ввести данные<br>![img_1.png](img/img_1.png)
   2. Запрос на скачивание и получение ссылки
      1. Кликнуть "Download" и выбрать платформу<br>![img_2.png](img/img_2.png)
      2. Получить письмо с ссылкой и инструкцией по установке<br>![img_3.png](img/img_3.png)<br>![img_4.png](img/img_4.png)

## Известные баги
### &emsp;Android app


### &emsp;iOS app
- На iOS клавиатура перекрывает поле ввода. Отображаеться нормально после сворачивания и разворачивания приложения (фикс - не найден)
- На iOS версиях приложения замечены белые полосы сверху и снизу приложения. Проверено на iPhone 13 (фикс - не найден)

### &emsp;Плагин eventer
- Базово плагин eventer не требует API ключ внутри запросов, только внутри своего приложения (фикс - ?. Возможно добавлю защиту запросов API ключом в следующих версиях)
- Существует баг корзины билетов из-за которого дублируются/исчезают билеты в бронировании (фикс - не найден)
- Не добавляются билеты в корзину не переходит вкладку оформления заказа (фикс - смена темы сайта)
- Ошибка открытия (пустая вкладка) оформления бронирования (фикс - перезагрузка страницы) 
