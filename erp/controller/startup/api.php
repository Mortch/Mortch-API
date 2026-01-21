<?php
namespace Opencart\Erp\Controller\Startup;
/**
 * Class Api
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Api extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return \Opencart\System\Engine\Action|null
	 */
	public function index(): ?\Opencart\System\Engine\Action {
		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		} else {
			$route = '';
		}

		$allowed = [
			'catalog/product',
            'catalog/product.add',
            'sale/order.changeStatus',
		];

		$noVerify = ['cron/cron','cron/order','cron/cart','cron/sync','sync/order','cron/cron.test'];


		if (in_array($route,$noVerify)){
		    return null;
        }
        // Block direct access to other methods
        if ($route == !in_array($route, $allowed)) {

            return new \Opencart\System\Engine\Action('startup/api.permission');
        }


		if (in_array($route, $allowed)) {
			$status = true;

			$required = [
				'route',
				'call',
				'username',
				'store_id',
				'time',
				'signature'
			];

			foreach ($required as $key) {
				if (!isset($this->request->get[$key])) {
					$status = false;
				}
			}

			if ($status) {
				// API
				$this->load->model('user/api');

				$api_info = $this->model_user_api->getApiByUsername((string)$this->request->get['username']);
//				if ($api_info) {
//					// Check if IP is allowed
//					$ip_data = [];
//
//					$results = $this->model_user_api->getIps($api_info['api_id']);
//
//					foreach ($results as $result) {
//						$ip_data[] = trim($result['ip']);
//					}
//					if (!in_array(oc_get_ip(), $ip_data)) {
//						$status = false;
//					}
//				} else {
//					$status = false;
//				}

				$time = $this->request->get['time'];

				$time_start = time() - 450;
				$time_end = time() + 450;

				if ($time < $time_start && $time > $time_end) {
					$status = false;
				}
			}
			if ($status) {
			    $post = $this->request->post;
			    if (isset($post['linkurl'])){
			        unset($post['linkurl']);
                }
                if (isset($post['msgurl'])){
                    unset($post['msgurl']);
                }
				$string  = (string)$this->request->get['route'] . "\n";
				$string .= (string)$this->request->get['call'] . "\n";
				$string .= $api_info['username'] . "\n";
				$string .= (string)$this->request->server['HTTP_HOST'] . "\n";
				$string .= (!empty($this->request->server['PHP_SELF']) ? rtrim(dirname($this->request->server['PHP_SELF']), '/') . '/' : '/') . "\n";
				$string .= (int)$this->request->get['store_id'] . "\n";
				if($this->request->post){
                    $string .= md5(http_build_query($post)) . "\n";
                }
				$string .= $time . "\n";
				if (rawurldecode($this->request->get['signature']) != base64_encode(hash_hmac('sha1', $string, $api_info['key'], 1))) {
					$status = false;
				}
			}
			if ($status) {
				$this->model_user_api->addHistory($api_info['api_id'], $this->request->get['call'], oc_get_ip());
			} else {
				return new \Opencart\System\Engine\Action('startup/api.permission');
			}
		}

		return null;
	}

	/**
	 * Permission
	 *
	 * @return void
	 */
	public function permission(): void {
		$this->language->load('error/permission');

		$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
		$this->response->setOutput(json_encode(['error' => $this->language->get('text_error')]));
	}
}
