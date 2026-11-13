<?php

namespace Opencart\System\Engine {
    class Model {
        public $db;
    }
}

namespace {
    define('DB_PREFIX', 'oc_');

    final class AtlasSyncSectionsDatabase {
        public array $queries = [];

        public function escape(string $value): string {
            return addslashes($value);
        }

        public function query(string $sql): object {
            $this->queries[] = $sql;

            if (str_starts_with($sql, 'SELECT `product_id` FROM `oc_product`')) {
                return (object)[
                    'num_rows' => 1,
                    'row' => ['product_id' => 101],
                    'rows' => [['product_id' => 101]],
                ];
            }

            return (object)['num_rows' => 1, 'row' => [], 'rows' => []];
        }
    }

    final class AtlasSyncSectionsDependency {
        public array $calls = [];

        private function record(string $method, array $arguments): void {
            $this->calls[] = [$method, $arguments];
        }

        public function deleteDescriptions(int $atlas_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function addDescription(int $atlas_id, int $language_id, array $data): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function deleteStores(int $atlas_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function addStore(int $atlas_id, int $store_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function deleteCategories(int $atlas_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function addCategory(int $atlas_id, int $category_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function deleteRelated(int $atlas_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function addRelated(int $atlas_id, int $product_id, string $sno, string $sortorder): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function deleteRelatedTemp(int $atlas_id): void {
            $this->record(__FUNCTION__, func_get_args());
        }

        public function addRelatedTemp(int $atlas_id, string $sno, string $model, string $sortorder): void {
            $this->record(__FUNCTION__, func_get_args());
        }
    }

    final class AtlasSyncSectionsStore {
        public function getStores(): array {
            return [['store_id' => 0]];
        }
    }

    final class AtlasSyncSectionsLoader {
        private object $model;

        public function __construct(object $model) {
            $this->model = $model;
        }

        public function model(string $route): void {
            if ($route === 'setting/store') {
                $this->model->model_setting_store = new AtlasSyncSectionsStore();
            }
        }
    }

    final class AtlasSyncSectionsCache {
        public function delete(string $key): void {
        }
    }

    require_once dirname(__DIR__) . '/catalog/model/sync/atlas.php';

    $reflection = new ReflectionClass(\Opencart\Catalog\Model\Sync\Atlas::class);
    $model = $reflection->newInstanceWithoutConstructor();
    $model->db = new AtlasSyncSectionsDatabase();
    $model->model_sync_atlas = new AtlasSyncSectionsDependency();
    $model->load = new AtlasSyncSectionsLoader($model);
    $model->cache = new AtlasSyncSectionsCache();

    $sections_method = $reflection->getMethod('getSyncSections');
    $sections_method->setAccessible(true);

    $all_sections = $sections_method->invoke($model, []);

    if (count(array_filter($all_sections)) !== 5) {
        fwrite(STDERR, "FAIL: legacy sync requests must default to all atlas sections.\n");
        exit(1);
    }

    $selected_sections = $sections_method->invoke($model, [
        'sync_sections' => ['general', 'associations', 'invalid'],
    ]);

    if (
        !$selected_sections['general']
        || !$selected_sections['associations']
        || $selected_sections['taxonomy']
        || $selected_sections['seo']
        || $selected_sections['markers']
    ) {
        fwrite(STDERR, "FAIL: sync section selection is not filtered by the allowed list.\n");
        exit(1);
    }

    $model->editAtlas(26, [
        'sync_sections' => ['associations'],
        'atlas_related' => [101],
        'atlas_sno' => ['01'],
        'atlas_sortorder' => ['2101'],
        'atlas_snos' => ['02'],
        'atlas_models' => ['MIC-TEMP'],
        'atlas_sortorders' => ['2102'],
        'atlas_related_temp' => [''],
    ]);

    $called_methods = array_column($model->model_sync_atlas->calls, 0);
    $required_calls = ['deleteRelated', 'deleteRelatedTemp', 'addRelated', 'addRelatedTemp'];
    $forbidden_calls = ['deleteDescriptions', 'deleteStores', 'deleteCategories'];

    foreach ($required_calls as $method) {
        if (!in_array($method, $called_methods, true)) {
            fwrite(STDERR, "FAIL: selected associations were not synchronized through {$method}.\n");
            exit(1);
        }
    }

    foreach ($forbidden_calls as $method) {
        if (in_array($method, $called_methods, true)) {
            fwrite(STDERR, "FAIL: unselected atlas section unexpectedly called {$method}.\n");
            exit(1);
        }
    }

    if (str_contains(implode("\n", $model->db->queries), 'UPDATE `oc_atlas`')) {
        fwrite(STDERR, "FAIL: unselected general data was updated.\n");
        exit(1);
    }

    $source = file_get_contents(dirname(__DIR__) . '/catalog/model/sync/atlas.php');

    if (substr_count($source, '$sync_sections = $this->getSyncSections($data);') !== 2) {
        fwrite(STDERR, "FAIL: add and edit atlas sync must both honor selected sections.\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: atlas sync updates only the selected sections and defaults to all.\n");
}
