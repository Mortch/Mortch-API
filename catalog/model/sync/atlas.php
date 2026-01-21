<?php
namespace Opencart\Catalog\Model\Sync;

/**
 * Class Altas
 *
 * Can be loaded using $this->load->model('catalog/atlas');
 *
 * @package Opencart\Admin\Model\Catalog
 */
class Atlas extends \Opencart\System\Engine\Model {


    public function getAtlasByAtlasId(int $atlas_id): array {
        $atlas_data = [];
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "atlas` `c` WHERE `c`.`atlas_id` = '" . $atlas_id . "';");


        if ($query->num_rows) {
            $atlas_data = $query->row;
        }

        return $atlas_data;
    }

    /**
	 * Add atlas
	 *
	 * Create a new atlas record in the database.
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return int returns the primary key of the new atlas record
	 *
	 * @example
	 *
	 * $atlas_data = [
	 *     'atlas_description'           => [],
	 *     'atlas_attribute_description' => [],
	 *     'master_id'                     => 'Master ID',
	 *     'model'                         => 'atlas Model',
	 *     'sort_order'                    => 0,
	 * ];
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $atlas_id = $this->model_sync_atlas->addatlas($atlas_data);
	 */
	public function addAtlas(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "atlas` SET `atlas_id` = '" . (int)$data['atlas_id'] . "',`model` = '" . $this->db->escape((string)$data['model']) . "', `status` = '" . (bool)($data['status'] ?? 0) . "',  `sort_order` = '" . (int)$data['sort_order'] . "', `date_added` = NOW(), `date_modified` = NOW()");

		$atlas_id = $data['atlas_id'];

		if ($data['image']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "atlas` SET `image` = '" . $this->db->escape((string)$data['image']) . "' WHERE `atlas_id` = '" . (int)$atlas_id . "'");
		}

		// Description
		foreach ($data['atlas_description'] as $language_id => $value) {
			$this->model_sync_atlas->addDescription($atlas_id, $language_id, $value);
		}

        $this->load->model('setting/store');
        $stores = $this->model_setting_store->getStores();

		// Stores
		if (isset($data['atlas_store'])) {
            foreach ($stores as $store) {
                if (!in_array($store['store_id'], $data['atlas_store'])) {
                    array_push($data['atlas_store'], $store['store_id']);
                }
            }
			foreach ($data['atlas_store'] as $store_id) {
				$this->model_sync_atlas->addStore($atlas_id, $store_id);
			}
		}

        // Categories
        if (isset($data['atlas_category'])) {
            foreach ($data['atlas_category'] as $category_id) {
                $this->model_sync_atlas->addCategory($atlas_id, $category_id);

            }
        }

		// Related
		if (isset($data['atlas_related'])) {
            $atlas_sno = [];
            if(isset($data['atlas_sno'])){
                $atlas_sno = $data['atlas_sno'];
            }

			foreach ($data['atlas_related'] as $key=>$related_id) {
				$this->model_sync_atlas->addRelated($atlas_id, $related_id,$atlas_sno[$key]);
			}
		}

        if (isset($data['atlas_snos'])) {
            $model = [];
            if(isset($data['atlas_models'])){
                $model = $data['atlas_models'];
            }
            $atlas_related_temp = [];
            if(isset($data['atlas_related_temp'])){
                $atlas_related_temp = $data['atlas_related_temp'];
            }

            foreach ($data['atlas_snos'] as $key=>$sno) {
                if($atlas_related_temp && $atlas_related_temp[$key]){
                    $this->model_sync_atlas->addRelated($atlas_id, $atlas_related_temp[$key],$sno);
                }else{
                    $this->model_sync_atlas->addRelatedTemp($atlas_id, $sno,$model[$key]);
                }

            }
        }
        // Point
        $this->deleteMarkers($atlas_id);

        if (isset($data['atlas_marker_sno'])) {
            $atlas_sno = $data['atlas_marker_sno'];
            if(isset($data['atlas_marker_top'])){
                $atlas_top = $data['atlas_marker_top'];
            }
            if(isset($data['atlas_marker_left'])){
                $atlas_left = $data['atlas_marker_left'];
            }
            foreach ($atlas_sno as $key=>$sno) {
                $this->addMarker($atlas_id, $sno,$atlas_top[$key],$atlas_left[$key]);
            }
        }

		// SEO
		if (isset($data['atlas_seo_url'])) {
			$this->load->model('design/seo_url');

            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
            $language_list = [];
            foreach ($languages as $language){
                array_push($language_list, $language['language_id']);
            }

			foreach ($data['atlas_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
                    if (in_array($language_id, $language_list)) {
                        $this->model_design_seo_url->addSeoUrl('atlas_id', $atlas_id, $keyword, $store_id, $language_id);
                        foreach ($stores as $store) {
                            $this->model_design_seo_url->addSeoUrl('atlas_id', $atlas_id, $keyword, $store['store_id'], $language_id);
                        }
                    }
				}
			}
		}


		$this->cache->delete('atlas');

		return $atlas_id;
	}

	/**
	 * Edit atlas
	 *
	 * Edit atlas record in the database.
	 *
	 * @param int                  $atlas_id primary key of the atlas record
	 * @param array<string, mixed> $data       array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $atlas_data = [
	 *     'atlas_description'           => [],
	 *     'atlas_attribute_description' => [],
	 *     'master_id'                     => 'Master ID',
	 *     'model'                         => 'atlas Model',
	 *     'sku'                           => 'atlas Sku',
	 *     'upc'                           => 'atlas Upc',
	 *     'ean'                           => 'atlas Ean',
	 *     'jan'                           => 'atlas Jan',
	 *     'isbn'                          => 'atlas Isbn',
	 *     'mpn'                           => 'atlas Mpn',
	 *     'location'                      => 'Location',
	 *     'variant'                       => [],
	 *     'override'                      => [],
	 *     'quantity'                      => 1,
	 *     'minimum'                       => 1,
	 *     'subtract'                      => 0,
	 *     'stock_status_id'               => 1,
	 *     'date_available'                => '2021-01-01',
	 *     'manufacturer_id'               => 0,
	 *     'shipping'                      => 0,
	 *     'price'                         => 1.00,
	 *     'points'                        => 0,
	 *     'weight'                        => 0.00000000,
	 *     'weight_class_id'               => 0,
	 *     'length'                        => 0.00000000,
	 *     'length_class_id'               => 0,
	 *     'status'                        => 1,
	 *     'tax_class_id'                  => 0,
	 *     'sort_order'                    => 0,
	 * ];
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->editatlas($atlas_id, $atlas_data);
	 */
	public function editAtlas(int $atlas_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "atlas` SET `model` = '" . $this->db->escape((string)$data['model']) . "', `image` = '" . $this->db->escape((string)$data['image']) . "',  `status` = '" . (bool)($data['status'] ?? 0) . "', `sort_order` = '" . (int)$data['sort_order'] . "', `date_modified` = NOW() WHERE `atlas_id` = '" . (int)$atlas_id . "'");

		// Description
		$this->model_sync_atlas->deleteDescriptions($atlas_id);

		foreach ($data['atlas_description'] as $language_id => $value) {
			$this->model_sync_atlas->addDescription($atlas_id, $language_id, $value);
		}


		// Stores
		$this->model_sync_atlas->deleteStores($atlas_id);

        $this->load->model('setting/store');
        $stores = $this->model_setting_store->getStores();

		if (isset($data['atlas_store'])) {
            foreach ($stores as $store) {
                if (!in_array($store['store_id'], $data['atlas_store'])) {
                    array_push($data['atlas_store'], $store['store_id']);
                }
            }
			foreach ($data['atlas_store'] as $store_id) {
				$this->model_sync_atlas->addStore($atlas_id, $store_id);
			}
		}

        // Categories
        $this->model_sync_atlas->deleteCategories($atlas_id);

        if (isset($data['atlas_category'])) {
            foreach ($data['atlas_category'] as $category_id) {
                $this->model_sync_atlas->addCategory($atlas_id, $category_id);
            }
        }


		// Related
		$this->model_sync_atlas->deleteRelated($atlas_id);

		if (isset($data['atlas_related'])) {
            $atlas_sno = [];
            if(isset($data['atlas_sno'])){
                $atlas_sno = $data['atlas_sno'];
            }
			foreach ($data['atlas_related'] as $key=>$related_id) {
				$this->model_sync_atlas->addRelated($atlas_id, $related_id,$atlas_sno[$key]);
			}
		}

        // Related
        $this->model_sync_atlas->deleteRelatedTemp($atlas_id);

        // Related
        if (isset($data['atlas_snos'])) {
            $model = [];
            if(isset($data['atlas_models'])){
                $model = $data['atlas_models'];
            }
            $atlas_related_temp = [];
            if(isset($data['atlas_related_temp'])){
               $atlas_related_temp = $data['atlas_related_temp'];
            }

            foreach ($data['atlas_snos'] as $key=>$sno) {
                if($atlas_related_temp && $atlas_related_temp[$key]){
                    $this->model_sync_atlas->addRelated($atlas_id, $atlas_related_temp[$key],$sno);
                }else{
                    $this->model_sync_atlas->addRelatedTemp($atlas_id, $sno,$model[$key]);
                }

            }
        }

        // Point
        $this->deleteMarkers($atlas_id);

        if (isset($data['atlas_marker_sno'])) {
            $atlas_sno = $data['atlas_marker_sno'];
            if(isset($data['atlas_marker_top'])){
                $atlas_top = $data['atlas_marker_top'];
            }
            if(isset($data['atlas_marker_left'])){
                $atlas_left = $data['atlas_marker_left'];
            }
            foreach ($atlas_sno as $key=>$sno) {
                $this->addMarker($atlas_id, $sno,$atlas_top[$key],$atlas_left[$key]);
            }
        }

		// SEO
        $this->load->model('design/seo_url');
		$this->model_design_seo_url->deleteSeoUrlsByKeyValue('atlas_id', $atlas_id);

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();
        $language_list = [];
        foreach ($languages as $language){
            array_push($language_list, $language['language_id']);
        }

		if (isset($data['atlas_seo_url'])) {
			foreach ($data['atlas_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
				    if (in_array($language_id, $language_list)){
                        $this->model_design_seo_url->addSeoUrl('atlas_id', $atlas_id, $keyword, $store_id, $language_id);
                        foreach ($stores as $store){
                            $this->model_design_seo_url->addSeoUrl('atlas_id', $atlas_id, $keyword, $store['store_id'], $language_id);
                        }
                    }

				}
			}
		}


		$this->cache->delete('atlas');
	}


	/**
	 * Delete atlas
	 *
	 * Delete atlas record in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteatlas($atlas_id);
	 */
	public function deleteAtlas(int $atlas_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas` WHERE `atlas_id` = '" . (int)$atlas_id . "'");


		$this->model_sync_atlas->deleteDescriptions($atlas_id);
        $this->model_sync_atlas->deleteCategories($atlas_id);
		$this->model_sync_atlas->deleteRelated($atlas_id);
        $this->model_sync_atlas->deleteRelatedTemp($atlas_id);
		$this->model_sync_atlas->deleteStores($atlas_id);
        $this->model_sync_atlas->deleteMarkers($atlas_id);


		// SEO
		$this->load->model('design/seo_url');

		$this->model_design_seo_url->deleteSeoUrlsByKeyValue('atlas_id', $atlas_id);


		$this->cache->delete('atlas');
	}

	/**
	 * Add Description
	 *
	 * Create a new atlas description record in the database.
	 *
	 * @param int                  $atlas_id  primary key of the atlas record
	 * @param int                  $language_id primary key of the language record
	 * @param array<string, mixed> $data        array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $atlas_data['atlas_description'] = [
	 *     'name'             => 'atlas Name',
	 *     'description'      => 'atlas Description',
	 *     'tag'              => 'atlas Tag',
	 *     'meta_title'       => 'Meta Title',
	 *     'meta_description' => 'Meta Description',
	 *     'meta_keyword'     => 'Meta Keyword'
	 * ];
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->addDescription($atlas_id, $language_id, $atlas_data);
	 */
	public function addDescription(int $atlas_id, int $language_id, array $data): void {
        $description = $this->db->escape($data['description']);
        $description = str_replace("amp;",'',$description);
        $name = $this->db->escape($data['name']);
        $name = str_replace("amp;",'',$name);
        $tag = $this->db->escape($data['tag']);
        $tag = str_replace("amp;",'',$tag);
        $meta_title = $this->db->escape($data['meta_title']);
        $meta_title = str_replace("amp;",'',$meta_title);
        $meta_description = $this->db->escape($data['meta_description']);
        $meta_description = str_replace("amp;",'',$meta_description);
        $meta_keyword = $this->db->escape($data['meta_keyword']);
        $meta_keyword = str_replace("amp;",'',$meta_keyword);
		$this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_description` SET `atlas_id` = '" . (int)$atlas_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $name . "', `description` = '" . $description . "', `tag` = '" . $tag . "', `meta_title` = '" . $meta_title . "', `meta_description` = '" . $meta_description . "', `meta_keyword` = '" . $meta_keyword . "'");
	}

	/**
	 * Delete Descriptions
	 *
	 * Delete atlas description records in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteDescriptions($atlas_id);
	 */
	public function deleteDescriptions(int $atlas_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_description` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
	}

	/**
	 * Delete Descriptions By Language ID
	 *
	 * Delete atlas descriptions by language records in the database.
	 *
	 * @param int $language_id primary key of the language record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteDescriptionsByLanguageId($language_id);
	 */
	public function deleteDescriptionsByLanguageId(int $language_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_description` WHERE `language_id` = '" . (int)$language_id . "'");
	}

    /**
     * Add Category
     *
     * Create a new product category record in the database.
     *
     * @param int $atlas_id  primary key of the product record
     * @param int $category_id primary key of the category record
     *
     * @return void
     *
     * @example
     *
     * $this->load->model('catalog/product');
     *
     * $this->model_sync_atlas->addCategory($product_id, $category_id);
     */
    public function addCategory(int $atlas_id, int $category_id): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_to_category` SET `atlas_id` = '" . (int)$atlas_id . "', `category_id` = '" . (int)$category_id . "'");
    }

    /**
     * Delete Categories
     *
     * Delete product category records in the database.
     *
     * @param int $atlas_id primary key of the product record
     *
     * @return void
     *
     * @example
     *
     * $this->load->model('catalog/product');
     *
     * $this->model_sync_atlas->deleteCategories($product_id);
     */
    public function deleteCategories(int $atlas_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_to_category` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
    }

    /**
     * Delete Categories By Category ID
     *
     * Delete categories by category record in the database.
     *
     * @param int $category_id primary key of the category record
     *
     * @return void
     *
     * @example
     *
     * $this->load->model('catalog/product');
     *
     * $this->model_sync_atlas->deleteCategoriesByCategoryId($category_id);
     */
    public function deleteCategoriesByCategoryId(int $category_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_to_category` WHERE `category_id` = '" . (int)$category_id . "'");
    }



	/**
	 * Add Image
	 *
	 * Create a new atlas image record in the database.
	 *
	 * @param int                  $atlas_id primary key of the atlas record
	 * @param array<string, mixed> $data       array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $atlas_data['atlas_image'] = [
	 *     'image'      => 'atlas_image',
	 *     'sort_order' => 0
	 * ];
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->addImage($atlas_id, $atlas_data);
	 */
	public function addImage(int $atlas_id, array $data): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_image` SET `atlas_id` = '" . (int)$atlas_id . "', `image` = '" . $this->db->escape($data['image']) . "', `sort_order` = '" . (int)$data['sort_order'] . "'");
	}

	/**
	 * Delete Images
	 *
	 * Delete atlas image records in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteImages($atlas_id);
	 */
	public function deleteImages(int $atlas_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_image` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
	}




	/**
	 * Add Store
	 *
	 * Create a new atlas store record in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 * @param int $store_id   primary key of the store record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->addStore($atlas_id, $store_id);
	 */
	public function addStore(int $atlas_id, int $store_id): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_to_store` SET `atlas_id` = '" . (int)$atlas_id . "', `store_id` = '" . (int)$store_id . "'");
	}

	/**
	 * Delete Stores
	 *
	 * Delete atlas store records in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteStores($atlas_id);
	 */
	public function deleteStores(int $atlas_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_to_store` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
	}

	/**
	 * Delete Stores By Store ID
	 *
	 * Delete atlas stores by store records in the database.
	 *
	 * @param int $store_id primary key of the store record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteStoresByStoreId($store_id);
	 */
	public function deleteStoresByStoreId(int $store_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_to_store` WHERE `store_id` = '" . (int)$store_id . "'");
	}


	/**
	 * Add Related
	 *
	 * Create a new related record in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 * @param int $related_id primary key of the atlas related record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->addRelated($atlas_id, $related_id);
	 */
	public function addRelated(int $atlas_id, int $product_id,string $sno): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_product_related` WHERE `atlas_id` = '" . (int)$atlas_id . "' AND `product_id` = '" . (int)$product_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_product_related` SET `atlas_id` = '" . (int)$atlas_id . "', `product_id` = '" . (int)$product_id . "', `sno` = '" . (string)$sno . "'");
	}

    public function addRelatedTemp(int $atlas_id, string $sno, string $model): void {
	    if ($atlas_id && $model){
            $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_product_related_temp` WHERE `atlas_id` = '" . (int)$atlas_id . "' AND `model` = '" . (string)$model . "'");
            $this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_product_related_temp` SET `atlas_id` = '" . (int)$atlas_id . "', `model` = '" . (string)$model . "', `sno` = '" . (string)$sno . "'");
        }
    }

    public function deleteRelatedTemp(int $atlas_id): void {
	    if ($atlas_id){
            $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_product_related_temp` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
        }
    }

	/**
	 * Delete Related
	 *
	 * Delete related record in the database.
	 *
	 * @param int $atlas_id primary key of the atlas record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('catalog/atlas');
	 *
	 * $this->model_sync_atlas->deleteRelated($atlas_id);
	 */
	public function deleteRelated(int $atlas_id): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_product_related` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
	}


    public function addMarker(int $atlas_id,string $sno, float $top, float $left): void {
//        $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_point` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "atlas_marker` SET `atlas_id` = '" . (int)$atlas_id . "', `top` = '" . (float)$top . "', `left` = '" . (float)$left . "', `sno` = '" . (string)$sno . "'");
    }

    public function deleteMarkers(int $atlas_id): void {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "atlas_marker` WHERE `atlas_id` = '" . (int)$atlas_id . "'");
    }
}
