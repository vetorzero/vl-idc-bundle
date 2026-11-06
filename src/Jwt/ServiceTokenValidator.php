<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Valida tokens máquina-a-máquina (client_credentials) que outro client pediu à central para chamar este.
 */
class ServiceTokenValidator
{
    public function __construct(
        private readonly KeySet $keySet,
        private readonly string $clientId,
    ) {
    }


    /**
     * @return string o `client_id` de quem está chamando
     * @throws InvalidTokenException
     * @throws ExceptionInterface quando a central não pôde ser consultada
     */
    public function validate(string $jwt): string
    {
        $claims = $this->keySet->decode($jwt);

        if (!\in_array($this->clientId, (array)($claims->aud ?? []), true)) {
            throw new InvalidTokenException('Token audience does not include this app');
        }

        $caller = $claims->client_id ?? null;
        if (!\is_string($caller) || $caller === $this->clientId) {
            throw new InvalidTokenException('Token was not issued to another client');
        }

        return $caller;
    }
}
