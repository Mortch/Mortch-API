<?php
namespace Opencart\Erp\Model\Sale;
/**
 * Class Order
 *
 * Can be loaded using $this->load->model('order');
 *
 * @package Opencart\Api\Model
 */
class Order extends \Opencart\System\Engine\Model {

    public function addHistory(int $order_id, int $order_status_id, string $comment = '', bool $notify = false, bool $override = false): int {
        $order_info = $this->getOrder($order_id);

        if ($order_info) {
            // Update the DB with the new statuses
            $this->editOrderStatusId($order_id, $order_status_id);

            $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . (int)$order_id . "', `order_status_id` = '" . (int)$order_status_id . "', `notify` = '" . (int)$notify . "', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
            return $this->db->getLastId();
        }
    }

    public function addOrderHistory(int $order_history_id,int $order_id,int $language_id,array $data): void {
        $sql = "INSERT INTO `" . DB_PREFIX . "order_history_info` SET `order_history_id` = '" . (int)$order_history_id . "', `order_id` = '" . (int)$order_id . "', `language_id` = '" . (int)$language_id . "'";
        if (isset($data['package_info'])){
            $sql.=",`packing_info` = '" . $this->db->escape((string)$data['package_info']) . "'";
        }
        if (isset($data['shipping_name'])){
            $sql.=",`shipping_name` = '" . $this->db->escape((string)$data['shipping_name']) . "'";
        }
        if (isset($data['shipping_no'])){
            $sql.=",`shipping_no` = '" . $this->db->escape((string)$data['shipping_no']) . "'";
        }
        $this->log->write($sql);

        $this->db->query($sql);

    }

    public function editOrderStatusId(int $order_id, int $order_status_id): void {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `order_status_id` = '" . (int)$order_status_id . "' WHERE `order_id` = '" . (int)$order_id . "'");
    }

    public function getOrderStatus(string $order_status): array {
        $order_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`name` = '" . (string)$order_status . "'");
        if ($order_query->num_rows) {
            $order_status = $order_query->row;

            return $order_status;
        }

        return [];
    }

    public function getOrder(int $order_id): array {
//        $this->log->write("SELECT * (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') AS `order_status` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_id` = '" . (int)$order_id . "'");
        $order_query = $this->db->query("SELECT *, (SELECT `os`.`name` FROM `" . DB_PREFIX . "order_status` `os` WHERE `os`.`order_status_id` = `o`.`order_status_id` AND `os`.`language_id` = '" . (int)$this->config->get('config_language_id') . "') AS `order_status` FROM `" . DB_PREFIX . "order` `o` WHERE `o`.`order_id` = '" . (int)$order_id . "'");

        if ($order_query->num_rows) {
            $order_data = $order_query->row;

            return $order_data;
        }

        return [];
    }

    public function getPayPalOrder(int $order_id): array {

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "paypal_checkout_integration_order` WHERE `order_id` = '" . (int)$order_id . "'");



