<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

/**
 * O que o login precisa do id_token, já verificado.
 */
final readonly class IdToken
{
    /**
     * @param string $sub a pessoa na central
     * @param ?string $sid a sessão da central em que o login aconteceu
     */
    public function __construct(
        public string $sub,
        public ?string $sid,
    ) {
    }
}
