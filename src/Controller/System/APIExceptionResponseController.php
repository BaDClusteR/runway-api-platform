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

class APIExceptionResponseController implements IApiExceptionResponseController {
    public function __construct(
        protected IApiResponseController $apiResponseController,
        protected ILogger                $logger,
        protected IRequest               $request,
        protected IKernel                $kernel
    ) {}

    public function getResponse(Throwable $exception): Response {
        $this->logger->error(
            "Uncaught API exception: {$exception->getMessage()}",
            $this->getExceptionJsonData($exception, true)
        );

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

    protected function getExceptionJsonData(Throwable $exception, bool $isForLogs = false): array {
        $result = [
            'errors'    => $this->getExceptionErrors($exception),
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
                ($isForLogs ? 'exceptionTrace' : 'trace') => $exception->getTrace(),
            ];

            if ($previous = $exception->getPrevious()) {
                $result['previous'] = $this->getExceptionJsonData($previous, $isForLogs);
            }
        }

        return $result;
    }

    protected function getExceptionErrors(Throwable $exception): array {
        if ($exception instanceof ApiException) {
            return $this->getApiExceptionErrors($exception);
        }

        return [
            [
                'code'    => $this->getErrorCode($exception),
                'message' => $this->getErrorMessage($exception)
            ]
        ];
    }

    protected function getApiExceptionErrors(ApiException $exception): array {
        $errorCode = $exception->getCode();

        return array_map(
            static fn(string $error): array => [
                'code'    => $errorCode,
                'message' => $error,
            ],
            $exception->errors
        );
    }

    protected function getErrorMessage(Throwable $exception): string {
        if (
            $this->isInternalError($exception)
            && !$this->isDebugMode()
        ) {
            return "Internal server error";
        }

        return $exception->getMessage();
    }

    protected function getErrorCode(Throwable $exception): int {
        if ($this->isAPIException($exception)) {
            return $exception->getCode();
        }

        return 500;
    }

    protected function isInternalError(Throwable $exception): bool {
        if (!$this->isAPIException($exception)) {
            return true;
        }

        $code = $exception->getCode();

        return $code >= 500
            && $code < 600;
    }

    protected function isDebugMode(): bool {
        return $this->kernel->isDebugMode();
    }

    protected function isAPIException(Throwable $exception): bool {
        return $exception instanceof ApiException;
    }
}