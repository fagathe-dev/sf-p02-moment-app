<?php

namespace App\Utils\Mailer\Renderer;

use App\Utils\Mailer\Model\EmailInterface;
use Fagathe\CorePhp\Logger\Logger;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;
use Twig\Error\Error as TwigError;

final class EmailRenderer implements EmailRendererInterface
{
    private Logger $logger;

    public function __construct(
        private Environment $twig,
        private Security $security
    ) {
        $this->logger = new Logger('email-renderer', $security, true);
    }

    public function render(EmailInterface $email): string
    {
        try {
            $this->logger->info([
                'message' => 'Rendu du template email',
                'template' => $email->getTemplate(),
                'subject' => $email->getSubject()
            ], ['action' => 'email.render.attempt']);

            $rendered = $this->twig->render($email->getTemplate(), $email->getContext());

            $this->logger->info([
                'message' => 'Template email rendu avec succès',
                'template' => $email->getTemplate(),
                'content_length' => strlen($rendered)
            ], ['action' => 'email.render.success']);

            return $rendered;

        } catch (TwigError $e) {
            $this->logger->error([
                'message' => 'Erreur de rendu du template email',
                'template' => $email->getTemplate(),
                'error' => $e->getMessage(),
                'line' => $e->getTemplateLine()
            ], ['action' => 'email.render.error']);

            throw $e;

        } catch (\Throwable $e) {
            $this->logger->critical([
                'message' => 'Erreur critique lors du rendu du template',
                'template' => $email->getTemplate(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e)
            ], ['action' => 'email.render.critical_error']);

            throw $e;
        }
    }
}
