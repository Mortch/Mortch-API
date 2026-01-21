<?php
namespace Opencart\Catalog\Controller\Catalog;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('catalog/product');
 *
 * @package Opencart\Api\Controller\Catalog
 */
class Product extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function info(): void {
        $this->load->language('product');

        $post_info = $this->request->post;

        $json = [];

        if (!isset($post_info['product']) || !$post_info['product']){
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
        }

        $product_list = $this->request->post['product'];
        if(!$product_list){
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
        }

        if (!$json) {
            $this->load->model('catalog/product');

            $this->log->write($product_list);
            foreach ($product_list as $product){
                if ($product['product_id']) {
                    $product_info = $this->model_catalog_product->getProductByProductId($product['product_id']);
                    $this->log->write($product);
                    if($product_info){
                        $this->model_catalog_product->editProduct($product_info['product_id'], $product);
                    }else{
                        $this->model_catalog_product->addProduct($product, $store_id);
                    }
                }
            }
            $json['code'] = 0;
            $json['success'] = $this->language->get('text_success');
        }



        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
	}

	/**
	 * List
	 *
	 * @return void
	 */
	public function list(): void {
        $this->load->language('product');

        $post_info = $this->request->post;

        $json = [];

//        if (!isset($post_info['product']) || !$post_info['product']){
//            $json['code'] = 1;
//            $json['error'] = $this->language->get('error_warning');
//        }
//
//        $product = $this->request->post['product'];
//        if(!$product){
//            $json['code'] = 1;
//            $json['error'] = $this->language->get('error_warning');
//        }

        if (!$json) {
            $this->load->model('catalog/product');

//            $product = explode(",",$product);

            $product_list = $this->model_catalog_product->getProductsByModel();

            $json['code'] = 0;
            $json['data'] = $product_list;
            $json['success'] = $this->language->get('text_success');
        }


        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
	public function add(): void {
	    $this->log->write('add');
		$this->load->language('catalog/product');

		$json = [];

        $post_list = $this->request->post;

        $this->log->write($post_list);
		if (!$post_list || !isset($post_list['products'])) {
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
		}

		$store_id = $this->request->get('store_id');
		if (!$json) {
            $this->load->model('catalog/product');
		    foreach ($post_list['products'] as $product){
                if ($product['model']) {
                    $product_info = $this->model_catalog_product->getProductByModel($product['model']);
                    $this->log->write($product);
                    if($product_info){
                        $this->model_catalog_product->editProduct($product_info['product_id'], $product);
                    }else{
                        $this->model_catalog_product->addProduct($product, $store_id);
                    }
                }
            }
            $json['code'] = 0;
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}


}