        if ($query->num_rows) {

            return $query->row;

        } else {

            return [];

        }

    }

    public function addOrderHistoryForPaypal( int $order_id,$order_history): void {

        if ($this->config->get('payment_paypal_status') && $order_id) {


            $country_code = "";

            $tracking_number =$order_history['shipping_no'];

            $carrier_name = $order_history['shipping_name'];

            $comment = "";

            $notify = true;





            $order_info = $this->getOrder($order_id);



            $paypal_order_info = $this->getPayPalOrder($order_id);



            if ($order_info && $paypal_order_info) {

                $paypal_order_id = $paypal_order_info['paypal_order_id'];

                $transaction_id = $paypal_order_info['transaction_id'];



                $_config = new \Opencart\System\Engine\Config();

                $_config->addPath(DIR_SYSTEM . 'library/paypal/config/');

                $_config->load('paypal_carrier');



                $config_carrier = $_config->get('paypal_carrier');



                $carriers = [];



                if (!empty($config_carrier[$country_code])) {

                    $carriers = $config_carrier[$country_code];

                }



                $carriers = $carriers + $config_carrier['GLOBAL'];



                $carrier_code = 'OTHER';



                if (!empty($carriers[$carrier_name])) {

                    $carrier_code = $carriers[$carrier_name];

                }



                $_config = new \Opencart\System\Engine\Config();

                $_config->addPath(DIR_SYSTEM . 'library/paypal/config/');

                $_config->load('paypal');



                $config_setting = $_config->get('paypal_setting');



                $setting = array_replace_recursive((array)$config_setting, (array)$this->config->get('payment_paypal_setting'));



                $client_id = $this->config->get('payment_paypal_client_id');

                $secret = $this->config->get('payment_paypal_secret');

                $environment = $this->config->get('payment_paypal_environment');

                $partner_id = $setting['partner'][$environment]['partner_id'];

                $partner_attribution_id = $setting['partner'][$environment]['partner_attribution_id'];

                $transaction_method = $setting['general']['transaction_method'];



                require_once DIR_SYSTEM . 'library/paypal/paypal.php';



                $paypal_info = [

                    'partner_id' => $partner_id,

                    'client_id' => $client_id,

                    'secret' => $secret,

                    'environment' => $environment,

                    'partner_attribution_id' => $partner_attribution_id

                ];



                $paypal = new \Opencart\System\Library\PayPal($paypal_info);



                $token_info = [

                    'grant_type' => 'client_credentials'

                ];



                $paypal->setAccessToken($token_info);



                $tracker_info = [];



                $tracker_info['capture_id'] = $transaction_id;

                $tracker_info['tracking_number'] = $tracking_number;

                $tracker_info['carrier'] = $carrier_code;

                $tracker_info['notify_payer'] = $notify;



                if ($carrier_code == 'OTHER') {

                    $tracker_info['carrier_name_other'] = $carrier_name;

                }



                $result = $paypal->createOrderTracker($paypal_order_id, $tracker_info);



                if ($paypal->hasErrors()) {

                    $error_messages = [];



                    $errors = $paypal->getErrors();

                    foreach ($errors as $error) {

                        if (isset($error['name']) && ($error['name'] == 'CURLE_OPERATION_TIMEOUTED')) {

                            $error['message'] = $this->language->get('error_timeout');

                        }



                        if (isset($error['details'][0]['description'])) {

                            $error_messages[] = $error['details'][0]['description'];

                        } elseif (isset($error['message'])) {

                            $error_messages[] = $error['message'];

                        }



                        $this->log($error, $error['message']);

                    }
                }



                if (isset($result['id']) && isset($result['status'])) {

                    $paypal_order_data = [

                        'order_id' => $order_id,

                        'tracking_number' => $tracking_number,

                        'carrier_name' => $carrier_name

                    ];



                    $this->editPayPalOrder($paypal_order_data);

                }

            }

        }
    }

    public function log(array $data = [], string $title = ''): void {

        $_config = new \Opencart\System\Engine\Config();

        $_config->addPath(DIR_SYSTEM . 'library/paypal/config/');

        $_config->load('paypal');



        $config_setting = $_config->get('paypal_setting');



        $setting = array_replace_recursive((array)$config_setting, (array)$this->config->get('payment_paypal_setting'));



        if ($setting['general']['debug']) {

            $log = new \Opencart\System\Library\Log('paypal.log');

            $log->write('PayPal debug (' . $title . '): ' . json_encode($data));

        }

    }


    public function editPayPalOrder(array $data): void {

        $sql = "UPDATE `" . DB_PREFIX . "paypal_checkout_integration_order` SET";



        $implode = [];



        if (!empty($data['paypal_order_id'])) {

            $implode[] = "`paypal_order_id` = '" . $this->db->escape($data['paypal_order_id']) . "'";

        }



        if (!empty($data['transaction_id'])) {

            $implode[] = "`transaction_id` = '" . $this->db->escape($data['transaction_id']) . "'";

        }



        if (!empty($data['transaction_status'])) {

            $implode[] = "`transaction_status` = '" . $this->db->escape($data['transaction_status']) . "'";

        }



        if (!empty($data['payment_method'])) {

            $implode[] = "`payment_method` = '" . $this->db->escape($data['payment_method']) . "'";

        }



        if (!empty($data['vault_id'])) {

            $implode[] = "`vault_id` = '" . $this->db->escape($data['vault_id']) . "'";

        }



        if (!empty($data['vault_customer_id'])) {

            $implode[] = "`vault_customer_id` = '" . $this->db->escape($data['vault_customer_id']) . "'";

        }



        if (!empty($data['card_type'])) {

            $implode[] = "`card_type` = '" . $this->db->escape($data['card_type']) . "'";

        }



        if (!empty($data['card_nice_type'])) {

            $implode[] = "`card_nice_type` = '" . $this->db->escape($data['card_nice_type']) . "'";

        }



        if (!empty($data['card_last_digits'])) {

            $implode[] = "`card_last_digits` = '" . $this->db->escape($data['card_last_digits']) . "'";

        }



        if (!empty($data['card_expiry'])) {

            $implode[] = "`card_expiry` = '" . $this->db->escape($data['card_expiry']) . "'";

        }



        if (!empty($data['total'])) {

            $implode[] = "`total` = '" . (float)$data['total'] . "'";

        }



        if (!empty($data['currency_code'])) {

            $implode[] = "`currency_code` = '" . $this->db->escape($data['currency_code']) . "'";

        }



        if (!empty($data['environment'])) {

            $implode[] = "`environment` = '" . $this->db->escape($data['environment']) . "'";

        }



        if (isset($data['tracking_number'])) {

            $implode[] = "`tracking_number` = '" . $this->db->escape($data['tracking_number']) . "'";

        }



        if (isset($data['carrier_name'])) {

            $implode[] = "`carrier_name` = '" . $this->db->escape($data['carrier_name']) . "'";

        }



        if ($implode) {

            $sql .= implode(", ", $implode);

        }



        $sql .= " WHERE `order_id` = '" . (int)$data['order_id'] . "'";



        $this->db->query($sql);

    }


}
