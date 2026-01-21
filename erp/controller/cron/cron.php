<?php
namespace Opencart\Erp\Controller\Cron;
/**
 * Class Cron
 *
 * @package Opencart\Erp\Controller\Cron
 */
class Cron extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
	    $this->log->write('Cron');
		$time = time();

		$this->load->model('setting/cron');

		if (isset($this->request->get['code']) && $this->request->get['code']){
            $code = $this->request->get['code'];
            $result = $this->model_setting_cron->getCronByCode($code);
            if ($result) {
                $this->log->write($result);
                if(isset($this->request->get['order_id'])){
                    $this->load->controller($result['action'],[
                        'order_id' => $this->request->get['order_id']
                    ]);
                }else if(isset($this->request->get['customer_id'])){
                    $this->load->controller($result['action'],[
                        'customer_id' => $this->request->get['customer_id']
                    ]);
                }else{
                    $this->load->controller($result['action']);
                }

                $this->model_setting_cron->editCron($result['cron_id']);
            }

        }else{
            $results = $this->model_setting_cron->getCrons();
            foreach ($results as $result) {
                if ($result['status'] && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
                    if ($result['status']) {
                        $this->log->write($result['action']);
                        $this->load->controller($result['action']);

                        $this->model_setting_cron->editCron($result['cron_id']);
                    }
                }
            }
        }


	}

    public function test(){
        $this->load->model('sale/sendmsg');
////        $this->model_sale_sendmsg->packingDoneForCustomer(177,"123456");
////        $this->model_sale_sendmsg->shippedForCustomer(177,"顺丰","888888");
//
////        $this->load->model('sale/sendmsg');
//        $product = [];
//        $product['model'] = 'MICF0800-112000-20010';
//        $product['product_id'] = 2687;
//        $product_name = 'CF450/550/600/800/850 滤芯';
//        $this->model_sale_sendmsg->arrivalForUser($product,$product_name,2);
//        $this->model_sale_sendmsg->arrivalForCustomer($product,$product_name,2);

        $this->model_sale_sendmsg->reviewForUser(177);
        $this->model_sale_sendmsg->reviewForCustomer(177);
//        $product['model'] = "MICF0GS0-031000-10001";
//        $product['quantity'] = 2;
//        $product['price'] = 500;
//        $product['weight'] = 1.88;
//        $product['status'] = '在售';
//        $this->load->model('catalog/product');;
//        $product_info = $this->model_catalog_product->getProductByModel($product['model']);
//        if($product_info){
//            $this->model_catalog_product->editProduct($product_info['product_id'], $product);
//            if ($product_info['quantity']<=0 and $product['quantity']>0){
//                //当库存从0变成正数时，发送通知
//                $this->load->model('sale/sendmsg');
//                $product['product_id'] = $product_info['product_id'];
//                $this->model_sale_sendmsg->arrivalForUser($product,$product_info['name'],2);
//                $this->model_sale_sendmsg->arrivalForCustomer($product,$product_info['name'],2);
//            }
//        }
    }
}
