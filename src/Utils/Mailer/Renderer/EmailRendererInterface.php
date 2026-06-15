<?php

namespace App\Utils\Mailer\Renderer;

use App\Utils\Mailer\Model\EmailInterface;

interface EmailRendererInterface
{
    public function render(EmailInterface $email): string;
}
