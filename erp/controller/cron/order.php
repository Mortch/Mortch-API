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
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        $sync_id = isset($this->request->get['sync_id']) ? (int)$this->request->get['sync_id'] : 0;
        $wait = isset($this->request->get['wait']) && (int)$this->request->get['wait'] === 1;

        if (!$order_id) {
            return;
        }

        $this->load->model('cron/order');
        $this->load->model('cron/sync');

        if ($wait) {
            // 管理端手动同步：立即执行并等待结果
            $this->log->write('Cron order wait sync: ' . $order_id);
            $result = $this->model_cron_order->pushOrderInfo($order_id);

            if ($result && $sync_id) {
                $this->model_cron_sync->updateSync($sync_id);
            }

            return;
        }

        // 新订单自动同步：先把响应返回给调用方，再在后台延迟同步
        ignore_user_abort(true);
        set_time_limit(0);

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['success' => true]));
        $this->response->output();
        $this->response->setOutput('');

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }

        // 第一次 30 秒后同步，失败则每 60 秒重试
        $delays = [30, 60, 60, 60, 60];

        foreach ($delays as $index => $delay) {
            sleep($delay);

            $attempt = $index + 1;
            $this->log->write("Order async sync attempt {$attempt}: order_id={$order_id}");

            $result = $this->model_cron_order->pushOrderInfo($order_id);

            if ($result) {
                if ($sync_id) {
                    $this->model_cron_sync->updateSync($sync_id);
                }

                $this->log->write("Order async sync success: order_id={$order_id}, attempt={$attempt}");

                return;
            }

            $this->log->write("Order async sync failed: order_id={$order_id}, attempt={$attempt}");
        }

        $this->log->write("Order async sync gave up: order_id={$order_id}");
    }
}
