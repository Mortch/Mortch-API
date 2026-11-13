<?php

namespace Opencart\System\Engine {
    class Model {
        public $db;
    }
}

namespace {
    define('DB_PREFIX', 'oc_');

    final class AtlasSyncTestDatabase {
        public array $queries = [];

        public function escape(string $value): string {
            return addslashes($value);
        }

        public function query(string $sql): object {
            $this->queries[] = $sql;

            return (object)['num_rows' => 1, 'row' => []];
        }
    }

    require_once dirname(__DIR__) . '/catalog/model/sync/atlas.php';

    $reflection = new ReflectionClass(\Opencart\Catalog\Model\Sync\Atlas::class);
    $model = $reflection->newInstanceWithoutConstructor();
    $model->db = new AtlasSyncTestDatabase();
    $model->addRelatedTemp(26, '01', 'MIC-TEST', '1101');

    $insert = end($model->db->queries);

    if (!str_contains($insert, '`sortorder`') || !str_contains($insert, "'1101'")) {
        fwrite(STDERR, "FAIL: unbound product sort order is not written by the sync API.\n");
        exit(1);
    }

    $source = file_get_contents(dirname(__DIR__) . '/catalog/model/sync/atlas.php');

    if (substr_count($source, '$sortorder[$key] ??') < 2) {
        fwrite(STDERR, "FAIL: add or edit atlas sync does not pass the submitted sort order.\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: atlas sync carries unbound product sort order to the database.\n");
}
