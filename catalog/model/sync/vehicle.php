<?php
namespace Opencart\Catalog\Model\Sync;

class Vehicle extends \Opencart\System\Engine\Model {
	public function getVehicleByVehicleId(int $vid): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "eis_vehicle` WHERE `id` = '" . (int)$vid . "' LIMIT 1");

		return $query->row;
	}

	public function syncVehicle(int $vid, array $data): void {
		$this->ensureTables();

		$this->db->query('START TRANSACTION');

		try {
			$exists = $this->getVehicleByVehicleId($vid);
			$vehicle = [
				'brand'      => (string)($data['brand'] ?? ''),
				'year'       => (string)($data['year'] ?? ''),
				'category'   => (string)($data['category'] ?? ''),
				'model'      => (string)($data['model'] ?? ''),
				'commercial' => (string)($data['commercial'] ?? ''),
				'engine'     => (string)($data['engine'] ?? ''),
				'vin'        => (string)($data['vin'] ?? ''),
				'vinlist'    => (string)($data['vinlist'] ?? ''),
				'image'      => (string)($data['image'] ?? '')
			];

			if ($exists) {
				$this->editVehicle($vid, $vehicle);
			} else {
				$this->addVehicle($vid, $vehicle);
			}

			$language_map = $this->languageMap((array)($data['vehicle_language_code'] ?? []));
			$this->syncDescriptions($vid, $data, $language_map);
			$this->syncAdmin($vid, $data);
			$this->syncProducts($vid, $vehicle, (array)($data['vehicle_related'] ?? []));
			$this->syncSeo($vid, $data, $language_map);

			$this->db->query('COMMIT');
		} catch (\Throwable $e) {
			$this->db->query('ROLLBACK');
			throw $e;
		}
	}

	private function ensureTables(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "eis_vehicle_admin` (
			`vid` int(11) NOT NULL,
			`name` varchar(255) NOT NULL DEFAULT '',
			`description` text NOT NULL,
			`meta_title` varchar(255) NOT NULL DEFAULT '',
			`meta_description` varchar(255) NOT NULL DEFAULT '',
			`meta_keyword` varchar(255) NOT NULL DEFAULT '',
			`seo_keyword` varchar(64) NOT NULL DEFAULT '',
			`sort_order` int(11) NOT NULL DEFAULT 0,
			`status` tinyint(1) NOT NULL DEFAULT 1,
			`layout_id` int(11) NOT NULL DEFAULT 0,
			`store_ids` text NOT NULL,
			`date_modified` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
			PRIMARY KEY (`vid`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "eis_vehicle_description` (
			`vid` int(11) NOT NULL,
			`language_id` int(11) NOT NULL,
			`name` varchar(255) NOT NULL DEFAULT '',
			`description` text NOT NULL,
			`meta_title` varchar(255) NOT NULL DEFAULT '',
			`meta_description` text NOT NULL,
			`meta_keyword` text NOT NULL,
			PRIMARY KEY (`vid`, `language_id`),
			KEY `language_id` (`language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	private function addVehicle(int $vid, array $data): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "eis_vehicle` SET
			`id` = '" . (int)$vid . "',
			`brand` = '" . $this->db->escape($data['brand']) . "',
			`year` = '" . $this->db->escape($data['year']) . "',
			`category` = '" . $this->db->escape($data['category']) . "',
			`model` = '" . $this->db->escape($data['model']) . "',
			`commercial` = '" . $this->db->escape($data['commercial']) . "',
			`engine` = '" . $this->db->escape($data['engine']) . "',
			`vin` = '" . $this->db->escape($data['vin']) . "',
			`vinlist` = '" . $this->db->escape($data['vinlist']) . "',
			`image` = '" . $this->db->escape($data['image']) . "',
			`lastupdate` = NOW()");
	}

	private function editVehicle(int $vid, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "eis_vehicle` SET
			`brand` = '" . $this->db->escape($data['brand']) . "',
			`year` = '" . $this->db->escape($data['year']) . "',
			`category` = '" . $this->db->escape($data['category']) . "',
			`model` = '" . $this->db->escape($data['model']) . "',
			`commercial` = '" . $this->db->escape($data['commercial']) . "',
			`engine` = '" . $this->db->escape($data['engine']) . "',
			`vin` = '" . $this->db->escape($data['vin']) . "',
			`vinlist` = '" . $this->db->escape($data['vinlist']) . "',
			`image` = '" . $this->db->escape($data['image']) . "',
			`lastupdate` = NOW()
			WHERE `id` = '" . (int)$vid . "'");
	}

	private function syncDescriptions(int $vid, array $data, array $language_map): void {
		$descriptions = isset($data['vehicle_description']) && is_array($data['vehicle_description']) ? $data['vehicle_description'] : [];
		$default_name = $this->defaultName($data);
		$default_description = (string)($data['description'] ?? '');
		$default_meta_title = (string)($data['meta_title'] ?? $default_name);
		$default_meta_description = (string)($data['meta_description'] ?? $default_description);
		$default_meta_keyword = (string)($data['meta_keyword'] ?? '');

		if (!$descriptions) {
			$query = $this->db->query("SELECT `language_id` FROM `" . DB_PREFIX . "language` WHERE `status` = '1'");

			foreach ($query->rows as $language) {
				$descriptions[(int)$language['language_id']] = [
					'name'             => $default_name,
					'description'      => $default_description,
					'meta_title'       => $default_meta_title,
					'meta_description' => $default_meta_description,
					'meta_keyword'     => $default_meta_keyword
				];
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "eis_vehicle_description` WHERE `vid` = '" . (int)$vid . "'");

		foreach ($descriptions as $source_language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			$language_id = $language_map[(int)$source_language_id] ?? 0;

			if (!$language_id) {
				continue;
			}

			$this->db->query("INSERT INTO `" . DB_PREFIX . "eis_vehicle_description` SET
				`vid` = '" . (int)$vid . "',
				`language_id` = '" . (int)$language_id . "',
				`name` = '" . $this->db->escape((string)($description['name'] ?? $default_name)) . "',
				`description` = '" . $this->db->escape((string)($description['description'] ?? $default_description)) . "',
				`meta_title` = '" . $this->db->escape((string)($description['meta_title'] ?? $default_meta_title)) . "',
				`meta_description` = '" . $this->db->escape((string)($description['meta_description'] ?? $default_meta_description)) . "',
				`meta_keyword` = '" . $this->db->escape((string)($description['meta_keyword'] ?? $default_meta_keyword)) . "'
				ON DUPLICATE KEY UPDATE
					`name` = VALUES(`name`),
					`description` = VALUES(`description`),
					`meta_title` = VALUES(`meta_title`),
					`meta_description` = VALUES(`meta_description`),
					`meta_keyword` = VALUES(`meta_keyword`)");
		}
	}

	private function syncAdmin(int $vid, array $data): void {
		$store_ids = array_values(array_filter(array_map('intval', (array)($data['store_ids'] ?? []))));
		$valid_store_ids = $this->validStoreIds();
		$store_ids = array_values(array_filter($store_ids, static function($store_id) use ($valid_store_ids): bool {
			return isset($valid_store_ids[$store_id]);
		}));
		$name = trim((string)($data['name'] ?? '')) ?: $this->defaultName($data);
		$description = (string)($data['description'] ?? '');
		$meta_title = trim((string)($data['meta_title'] ?? '')) ?: $name;
		$meta_description = oc_substr((string)($data['meta_description'] ?? $description), 0, 255);
		$seo_keyword = oc_substr((string)($data['seo_keyword'] ?? ''), 0, 64);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "eis_vehicle_admin` SET
			`vid` = '" . (int)$vid . "',
			`name` = '" . $this->db->escape($name) . "',
			`description` = '" . $this->db->escape($description) . "',
			`meta_title` = '" . $this->db->escape($meta_title) . "',
			`meta_description` = '" . $this->db->escape($meta_description) . "',
			`meta_keyword` = '" . $this->db->escape((string)($data['meta_keyword'] ?? '')) . "',
			`seo_keyword` = '" . $this->db->escape($seo_keyword) . "',
			`sort_order` = '" . (int)($data['sort_order'] ?? 0) . "',
			`status` = '" . (!empty($data['status']) ? 1 : 0) . "',
			`layout_id` = '" . (int)($data['layout_id'] ?? 0) . "',
			`store_ids` = '" . $this->db->escape(implode(',', $store_ids)) . "'
			ON DUPLICATE KEY UPDATE
				`name` = VALUES(`name`),
				`description` = VALUES(`description`),
				`meta_title` = VALUES(`meta_title`),
				`meta_description` = VALUES(`meta_description`),
				`meta_keyword` = VALUES(`meta_keyword`),
				`seo_keyword` = VALUES(`seo_keyword`),
				`sort_order` = VALUES(`sort_order`),
				`status` = VALUES(`status`),
				`layout_id` = VALUES(`layout_id`),
				`store_ids` = VALUES(`store_ids`)");
	}

	private function syncProducts(int $vid, array $vehicle, array $product_ids): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "eis_product_to_vehicle` WHERE `vid` = '" . (int)$vid . "'");
		$product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$product_ids) {
			return;
		}

		$query = $this->db->query("SELECT `product_id`, `model` FROM `" . DB_PREFIX . "product` WHERE `product_id` IN (" . implode(',', $product_ids) . ")");

		foreach ($query->rows as $product) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "eis_product_to_vehicle` SET
				`product_id` = '" . (int)$product['product_id'] . "',
				`vid` = '" . (int)$vid . "',
				`model` = '" . $this->db->escape((string)$product['model']) . "',
				`oecode` = '',
				`category` = '" . $this->db->escape((string)($vehicle['category'] ?? '')) . "'
				ON DUPLICATE KEY UPDATE `model` = VALUES(`model`), `category` = VALUES(`category`)");
		}
	}

	private function syncSeo(int $vid, array $data, array $language_map): void {
		$this->load->model('design/seo_url');

		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'vid' AND `value` = '" . (int)$vid . "'");
		$seo_urls = isset($data['vehicle_seo_url']) && is_array($data['vehicle_seo_url']) ? $data['vehicle_seo_url'] : [];
		$valid_store_ids = $this->validStoreIds();

		foreach ($seo_urls as $store_id => $languages) {
			$store_id = (int)$store_id;

			if (!isset($valid_store_ids[$store_id])) {
				continue;
			}

			foreach ((array)$languages as $source_language_id => $keyword) {
				$language_id = $language_map[(int)$source_language_id] ?? 0;
				$keyword = trim((string)$keyword);

				if ($keyword === '' || !$language_id) {
					continue;
				}

				$this->ensureRouteSeo($store_id, $language_id);
				$keyword = $this->uniqueKeyword($keyword, $vid, $store_id, $language_id);
				$this->model_design_seo_url->addSeoUrl('vid', (string)$vid, $keyword, $store_id, $language_id);
			}
		}
	}

	private function ensureRouteSeo(int $store_id, int $language_id): void {
		$route = 'extension/vehicle_compat/product/vehicle';
		$query = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'route' AND `value` = '" . $this->db->escape($route) . "' AND `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");

		if ($query->num_rows) {
			return;
		}

		$keyword = 'vehicle';
		$conflict = $this->db->query("SELECT `seo_url_id` FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `store_id` = '" . (int)$store_id . "' AND `language_id` = '" . (int)$language_id . "' LIMIT 1");

		if ($conflict->num_rows) {
			$keyword = 'vehicle-compat';
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `key` = 'route', `value` = '" . $this->db->escape($route) . "', `keyword` = '" . $this->db->escape($keyword) . "', `sort_order` = '-1'");
	}

	private function uniqueKeyword(string $keyword, int $vid, int $store_id, int $language_id): string {
		$keyword = trim(preg_replace('/[^\p{Latin}\p{Cyrillic}\p{Greek}0-9\/_-]+/u', '-', oc_strtolower($keyword)), '-/');

		if ($keyword === '') {
			$keyword = 'vehicle-' . $vid;
		}

		$base = oc_substr($keyword, 0, 64);
		$candidate = $base;
		$attempt = 1;

		while ($this->model_design_seo_url->getSeoUrlByKeyword($candidate, $store_id, $language_id)) {
			$suffix = '-' . $vid . ($attempt > 1 ? '-' . $attempt : '');
			$candidate = rtrim(oc_substr($base, 0, max(1, 64 - oc_strlen($suffix))), '-/') . $suffix;
			$attempt++;
		}

		return $candidate;
	}

	private function languageMap(array $source_languages): array {
		$query = $this->db->query("SELECT `language_id`, `code` FROM `" . DB_PREFIX . "language` WHERE `status` = '1'");
		$target_by_code = [];
		$map = [];

		foreach ($query->rows as $language) {
			$target_by_code[(string)$language['code']] = (int)$language['language_id'];
			$map[(int)$language['language_id']] = (int)$language['language_id'];
		}

		foreach ($source_languages as $source_id => $code) {
			if (isset($target_by_code[(string)$code])) {
				$map[(int)$source_id] = $target_by_code[(string)$code];
			}
		}

		return $map;
	}

	private function validStoreIds(): array {
		$store_ids = [0 => true];
		$query = $this->db->query("SELECT `store_id` FROM `" . DB_PREFIX . "store`");

		foreach ($query->rows as $store) {
			$store_ids[(int)$store['store_id']] = true;
		}

		return $store_ids;
	}

	private function defaultName(array $data): string {
		return trim(implode(' ', array_filter([
			trim((string)($data['brand'] ?? '')),
			trim((string)($data['category'] ?? '')),
			trim((string)($data['year'] ?? '')),
			trim((string)($data['model'] ?? '')),
			trim((string)($data['commercial'] ?? ''))
		])));
	}
}
