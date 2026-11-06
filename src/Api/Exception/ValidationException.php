<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api\Exception;

class ValidationException extends ApiException
{
    /** @param array<mixed> $violations como vieram no problem details */
    public function __construct(
        string $message,
        int $status = 422,
        ?string $title = null,
        ?string $detail = null,
        ?\Throwable $previous = null,
        public readonly array $violations = [],
    ) {
        parent::__construct($message, $status, $title, $detail, $previous);
    }
}
