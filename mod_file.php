<?php
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
# region mod
                $mod = (isset($parameters['mod'])) ? $parameters['mod'] : '';
                if ($mod != "") {
                    $woo = "2";
                    break;
                }
# endregion
            }
        }
# region mod_scan
        if ($woo == "2") {
            $tickets_info = [];
            foreach ($tickets as $ticket) {
                if (!in_array($ticket['event'], array_keys($tickets_info))) {
                    $tickets_info[$ticket['event']] = [];
                }
                // ToDo API Key Validation
                // $get_key = get_option('eventer-android-app-api-key');
                //
                $tickets_info[$ticket['event']][] = [
                    'ticket_id' => $ticket['id'],
                    'event_name' => htmlspecialchars_decode(get_the_title($ticket['event'])),
                    'ticket_type' => $ticket['type'],
                    'ticket_name' => htmlspecialchars_decode($ticket['ticket']),
                    'ticket_date' => date('d-m-Y', $ticket['date']), // edit this line if you want month-day-year format to this 'ticket_date' => date('m-d-Y', $ticket['date']),
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
# endregion
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
#region mod_checkin
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
# region mod_end_bracket
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
