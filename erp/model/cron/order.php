<?php
namespace Opencart\Erp\Model\Cron;
/**
 * Class Order
 *
 * Can be loaded using $this->load->model('cron/order');
 *
 * @package Opencart\Erp\Model\Sale
 */
class Order extends \Opencart\System\Engine\Model {

    public function getUnSyncOrders(): array {
        $query = $this->db->query("SELECT order_id FROM `" . DB_PREFIX . "order` WHERE `is_sync_erp` = '0' and `order_id`>200 and `order_status_id`>0");

	    return $query->rows;
    }

    public function getTotals(int $order_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `sort_order`");

        return $query->rows;
    }

	public function getOrder(int $order_id): array {
	    $field = "order_id,customer_id,firstname,lastname,email,telephone,shipping_firstname,shipping_lastname,shipping_company,shipping_address_1,shipping_city,shipping_country,shipping_zone,total,currency_code,shipping_method,comment,payment_method,order_status_id,invoice_no,invoice_prefix";
		$order_query = $this->db->query("SELECT ".$field."  FROM `" . DB_PREFIX . "order`  WHERE `order_id` = '" . (int)$order_id . "'");
		$this->log->write("SELECT ".$field."  FROM `" . DB_PREFIX . "order`  WHERE `order_id` = '" . (int)$order_id . "'");

		if ($order_query->num_rows) {


			// Customer
			$customer_info = $this->getCustomer($order_query->row['customer_id']);

            $totals = $this->getTotals($order_id);

            $tax_fee = 0;
            $other_fee = 0;
            foreach ($totals as $total) {
                if ($total['code'] == 'tax'){
                    $tax_fee = $total['value'];
                }
                if (!in_array($total['code'], ['sub_total','shipping', 'tax', 'total'])) {
                    $other_fee += $total['value'];
                }
            }

			return [
				'products'              => $this->getProducts($order_id),
				'payment_method'        => $order_query->row['payment_method'] ? json_decode($order_query->row['payment_method'], true) : [],
				'shipping_method'       => $order_query->row['shipping_method'] ? json_decode($order_query->row['shipping_method'], true) : [],
				'customer'             => $customer_info,
                'tax_fee'            => $tax_fee,
                'other_fee'          => $other_fee,
			] + $order_query->row;
		} else {
			return [];
		}
	}

	public function getProducts(int $order_id): array {
		$query = $this->db->query("SELECT model,quantity,price,total FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `order_product_id` ASC");

		return $query->rows;
	}

    public function getCustomer(int $customer_id): array {
        $query = $this->db->query("SELECT DISTINCT customer_id, firstname, lastname, email, telephone FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'");

        if ($query->num_rows) {
            return $query->row;
        } else {
            return [];
        }
    }

    public function getLanguage(int $language_id): array {
        $query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "language` WHERE `language_id` = '" . (int)$language_id . "'");

        $language = $query->row;

        if ($language) {
            $language['image'] = HTTP_CATALOG;

            if (!$language['extension']) {
                $language['image'] .= 'catalog/';
            } else {
                $language['image'] .= 'extension/' . $language['extension'] . '/catalog/';
            }

            $language['image'] .= 'language/' . $language['code'] . '/' . $language['code'] . '.png';
        }

