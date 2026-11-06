<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api\Exception;

/**
 * A API da central respondeu com erro (problem details, RFC 9457) ou não respondeu.
 */
class ApiException extends \RuntimeException
{
    /** @param int $status 0 quando a central não respondeu */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $title = null,
        public readonly ?string $detail = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }


    /** @param array<string, mixed> $problem */
    public static function fromProblem(
        string $method,
        string $url,
        int $status,
        array $problem,
        ?string $retryAfter = null,
    ): self {
        $title = \is_string($problem['title'] ?? null) ? $problem['title'] : null;
        $detail = \is_string($problem['detail'] ?? null) ? $problem['detail'] : null;
        $message = "VL-IdC API answered {$method} {$url} with HTTP {$status}"
            . ($title !== null ? ": {$title}" : '')
            . ($detail !== null ? " ({$detail})" : '');

        return match ($status) {
            401, 403 => new AccessDeniedException($message, $status, $title, $detail),
            404 => new NotFoundException($message, $status, $title, $detail),
            409 => new ConflictException(
                $message, $status, $title, $detail,
                userId: \is_string($problem['user_id'] ?? null) ? $problem['user_id'] : null,
            ),
            422 => new ValidationException(
                $message, $status, $title, $detail,
                violations: \is_array($problem['violations'] ?? null) ? $problem['violations'] : [],
            ),
            429 => new RateLimitedException(
                $message, $status, $title, $detail,
                retryAfter: is_numeric($retryAfter) ? max(1, (int)$retryAfter) : 1,
            ),
            default => new self($message, $status, $title, $detail),
        };
    }


    public static function unreachable(string $method, string $url, \Throwable $previous): self
    {
        return new self("VL-IdC API did not answer {$method} {$url}: {$previous->getMessage()}", previous: $previous);
    }
}
