<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use Runway\DataStorage\IDataStorageDriver;
use Runway\Singleton\Container;

/**
 * The tables of the authorization (see AuthStorage) in the database of the tests: SQLite in memory (see
 * tests/config/test.env), the names with the prefix.
 */
final class AuthTables {
    /** @var array<string, string> Table => columns. */
    private const array TABLES = [
        'options'        => 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, value TEXT NOT NULL',
        'tokens'         => 'id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL, generated INTEGER NOT NULL,'
            . ' valid_until INTEGER NOT NULL, last_active INTEGER NOT NULL, ip TEXT NOT NULL, user_agent TEXT NOT NULL,'
            . ' server_info TEXT NOT NULL, expired INTEGER NOT NULL, active INTEGER NOT NULL',
        'login_attempts' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT NOT NULL, attempted INTEGER NOT NULL',
    ];

    /**
     * Empty tables.
     */
    public static function create(): IDataStorageDriver {
        $driver = Container::getInstance()->getDataStorageDriver();

        foreach (self::TABLES as $table => $columns) {
            $driver->execute("DROP TABLE IF EXISTS `{{$table}}`");
            $driver->execute("CREATE TABLE `{{$table}}` ($columns)");
        }

        return $driver;
    }
}
