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
        return $query->rows;
    }

    public function editProduct(string $model): void {
        $sql = "UPDATE `" . DB_PREFIX . "product` SET `quantity` = '0', `erp_status` = '0', `erp_exist_status` = '0' WHERE `model`='" . $this->db->escape((string)$model) . "';";
//        $this->log->write($sql);
        $this->db->query($sql);
    }

    private function reconnectDatabase(): void {
        $this->registry->set('db', new \Opencart\System\Library\DB(
            DB_DRIVER,
            DB_HOSTNAME,
            DB_USERNAME,
            DB_PASSWORD,
            DB_DATABASE,
            DB_PORT,
            DB_SSL_KEY,
            DB_SSL_CERT,
            DB_SSL_CA
        ));
    }

    private function markProductsSynced(): void {
        $sql = "UPDATE `" . DB_PREFIX . "product` SET `erp_exist_status` = 1;";

        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            $connection_error = false;

            foreach (['Error No: 2002', 'Error No: 2006', 'Error No: 2013'] as $error_code) {
                if (strpos($e->getMessage(), $error_code) !== false) {
                    $connection_error = true;
                    break;
                }
            }

            if (!$connection_error) {
                throw $e;
            }

            $this->log->write('pushProductList: database connection lost after API call, reconnecting');
            $this->reconnectDatabase();
            $this->db->query($sql);
        }
    }

    public function pushProductList(){
        // 整表推送，EIS 处理需要时间，别让 PHP 的执行时间限制先把它杀掉
        @set_time_limit(0);

        // The upstream full-catalog request can run for up to 300 seconds.
        // Keep this session alive long enough to write the result back afterwards.
        $this->db->query("SET SESSION wait_timeout = 900");

        $product_list = $this->getProducts();

        $products = [];

        if(!$product_list){
            $this->log->write('pushProductList: no products found (config_language_id=' . (int)$this->config->get('config_language_id') . ')');
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
                $this->log->write('pushProductList: api user ' . $username . ' not found or disabled');
                return false;
            }

//            $http_host = "erp.mortch.cn:7777";
            $http_host = "mortch.sunny-eis.com";
            $php_self = "/mortch_api_abroad.php";
            $sync_key = $api_info['key'];

            $call = "getProductList";
            $max_attempts = 3;

            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                // Rebuild the signature on every attempt so a delayed retry cannot reuse an expired timestamp.
                $time = time();
                $string = (string)$call . "\n";
                $string .= $username . "\n";
                $string .= (string)$http_host . "\n";
                $string .= (!empty($php_self) ? rtrim(dirname($php_self), '/') . '/' : '/') . "\n";
                $string .= md5(http_build_query($products)) . "\n";
                $string .= $time . "\n";
                $signature = rawurlencode(base64_encode(hash_hmac('sha1', $string, $sync_key, 1)));
                $url = "http://".$http_host.$php_self."?call=".$call."&user_name=".$username."&time=".$time."&signature=".$signature;

                $result = $this->curlRequest($url, "POST", $products);
                if ($result !== null) {
                    $this->markProductsSynced();

                    foreach ($result as $model) {
                        $this->editProduct($model);
                    }

                    return true;
                }

                if ($attempt < $max_attempts) {
                    sleep($attempt * 10);
                }
            }

            $this->log->write('pushProductList: sync failed after ' . $max_attempts . ' attempts');
            return false;
        }



    }

    public function curlRequest($url,$method,$postData=null){
        $payload = json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($payload === false) {
            $this->log->write('pushProductList: request JSON encode failed: ' . json_last_error_msg());
            return null;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Content-Type: application/json; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $data = json_decode($response, true);

        if ($data && isset($data['code']) && $data['code'] == 0){
            if (isset($data['data'])){
                return $data['data'];
            }else{
                $this->log->write('pushProductList response error: HTTP ' . $httpCode . ', code=0, missing data');
                return null;
            }

        }else{
            if ($curlError){
                $this->log->write('pushProductList curl error ' . $curlErrno . ': ' . $curlError);
            }

            $message = is_array($data) && isset($data['code'])
                ? 'HTTP ' . $httpCode . ', code=' . (int)$data['code'] . ', msg=' . (isset($data['msg']) ? (string)$data['msg'] : '')
                : 'HTTP ' . $httpCode . ', invalid response, length=' . strlen((string)$response);

            $this->log->write('pushProductList response error: ' . $message);
            return null;
        }
    }

}