        return $language;
    }

    public function getCountry(int $country_id): array {
        $query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "country` `c` LEFT JOIN `" . DB_PREFIX . "country_description` `cd` ON (`c`.`country_id` = `cd`.`country_id`) WHERE `c`.`country_id` = '" . (int)$country_id . "' AND `cd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    public function getZone(int $zone_id): array {
        $query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "zone` `z` LEFT JOIN `" . DB_PREFIX . "zone_description` `zd` ON (`z`.`zone_id` = `zd`.`zone_id`) WHERE `z`.`zone_id` = '" . (int)$zone_id . "' AND `zd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

        return $query->row;
    }

    public function updateOrderSync($order_id){
	    $this->db->query("UPDATE `" . DB_PREFIX . "order` SET `is_sync_erp` = '1' WHERE `order_id` = '" . (int)$order_id . "'");
    }


    public function pushOrderInfo($order_id){
        $order_info = $this->getOrder($order_id);

        $this->log->write($order_info);
        $products = [];

        // 第一次校验：基础校验
        $basic_errors = $this->validateBasicData($order_info);
        if (!empty($basic_errors)) {
            $this->logSyncError($order_id, '基础校验失败', $basic_errors);
            return false;
        }

        // 获取完整订单数据

        // 第二次校验：业务规则校验
        $business_errors = $this->validateBusinessRules($order_info);
        if (!empty($business_errors)) {
            $this->logSyncError($order_id, '业务规则校验失败', $business_errors);
            return false;
        }

        if(!$order_info){
            return false;
        }else{

            $username = "erp_abroad";
            $this->load->model('user/api');

            $api_info = $this->model_user_api->getApiByUsername($username);
            if (!$api_info){
                return false;
            }

//            $http_host = "erp.mortch.cn:7777";

            $http_host = "mortch.sunny-eis.com:7777";
            $php_self = "/mortch_api_abroad.php";
            $sync_key = $api_info['key'];

            $call = "addOrderInfo";
            $time = time();
            $string = (string)$call . "\n";
            $string .= $username . "\n";
            $string .= (string)$http_host . "\n";
            $string .= (!empty($php_self) ? rtrim(dirname($php_self), '/') . '/' : '/') . "\n";
            $string .= md5(http_build_query($order_info)) . "\n";
            $string .= $time . "\n";
            $signature = rawurlencode(base64_encode(hash_hmac('sha1', $string, $sync_key, 1)));
            $url = "http://".$http_host.$php_self."?call=".$call."&user_name=".$username."&time=".$time."&signature=".$signature;

            $this->log->write($string);
            $this->log->write($signature);
            $this->log->write($url);

            $result = $this->curlRequest($url,"POST",$order_info);
            if($result){
                $this->updateOrderSync($order_id);
            }
            return $result;
        }



    }

    private function validateBasicData($order_data) {
        $errors = [];

        $required_fields = [
            'order_id',
            'customer_id',
            'email',
            'telephone'];
        // 必填字段校验
        foreach ($required_fields as $field) {
            if (empty($order_data[$field])) {
                $errors[] = "必填字段缺失: {$field}";
            }
        }

//        // 金额一致性校验
//        if ($order_data['total'] != $order_data['subtotal'] + $order_data['shipping'] + $order_data['tax']) {
//            $errors[] = "订单金额计算不一致";
//        }

        return $errors;
    }

    private function validateBusinessRules($order_data) {
        $errors = [];

        if (!$order_data['order_status_id'] || $order_data['order_status_id'] == 0 || $order_data['order_status_id'] == $this->config->get('config_void_status_id') || $order_data['order_status_id'] == $this->config->get('config_failed_status_id') || $order_data['order_status_id'] == $this->config->get('config_fraud_status_id')) {
            $errors[] = "订单无效，不能同步";
        }
        // 支付状态校验
//        if ($order_data['order_status_id'] != $this->config->get('config_complete_status_id')) {
//            $errors[] = "订单未完成支付，不能同步";
//        }
        return $errors;
    }

    private function logSyncError($order_id, $stage, $errors) {
        $log_message = "订单同步失败 [订单ID: {$order_id}, 阶段: {$stage}]";

        if (is_array($errors)) {
            $log_message .= " - 错误详情: " . implode('; ', $errors);
        } else {
            $log_message .= " - 错误: {$errors}";
        }

        // 写入OpenCart日志
        $this->log->write($log_message);

    }
    public function curlRequest($url,$method,$postData=null){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS =>  json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/json;',
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);

        $this->log->write($data);
        if ($data && isset($data['code']) && $data['code'] == 0){
            return true;
        }else{
            error_log("Sync failed with HTTP code $httpCode");
            return false;
        }
    }


}
