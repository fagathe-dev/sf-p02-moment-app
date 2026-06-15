<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Utils\Mailer\Enum\EmailTypeEnum;
use App\Utils\Mailer\Service\EmailMockFactory;
use App\Utils\Mailer\Service\EmailPreviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de prévisualisation des e-mails.
 * 
 * Permet de visualiser le rendu HTML des templates d'e-mails directement
 * dans le navigateur pour faciliter l'intégration CSS et le debug.
 * 
 * ⚠️ Réservé à l'environnement de développement (accès restreint ROLE_ADMIN).
 */
#[Route(path: '/admin/emails', name: 'admin_email_')]
final class EmailController extends AbstractController
{
    public function __construct(
        private readonly EmailPreviewService $emailPreviewService,
        private readonly EmailMockFactory $emailMockFactory,
    ) {
    }

    /**
     * Liste tous les types d'e-mails disponibles pour prévisualisation.
     */
    #[Route(path: '', name: 'index')]
    public function index(): Response
    {
        $types = $this->emailMockFactory->getAvailableTypes();

        return $this->render('emails/preview/index.html.twig', [
            'types' => $types,
        ]);
    }

    /**
     * Affiche le rendu HTML d'un e-mail spécifique.
     */
    #[Route(path: '/preview/{type}', name: 'preview')]
    public function preview(string $type): Response
    {
        $emailType = EmailTypeEnum::tryFrom($type);

        if ($emailType === null) {
            throw $this->createNotFoundException(sprintf('Type d\'email inconnu : "%s"', $type));
        }

        $html = $this->emailPreviewService->preview($emailType);

        return new Response($html);
    }
}
