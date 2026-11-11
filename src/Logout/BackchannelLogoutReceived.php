<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Logout;

/**
 * Despachado de forma síncrona pelo receptor, para cada logout token válido.
 *
 * Uma exceção num listener vira 5xx: a central manda o logout de novo depois, com outro token.
 * Por isso quem escuta tem que aguentar receber o mesmo logout mais de uma vez.
 */
final readonly class BackchannelLogoutReceived
{
    public function __construct(
        public LogoutToken $token,
    ) {
    }
}
