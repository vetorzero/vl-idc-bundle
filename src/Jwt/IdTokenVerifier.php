<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;

/**
 * Verifica o id_token que a central devolve no login deste client.
 */
class IdTokenVerifier
{
    public function __construct(
        private readonly KeySet $keySet,
        private readonly Discovery $discovery,
        private readonly string $clientId,
    ) {
    }


    /**
     * @throws InvalidTokenException
     * @throws ExceptionInterface quando a central não pôde ser consultada
     */
    public function verify(string $jwt): IdToken
    {
        $claims = $this->keySet->decode($jwt)->claims;

        if (($claims->iss ?? null) !== $this->discovery->get()->issuer) {
            throw new InvalidTokenException('Token was not issued by the central');
        }

        if (!\in_array($this->clientId, (array)($claims->aud ?? []), true)) {
            throw new InvalidTokenException('Token audience does not include this app');
        }

        $sub = $claims->sub ?? null;
        if (!\is_string($sub) || $sub === '') {
            throw new InvalidTokenException('Token has no sub');
        }

        $sid = $claims->sid ?? null;

        return new IdToken($sub, \is_string($sid) && $sid !== '' ? $sid : null);
    }
}
