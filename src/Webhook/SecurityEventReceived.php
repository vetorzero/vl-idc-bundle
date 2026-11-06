<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Webhook;

/**
 * Despachado de forma síncrona pelo receptor, para cada aviso que não é de verificação.
 *
 * Uma exceção num listener vira 5xx: a central segura a fila e manda o mesmo aviso de novo depois.
 */
final readonly class SecurityEventReceived
{
    public function __construct(
        public SecurityEvent $event,
    ) {
    }
}
