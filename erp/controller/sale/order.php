<?php
namespace Opencart\Erp\Controller\Sale;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('sale/order');
 *
 * @package Opencart\Api\Controller\Sale
 */
class Order extends \Opencart\System\Engine\Controller {

	/**
	 * Save
	 *
	 * @return void
	 */
	public function changeStatus(): void {
        $this->load->language('sale/order');

		$json = [];

        $post_list = $this->request->post;

		if (!$post_list) {
            $this->log->write('changeStatus failed: empty request');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['code' => 1, 'error' => $this->language->get('error_warning')]));
            return;
		}

		if (!$json) {
		    $order_id = (int)$post_list['order_id'];
            $this->load->model('sale/order');
            $order_info = $this->model_sale_order->getOrder($order_id);
            if(!$order_info){
                $this->log->write('changeStatus failed: order not found, order_id=' . $order_id);
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['code' => 1, 'error' => $this->language->get('error_warning')]));
                return;
            }

            $order_status = isset($post_list['order_status']) ? (string)$post_list['order_status'] : '';
            $order_status_info = $this->model_sale_order->getOrderStatus($order_status);
            if (!$order_status_info){
                $this->log->write('changeStatus failed: unknown order status "' . $order_status . '", order_id=' . $order_id);
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode(['code' => 1, 'error' => $this->language->get('error_warning')]));
                return;
            }

            $order_history_info = [];
            if ($order_status_info){
                $order_history_info = $this->model_sale_order->getHistory($order_id,$order_status_info['order_status_id']);;
            }

            if ($order_status == 'Order Approved' && !$order_history_info){
               
            }
            if($order_status == 'Packing Done' && $order_info['order_status'] != 'Packing Done' && !$order_history_info){

                $order_history_id = $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],"",1);

                $order_history['package_info'] = $post_list['comment'];
                $this->model_sale_order->addOrderHistory((int)$order_history_id,(int)$order_id,1,$order_history);

                $order_status_info = $this->model_sale_order->getOrderStatus("Documents");
                $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],$this->language->get('text_openlink')."<br><a href='".$post_list['linkurl']."'>".$post_list['linkurl']."</a>",1);
            }


            if($order_status == 'Shipped' && !$order_history_info){

                $comment = "";
                if ($post_list['linkurl']){
                    $comment="<a href='".$post_list['linkurl']."'>".$post_list['linkurl']."</a>";
                }

                // FedEx / DHL 交寄时，先同步一条 DOCUMENTS 消息（内容为 AW 空运单下载链接），再同步 Shipped
                $shipping_name = isset($post_list['shipping_name']) ? strtolower(trim((string)$post_list['shipping_name'])) : '';

                if ($shipping_name == 'fedex' || $shipping_name == 'dhl'){
                    $documents_status_info = $this->model_sale_order->getOrderStatus('Documents');

                    if ($documents_status_info && $order_info['invoice_no']){
                        $invoice_no = $order_info['invoice_prefix'] . $order_info['invoice_no'];
                        $aw_url = 'http://www.mortch.com/orders/' . $invoice_no . 'AW.pdf';
                        $documents_comment = $this->language->get('text_download_aw') . " <a href='" . $aw_url . "'>" . $aw_url . "</a>";

                        $this->model_sale_order->addHistory($order_id,$documents_status_info['order_status_id'],$documents_comment,1);
                    }
                }

                $order_history_id = $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],$comment,1);

                $order_history['shipping_name'] = $post_list['shipping_name'];
                $order_history['shipping_no'] = $post_list['shipping_no'];
                $this->model_sale_order->addOrderHistory((int)$order_history_id,(int)$order_id,1,$order_history);

                // 推送 PayPal 跟踪号：FedEx / DHL / SFC / Yanwen / YunExpress
                $paypal_shipping_name = strtolower(preg_replace('/[^a-z0-9]/i','',(string)$post_list['shipping_name']));

                if (in_array($paypal_shipping_name, ['fedex','fedexexpress','dhl','dhlexpress','sfc','sfcexpress','sfcservice','yanwen','yanwenlogistics','yunexpress'])){
                    $this->model_sale_order->addOrderHistoryForPaypal((int)$order_id,$order_history);
                }

            }

            if($order_status == 'Canceled' && $order_info['order_status'] != 'Canceled' && !$order_history_info){

                $comment = "";
                $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],$comment,1);

            }

            $json['code'] = 0;
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}



}
