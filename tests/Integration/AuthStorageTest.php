<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use ApiPlatform\Core\Auth\AuthStorage;
use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Tests\Support\AuthTables;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\IDataStorageDriver;

/**
 * The queries of the storage in a real database (SQLite), with the table prefix of the project.
 */
class AuthStorageTest extends TestCase {
    protected IDataStorageDriver $driver;

    protected AuthStorage $storage;

    protected function setUp(): void {
        $this->driver = AuthTables::create();
        $this->storage = new AuthStorage();
    }

    public function testCredentials(): void {
        self::assertNull($this->storage->getCredentials());

        $this->storage->setCredentials(new CredentialsDTO('me', 'hash1'));
        $this->storage->setCredentials(new CredentialsDTO('admin', 'hash2'));

        self::assertEquals(new CredentialsDTO('admin', 'hash2'), $this->storage->getCredentials());
        // One row of each option.
        self::assertSame(2, $this->countRows('options'));
        // With the prefix of the project (see tests/config/test.env).
        self::assertSame(
            ['auth_login', 'auth_password_hash'],
            $this->driver->getColumn('SELECT name FROM test_options ORDER BY name', [], 'name')
        );
    }

    /**
     * E.g. the password hash is not set yet.
     */
    public function testIncompleteCredentials(): void {
        $this->driver->execute("INSERT INTO `{options}` (name, value) VALUES ('auth_login', 'me')");

        self::assertNull($this->storage->getCredentials());
    }

    /**
     * The last value wins if a project has added a duplicate.
     */
    public function testLastOptionWins(): void {
        $this->driver->execute("INSERT INTO `{options}` (name, value) VALUES ('auth_login', 'old'), ('auth_password_hash', 'h')");
        $this->driver->execute("INSERT INTO `{options}` (name, value) VALUES ('auth_login', 'new')");

        self::assertSame('new', $this->storage->getCredentials()?->login);
    }

    public function testSession(): void {
        $before = time();
        $this->storage->addSession('hash', 1900000000, '203.0.113.5', 'Browser');

        $session = $this->storage->findSession('hash');
        self::assertNotNull($session);
        self::assertSame(1900000000, $session->validUntil);
        self::assertGreaterThanOrEqual($before, $session->lastActive);
        self::assertTrue($session->isActive);
        self::assertNull($this->storage->findSession('other'));

        $row = $this->driver->getFirstResult('SELECT * FROM `{tokens}` WHERE id = :id', ['id' => $session->id]);
        self::assertSame('203.0.113.5', $row['ip']);
        self::assertSame('Browser', $row['user_agent']);
        self::assertSame('', $row['server_info']);
        self::assertSame($row['generated'], $row['last_active']);
    }

    public function testTouchSession(): void {
        $this->storage->addSession('hash', 1900000000, '', '');
        $session = $this->storage->findSession('hash');
        self::assertNotNull($session);

        $this->storage->touchSession($session->id, 1800000000);

        self::assertSame(1800000000, $this->storage->findSession('hash')?->lastActive);
    }

    public function testDeactivateSession(): void {
        $this->storage->addSession('hash1', 1900000000, '', '');
        $this->storage->addSession('hash2', 1900000000, '', '');

        $this->storage->deactivateSession('hash1');

        self::assertFalse($this->storage->findSession('hash1')?->isActive);
        self::assertTrue($this->storage->findSession('hash2')?->isActive);

        $this->storage->deactivateAllSessions();

        self::assertFalse($this->storage->findSession('hash2')?->isActive);
    }

    /**
     * A session ended by the project (the "expired" flag) is not active.
     */
    public function testExpiredSession(): void {
        $this->storage->addSession('hash', 1900000000, '', '');
        $this->driver->execute('UPDATE `{tokens}` SET expired = 1');

        self::assertFalse($this->storage->findSession('hash')?->isActive);
    }

    public function testFailedAttempts(): void {
        $now = time();
        $this->storage->addFailedAttempt('203.0.113.5', $now - 100);
        $this->storage->addFailedAttempt('203.0.113.5', $now);
        $this->storage->addFailedAttempt('198.51.100.7', $now);

        self::assertSame(2, $this->storage->countFailedAttempts('203.0.113.5', $now - 100));
        self::assertSame(1, $this->storage->countFailedAttempts('203.0.113.5', $now - 99));
        self::assertSame(3, $this->storage->countFailedAttempts(null, $now - 100));
        self::assertSame(0, $this->storage->countFailedAttempts('192.0.2.1', $now - 100));

        $this->storage->clearFailedAttempts('203.0.113.5');

        self::assertSame(0, $this->storage->countFailedAttempts('203.0.113.5', 0));
        self::assertSame(1, $this->storage->countFailedAttempts(null, 0));
    }

    /**
     * The table does not grow forever: the attempts older than a day are deleted.
     */
    public function testOldAttemptsAreDeleted(): void {
        $now = time();
        $this->storage->addFailedAttempt('203.0.113.5', $now - 86401);
        $this->storage->addFailedAttempt('203.0.113.5', $now - 86400);
        $this->storage->addFailedAttempt('203.0.113.5', $now);

        self::assertSame(2, $this->countRows('login_attempts'));
    }

    protected function countRows(string $table): int {
        $count = $this->driver->getFirstScalarResult("SELECT COUNT(*) FROM `{{$table}}`");
        self::assertIsInt($count);

        return $count;
    }
}
