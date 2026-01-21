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
	    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "sync` WHERE `status` = '0'");

	    return $query->rows;
    }

    public function updateSync(int $sync_id): void {
	    $this->db->query("UPDATE `" . DB_PREFIX . "sync` SET `status` = '1', `date_modified` = NOW() WHERE `sync_id` = '" . (int)$sync_id . "'");
    }
}
