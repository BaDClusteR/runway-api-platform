<?php

declare(strict_types=1);

namespace ApiPlatform\Controller\System;

use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\Exception\ApiException;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\Response;
use Runway\Singleton\IKernel;
use Throwable;

class ApiExceptionResponseController implements IApiExceptionResponseController {
    public function __construct(
        protected IApiResponseController $apiResponseController,
        protected ILogger                $logger,
        protected IRequest               $request,
        protected IKernel                $kernel
    ) {}

    public function getResponse(Throwable $exception): Response {
        // The client errors (404, 400, etc.) are expected: they don't need the trace nor the error level.
        if ($this->isInternalError($exception)) {
            $this->logger->error(
                "Uncaught API exception: {$exception->getMessage()}",
                $this->getExceptionJsonData($exception, true)
            );
        } else {
            $this->logger->info(
                "API error: {$exception->getMessage()}",
                ['code' => $exception->getCode(), 'requestId' => $this->request->getRequestId()]
            );
        }

        return $this->apiResponseController->getResponse(
            $this->convertToResponseDTO(
                $exception
            )
        );
    }

    protected function convertToResponseDTO(Throwable $exception): ApiResponseDTO {
        return new ApiResponseDTO(
            data: $this->getExceptionJsonData($exception),
            code: $this->getErrorCode($exception)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExceptionJsonData(Throwable $exception, bool $isForLogs = false): array {
        $result = [
            'errors'    => $this->getExceptionErrors($exception, $isForLogs),
            'requestId' => $this->request->getRequestId()
        ];

        if ($isForLogs) {
            $result['exceptionType'] = get_debug_type($exception);
        }

        if (
            $isForLogs
            || (
                $this->isDebugMode()
                && $this->isInternalError($exception)
            )
        ) {
            $result += [
                'file'                                    => $exception->getFile(),
                'line'                                    => $exception->getLine(),
                // Without the arguments of the calls: they can contain passwords and personal data.
                ($isForLogs ? 'exceptionTrace' : 'trace') => explode("\n", $exception->getTraceAsString()),
            ];

            if ($previous = $exception->getPrevious()) {
                $result['previous'] = $this->getExceptionJsonData($previous, $isForLogs);
            }
        }

        return $result;
    }

    /**
     * @return array<array{code: int, message: string}>
     */
    protected function getExceptionErrors(Throwable $exception, bool $isForLogs): array {
        $code = $this->getErrorCode($exception);

        // The messages of an internal error can tell about the code, the files and the data: only for the logs and
        // the debug mode.
        if (!$isForLogs && $this->isInternalError($exception) && !$this->isDebugMode()) {
            return [['code' => $code, 'message' => 'Internal server error']];
        }

        $messages = ($exception instanceof ApiException) ? $exception->errors : [$exception->getMessage()];

        return array_map(
            static fn(string $message): array => ['code' => $code, 'message' => $message],
            $messages
        );
    }

    /**
     * The code of an API exception, if it's an error code (4xx or 5xx): e.g. an ApiException without the code would
     * be an invalid response.
     */
    protected function getErrorCode(Throwable $exception): int {
        $code = $exception->getCode();

        return ($this->isAPIException($exception) && $code >= 400 && $code < 600) ? $code : 500;
    }

    protected function isInternalError(Throwable $exception): bool {
        return $this->getErrorCode($exception) >= 500;
    }

    protected function isDebugMode(): bool {
        return $this->kernel->isDebugMode();
    }

    protected function isAPIException(Throwable $exception): bool {
        return $exception instanceof ApiException;
    }
}
