<?php
namespace Opencart\Catalog\Model\Tool;
/**
 * Class Backup
 *
 * Can be loaded using $this->load->model('tool/backup');
 *
 * @package Opencart\Admin\Model\Tool
 */
class Backup extends \Opencart\System\Engine\Model {
	/**
	 * Get Tables
	 *
	 * @return array<int, string>
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $tables = $this->model_tool_backup->getTables();
	 */
	public function getTables($db,$database,$prefix): array {
		$table_data = [];

		$query = $db->query("SHOW TABLES FROM `" . $database . "`");

		foreach ($query->rows as $result) {
			if (isset($result['Tables_in_' . $database]) && substr($result['Tables_in_' . $database], 0, strlen($prefix)) == $prefix) {
				$table_data[] = $result['Tables_in_' . $database];
			}
		}

		return $table_data;
	}

	/**
	 * Get Records
	 *
	 * Get the record of the database table records in the database.
	 *
	 * @param string $table
	 * @param int    $start
	 * @param int    $limit
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $records = $this->model_tool_backup->getRecords($table, $start, $limit);
	 */
	public function getRecords($db,string $table, int $start = 0, int $limit = 100): array {
		if ($start < 0) {
			$start = 0;
		}

		if ($limit < 1) {
			$limit = 10;
		}

		$query = $db->query("SELECT * FROM `" . $table . "` LIMIT " . (int)$start . "," . (int)$limit);

		if ($query->num_rows) {
			return $query->rows;
		} else {
			return [];
		}
	}

	/**
	 * Get Total Records
	 *
	 * Get the total number of total database table records in the database.
	 *
	 * @param string $table
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $this->load->model('tool/backup');
	 *
	 * $record_total = $this->model_tool_backup->getTotalRecords($table);
	 */
	public function getTotalRecords($db,string $table): int {
		$query =$db->query("SELECT COUNT(*) AS `total` FROM `" . $table . "`");

		if ($query->num_rows) {
			return (int)$query->row['total'];
		} else {
			return 0;
		}
	}
}
