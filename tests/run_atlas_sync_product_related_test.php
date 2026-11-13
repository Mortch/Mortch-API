<?php

namespace Opencart\System\Engine {
    class Model {
        public $db;
    }
}

namespace {
    define('DB_PREFIX', 'oc_');

    final class AtlasProductRelationsTestDatabase {
        public array $queries = [];
        public array $product_ids = [];

        public function query(string $sql): object {
            $this->queries[] = $sql;

            if (str_starts_with($sql, 'SELECT `product_id` FROM `oc_product`')) {
                return (object)[
                    'num_rows' => count($this->product_ids),
                    'rows' => array_map(static fn(int $product_id): array => ['product_id' => $product_id], $this->product_ids),
                ];
            }

            return (object)['num_rows' => 1, 'row' => [], 'rows' => []];
        }
    }

    require_once dirname(__DIR__) . '/catalog/model/sync/atlas.php';

    $reflection = new ReflectionClass(\Opencart\Catalog\Model\Sync\Atlas::class);
    $model = $reflection->newInstanceWithoutConstructor();
    $model->db = new AtlasProductRelationsTestDatabase();
    $model->db->product_ids = [101, 102, 103];

    $model->syncProductRelations([101, 102, 103, 101, 0, 999]);

    $inserts = array_values(array_filter(
        $model->db->queries,
        static fn(string $sql): bool => str_starts_with($sql, 'INSERT IGNORE INTO `oc_product_related`')
    ));
    $trims = array_values(array_filter(
        $model->db->queries,
        static fn(string $sql): bool => str_starts_with($sql, 'DELETE a FROM `oc_product_related`')
    ));

    if (count($inserts) !== 6) {
        fwrite(STDERR, "FAIL: expected six valid product relation pairs, got " . count($inserts) . ".\n");
        exit(1);
    }

    if (count($trims) !== 3 || !str_contains(implode("\n", $trims), 'LIMIT 20, 1024')) {
        fwrite(STDERR, "FAIL: product relation trimming is not applied per valid product.\n");
        exit(1);
    }

    $source = file_get_contents(dirname(__DIR__) . '/catalog/model/sync/atlas.php');

    if (substr_count($source, 'syncProductRelations($related_product_ids)') < 2) {
        fwrite(STDERR, "FAIL: add or edit atlas sync does not rebuild product relations.\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: atlas sync rebuilds valid product relations and keeps the top 20 sellers.\n");
}
