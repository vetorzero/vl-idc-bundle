<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api;

/**
 * Uma pessoa da central, só com os campos que algum app usa.
 */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {
    }


    /**
     * @param array<string, mixed> $data
     * @throws \UnexpectedValueException
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'email'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                throw new \UnexpectedValueException("The user has no '{$key}'.");
            }
        }

        return new self($data['id'], $data['name'], $data['email']);
    }
}
