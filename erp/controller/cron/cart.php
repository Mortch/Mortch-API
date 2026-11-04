<?php
namespace Opencart\Erp\Controller\Cron;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('catalog/product');
 *
 * @package Opencart\Api\Controller\Catalog
 */
class Cart extends \Opencart\System\Engine\Controller {
    /**
     * Index
     *
     * @param int    $cron_id
     * @param string $code
     * @param string $cycle
     * @param string $date_added
     * @param string $date_modified
     *
     * @return void
     */
    public function index(): void {
        // Extension

        $customer_id = $this->request->get['customer_id'];
        $this->load->model('cron/cart');
        $this->model_cron_cart->pushCartInfo($customer_id);
        /* 20260717关闭
        $count = 2;
        for ($i = 0; $i < $count; $i++){
            $this->log->write('customer id ' . $customer_id);
            $this->model_cron_cart->pushCartInfo($customer_id);
            if($i < $count - 1){
                sleep(30);
            }
        }
        */
    }
}
