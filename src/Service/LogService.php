<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Http\Cookie;
use Fagathe\CorePhp\Logger\Log;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service for managing application logs.
 * Handles retrieval and organization of log files.
 * 
 * Provides business logic layer above the filesystem for log management operations.
 * 
 * @author fagathe-dev <https://github.com/fagathe-dev/>
 */
final class LogService
{
    use DatetimeTrait;

    private const DATE_PATTERN = '/-(\d{2}-\d{2}-\d{4})\.json$/';

    // Configuration du cookie
    private const COOKIE_KEY = '__ffrv.lgfls';
    private const COOKIE_RETENTION = 1200; // 20 minutes en secondes

    private string $logDir;
    private Filesystem $filesystem;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->filesystem = new Filesystem();
        $this->logDir = LOGS_DIR; // Constante définie dans le projet
    }

    /**
     * Récupère la structure arborescente des logs groupés par date.
     * * @return array Structure: ['dd-mm-yyyy' => ['folder' => ['file'], 'file']]
     */
    public function getLogStructure(): array
    {
        if (!$this->filesystem->exists($this->logDir)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($this->logDir)->name('*.json')->sortByName();

        $structure = [];

        foreach ($finder as $file) {
            $this->processFileIntoStructure($file, $structure);
        }

        // Tri des dates (plus récent en premier)
        uksort($structure, function ($a, $b) {
            $dateA = DateTimeImmutable::createFromFormat('d-m-Y', (string) $a);
            $dateB = DateTimeImmutable::createFromFormat('d-m-Y', (string) $b);

            return $this->isNewerThan($dateB, $dateA) ? 1 : -1;
        });

        // Mise à jour du cookie de "dernière activité" ou "consultation"
        $this->refreshCookie();

        return $structure;
    }

    /**
     * Récupère le contenu d'un fichier de log spécifique.
     *
     * @param string $date La date (ex: 02-11-2025)
     * @param string $filePath Le chemin relatif/nom (ex: 'command/create-user' ou 'toto')
     * @return Log[] Tableau d'objets Log
     */
    public function getLogFileContent(string $date, string $filePath): array
    {
        // Reconstruction du nom de fichier réel
        // filePath arrive comme "command/create-user", le fichier physique est "logs/command/create-user-02-11-2025.json"

        // On sépare le dossier du nom de fichier si nécessaire
        $pathParts = explode('/', $filePath);
        $fileName = array_pop($pathParts);
        $subDir = implode('/', $pathParts);

        $realFileName = $fileName . '-' . $date . '.json';

        $fullPath = $this->logDir;
        if (!empty($subDir)) {
            $fullPath .= DIRECTORY_SEPARATOR . $subDir;
        }
        $fullPath .= DIRECTORY_SEPARATOR . $realFileName;

        if (!$this->filesystem->exists($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath);
        if (empty($content)) {
            return [];
        }

        $logsData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentative de lecture ligne par ligne si ce n'est pas un tableau JSON global
            // (cas fréquent des logs qui append des lignes JSON indépendantes)
            $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logsData = array_map(fn($line) => json_decode($line, true), $lines);
        }

        // Rafraîchir le cookie lors de la lecture
        $this->refreshCookie();

        // Conversion des données en objets Log
        return array_map(fn($logData) => Log::fromArray($logData), $logsData);
    }

    /**
     * Traite un fichier Finder pour l'insérer dans la structure array.
     * Supporte n'importe quelle profondeur de sous-dossiers.
     */
    private function processFileIntoStructure(SplFileInfo $file, array &$structure): void
    {
        $relativePath = $file->getRelativePathname(); // ex: security/login/attempts-login-07-12-2025.json

        // Extraction de la date via Regex
        if (!preg_match(self::DATE_PATTERN, $relativePath, $matches)) {
            return;
        }

        $date = $matches[1];

        // Nettoyage pour obtenir la "clé" du fichier
        // On retire la date et l'extension
        $cleanPath = str_replace('-' . $date . '.json', '', $relativePath);
        // On normalise les séparateurs
        $cleanPath = str_replace('\\', '/', $cleanPath);

        if (!isset($structure[$date])) {
            $structure[$date] = [];
        }

        $parts = explode('/', $cleanPath);

        if (count($parts) === 1) {
            // C'est un fichier à la racine (ex: security)
            $structure[$date][] = $cleanPath;
        } else {
            // C'est un fichier dans un ou plusieurs sous-dossiers
            // Ex: security/login/attempts-login -> ['security', 'login', 'attempts-login']
            $filename = array_pop($parts); // Dernier élément = nom du fichier

            // Naviguer dans la structure de manière récursive
            $current = &$structure[$date];

            foreach ($parts as $folder) {
                if (!isset($current[$folder])) {
                    $current[$folder] = [];
                }
                $current = &$current[$folder];
            }

            // Ajouter le fichier au bon niveau
            $current[] = $filename;
        }
    }

    /**
     * Gestion du cookie de persistance/rétention.
     * Utilise la classe Cookie fournie statiquement.
     */
    private function refreshCookie(): void
    {
        // La valeur peut être la date courante ou un token simple
        // Ici on stocke le Timestamp actuel pour vérifier la rétention plus tard si besoin
        $value = ($this->now())->format('Y-m-d H:i:s');

        Cookie::set(self::COOKIE_KEY, $value, [
            'expires' => time() + self::COOKIE_RETENTION, // 20 minutes
            'path' => '/admin/log', // Restreint au path des logs pour la sécurité
            'samesite' => 'Lax',
            'httponly' => true
        ]);
    }

    /**
     * @param BreadcrumbItem[] $items
     * 
     * @return Breadcrumb
     */
    public function breadcrumb(array $items = []): Breadcrumb
    {
        $items = [
            new BreadcrumbItem('Logs', $this->urlGenerator->generate('admin_log_index')),
            ...$items
        ];

        return new Breadcrumb($items);
    }
}