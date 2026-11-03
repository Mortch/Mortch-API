<?php

namespace {
    if (PHP_SAPI === 'cli-server') {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $responses = [
            '/success' => [
                'code' => 0,
                'msg' => '同步成功！',
                'history_status' => 1,
            ],
            '/duplicate' => [
                'code' => 200,
                'msg' => '订单已存在，请勿重复提交！',
                'duplicate' => true,
                'history_status' => 2,
            ],
            '/error' => [
                'code' => 200,
                'msg' => '产品不存在！',
                'history_status' => 2,
            ],
        ];

        header('Content-Type: application/json');
        echo json_encode($responses[$path] ?? $responses['/error'], JSON_UNESCAPED_UNICODE);
        return;
    }
}

namespace Opencart\System\Engine {
    class Model {
    }
}

namespace {
    require_once dirname(__DIR__) . '/erp/model/cron/order.php';

    final class TestLog {
        public function write($message): void {
        }
    }

    $host = '127.0.0.1';
    $port = random_int(20000, 40000);
    $command = [PHP_BINARY, '-S', $host . ':' . $port, __FILE__];
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to start the test HTTP server.\n");
        exit(2);
    }

    try {
        $ready = false;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.1);

            if ($socket) {
                fclose($socket);
                $ready = true;
                break;
            }

            usleep(50000);
        }

        if (!$ready) {
            fwrite(STDERR, "Test HTTP server did not become ready.\n");
            exit(2);
        }

        $order = new \Opencart\Erp\Model\Cron\Order();
        $order->log = new TestLog();

        $cases = [
            '/success' => true,
            '/duplicate' => true,
            '/error' => false,
        ];

        foreach ($cases as $path => $expected) {
            $actual = $order->curlRequest(
                'http://' . $host . ':' . $port . $path,
                'POST',
                ['order_id' => 1619]
            );

            if ($actual !== $expected) {
                fwrite(STDERR, "FAIL: response for {$path} returned " . var_export($actual, true) . ".\n");
                exit(1);
            }
        }

        fwrite(STDOUT, "PASS: success, duplicate, and error responses have the expected sync result.\n");
    } finally {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_terminate($process);
        proc_close($process);
    }
}
