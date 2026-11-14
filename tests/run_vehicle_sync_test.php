<?php

$root = dirname(__DIR__);
$failures = [];

$checks = [
    'catalog/controller/startup/api.php' => [
        "'sync/sync.vehicle'",
        '!in_array($route, $allowed, true)',
        '$time < $time_start || $time > $time_end'
    ],
    'catalog/controller/sync/sync.php' => [
        'public function vehicle()',
        "syncVehicle(\$vid, \$vehicle_info)",
        "'vid is missing in the posted data'"
    ],
    'catalog/model/sync/vehicle.php' => [
        'class Vehicle',
        'public function syncVehicle(int $vid, array $data)',
        'eis_vehicle_admin',
        'eis_vehicle_description',
        'eis_product_to_vehicle',
        'seo_url'
    ]
];

foreach ($checks as $file => $needles) {
    $source = (string)file_get_contents($root . '/' . $file);

    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $failures[] = $file . ' is missing ' . $needle;
        }
    }
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "PASS: vehicle sync endpoint checks passed.\n");
