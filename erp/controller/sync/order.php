<?php
namespace Opencart\Erp\Controller\Sync;
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
        $this->load->model('cron/order');
        $order_list= $this->model_cron_order->getUnSyncOrders();
        if ($order_list){
            foreach ($order_list as $order_info){
                if (!$this->model_cron_order->pushOrderInfo($order_info['order_id'])){
                    $this->log->write('Order sync failed: order_id=' . (int)$order_info['order_id']);
                }
            }
        }
    }
}
