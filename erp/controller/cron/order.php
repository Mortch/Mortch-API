<?php
namespace Opencart\Erp\Controller\Cron;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('catalog/product');
 *
 * @package Opencart\Api\Controller\Catalog
 */
class Order extends \Opencart\System\Engine\Controller {
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

        $this->log->write('Cron order');
        $order_id = $this->request->get['order_id'];
        $this->load->model('cron/order');
//        $this->model_cron_order->pushOrderInfo($order_id);
        $count = 2;
        for ($i = 0; $i < $count; $i++){
            $this->log->write('order id ' . $order_id);
            $this->model_cron_order->pushOrderInfo($order_id);
            if($i < $count - 1){
                sleep(30);
            }
        }
    }
}
