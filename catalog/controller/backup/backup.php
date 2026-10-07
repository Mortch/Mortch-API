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
            return false;
        }
        $this->load->model('tool/backup');

        $table_list = $this->model_tool_backup->getTables($db,$db_database,$db_prefix);

        if (!$table_list){
            return false;
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

    }
}
