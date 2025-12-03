<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    private string $errorCode;

    public function __construct(string $message, int $statusCode = 500, ?Exception $previous = null, string $errorCode = 'INTERNAL_ERROR')
    {
        parent::__construct($message, $statusCode, $previous);
        $this->errorCode = $errorCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Преобразовать в единый формат ошибки
     */
    public function toArray(): array
    {
        return [
            'ok' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'trace_id' => uniqid('err_', true)
            ]
        ];
    }
}