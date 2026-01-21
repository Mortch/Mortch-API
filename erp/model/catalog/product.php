<?php
namespace Opencart\Erp\Model\Catalog;
/**
 * Class Product
 *
 * Can be loaded using $this->load->model('product');
 *
 * @package Opencart\Api\Model
 */
class Product extends \Opencart\System\Engine\Model {


    public function addProduct(array $data,$store_id = 0): int {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET  `model` = '" . $this->db->escape((string)$data['model']) . "', `quantity` = '" . (int)$data['quantity'] . "', `price` = '" . (float)$data['price'] . "', `weight` = '" . (float)$data['weight'] . "', `length` = '" . (float)$data['length'] . "', `width` = '" . (float)$data['width'] . "', `height` = '" . (float)$data['height'] . "', `status` = '" . (bool)($data['status'] ?? 0) . "', `date_added` = NOW(), `date_modified` = NOW()");

        $product_id = $this->db->getLastId();

        // Description

        $this->model_catalog_product->addDescription($product_id, 1, $data['name_1']);
        $this->model_catalog_product->addDescription($product_id, 2, $data['name_2']);


        // Stores
        $this->model_catalog_product->addStore($product_id, $store_id);

        return $product_id;
    }
    public function addStore(int $product_id, int $store_id): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET `product_id` = '" . (int)$product_id . "', `store_id` = '" . (int)$store_id . "'");
    }
    public function addDescription(int $product_id, int $language_id, string $name): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET `product_id` = '" . (int)$product_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $this->db->escape($name) . "'");
    }

	/**
	 * Edit Product
	 *
	 * Edit product record in the database.
	 *
	 * @param int                  $product_id primary key of the product record
	 * @param array<string, mixed> $data       array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $product_data = [
	 *     'quantity'                      => 1,
	 *     'price'                         => 1.00,
	 * ];
	 *
	 * $this->load->model('catalog/product');
	 *
	 * $this->model_catalog_product->editProduct($product_id, $product_data);
	 */
	public function editProduct(int $product_id, array $data): void {
	    $sql = "UPDATE `" . DB_PREFIX . "product` SET `product_id`='".(int)$product_id."'";
		if (isset($data['quantity'])){
		    $sql.=",`quantity` = '" . (int)$data['quantity'] . "'";
        }
        if (isset($data['price'])){
            $sql.=",`price` = '" . (float)$data['price'] . "'";
        }
        if (isset($data['model'])){
            $sql.=",`model` = '" . $this->db->escape((string)$data['model']) . "'";
        }
        if (isset($data['weight'])){
            $sql.=",`weight` = '" . (float)$data['weight'] . "'";
        }
        if (isset($data['length'])){
            $sql.=",`length` = '" . (float)$data['length'] . "'";
        }
        if (isset($data['width'])){
            $sql.=",`width` = '" . (float)$data['width'] . "'";
        }
        if (isset($data['height'])){
            $sql.=",`height` = '" . (float)$data['height'] . "'";
        }
        if (isset($data['status']) && $data['status']=='停售'){
            $sql.=",`erp_status` = 0";
        }
        if (isset($data['status']) && $data['status']=='在售'){
            $sql.=",`erp_status` = 1";
        }
        $sql.=" WHERE `product_id` = '" . (int)$product_id . "'";
        $this->log->write($sql);
	    $this->db->query($sql);
	}

	/**
	 * Get Product
	 *
	 * Get the record of the product record in the database.
	 *
	 * @param int $product_id primary key of the product record
	 *
	 * @return array<string, mixed> product record that has product ID
	 *
	 * @example
	 *
	 * $this->load->model('catalog/product');
	 *
	 * $product_info = $this->model_catalog_product->getProduct($product_id);
	 */
	public function getProduct(int $product_id): array {
		$product_data = [];

		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `p`.`product_id` = '" . (int)$product_id . "' AND `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		if ($query->num_rows) {
			$product_data = $query->row;

			$product_data['variant'] = $product_data['variant'] ? json_decode($product_data['variant'], true) : [];
			$product_data['override'] = $product_data['override'] ? json_decode($product_data['override'], true) : [];
		}

		return $product_data;
	}

    /**
     * Get Product
     *
     * Get the record of the product record in the database.
     *
     * @param string $model primary key of the product record
     *
     * @return array<string, mixed> product record that has product ID
     *
     * @example
     *
     * $this->load->model('catalog/product');
     *
     * $product_info = $this->model_catalog_product->getProduct($product_id);
     */
    public function getProductByModel(string $model): array {
        $product_data = [];

//        echo "SELECT DISTINCT * FROM `" . DB_PREFIX . "product`  WHERE `model` = '" . $model . "';";exit;
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` `p` LEFT JOIN `" . DB_PREFIX . "product_description` `pd` ON (`p`.`product_id` = `pd`.`product_id`) WHERE `pd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND `p`.`model` = '" . $model . "';");


        if ($query->num_rows) {
            $product_data = $query->row;
        }

        return $product_data;
    }

    /**
     * Get Product
     *
     * Get the record of the product record in the database.
     *
     * @param string $model primary key of the product record
     *
     * @return array<string, mixed> product record that has product ID
     *
     * @example
     *
     * $this->load->model('catalog/product');
     *
     * $product_info = $this->model_catalog_product->getProduct($product_id);
     */
    public function getProductsByModel(): array {
//        echo "SELECT DISTINCT * FROM `" . DB_PREFIX . "product`  WHERE `model` = '" . $model . "';";exit;
        $query = $this->db->query("SELECT `p`.`model`, `p`.`price`, `p`.`quantity` FROM `" . DB_PREFIX . "product` `p` ;");
//        echo "SELECT  `p`.`model`, `p`.`price`, `p`.`quantity` FROM `" . DB_PREFIX . "product` `p` ;";

        return $query->rows;
    }


}
