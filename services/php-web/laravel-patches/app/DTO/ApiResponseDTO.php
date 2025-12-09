<?php
namespace App\DTO;

class ApiResponseDTO
{
    public function __construct(
        public readonly bool $ok,
        public readonly mixed $data = null,
        public readonly ?array $error = null
    ) {}

    public static function success(mixed $data): self
    {
        return new self(ok: true, data: $data);
    }

    public static function error(string $code, string $message, ?string $traceId = null): self
    {
        return new self(
            ok: false,
            error: [
                'code' => $code,
                'message' => $message,
                'trace_id' => $traceId ?? uniqid('err_', true)
            ]
        );
    }

    public function toArray(): array
    {
        $result = ['ok' => $this->ok];

        if ($this->ok) {
            $result['data'] = $this->data instanceof \JsonSerializable
                ? $this->data->jsonSerialize()
                : $this->data;
        } else {
            $result['error'] = $this->error;
        }

        return $result;
    }
}