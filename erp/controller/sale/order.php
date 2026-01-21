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
	    $this->log->write('changeStatus');

        $this->load->language('sale/order');

		$json = [];

        $post_list = $this->request->post;

        $this->log->write($post_list);
		if (!$post_list) {
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
		}

		if (!$json) {
		    $order_id = $post_list['order_id'];
            $this->load->model('sale/order');
            $order_info = $this->model_sale_order->getOrder($order_id);
            if(!$order_info){
                $json['code'] = 1;
                $json['error'] = $this->language->get('error_warning');
            }

            $order_status = $post_list['order_status'];
            $order_status_info = $this->model_sale_order->getOrderStatus($order_status);

            if ($order_status == 'Order Approved'){
               
            }
            if($order_status == 'Packing Done' && $order_info['order_status'] != 'Packing Done'){

                $order_history_id = $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],"",1);

                $order_history['package_info'] = $post_list['comment'];
                $this->model_sale_order->addOrderHistory((int)$order_history_id,(int)$order_id,1,$order_history);

                $order_status_info = $this->model_sale_order->getOrderStatus("Documents");
                $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],$this->language->get('text_openlink')."<br><a href='".$post_list['linkurl']."'>".$post_list['linkurl']."</a>",1);
            }


            if($order_status == 'Shipped'){

                $comment = "";
                if ($post_list['linkurl']){
                    $comment="<a href='".$post_list['linkurl']."'>".$post_list['linkurl']."</a>";
                }
                $order_history_id = $this->model_sale_order->addHistory($order_id,$order_status_info['order_status_id'],$comment,1);

                $order_history['shipping_name'] = $post_list['shipping_name'];
                $order_history['shipping_no'] = $post_list['shipping_no'];
                $this->model_sale_order->addOrderHistory((int)$order_history_id,(int)$order_id,1,$order_history);
                if (strtolower($post_list['shipping_name']) == 'fedex' || strtolower($post_list['shipping_name']) == 'dhl' ){
                    $this->model_sale_order->addOrderHistoryForPaypal((int)$order_id,$order_history);
                }

            }

            if($order_status == 'Canceled' && $order_info['order_status'] != 'Canceled'){

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
