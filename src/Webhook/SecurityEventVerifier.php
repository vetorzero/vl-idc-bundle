<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Webhook;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;

/**
 * Verifica um Security Event Token (RFC 8417) que a central mandou para este client.
 */
class SecurityEventVerifier
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
    public function verify(string $jwt): SecurityEvent
    {
        $decoded = $this->keySet->decode($jwt);
        $claims = $decoded->claims;

        // sem isso, um access token assinado pela mesma chave passaria por aviso
        if (strtolower((string)($decoded->header->typ ?? '')) !== 'secevent+jwt') {
            throw new InvalidTokenException('Token is not a security event token');
        }

        if (($claims->iss ?? null) !== $this->discovery->get()->issuer) {
            throw new InvalidTokenException('Token was not issued by the central');
        }

        if (!\in_array($this->clientId, (array)($claims->aud ?? []), true)) {
            throw new InvalidTokenException('Token audience does not include this app');
        }

        $jti = $claims->jti ?? null;
        if (!\is_string($jti) || $jti === '') {
            throw new InvalidTokenException('Token has no jti');
        }

        $events = $claims->events ?? null;
        if (!$events instanceof \stdClass || \count(get_object_vars($events)) !== 1) {
            throw new InvalidTokenException('Token does not carry exactly one event');
        }

        $type = (string)array_key_first(get_object_vars($events));

        return new SecurityEvent(
            id: $jti,
            type: $type,
            subject: self::subject($events->{$type}),
            occurredAt: is_numeric($claims->toe ?? null) ? new \DateTimeImmutable('@' . (int)$claims->toe) : null,
            own: ($claims->{'urn:vl-idc:claim:own'} ?? false) === true,
        );
    }


    private static function subject(mixed $payload): ?Subject
    {
        $subject = $payload instanceof \stdClass ? ($payload->subject ?? null) : null;
        if (!$subject instanceof \stdClass) {
            return null;
        }

        $type = $subject->type ?? null;
        $id = $subject->id ?? null;

        return \is_string($type) && \is_string($id) ? new Subject($type, $id) : null;
    }
}
