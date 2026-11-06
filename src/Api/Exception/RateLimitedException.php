<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api\Exception;

class RateLimitedException extends ApiException
{
    /** @param int $retryAfter segundos até poder tentar de novo */
    public function __construct(
        string $message,
        int $status = 429,
        ?string $title = null,
        ?string $detail = null,
        ?\Throwable $previous = null,
        public readonly int $retryAfter = 1,
    ) {
        parent::__construct($message, $status, $title, $detail, $previous);
    }
}
