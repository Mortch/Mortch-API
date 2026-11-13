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

    $model->addRelated(26, 123, '02', '1202');

    $boundQueries = array_slice($model->db->queries, -3);
    $boundUpdate = implode("\n", $boundQueries);

    if (
        !str_contains($boundUpdate, 'INSERT INTO `oc_atlas_product_related`')
        || !str_contains($boundUpdate, 'UPDATE `oc_product` SET `sort_order` = \'1202\'')
    ) {
        fwrite(STDERR, "FAIL: bound product sort order is not written by the sync API.\n");
        exit(1);
    }

    $model->db->queries = [];
    $model->addRelatedTemp(26, '01', 'MIC-TEST', '1101');

    $insert = end($model->db->queries);

    if (!str_contains($insert, '`sortorder`') || !str_contains($insert, "'1101'")) {
        fwrite(STDERR, "FAIL: unbound product sort order is not written by the sync API.\n");
        exit(1);
    }

    $source = file_get_contents(dirname(__DIR__) . '/catalog/model/sync/atlas.php');

    if (
        substr_count($source, '$related_sortorder[$key] ??') < 2
        || substr_count($source, '$sortorder[$key] ??') < 4
    ) {
        fwrite(STDERR, "FAIL: add or edit atlas sync does not pass bound and unbound sort orders.\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: atlas sync carries bound and unbound product sort orders to the database.\n");
}
