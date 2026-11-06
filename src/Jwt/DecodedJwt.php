<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

final readonly class DecodedJwt
{
    public function __construct(
        public \stdClass $header,
        public \stdClass $claims,
    ) {
    }
}
