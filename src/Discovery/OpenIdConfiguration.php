<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Discovery;

/**
 * Os pedaços do `/.well-known/openid-configuration` que o pacote usa.
 */
final readonly class OpenIdConfiguration
{
    public function __construct(
        public string $issuer,
        public string $jwksUri,
        public string $tokenEndpoint,
    ) {
    }


    /**
     * @param array<string, mixed> $data
     * @throws \UnexpectedValueException
     */
    public static function fromArray(array $data): self
    {
        $get = static function (string $key) use ($data): string {
            $value = $data[$key] ?? null;
            if (!\is_string($value) || $value === '') {
                throw new \UnexpectedValueException("The OpenID configuration has no '{$key}'.");
            }

            return $value;
        };

        return new self($get('issuer'), $get('jwks_uri'), $get('token_endpoint'));
    }
}
