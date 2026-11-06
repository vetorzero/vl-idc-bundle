<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Token;

use Throwable;

/**
 * A central não entregou um token máquina-a-máquina.
 */
class TokenException extends \RuntimeException
{
    public static function refused(
        ?string $audience,
        int $status,
        ?string $error,
        ?string $description,
        Throwable $previous,
    ): self {
        $reason = implode(': ', array_filter([$error, $description])) ?: 'no error given';

        return new self('VL-IdC refused a token for ' . self::target($audience) . " (HTTP {$status}, {$reason}).", previous: $previous);
    }


    public static function unreachable(?string $audience, Throwable $previous): self
    {
        return new self('Could not get a token for ' . self::target($audience) . " from VL-IdC: {$previous->getMessage()}", previous: $previous);
    }


    public static function invalidResponse(?string $audience): self
    {
        return new self('VL-IdC answered the token request for ' . self::target($audience) . ' without a usable token.');
    }


    private static function target(?string $audience): string
    {
        return $audience === null ? 'its own API' : "audience '{$audience}'";
    }
}
