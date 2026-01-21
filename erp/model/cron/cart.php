<?php
namespace Opencart\Erp\Model\Cron;
/**
 * Class Order
 *
 * Can be loaded using $this->load->model('cron/order');
 *
 * @package Opencart\Erp\Model\Sale
 */
class Cart extends \Opencart\System\Engine\Model {
    public function getProducts(int $customer_id): array {
        $cart_query = $this->db->query("SELECT p.model,c.quantity FROM `" . DB_PREFIX . "cart` c join  `" . DB_PREFIX . "product` `p` ON (`c`.`product_id` = `p`.`product_id`) WHERE `store_id` = '" . (int)$this->config->get('config_store_id') . "' AND `customer_id` = '" . (int)$customer_id . "'");

        $products = [];
        if ($cart_query->num_rows){
            // Customer

            foreach ($cart_query->rows as $row) {
                $products[] = [
                    'model'      => $row['model'],
                    'quantity'   => $row['quantity'],
                ];
            }
        }
        return $products;
    }

    public function getCustomer(int $customer_id): array {
        $query = $this->db->query("SELECT DISTINCT customer_id, firstname, lastname, email, telephone FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'");

        if ($query->num_rows) {
            return $query->row;
        } else {
            return [];
        }
    }


    public function pushCartInfo($customer_id){
        $cart_info = $this->getProducts($customer_id);


        $customer_info = $this->getCustomer($customer_id);
        $this->log->write($cart_info);

        $post_data = [
            'customer' => $customer_info,
            'products' => $cart_info,
        ];

        if(!$post_data){
            return false;
        }else{

            $username = "erp_abroad";
            $this->load->model('user/api');

            $api_info = $this->model_user_api->getApiByUsername($username);
            if (!$api_info){
                return false;
            }

            $http_host = "erp.mortch.cn:7777";

//            $http_host = "mortch.sunny-eis.com:7777";
            $php_self = "/mortch_api_cart.php";
            $sync_key = $api_info['key'];

            $call = "addCartInfo";
            $time = time();
            $string = (string)$call . "\n";
            $string .= $username . "\n";
            $string .= (string)$http_host . "\n";
            $string .= (!empty($php_self) ? rtrim(dirname($php_self), '/') . '/' : '/') . "\n";
            $string .= md5(http_build_query($post_data)) . "\n";
            $string .= $time . "\n";
            $signature = rawurlencode(base64_encode(hash_hmac('sha1', $string, $sync_key, 1)));
            $url = "http://".$http_host.$php_self."?call=".$call."&user_name=".$username."&time=".$time."&signature=".$signature;

            $this->log->write($string);
            $this->log->write($signature);
            $this->log->write($url);

            $result = $this->curlRequest($url,"POST",$post_data);
            return $result;
        }

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
