<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use Runway\Logger\Enum\LogLevelEnum;
use Runway\Logger\ILogger;

/**
 * Collects the log records.
 */
final class LoggerSpy implements ILogger {
    /** @var array<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function setLogLevel(LogLevelEnum $level): static {
        return $this;
    }

    /**
     * @param array<mixed> $context
     */
    public function debug(string $message, array $context = []): void {
        $this->add('debug', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function info(string $message, array $context = []): void {
        $this->add('info', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function deprecated(string $message, array $context = []): void {
        $this->add('deprecated', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function warning(string $message, array $context = []): void {
        $this->add('warning', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function error(string $message, array $context = []): void {
        $this->add('error', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function critical(string $message, array $context = []): void {
        $this->add('critical', $message, $context);
    }

    /**
     * @param array<mixed> $context
     */
    public function emergency(string $message, array $context = []): void {
        $this->add('emergency', $message, $context);
    }

    /**
     * @return array<array{level: string, message: string, context: array<mixed>}>
     */
    public function getRecords(string $level): array {
        return array_values(array_filter(
            $this->records,
            static fn(array $record): bool => $record['level'] === $level
        ));
    }

    /**
     * @param array<mixed> $context
     */
    protected function add(string $level, string $message, array $context): void {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
