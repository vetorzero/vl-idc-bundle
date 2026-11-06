<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Webhook;

/**
 * O alvo de um evento, com o mesmo id que a API usa para ele.
 */
final readonly class Subject
{
    /** @param string $type `user`, `client`, `organization`, `app`, `group` ou `permission` */
    public function __construct(
        public string $type,
        public string $id,
    ) {
    }
}
