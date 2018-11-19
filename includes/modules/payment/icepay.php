<?php

/*
#################################################################
#                                                             	#
#	The property of ICEPAY www.icepay.eu                      	#
#                                                             	#
#   The merchant is entitled to change de ICEPAY plug-in code,	#
#	any changes will be at merchant's own risk.					#
#	Requesting ICEPAY support for a modified plug-in will be	#
#	charged in accordance with the standard ICEPAY tariffs.		#
#                                                             	#
#################################################################

	osCommerce, Open Source E-Commerce Solutions
	http://www.oscommerce.com
	Copyright (c) 2008 osCommerce
	Released under the GNU General Public License
	
*/

if (!class_exists("icepay_order_total")) {
    class icepay_order_total
    {
        var $modules;

        // class constructor
        function icepay_order_total()
        {
            global $language;

            if (defined('MODULE_ORDER_TOTAL_INSTALLED') && tep_not_null(MODULE_ORDER_TOTAL_INSTALLED)) {
                $this->modules = explode(';', MODULE_ORDER_TOTAL_INSTALLED);

                reset($this->modules);
                while (list(, $value) = each($this->modules)) {
                    include_once(DIR_WS_LANGUAGES . $language . '/modules/order_total/' . $value);
                    include_once(DIR_WS_MODULES . 'order_total/' . $value);

                    $class = substr($value, 0, strrpos($value, '.'));
                    if ($this->checkMS2()) $GLOBALS[$class] = new $class; // <--- MS2 compatibility
                }
            }
        }

        function checkMS2()
        {
            if (PROJECT_VERSION == 'osCommerce 2.2-MS2') {
                return true;
            }
            return false;
        }

        function process()
        {
            $order_total_array = array();

            if (is_array($this->modules)) {
                reset($this->modules);
                while (list(, $value) = each($this->modules)) {
                    $class = substr($value, 0, strrpos($value, '.'));
                    if ($GLOBALS[$class]->enabled) {
                        if ($this->checkMS2()) $GLOBALS[$class]->process(); // <--- MS2 compatibility

                        for ($i = 0, $n = sizeof($GLOBALS[$class]->output); $i < $n; $i++) { // <--- Update
                            if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
                                $order_total_array[] = array
                                (
                                    'code' => $GLOBALS[$class]->code,
                                    'title' => $GLOBALS[$class]->output[$i]['title'],
                                    'text' => $GLOBALS[$class]->output[$i]['text'],
                                    'value' => $GLOBALS[$class]->output[$i]['value'],
                                    'sort_order' => $GLOBALS[$class]->sort_order
                                );
                            }
                        }
                    }
                }
            }

            return $order_total_array;
        }


        function output()
        {
            $output_string = '';
            if (is_array($this->modules)) {
                reset($this->modules);
                while (list(, $value) = each($this->modules)) {
                    $class = substr($value, 0, strrpos($value, '.'));
                    if ($GLOBALS[$class]->enabled) {
                        $size = sizeof($GLOBALS[$class]->output);
                        for ($i = 0; $i < $size; $i++) {
                            $output_string .= '              <tr>' . "\n" .
                                '                <td align="right" class="main">' . $GLOBALS[$class]->output[$i]['title'] . '</td>' . "\n" .
                                '                <td align="right" class="main">' . $GLOBALS[$class]->output[$i]['text'] . '</td>' . "\n" .
                                '              </tr>';
                        }
                    }
                }
            }
            return $output_string;
        }
    }
}

