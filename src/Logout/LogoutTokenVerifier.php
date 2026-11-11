<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Logout;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;

/**
 * Verifica o logout token do OIDC Back-Channel Logout (§2.6 da spec).
 *
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html#Validation
 */
class LogoutTokenVerifier
{
    public const string EVENT = 'http://schemas.openid.net/event/backchannel-logout';


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
    public function verify(string $jwt): LogoutToken
    {
        // assinatura, `exp` e `iat`, com a tolerância de relógio do KeySet
        $decoded = $this->keySet->decode($jwt);
        $claims = $decoded->claims;

        // sem isso, um id_token assinado pela mesma chave passaria por logout
        $typ = strtolower((string)($decoded->header->typ ?? ''));
        if ($typ !== 'logout+jwt' && $typ !== 'application/logout+jwt') {
            throw new InvalidTokenException('Token is not a logout token');
        }

        if (($claims->iss ?? null) !== $this->discovery->get()->issuer) {
            throw new InvalidTokenException('Token was not issued by the central');
        }

        if (!\in_array($this->clientId, (array)($claims->aud ?? []), true)) {
            throw new InvalidTokenException('Token audience does not include this app');
        }

        // a lib só confere quando vêm; a spec exige os dois
        if (!is_numeric($claims->iat ?? null) || !is_numeric($claims->exp ?? null)) {
            throw new InvalidTokenException('Token has no iat or exp');
        }

        $events = $claims->events ?? null;
        if (!$events instanceof \stdClass || !(($events->{self::EVENT} ?? null) instanceof \stdClass)) {
            throw new InvalidTokenException('Token does not carry the back-channel logout event');
        }

        if (property_exists($claims, 'nonce')) {
            throw new InvalidTokenException('Token must not have a nonce');
        }

        $sub = self::nonEmptyString($claims->sub ?? null);
        $sid = self::nonEmptyString($claims->sid ?? null);
        if ($sub === null && $sid === null) {
            throw new InvalidTokenException('Token has neither sub nor sid');
        }

        return new LogoutToken($sub, $sid);
    }


    private static function nonEmptyString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
