<?php
namespace Opencart\Catalog\Controller\Sync;
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
     * @return void
     */
    public function category(): void {
        $this->load->language('sync/sync');


        $json = [];

        $category_info = $this->request->post;
        if(!$category_info){
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
        }

        if (!$json) {
            $this->load->model('sync/category');

            if ($category_info['category_id']) {
                $category_date = $this->model_sync_category->getCategoryByCategoryId($category_info['category_id']);
                if($category_date){
                    $this->model_sync_category->editCategory($category_info['category_id'],$category_info);
                }else{
                    $this->model_sync_category->addCategory($category_info);
                }
            }
            $json['code'] = 0;
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

	/**
	 * Index
	 *
	 * @return void
	 */
	public function product(): void {
        $this->load->language('sync/sync');


        $json = [];

        $product_info = $this->request->post;
        if(!$product_info){
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
        }

        if (!$json) {
            $this->load->model('sync/product');

            if ($product_info['product_id']) {
                $product_data = $this->model_sync_product->getProductByProductId($product_info['product_id']);
                if($product_data){
                    $this->model_sync_product->editProduct($product_info['product_id'], $product_info);
                }else{
                    $this->model_sync_product->addProduct($product_info);
                }
            }
            $json['code'] = 0;
            $json['success'] = $this->language->get('text_success');
        }



        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
	}

    /**
     * Index
     *
     * @return void
     */
    public function atlas(): void {
        $this->load->language('sync/sync');


        $json = [];

        $atlas_info = $this->request->post;
        if(!$atlas_info){
            $json['code'] = 1;
            $json['error'] = $this->language->get('error_warning');
        }

        if (!$json) {
            $this->load->model('sync/atlas');

            if ($atlas_info['atlas_id']) {
                $atlas_data = $this->model_sync_atlas->getAtlasByAtlasId($atlas_info['atlas_id']);
                if($atlas_data){
                    $this->model_sync_atlas->editAtlas($atlas_info['atlas_id'], $atlas_info);
                }else{
                    $this->model_sync_atlas->addAtlas($atlas_info);
                }
            }
            $json['code'] = 0;
            $json['success'] = $this->language->get('text_success');
        }



        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

}
