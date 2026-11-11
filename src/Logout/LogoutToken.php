<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Logout;

/**
 * Um logout token da central, já verificado. Traz pelo menos um dos dois.
 */
final readonly class LogoutToken
{
    /**
     * @param ?string $sub a pessoa; sozinho, encerra todas as sessões dela
     * @param ?string $sid a sessão da central que foi encerrada
     */
    public function __construct(
        public ?string $sub,
        public ?string $sid,
    ) {
    }
}
