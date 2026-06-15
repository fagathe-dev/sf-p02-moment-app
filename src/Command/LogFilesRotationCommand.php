<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Commande de rotation des fichiers de logs.
 * * Supprime automatiquement les fichiers de logs obsolètes
 * selon la durée de rétention configurée (LOGS_RETENTION_DELAY).
 */
#[AsCommand(
    name: 'app:log-file-rotation',
    description: 'Rotation et nettoyage des fichiers de logs obsolètes',
)]
class LogFilesRotationCommand extends Command
{
    public function __construct(
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(
            <<<'HELP'
Cette commande nettoie automatiquement les fichiers de logs obsolètes.

Les fichiers plus anciens que LOGS_RETENTION_DELAY jours (configuré dans constants.php)
seront supprimés. Les dossiers vides sont également nettoyés.

Utilisation :
  php bin/console app:log-file-rotation

La commande affichera :
  - La date limite de conservation
  - Le nombre de fichiers supprimés
  - Le nombre de dossiers vides supprimés
  - Les éventuelles erreurs rencontrées

Il est recommandé d'ajouter cette commande dans un cron pour l'exécuter quotidiennement.
HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🗂️  Rotation des fichiers de logs');
        
        $logsDir = defined('LOGS_DIR') ? LOGS_DIR : null;
        $retentionDays = defined('LOGS_RETENTION_DELAY') ? LOGS_RETENTION_DELAY : 30;

        if (!$logsDir || !$this->filesystem->exists($logsDir)) {
            $io->error(sprintf("Le répertoire des logs n'existe pas ou n'est pas défini : %s", $logsDir ?? 'Non défini'));
            return Command::FAILURE;
        }

        $io->text([
            'Durée de rétention : ' . $retentionDays . ' jours',
            'Répertoire des logs : ' . $logsDir,
        ]);
        $io->newLine();

        $report = [
            'success' => true,
            'threshold_date' => (new \DateTimeImmutable("-{$retentionDays} days"))->format('d/m/Y H:i:s'),
            'files_deleted' => 0,
            'folders_deleted' => 0,
            'errors' => []
        ];

        // 1. Suppression des vieux fichiers JSON
        try {
            $finder = new Finder();
            $finder->files()
                   ->in($logsDir)
                   ->name('*.json')
                   ->date('< now - ' . $retentionDays . ' days');
            
            foreach ($finder as $file) {
                try {
                    $this->filesystem->remove($file->getRealPath());
                    $report['files_deleted']++;
                } catch (\Throwable $e) {
                    $report['errors'][] = "Impossible de supprimer le fichier {$file->getFilename()} : " . $e->getMessage();
                    $report['success'] = false;
                }
            }
        } catch (\Throwable $e) {
            $report['errors'][] = "Erreur lors de la recherche des fichiers : " . $e->getMessage();
            $report['success'] = false;
        }

        // 2. Nettoyage des dossiers vides restants
        try {
            $folderFinder = new Finder();
            $folderFinder->directories()->in($logsDir);
            
            // Tri par profondeur pour supprimer les enfants avant les parents
            $directories = iterator_to_array($folderFinder);
            usort($directories, fn($a, $b) => substr_count($b->getRealPath(), DIRECTORY_SEPARATOR) <=> substr_count($a->getRealPath(), DIRECTORY_SEPARATOR));

            foreach ($directories as $dir) {
                $dirPath = $dir->getRealPath();
                $innerFinder = new Finder();
                $innerFinder->in($dirPath)->ignoreDotFiles(true);
                
                if (!count($innerFinder)) {
                    try {
                        $this->filesystem->remove($dirPath);
                        $report['folders_deleted']++;
                    } catch (\Throwable $e) {
                        $report['errors'][] = "Impossible de supprimer le dossier vide " . basename($dirPath);
                        $report['success'] = false;
                    }
                }
            }
        } catch (\Throwable $e) {
             $report['errors'][] = "Erreur lors du nettoyage des dossiers : " . $e->getMessage();
             $report['success'] = false;
        }

        // 3. Affichage du rapport final
        if ($report['success'] && empty($report['errors'])) {
            $io->success([
                'Rotation des logs terminée avec succès ! 🚀',
                '',
                'Statistiques :',
                sprintf('  • Date limite de conservation : %s', $report['threshold_date']),
                sprintf('  • Fichiers supprimés : %d', $report['files_deleted']),
                sprintf('  • Dossiers vides supprimés : %d', $report['folders_deleted']),
            ]);

            return Command::SUCCESS;
        } else {
            $io->warning([
                'Rotation des logs terminée avec des erreurs',
                '',
                'Statistiques :',
                sprintf('  • Date limite de conservation : %s', $report['threshold_date']),
                sprintf('  • Fichiers supprimés : %d', $report['files_deleted']),
                sprintf('  • Dossiers vides supprimés : %d', $report['folders_deleted']),
                sprintf('  • Erreurs rencontrées : %d', count($report['errors'])),
            ]);

            $io->newLine();
            $io->section('Détails des erreurs');
            foreach ($report['errors'] as $error) {
                $io->text('  ❌ ' . $error);
            }

            return Command::FAILURE;
        }
    }
}