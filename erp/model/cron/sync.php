<?php
namespace Opencart\Erp\Model\Cron;
/**
 * Class Sync
 *
 * Can be loaded using $this->load->model('cron/order');
 *
 * @package Opencart\Erp\Model\Sale
 */
class Sync extends \Opencart\System\Engine\Model {

    public function getSyncs(): array {
	    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "sync` WHERE `status` = '0' AND (`next_attempt` IS NULL OR `next_attempt` <= NOW()) ORDER BY `sync_id` ASC LIMIT 50");

	    return $query->rows;
    }

    public function updateSync(int $sync_id): void {
	    $this->db->query("UPDATE `" . DB_PREFIX . "sync` SET `status` = '1', `date_modified` = NOW() WHERE `sync_id` = '" . (int)$sync_id . "'");
    }

    public function failSync(int $sync_id, string $error = ''): void {
	    $this->db->query("UPDATE `" . DB_PREFIX . "sync` SET `attempts` = `attempts` + 1, `last_error` = '" . $this->db->escape($error) . "', `next_attempt` = DATE_ADD(NOW(), INTERVAL 5 MINUTE), `status` = CASE WHEN (`date_added` < DATE_SUB(NOW(), INTERVAL 1 DAY) OR (`attempts` + 1) >= 20) THEN 2 ELSE 0 END, `date_modified` = NOW() WHERE `sync_id` = '" . (int)$sync_id . "'");
    }
}
