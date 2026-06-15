<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\LogService;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de visualisation des logs.
 * 
 * Permet de visualiser les fichiers de log organisés par date et chemin, avec une navigation hiérarchique.
 * 
 * ⚠️ Réservé à l'environnement de développement (accès restreint ROLE_ADMIN).
 */
#[Route('/admin/log', name: 'admin_log_')]
final class LogController extends AbstractController
{
    public function __construct(
        private readonly LogService $logService
    ) {
    }


    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // On récupère toute la structure ['date' => [...]]
        $structure = $this->logService->getLogStructure();

        // On extrait seulement les dates (les clés)
        $dates = array_keys($structure);

        $breadcrumb = $this->logService->breadcrumb();

        return $this->render('admin/log/index.html.twig', [
            'dates' => $dates,
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Route 3 : Affiche le contenu du fichier de log.
     * URL: /admin/log/{date}/view/{filePath}
     * Requirements: filePath utilise des points comme séparateurs (ex: command.create-user ou security.login.attempts-login)
     */
    #[Route('/{date}/view/{filePath}', name: 'view', requirements: ['filePath' => '[\w\-\.]+', 'date' => '\d{2}-\d{2}-\d{4}'], methods: ['GET'])]
    public function view(string $date, string $filePath): Response
    {
        // Convertir les points en slashes pour le système de fichiers
        $realFilePath = str_replace('.', '/', $filePath);

        // Récupère le tableau d'objets Log
        $logs = $this->logService->getLogFileContent($date, $realFilePath);

        // Construction du breadcrumb avec navigation hiérarchique
        $filePathParts = explode('.', $filePath);
        $breadcrumbItems = [new BreadcrumbItem($date, $this->generateUrl('admin_log_show_date', ['date' => $date]))];

        $cumulativePath = [];
        foreach ($filePathParts as $index => $part) {
            $cumulativePath[] = $part;
            $isLast = $index === count($filePathParts) - 1;

            if ($isLast) {
                // Dernier élément = fichier (pas de lien)
                $breadcrumbItems[] = new BreadcrumbItem($part);
            } else {
                // Dossier intermédiaire (avec lien vers la navigation)
                $breadcrumbItems[] = new BreadcrumbItem(
                    $part,
                    $this->generateUrl('admin_log_show_date_path', [
                        'date' => $date,
                        'path' => implode('.', $cumulativePath)
                    ])
                );
            }
        }

        $breadcrumb = $this->logService->breadcrumb($breadcrumbItems);

        return $this->render('admin/log/view.html.twig', [
            'date' => $date,
            'filename' => $filePath,
            'logs' => $logs,
            'error' => null,
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Route 2a : Affiche la racine des fichiers disponibles pour une date spécifique.
     * URL: /admin/log/{date}
     */
    #[Route('/{date}', name: 'show_date', requirements: ['date' => '\d{2}-\d{2}-\d{4}'], methods: ['GET'], priority: 2)]
    public function showDate(string $date): Response
    {
        return $this->showDateWithPath($date, null);
    }

    /**
     * Route 2b : Affiche un sous-répertoire des fichiers disponibles pour une date spécifique.
     * URL: /admin/log/{date}/{path} (ex: /admin/log/07-12-2025/security.login)
     */
    #[Route('/{date}/{path}', name: 'show_date_path', requirements: ['date' => '\d{2}-\d{2}-\d{4}', 'path' => '[\w\-\.]+'], methods: ['GET'], priority: 1)]
    public function showDatePath(string $date, string $path): Response
    {
        return $this->showDateWithPath($date, $path);
    }

    /**
     * Méthode privée pour gérer l'affichage avec ou sans path.
     */
    private function showDateWithPath(string $date, ?string $path): Response
    {
        $structure = $this->logService->getLogStructure();

        if (!isset($structure[$date])) {
            throw $this->createNotFoundException("Aucun log trouvé pour la date $date");
        }

        // Navigation dans la structure selon le path
        $items = $structure[$date];
        $breadcrumbItems = [new BreadcrumbItem($date, $this->generateUrl('admin_log_show_date', ['date' => $date]))];

        if ($path) {
            $pathParts = explode('.', $path);
            $cumulativePath = [];

            foreach ($pathParts as $part) {
                $cumulativePath[] = $part;

                if (!isset($items[$part]) || !is_array($items[$part])) {
                    throw $this->createNotFoundException("Chemin de log introuvable : $path");
                }

                $breadcrumbItems[] = new BreadcrumbItem(
                    $part,
                    $this->generateUrl('admin_log_show_date_path', [
                        'date' => $date,
                        'path' => implode('.', $cumulativePath)
                    ])
                );

                $items = $items[$part];
            }
        }

        $breadcrumb = $this->logService->breadcrumb($breadcrumbItems);

        return $this->render('admin/log/show_date.html.twig', [
            'date' => $date,
            'items' => $items,
            'breadcrumb' => $breadcrumb,
            'currentPath' => $path ?? '',
        ]);
    }


}