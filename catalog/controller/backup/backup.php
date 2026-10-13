<?php
namespace Opencart\Catalog\Controller\Backup;
/**
 * Class Product
 *
 * Can be loaded using $this->load->controller('catalog/product');
 *
 * @package Opencart\Api\Controller\Catalog
 */
class Backup extends \Opencart\System\Engine\Controller {

    public function index() {

        $backup_dir = DIR_STORAGE . 'backup/other/';

        if (!is_dir($backup_dir)) {
            @mkdir($backup_dir, 0755, true);
        }

        $lock_file = $backup_dir . '.backup.lock';
        $last_run_file = $backup_dir . '.last_backup';
        $lock_handle = fopen($lock_file, 'c');

        if (!$lock_handle || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
            $this->log->write('Backup skipped: another backup process is running.');
            $this->response->setOutput(json_encode(['status' => 'skipped', 'reason' => 'locked']));

            return;
        }

        if (is_file($last_run_file) && (time() - filemtime($last_run_file) < 86400)) {
            $this->log->write('Backup skipped: it already ran within the last 24 hours.');
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
            $this->response->setOutput(json_encode(['status' => 'skipped', 'reason' => 'interval']));

            return;
        }

        @touch($last_run_file);

        $this->log->write('Backup started. IP=' . ($this->request->server['REMOTE_ADDR'] ?? '') . ' UA=' . ($this->request->server['HTTP_USER_AGENT'] ?? ''));

        try {
        $filename = date('Y-m-d H.i.s') . '.sql';

        $db_engine = "mysqli";
        $db_hostname = "184.168.119.167";
        $db_username = "ws_mortch";
        $db_password = "Mortch2026";
        $db_database = "ws_mortch";

        $db_port = "3306";
        $db_ssl_key = "";
        $db_ssl_cert = "";
        $db_ssl_ca = "";
        $db_prefix = "oc_mortch_cn_";

        $db = new \Opencart\System\Library\DB($db_engine, $db_hostname,$db_username, $db_password, $db_database, $db_port, $db_ssl_key, $db_ssl_cert, $db_ssl_ca);

//        var_dump($db->isConnected());exit;
        if (!$db->isConnected()){
            throw new \RuntimeException('Database connection failed.');
        }
        $this->load->model('tool/backup');

        $table_list = $this->model_tool_backup->getTables($db,$db_database,$db_prefix);

        if (!$table_list){
            throw new \RuntimeException('No database tables found.');
        }
        $page = 1;
        foreach ($table_list as $table){
            $output = '';
            $record_total = $this->model_tool_backup->getTotalRecords($db,$table);

            $pages = ceil($record_total / 200);
            for ($page=1;$page<=$pages;$page++) {
                if ($page == 1) {
                    $output .= 'TRUNCATE TABLE `' . $this->db->escape($table) . '`;' . "\n\n";
                }

                $results = $this->model_tool_backup->getRecords($db, $table, ($page - 1) * 200, 200);

                foreach ($results as $result) {
                    $fields = '';

                    foreach (array_keys($result) as $value) {
                        $fields .= '`' . $value . '`, ';
                    }

                    $values = '';

                    foreach (array_values($result) as $value) {
                        if ($value !== null) {
                            $value = str_replace(["\x00", "\x0a", "\x0d", "\x1a"], ['\0', '\n', '\r', '\Z'], $value);
                            $value = str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $value);
                            $value = str_replace('\\', '\\\\', $value);
                            $value = str_replace('\'', '\\\'', $value);
                            $value = str_replace('\\\n', '\n', $value);
                            $value = str_replace('\\\r', '\r', $value);
                            $value = str_replace('\\\t', '\t', $value);

                            $values .= '\'' . $value . '\', ';
                        } else {
                            $values .= 'NULL, ';
                        }
                    }

                    $output .= 'INSERT INTO `' . $table . '` (' . preg_replace('/, $/', '', $fields) . ') VALUES (' . preg_replace('/, $/', '', $values) . ');' . "\n";
                }

                $position = array_search($table, $table_list);

//                $record_total = $this->model_tool_backup->getTotalRecords($db, $table);

                if (($page * 200) >= $record_total) {
                    $output .= "\n";

                    if (isset($table_list[$position + 1])) {
                        $table = $table_list[$position + 1];
                    } else {
                        $table = '';
                    }
                }
            }

            $handle = fopen(DIR_STORAGE . 'backup/other/' . $filename, 'a');

            fwrite($handle, $output);

            fclose($handle);

        }

            $this->cleanupOldBackups($backup_dir);
            $this->log->write('Backup completed: ' . $filename);

            $this->response->setOutput(json_encode(['status' => 'success', 'file' => $filename]));
        } catch (\Throwable $e) {
            $this->log->write('Backup failed: ' . $e->getMessage());
            $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            $this->response->setOutput(json_encode(['status' => 'error', 'message' => $e->getMessage()]));
        } finally {
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }

    }

    private function cleanupOldBackups(string $backup_dir): void {
        $expires = time() - (30 * 86400);

        foreach (glob($backup_dir . '*.sql') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $expires) {
                @unlink($file);
            }
        }
    }
}
