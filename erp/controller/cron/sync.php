<?php
namespace Opencart\Erp\Controller\Cron;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('catalog/product');
 *
 * @package Opencart\Api\Controller\Catalog
 */
class Sync extends \Opencart\System\Engine\Controller {
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

        $this->log->write('Cron sync');
        $this->load->model('cron/sync');
        $result = $this->model_cron_sync->getSyncs();
        foreach ($result as $sync){
            $this->log->write('Cron sync ' . $sync['sync_id']);
            if ($sync['code'] == 'cart'){
                $this->load->model('cron/cart');
                $res = $this->model_cron_cart->pushCartInfo($sync['value']);
            }
            if ($sync['code'] == 'order'){
                $this->load->model('cron/order');
                $res = $this->model_cron_order->pushOrderInfo($sync['value']);
            }
            if ($res){
                $this->model_cron_sync->updateSync($sync['sync_id']);
            }
        }
    }




}