if (!class_exists('icepay')) {
    class icepay
    {
        var $icon = "icepay.payments.gif";
        var $code;
        var $title;
        var $description;
        var $enabled;
        var $base_url = 'https://pay.icepay.eu/basic/';
        var $redirect_url = 'https://pay.icepay.eu/basic/';
        var $version, $disclaimer;

        function icepay()
        {
            global $order;
            $statuserror = false;

            $this->code = 'icepay';
            $this->version = "2.3.8";
            $this->manualLink = "https://icepay.com/downloads/pdf/manuals/oscommerce/oscommerce-implementation-manual-icepay.pdf";
            $this->title = $this->getTitle("Core module");//MODULE_PAYMENT_ICEPAY_TEXT_TITLE;
            $this->description = MODULE_PAYMENT_ICEPAY_TEXT_DESCRIPTION;
            $this->sort_order = MODULE_PAYMENT_ICEPAY_SORT_ORDER;
            $this->enabled = ((MODULE_PAYMENT_ICEPAY_STATUS == 'True') ? true : false);
            $this->disclaimer = "The merchant is entitled to change de ICEPAY plug-in code, any changes will be at merchant's own risk. Requesting ICEPAY support for a modified plug-in will be charged in accordance with the standard ICEPAY tariffs.";


            if (
                (int)MODULE_PAYMENT_ICEPAY_ORDER_STATUS_ID == 0
                || (int)MODULE_PAYMENT_ICEPAY_CANCELLED_ORDER_STATUS_ID == 0
                || (int)MODULE_PAYMENT_ICEPAY_REFUND_ORDER_STATUS_ID == 0
                || (int)MODULE_PAYMENT_ICEPAY_SUCCESS_ORDER_STATUS_ID == 0
                || (int)MODULE_PAYMENT_ICEPAY_CHARGEBACK_ORDER_STATUS_ID == 0
                || (int)MODULE_PAYMENT_ICEPAY_OPEN_ORDER_STATUS_ID == 0
            ) {
                $statuserror = true;
                $this->description .= "<BR><BR><font color=#FF0000><B>Order statuses can not be default and need to be unique! Please set them properly.</B></font>";
            };

            if (
                MODULE_PAYMENT_ICEPAY_MERCHANT_ID == "ICEPAY"
                || MODULE_PAYMENT_ICEPAY_SECRET == "ICEPAY"
            ) {
                $statuserror = true;
                $this->description .= "<BR><BR><font color=#FF0000><B>Merchant ID and Secret code are not set</B></font>";
            };

            if (
                MODULE_PAYMENT_ICEPAY_MERCHANT_ID != "ICEPAY"
                && (int)MODULE_PAYMENT_ICEPAY_MERCHANT_ID == 0
            ) {
                $this->title .= "<BR><font color=#FF0000><B>This module is required to be installed and configured to make use of the ICEPAY payment methods.</B></font>";
            };

            $this->description .= "<BR><BR><img src='images/icon_info.gif' border='0'>&nbsp;<strong>Module version:</strong> {$this->version}"
                . "<BR><BR><img src='images/icon_info.gif' border='0'>&nbsp;<strong>Module ID:</strong> {$this->generateFingerPrint()}"
                . "<BR><BR><img src='images/icon_popup.gif' border='0'>&nbsp;<strong>Check for latest version:</strong> <a href=\"http://developer.icepay.eu/download\" target=\"_blank\">Open in new window</a>"
                . "<BR><BR><img src='images/icon_popup.gif' border='0'>&nbsp;<strong>Read the manual:</strong> <a href=\"{$this->manualLink}\" target=\"_blank\">Open in new window</a>"
                . "<BR><BR><img src='images/icon_popup.gif' border='0'>&nbsp;<strong>Support:</strong> <a href=\"http://support.icepay.eu\" target=\"_blank\">Open in new window</a>"
                . "<BR><BR>{$this->disclaimer}";

            if ((int)MODULE_PAYMENT_ICEPAY_ORDER_STATUS_ID > 0)
                $this->order_status = MODULE_PAYMENT_ICEPAY_ORDER_STATUS_ID;

            $this->description .= "<BR><BR><strong>Merchant Success URL:</strong><br>" . HTTP_SERVER . DIR_WS_HTTP_CATALOG . "ext/modules/payment/icepay/result.php";
            $this->description .= "<BR><BR><strong>Merchant Error URL:</strong><br>" . HTTP_SERVER . DIR_WS_HTTP_CATALOG . "ext/modules/payment/icepay/result.php";
            $this->description .= "<BR><BR><strong>Merchant Notify/Postback URL:</strong><br>" . HTTP_SERVER . DIR_WS_HTTP_CATALOG . "ext/modules/payment/icepay/notify.php";
            $this->description .= "<hr>";

            if ($statuserror == true && $this->enabled == true) {
                $this->title = "<font color=\"#FF0000\"><b>!</b></font>" . MODULE_PAYMENT_ICEPAY_TEXT_TITLE . "<br><font color=\"#FF0000\">(Not properly configured)</font>";
            };


            if (is_object($order))
                $this->update_status();
        }

        function update_status()
        {

        }

        function javascript_validation()
        {
            return false;
        }

        function selection()
        {
            return array('id' => $this->code,
                'module' => $this->title);
        }

        function pre_confirmation_check()
        {
            return false;
        }

        function confirmation()
        {
            return false;
        }

        function process_button()
        {
            return false;
        }

        function before_process()
        {

            $formdata = $this->createOrder();

            $url = $this->redirect_url;
            if ($_POST["type"]) $url .= "?type=" . $_POST["type"];


            $html = '<html><body>';
            //$html.= 'Redirecting to payment portal';
            $html .= '<form id="icepay_checkout" name="icepay_checkout" method="POST" action="' . $url . '">';

            $html .= $formdata;
            $html .= '</form>';
            $html .= '<script type="text/javascript">document.getElementById("icepay_checkout").submit();</script>';
            $html .= '</body></html>';
            die($html);

        }

        function after_process()
        {
            return false;
        }

        function get_error()
        {
            global $HTTP_GET_VARS;

            if (isset($HTTP_GET_VARS['message']) && (strlen($HTTP_GET_VARS['message']) > 0)) {
                $error = stripslashes(urldecode($HTTP_GET_VARS['message']));
            } else {
                $error = MODULE_PAYMENT_ICEPAY_TEXT_ERROR_MESSAGE;
            }

            return array('title' => MODULE_PAYMENT_ICEPAY_TEXT_ERROR, 'error' => $error);
        }

        function doLogging($line)
        {
            if (MODULE_PAYMENT_ICEPAY_LOGGING == 'False') return false;

            $filename = sprintf("%s/#%s.log", DIR_WS_MODULES . 'payment/icepay/icepay_log', date("Ymd", time()));
            $fp = @fopen($filename, "a");
            $line = sprintf("%s - %s\r\n", date("H:i", time()), $line);
            @fwrite($fp, $line);
            @fclose($fp);

            return true;
        }

        function check()
        {
            if (!isset($this->_check)) {
                $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_ICEPAY_STATUS'");
                $this->_check = tep_db_num_rows($check_query);
            }
            return $this->_check;
        }

        function updateStatus($orderID, $statusID, $statusInfo, $notified)
        {
            $this->doLogging(sprintf("status updated to: %s", mysql_real_escape_string($statusID)));
            tep_db_query(sprintf("update %s set orders_status = '%s', last_modified = now() where orders_id = '%s'",
                TABLE_ORDERS,
                mysql_real_escape_string($statusID),
                mysql_real_escape_string($orderID)
            ));
            $sql_data_array = array('orders_id' => mysql_real_escape_string($orderID),
                'orders_status_id' => mysql_real_escape_string($statusID),
                'date_added' => 'now()',
                'customer_notified' => $notified,
                'comments' => mysql_real_escape_string($statusInfo));
            tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }

        function _notify_customer($orderID)
        {
            include_once(DIR_WS_MODULES . 'payment/icepay/custom/mail_notifycustomer.php');
            $notify = new icepay_mail_notifycustomer();
            $notify->_notify_customer($orderID);
        }

        function getStatuscodeID($status_name)
        {
            $query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = '$status_name' limit 1");
            $fetch = tep_db_fetch_array($query);
            return $fetch["orders_status_id"];
        }

        function _installstatus($status_name, $public_flag = 0)
        {
            $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = '$status_name' limit 1");

            if (tep_db_num_rows($check_query) < 1) {
                $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
                $status = tep_db_fetch_array($status_query);

                $status_id = $status['status_id'] + 1;

                $languages = tep_get_languages();

                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $languages[$i]['id'] . "', '$status_name')");
                }

                $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
                if (tep_db_num_rows($flags_query) == 1) {
                    tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = " . $public_flag . " and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
                }
            } else {
                $check = tep_db_fetch_array($check_query);
                $status_id = $check['orders_status_id'];
            }
        }

        function install()
        {
            $this->_installstatus("Starting Payment", 0);
            $this->_installstatus("Pending Payment", 1);
            $this->_installstatus("Payment Received", 1);
            $this->_installstatus("Payment Error", 0);
            $this->_installstatus("Refund Requested", 0);
            $this->_installstatus("Chargeback Requested", 0);


            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable ICEPAY Module', 'MODULE_PAYMENT_ICEPAY_STATUS', 'True', 'Do you want to enable ICEPAY for online payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable log to files', 'MODULE_PAYMENT_ICEPAY_LOGGING', 'True', 'Do you want to enable logging to files? The /icepay_log directory must have full writing privileges (CHMOD777)', '6', '20', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_ICEPAY_MERCHANT_ID', 'ICEPAY', 'Merchant ID to use for the ICEPAY service', '6', '2', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret code', 'MODULE_PAYMENT_ICEPAY_SECRET', 'ICEPAY', 'Secret code to use for the ICEPAY service', '6', '3', now())");

            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Send confirmation e-mail after succesful payment (Status OK)', 'MODULE_PAYMENT_ICEPAY_SENDEMAIL_OK', 'True', 'Mail will be sent to customer and to e-mail addresses saved in the osCommerce setting \"send extra e-mails to\". The osCommerce settings \"send e-mails\" must be active.', '6', '15', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Send confirmation e-mail after order has been placed (Status OPEN)', 'MODULE_PAYMENT_ICEPAY_SENDEMAIL_OPEN', 'True', 'Mail will be sent to customer and to e-mail addresses saved in the osCommerce setting \"send extra e-mails to\". The osCommerce settings \"send e-mails\" must be active.', '6', '16', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Update stock only on succes', 'MODULE_PAYMENT_ICEPAY_STOCKUPDATE', 'True', 'When set to True the stock will update on payment received, when set to False stock will update on each new order', '6', '17', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display icons', 'MODULE_PAYMENT_ICEPAY_CHECKOUTIMAGES', 'True', 'Display paymentmethod images in checkout', '6', '18', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");


            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_ICEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '21', now())");

            // Preparing
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (preparing)', 'MODULE_PAYMENT_ICEPAY_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Preparing [ICEPAY]") . "', 'Set the status of prepared orders', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
            // OPEN
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (open)', 'MODULE_PAYMENT_ICEPAY_OPEN_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Open [ICEPAY]") . "', 'Set the status of orders waiting for payment.', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
            // OK
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (successful)', 'MODULE_PAYMENT_ICEPAY_SUCCESS_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Success [ICEPAY]") . "', 'Set the status of successful orders', '6', '7', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
            // ERR
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (cancelled)', 'MODULE_PAYMENT_ICEPAY_CANCELLED_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Cancelled [ICEPAY]") . "', 'Set the status of cancelled orders', '6', '8', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
            // REFUND
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (refund)', 'MODULE_PAYMENT_ICEPAY_REFUND_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Refund [ICEPAY]") . "', 'Set the status of successful orders', '6', '9', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
            // CBACK
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status (chargeback)', 'MODULE_PAYMENT_ICEPAY_CHARGEBACK_ORDER_STATUS_ID', '" .
                $this->getStatuscodeID("Chargeback [ICEPAY]") . "', 'Set the status of cancelled orders', '6', '10', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

            // Regional settings
            $this->default_language = $this->getDefaultLanguage();
            $this->default_currency = DEFAULT_CURRENCY;
            $this->default_country = 'DETECT';

            $this->languages_dbstring = $this->db_implode($this->allowedLanguages());
            $this->currencies_dbstring = $this->db_implode($this->allowedCurrencies());
            $this->countries_dbstring = $this->db_implode($this->allowedCountries());

            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Language display settings', 'MODULE_PAYMENT_ICEPAY_LANGUAGE', '" . $this->default_language . "', 'Set the language. Default setting is current OSCommerce language.', '6', '11', 'tep_cfg_select_option(array(" . $this->languages_dbstring . "), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Currency', 'MODULE_PAYMENT_ICEPAY_CURRENCY', '" . $this->default_currency . "', 'Set the currency. Default setting is current OSCommerce currency.', '6', '12', 'tep_cfg_select_option(array(" . $this->currencies_dbstring . "), ', now())");
            tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Country', 'MODULE_PAYMENT_ICEPAY_COUNTRY', '" . $this->default_country . "', 'Set the country. Default setting is DETECT, using the country of the user.', '6', '13', 'tep_cfg_select_option(array(" . $this->countries_dbstring . "), ', now())");

        }

        function remove()
        {
            tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        }

        function keys()
        {
            return array
            (
                'MODULE_PAYMENT_ICEPAY_STATUS',
                'MODULE_PAYMENT_ICEPAY_MERCHANT_ID',
                'MODULE_PAYMENT_ICEPAY_SECRET',
                'MODULE_PAYMENT_ICEPAY_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_SUCCESS_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_CANCELLED_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_REFUND_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_CHARGEBACK_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_OPEN_ORDER_STATUS_ID',
                'MODULE_PAYMENT_ICEPAY_SORT_ORDER',
                'MODULE_PAYMENT_ICEPAY_SENDEMAIL_OK',
                'MODULE_PAYMENT_ICEPAY_SENDEMAIL_OPEN',
                'MODULE_PAYMENT_ICEPAY_LANGUAGE',
                'MODULE_PAYMENT_ICEPAY_CURRENCY',
                'MODULE_PAYMENT_ICEPAY_COUNTRY',
                'MODULE_PAYMENT_ICEPAY_STOCKUPDATE',
                'MODULE_PAYMENT_ICEPAY_CHECKOUTIMAGES',
                'MODULE_PAYMENT_ICEPAY_LOGGING'

            );
        }

        function createOrder()
        {
            global $order_total_modules;
            global $customer_id, $order, $currencies, $currency, $language, $languages_id;

            if (strtolower(MODULE_PAYMENT_ICEPAY_STOCKUPDATE) == 'true') {
                include_once(DIR_WS_MODULES . "payment/icepay/custom/class_new_order_stockunchanged.php");
            } else {
                include_once(DIR_WS_MODULES . "payment/icepay/custom/class_new_order.php");
            };

            $this->order_id = $insert_id;

            $this->doLogging(sprintf("New Order: %s", $insert_id));

            $process_button_string = "";

            if ($_POST["ic_paymentmethod"]) {
                $icepay_country = $_POST["ic_country"];
                $icepay_currency = $_POST["ic_currency"];
                $icepay_language = $_POST["ic_language"];
                $amount = $_POST["ic_amount"];
                $process_button_string .= tep_draw_hidden_field('ic_paymentmethod', $_POST["ic_paymentmethod"]) .
                    tep_draw_hidden_field('ic_issuer', $_POST["ic_issuer"]);
                $checksum = $this->getChecksum($amount, $this->order_id, $icepay_language, $icepay_country, $icepay_currency);
            } else {
                $icepay_country = $this->getUserCountry(MODULE_PAYMENT_ICEPAY_COUNTRY);
                $icepay_currency = $this->getUserCurrency(MODULE_PAYMENT_ICEPAY_CURRENCY);
                $icepay_language = $this->getUserLanguage(MODULE_PAYMENT_ICEPAY_LANGUAGE);
                $amount = number_format($order->info['total'] * $currencies->get_value($icepay_currency), $currencies->currencies[$icepay_currency]['decimal_places'], '.', '') * 100;
                $checksum = sha1(MODULE_PAYMENT_ICEPAY_MERCHANT_ID . "|" . MODULE_PAYMENT_ICEPAY_SECRET . "|" . $amount . "|" . $this->order_id . "|" . $icepay_language . "|" . $icepay_currency . "|" . $icepay_country);
            };

            $process_button_string .= tep_draw_hidden_field('ic_merchantid', MODULE_PAYMENT_ICEPAY_MERCHANT_ID) .
                tep_draw_hidden_field('ic_orderid', $this->order_id) .
                tep_draw_hidden_field('ic_amount', $amount) .
                tep_draw_hidden_field('ic_currency', $icepay_currency) .
                tep_draw_hidden_field('ic_language', $icepay_language) .
                tep_draw_hidden_field('ic_country', $icepay_country) .
                tep_draw_hidden_field('ic_description', STORE_NAME) .
                tep_draw_hidden_field('ic_reference', $icepay_language) .
                tep_draw_hidden_field('ic_fp', $this->generateFingerPrint()) .
                tep_draw_hidden_field('chk', $checksum);


            return $process_button_string;
        }

        function getChecksum($amount, $icepay_orderid, $icepay_language, $icepay_country, $icepay_currency)
        {
            $checksum = sha1(MODULE_PAYMENT_ICEPAY_MERCHANT_ID . "|" .
                MODULE_PAYMENT_ICEPAY_SECRET . "|" .
                $amount . "|" .
                $icepay_orderid . "|" .
                $icepay_language . "|" .
                $icepay_currency . "|" .
                $icepay_country);

            return $checksum;
        }

        function getOrderAmount($cur = MODULE_PAYMENT_ICEPAY_CURRENCY)
        {
            global $order, $currencies;

            $icepay_currency = $this->getUserCurrency($cur);
            $amount = number_format($order->info['total'] * $currencies->get_value($icepay_currency), $currencies->currencies[$icepay_currency]['decimal_places'], '.', '') * 100;

            return $amount;
        }

        function checkView()
        {
            $view = "admin";

            if (!tep_session_is_registered('admin')) {
                if ($this->getScriptName() == FILENAME_CHECKOUT_PAYMENT) {
                    $view = "checkout";
                } else {
                    $view = "frontend";
                }
            }
            return $view;
        }

        function getScriptName()
        {

            global $PHP_SELF;

            return basename($PHP_SELF);
            /*
            if (isset($_SERVER["SCRIPT_NAME"])){
                $file 	= $_SERVER["SCRIPT_NAME"];
                $break 	= Explode('/', $file);
                $file 	= $break[count($break) - 1];
            };

            return $file;
            */
        }

        function GetCoreClasses()
        {
            return array
            (
                'icepay.php',
                'icepay_bancash.php',
                'icepay_cc.php',
                'icepay_ddebit.php',
                'icepay_ideal.php',
                'icepay_paypal.php',
                'icepay_pbar.php',
                'icepay_wire.php',
                'icepay_sms.php',
                'icepay_giropay.php',
                'icepay_ebanking.php',
                'icepay_creditclick.php',
                '../../../ext/modules/payment/icepay/notify.php',
                '../../../ext/modules/payment/icepay/result.php'
            );
        }

        function generateFingerPrint()
        {
            if ($this->fingerPrint != "") return $this->fingerPrint;

            $content = "";

            foreach ($this->GetCoreClasses() as $item) {
                if (false === ($content .= file_get_contents(dirname(__FILE__) . '/' . $item))) {
                };
            };
            $this->fingerPrint = sha1($content);

            return $this->fingerPrint;
        }

        function db_implode($array)
        {
            $str = '';
            $lem = array_keys($array);
            $char = htmlentities(', ');
            for ($i = 0; $i < sizeof($lem); $i++) {
                $str .= "\'" . (($i == sizeof($lem) - 1) ? $array[$lem[$i]] . "\'" : $array[$lem[$i]] . "\'" . $char);
            }
            return $str;
        }

        function allowedCurrencies()
        {
            return array('DETECT', 'EUR', 'GBP', 'USD', 'AUD', 'CAD', 'CHF', 'CZK', 'PLN', 'SKK', 'MXN', 'CLP', 'LVL');
        }

        function allowedIssuers()
        {
            return array();
        }

        function allowedCountries()
        {
            return array('DETECT', '00', 'NL', 'AT', 'AU', 'BE', 'CA', 'CH', 'CZ', 'DE', 'ES', 'IT', 'LU', 'PL', 'PT', 'SK', 'UK', 'US', 'FR');
        }

        function allowedLanguages()
        {
            return array('DETECT', 'EN', 'DE', 'NL');
        }

        function getTitle($admin = null)
        {
            $title = ($this->checkView() == "checkout") ? $this->generateIcon($this->getIcon()) . " " : "";
            $title .= ($this->checkView() == "admin") ? "ICEPAY - " : "";
            if ($admin && $this->checkView() == "admin") {
                $title .= $admin;
            } else {
                $title .= $this->getLangStr("title");
            };
            return $title;
        }

        function getLangStr($str)
        {
            switch ($str) {
                case "title":
                    return MODULE_PAYMENT_ICEPAY_TEXT_TITLE;
                    break;
            }
        }

        function generateIcon($icon)
        {
            if (defined('MODULE_PAYMENT_ICEPAY_CHECKOUTIMAGES') && MODULE_PAYMENT_ICEPAY_CHECKOUTIMAGES != 'True') return "";
            return tep_image($icon);
        }

        function getIcon()
        {
            $icon = DIR_WS_IMAGES . "/icepay/en/" . $this->icon;
            if (file_exists(DIR_WS_IMAGES . "/icepay/" . strtolower($this->getUserLanguage("DETECT")) . "/" . $this->icon)) $icon = DIR_WS_IMAGES . "/icepay/" . strtolower($this->getUserLanguage("DETECT")) . "/" . $this->icon;
            return $icon;
        }

        function getDefaultLanguage()
        {
            global $languages_id;

            $query = tep_db_query("select languages_id, name, code, image, directory from " . TABLE_LANGUAGES . " where languages_id = " . (int)$languages_id . " limit 1");
            if ($languages = tep_db_fetch_array($query)) {
                return strtoupper($languages['code']);
            }

            return "EN";
        }

        function getUserLanguage($savedSetting)
        {
            if ($savedSetting != "DETECT") {
                return $savedSetting;
            }

            global $languages_id;

            $query = tep_db_query("select languages_id, name, code, image, directory from " . TABLE_LANGUAGES . " where languages_id = " . (int)$languages_id . " limit 1");
            if ($languages = tep_db_fetch_array($query)) {
                return strtoupper($languages['code']);
            }

            return "EN";
        }

        function getUserCountry($savedSetting)
        {
            if ($savedSetting != "DETECT") {
                return $savedSetting;
            }

            global $customer_country_id;

            $query = tep_db_query("select countries_iso_code_2 from " . TABLE_COUNTRIES . " where countries_id = " . (int)$customer_country_id . " limit 1");
            if ($countries = tep_db_fetch_array($query)) {
                return strtoupper($countries['countries_iso_code_2']);
            }

            return "NL";
        }

        function checkActivation($savedSetting)
        {
            if ($savedSetting != "DETECT") {
                if ($_SESSION["currency"] != $savedSetting) return false;
            };

            return true;
        }

        function doAllowedCurrencyCheck($allowedcurrency)
        {
            $currency = strtoupper($_SESSION["currency"]);
            if ($currency != $allowedcurrency) {
                return false;
            }

            return true;
        }

        function getUserCurrency($savedSetting)
        {
            if (in_array($_SESSION["currency"], array('EUR', 'GBP', 'USD', 'AUD', 'CAD', 'CHF', 'CZK', 'PLN', 'SKK', 'MXN', 'CLP', 'LVL'))) {
                return $_SESSION["currency"];
            }

            return "EUR";
        }

        function setMerchant()
        {
            $this->secretCode = MODULE_PAYMENT_ICEPAY_SECRET;
            $this->merchantID = MODULE_PAYMENT_ICEPAY_MERCHANT_ID;
        }

        function clearPostback()
        {
            $this->postback = NULL;

            $this->postback->status = "";
            $this->postback->statusCode = "";
            $this->postback->merchant = "";
            $this->postback->orderID = "";
            $this->postback->paymentID = "";
            $this->postback->reference = "";
            $this->postback->transactionID = "";
            $this->postback->consumerName = "";
            $this->postback->consumerAccountNumber = "";
            $this->postback->consumerAddress = "";
            $this->postback->consumerHouseNumber = "";
            $this->postback->consumerCity = "";
            $this->postback->consumerCountry = "";
            $this->postback->consumerEmail = "";
            $this->postback->consumerPhoneNumber = "";
            $this->postback->consumerIPAddress = "";
            $this->postback->amount = "";
            $this->postback->currency = "";
            $this->postback->duration = "";
            $this->postback->paymentMethod = "";
            $this->postback->checksum = "";

            return;
        }

        function generateChecksumForPostback()
        {
            return sha1
            (
                $this->secretCode . "|" .
                $this->merchantID . "|" .
                $this->postback->status . "|" .
                $this->postback->statusCode . "|" .
                $this->postback->orderID . "|" .
                $this->postback->paymentID . "|" .
                $this->postback->reference . "|" .
                $this->postback->transactionID . "|" .
                $this->postback->amount . "|" .
                $this->postback->currency . "|" .
                $this->postback->duration . "|" .
                $this->postback->consumerIPAddress
            );
        }

        function OnPostback()
        {
            if ($_SERVER['REQUEST_METHOD'] != 'POST') return false;

            $this->postback = NULL;
            $this->postback->status = $_POST['Status'];
            $this->postback->statusCode = $_POST['StatusCode'];
            $this->postback->merchant = $_POST['Merchant'];
            $this->postback->orderID = $_POST['OrderID'];
            $this->postback->paymentID = $_POST['PaymentID'];
            $this->postback->reference = $_POST['Reference'];
            $this->postback->transactionID = $_POST['TransactionID'];
            $this->postback->consumerName = $_POST['ConsumerName'];
            $this->postback->consumerAccountNumber = $_POST['ConsumerAccountNumber'];
            $this->postback->consumerAddress = $_POST['ConsumerAddress'];
            $this->postback->consumerHouseNumber = $_POST['ConsumerHouseNumber'];
            $this->postback->consumerCity = $_POST['ConsumerCity'];
            $this->postback->consumerCountry = $_POST['ConsumerCountry'];
            $this->postback->consumerEmail = $_POST['ConsumerEmail'];
            $this->postback->consumerPhoneNumber = $_POST['ConsumerPhoneNumber'];
            $this->postback->consumerIPAddress = $_POST['ConsumerIPAddress'];
            $this->postback->amount = $_POST['Amount'];
            $this->postback->currency = $_POST['Currency'];
            $this->postback->duration = $_POST['Duration'];
            $this->postback->paymentMethod = $_POST['PaymentMethod'];
            $this->postback->checksum = $_POST['Checksum'];

            $this->doLogging(sprintf("Postback: %s", serialize($_POST)));

            if (!is_numeric($this->postback->merchant)) {
                $this->clearPostback();
                return false;
            }

            if (!is_numeric($this->postback->amount)) {
                $this->clearPostback();
                return false;
            }

            if ($this->merchantID != $this->postback->merchant) {
                $this->clearPostback();
                $this->doLogging("Invalid merchant ID");
                return false;
            }

            if ($this->generateChecksumForPostback() != $this->postback->checksum) {
                $this->clearPostback();
                $this->doLogging("Checksum does not match");
                return false;
            }

            return true;
        }


    } // class
} // if

?>
