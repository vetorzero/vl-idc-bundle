<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api\Exception;

class ConflictException extends ApiException
{
    /** @param ?string $userId quem já tem o email, quando o conflito é de email */
    public function __construct(
        string $message,
        int $status = 409,
        ?string $title = null,
        ?string $detail = null,
        ?\Throwable $previous = null,
        public readonly ?string $userId = null,
    ) {
        parent::__construct($message, $status, $title, $detail, $previous);
    }
}
