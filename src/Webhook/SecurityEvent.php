<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Webhook;

/**
 * Um aviso de mudança da central, já verificado. Não traz dados do alvo: o estado vem da API.
 */
final readonly class SecurityEvent
{
    public const string VERIFICATION = 'https://schemas.openid.net/secevent/ssf/event-type/verification';


    /**
     * @param string $id o `jti`, igual em todas as retentativas
     * @param string $type ex.: `urn:vl-idc:event:user.updated`
     * @param ?Subject $subject null no evento de verificação
     * @param ?\DateTimeImmutable $occurredAt o `toe`: quando a ação aconteceu
     * @param bool $own a mudança foi feita por este próprio client, via API
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?Subject $subject,
        public ?\DateTimeImmutable $occurredAt,
        public bool $own,
    ) {
    }


    public function isVerification(): bool
    {
        return $this->type === self::VERIFICATION;
    }
}
