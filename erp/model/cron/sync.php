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
    // status: 0=pending, 1=success, -1..-4=retry attempt, 2=terminal failure.
    private const MAX_ATTEMPTS = 5;
    private const RETRY_DELAY_MINUTES = 5;
    private const BATCH_SIZE = 3;

    public function getSyncs(): array {
	    $delay = self::RETRY_DELAY_MINUTES;
	    $limit = self::BATCH_SIZE;
	    $retry_statuses = [];

	    for ($attempt = 1; $attempt < self::MAX_ATTEMPTS; $attempt++) {
		    $retry_statuses[] = (string)-$attempt;
	    }

	    $statuses = "'0','" . implode("','", $retry_statuses) . "'";
	    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "sync` WHERE `status` IN (" . $statuses . ") AND (`status` = '0' OR `date_modified` IS NULL OR `date_modified` <= DATE_SUB(NOW(), INTERVAL " . $delay . " MINUTE)) ORDER BY `sync_id` DESC LIMIT " . $limit);

	    return $query->rows;
    }

    public function updateSync(int $sync_id): void {
	    $this->db->query("UPDATE `" . DB_PREFIX . "sync` SET `status` = '1', `date_modified` = NOW() WHERE `sync_id` = '" . (int)$sync_id . "'");
    }

    public function failSync(int $sync_id, string $error = ''): void {
	    $query = $this->db->query("SELECT `status` FROM `" . DB_PREFIX . "sync` WHERE `sync_id` = '" . (int)$sync_id . "' LIMIT 1");

	    if (!$query->num_rows) {
		    return;
	    }

	    $current_status = (int)$query->row['status'];

	    if ($current_status === 1 || $current_status === 2) {
		    return;
	    }

	    $attempt = $current_status < 0 ? abs($current_status) + 1 : 1;
	    $status = $attempt >= self::MAX_ATTEMPTS ? 2 : -$attempt;

	    $this->db->query("UPDATE `" . DB_PREFIX . "sync` SET `status` = '" . (int)$status . "', `date_modified` = NOW() WHERE `sync_id` = '" . (int)$sync_id . "'");
	    $this->log->write('Sync ' . (int)$sync_id . ' failed on attempt ' . $attempt . '/' . self::MAX_ATTEMPTS . ($error !== '' ? ': ' . substr($error, 0, 300) : ''));
    }
}
