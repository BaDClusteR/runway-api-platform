<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use PDO;
use RuntimeException;

/**
 * The PHP built-in server with the front controller of the E2E tests (tests/E2E/server.php), its database (an SQLite
 * file with the tables of the authorization) and its environment, in a temporary folder.
 */
final class HttpServer {
    /** @var resource|null */
    private $process = null;

    private string $root;

    private int $port;

    public function __construct() {
        $this->root = sys_get_temp_dir() . '/runway-api-platform-e2e-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    public function start(): void {
        $this->createDatabase();
        $this->writeEnv();
        $this->port = $this->getFreePort();

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$this->port", dirname(__DIR__) . '/E2E/server.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['file', "$this->root/server.log", 'a'],
                2 => ['file', "$this->root/server.log", 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [...getenv(), 'API_E2E_ROOT' => $this->root, 'API_E2E_ENV_FILE' => "$this->root/e2e.env"]
        );

        if ($process === false) {
            throw new RuntimeException('Cannot start the PHP built-in server.');
        }

        $this->process = $process;
        $this->waitUntilReady();
    }

    public function stop(): void {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        $this->removeDirectory($this->root);
    }

    public function getBaseUrl(): string {
        return "http://127.0.0.1:$this->port";
    }

    public function getHost(): string {
        return "127.0.0.1:$this->port";
    }

    public function getDatabase(): PDO {
        return new PDO("sqlite:$this->root/e2e.sqlite", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * @return string The log of the server (e.g. for a failed test).
     */
    public function getLog(): string {
        return (string)@file_get_contents("$this->root/server.log");
    }

    private function createDatabase(): void {
        $database = $this->getDatabase();
        $database->exec('CREATE TABLE e2e_options (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, value TEXT)');
        $database->exec(
            'CREATE TABLE e2e_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT, generated INTEGER,'
            . ' valid_until INTEGER, last_active INTEGER, ip TEXT, user_agent TEXT, server_info TEXT, expired INTEGER,'
            . ' active INTEGER)'
        );
        $database->exec('CREATE TABLE e2e_login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT, attempted INTEGER)');
    }

    private function writeEnv(): void {
        $dsn = 'sqlite:' . str_replace('\\', '/', "$this->root/e2e.sqlite");

        file_put_contents("$this->root/e2e.env", implode("\n", [
            'APP_DEBUG=false',
            'LOG_LEVEL=4',
            "DSN=\"$dsn\"",
            'DB_PREFIX="e2e_"',
            'API_AUTH_ENABLED=true',
            'API_TOKEN_HEADER="X-Token"',
            'API_ALLOW_ORIGIN="https://app.example"',
            'API_ALLOW_METHODS="GET, POST, PATCH, OPTIONS"',
            'API_ALLOW_HEADERS="Content-Type, X-Token"',
        ]));
    }

    private function getFreePort(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0');

        if ($socket === false) {
            throw new RuntimeException('Cannot find a free port.');
        }

        $name = (string)stream_socket_get_name($socket, false);
        fclose($socket);

        return (int)substr($name, (int)strrpos($name, ':') + 1);
    }

    private function waitUntilReady(): void {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.2);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException("The PHP built-in server has not started:\n" . $this->getLog());
    }

    private function removeDirectory(string $path): void {
        foreach (glob("$path/*") ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($path);
    }
}
