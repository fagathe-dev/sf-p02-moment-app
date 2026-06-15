<?php

namespace App\Utils\Mailer\Model;

use App\Utils\Mailer\Enum\EmailTypeEnum;

interface EmailInterface
{
    // Type d’email
    public function getType(): EmailTypeEnum;

    // Sujet et template
    public function getSubject(): string;
    public function getTemplate(): string;

    // Contexte pour Twig
    public function getContext(): array;

    // Adresses
    public function getFrom(): ?array;        // ['email' => string, 'name' => ?string]
    public function getTo(): array;           // [['email' => string, 'name' => ?string], ...]
    public function getCc(): array;           // idem
    public function getBcc(): array;          // idem
}
