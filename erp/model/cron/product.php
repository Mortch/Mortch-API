<?php
namespace Opencart\Erp\Model\Cron;
/**
 * Class Product
 *
 * Can be loaded using $this->load->model('product');
 *
 * @package Opencart\Api\Model
 */
class Product extends \Opencart\System\Engine\Model {


    /**
     * Currency
     *
     * @param string $default
     *
     * @return void
     */
    public function list(string $default = ''): void {

        $this->load->model('catalog/product');

        $product_list = $this->model_catalog_product->getProductsByModel();

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($curl);

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($status == 200) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXml($response);

            $cube = $dom->getElementsByTagName('Cube')->item(0);

            $currencies = [];

            $currencies['EUR'] = 1.0000;

            foreach ($cube->getElementsByTagName('Cube') as $currency) {
                if ($currency->getAttribute('currency')) {
                    $currencies[$currency->getAttribute('currency')] = $currency->getAttribute('rate');
                }
            }

            if (count($currencies) > 1) {
                $this->load->model('localisation/currency');

                $results = $this->model_localisation_currency->getCurrencies();

                foreach ($results as $result) {
                    if (isset($currencies[$result['code']])) {
                        $from = $currencies['EUR'];

                        $to = $currencies[$result['code']];

                        $this->model_localisation_currency->editValueByCode($result['code'], 1 / ($currencies[$default] * ($from / $to)));
                    }
                }
            }

            $this->model_localisation_currency->editValueByCode($default, 1.00000);

            $this->cache->delete('currency');
        }
    }

    public function getProducts(): array {
        $query = $this->db->query("SELECT `p`.`model`,`p`.`weight`, `p`.`price`, `p`.`quantity`, `pd`.`name` FROM `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) where `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "';");
        $this->log->write("SELECT `p`.`model`, `p`.`price`, `p`.`quantity` FROM `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) where `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "';");
        return $query->rows;
    }

    public function editProduct(string $model): void {
        $sql = "UPDATE `" . DB_PREFIX . "product` SET `erp_exist_status` = '0' WHERE `model`='" . $this->db->escape((string)$model) . "';";
//        $this->log->write($sql);
        $this->db->query($sql);
    }

    public function pushProductList(){
        $product_list = $this->getProducts();

        $products = [];

        if(!$product_list){
            return false;
        }else{
            foreach ($product_list as $product){
                $products[] = [
                    'name'   => $product['name'],
                    'model'   => $product['model'],
                    'price'   => $product['price'],
                    'weight'   => $product['weight'],
                    'quantity'=> $product['quantity'],
                ];
            }

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

            $call = "getProductList";
            $time = time();
            $string = (string)$call . "\n";
            $string .= $username . "\n";
            $string .= (string)$http_host . "\n";
            $string .= (!empty($php_self) ? rtrim(dirname($php_self), '/') . '/' : '/') . "\n";
            $string .= md5(http_build_query($products)) . "\n";
            $string .= $time . "\n";
            $signature = rawurlencode(base64_encode(hash_hmac('sha1', $string, $sync_key, 1)));
            $url = "http://".$http_host.$php_self."?call=".$call."&user_name=".$username."&time=".$time."&signature=".$signature;

            $this->log->write($string);
            $this->log->write($signature);
            $this->log->write($url);

            $result = $this->curlRequest($url,"POST",$products);
            if($result){
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET `erp_exist_status` = 1;");

                foreach ($result as $model){
                    $this->editProduct($model);
                }


            }

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
            if (isset($data['data'])){
                return $data['data'];
            }else{
                return null;
            }

        }else{
            error_log("Sync failed with HTTP code $httpCode");
            return null;
        }
    }

}
