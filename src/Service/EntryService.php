<?php
 
namespace App\Service;
 
use App\Entity\Entry;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\EntryMediaRepository;
use App\Repository\EntryRepository;
use Cocur\Slugify\Slugify;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
 
final class EntryService
{
    use LoggerTrait, DatetimeTrait;
 
    public function __construct(
        private readonly EntryRepository $repository,
        private readonly CategoryRepository $categoryRepository,
        private readonly EntryMediaRepository $mediaRepository,
        private readonly EntryMediaService $mediaService,
        private readonly LocationService $locationService,
        private readonly ValidatorInterface $validator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security
    ) {
    }
 
    /**
     * Crée ou met à jour une entrée depuis une Request (FormData).
     *
     * @return array{success: bool, errors?: array<string, string[]>, entry?: array{id: int}, redirectUrl?: string}
     */
    public function saveEntry(?Entry $entry, Request $request, User $user): array
    {
        $isCreation = $entry === null;
 
        if ($isCreation) {
            $entry = new Entry();
        }
 
        $data = $this->extractData($request);
 
        $this->hydrate($entry, $data);
 
        $violations = $this->validator->validate($entry);
 
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = $violation->getPropertyPath() ?: 'content';
                $errors[$field][] = $violation->getMessage();
            }
 
            return ['success' => false, 'errors' => $errors];
        }

 
        $this->syncCategories($entry, $data['categories']);
        $this->handleLocation($entry, $data, $user);
 
        if (!$isCreation) {
            $this->handleDeletedMedias($data['deleted_medias']);
        }
 
        $entry->setOwner($user);
 
        $saved = $this->repository->save($entry, true, $isCreation);
 
        if (!$saved) {
            return ['success' => false, 'errors' => ['_base' => ['Erreur lors de la sauvegarde.']]];
        }
 
        // Upload des nouveaux médias joints dans le FormData
        $this->handleNewMedias($request->files->get('medias') ?? [], $entry, $user);
 
        $this->generateLog(
            LoggerLevelEnum::Info,
            [
                'message' => $isCreation ? 'Entrée créée' : 'Entrée mise à jour',
                'entry_id' => $entry->getId(),
                'user_id' => $user->getId(),
            ],
            ['action' => $isCreation ? 'app.entry.create.success' : 'app.entry.update.success']
        );
 
        return [
            'success' => true,
            'entry' => ['id' => $entry->getId()],
            'redirectUrl' => $this->urlGenerator->generate('app_view_feed'),
        ];
    }
 
    /**
     * Supprime une entrée et ses médias.
     */
    public function deleteEntry(int $id, User $user): bool
    {
        $entry = $this->repository->find($id);
 
        if ($entry === null) {
            return false;
        }
 
        try {
            $this->mediaService->deleteByEntry($entry);
            $this->repository->remove($entry);
 
            $this->generateLog(
                LoggerLevelEnum::Info,
                ['message' => 'Entrée supprimée', 'entry_id' => $id, 'user_id' => $user->getId()],
                ['action' => 'app.entry.delete.success']
            );
 
            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                ['message' => 'Erreur suppression entrée', 'entry_id' => $id, 'error' => $th->getMessage()],
                ['action' => 'app.entry.delete.error']
            );
 
            return false;
        }
    }
 
    /**
     * Données pour la page de gestion du journal.
     */
    public function manage(User $user): array
    {
        return [
            'entries' => $this->repository->findByOwnerOrderedByDate($user),
            'breadcrumb' => $this->breadcrumb(),
        ];
    }
 
    public function breadcrumb(array $items = []): Breadcrumb
    {
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Mon journal', link: $this->urlGenerator->generate('app_view_feed')),
            ...$items,
        ]);
    }
 
    // =========================================================================
    // Helpers privés
    // =========================================================================
 
    /**
     * Extrait et normalise les données depuis la Request (FormData).
     */
    private function extractData(Request $request): array
    {
        $bag = $request->request;
 
        return [
            'title' => trim((string) ($bag->get('title') ?? '')),
            'content' => trim((string) ($bag->get('content') ?? '')),
            'mood'  => $bag->get('mood')  ?: null,
            'color' => $bag->get('color') ?: null,
            'is_private' => $bag->getBoolean('is_private'),
            'categories' => $bag->all('categories[]'),
            'location_lat' => $bag->get('location_lat') ?: null,
            'location_lng' => $bag->get('location_lng') ?: null,
            'location_name' => $bag->get('location_name') ?: null,
            'deleted_medias' => $bag->all('deleted_medias'),
        ];
    }
 
    private function hydrate(Entry $entry, array $data): void
    {
        $slugify = new Slugify();
 
        $title = $data['title'] ?: null;
        $content = $data['content'] ?: null;
        $slug = $title ? $slugify->slugify($title) : date('Y-m-d-His');
 
        $entry
            ->setTitle($title)
            ->setContent($content)
            ->setSlug($slug)
            ->setMood($data['mood'])
            ->setColor($data['color'] ?? null)
            ->setIsPrivate($data['is_private'])
        ;
 
        if (!$entry->getCreatedAt()) {
            $entry->setCreatedAt($this->now());
        } else {
            $entry->setUpdatedAt($this->now());
        }
    }
 
    private function syncCategories(Entry $entry, array $categoryIds): void
    {
        foreach ($entry->getCategories() as $existing) {
            $entry->removeCategory($existing);
        }
 
        foreach ($categoryIds as $id) {
            $category = $this->categoryRepository->find((int) $id);
            if ($category !== null) {
                $entry->addCategory($category);
            }
        }
    }
 
    private function handleLocation(Entry $entry, array $data, User $user): void
    {
        $lat = $data['location_lat'] !== null ? (float) $data['location_lat'] : null;
        $lng = $data['location_lng'] !== null ? (float) $data['location_lng'] : null;
        $name = $data['location_name'];
 
        if ($lat !== null && $lng !== null && $name !== null) {
            $location = $this->locationService->createFromCoordinates($lat, $lng, $name, $user);
            $entry->setLocation($location);
        }
    }
 
    private function handleDeletedMedias(array $ids): void
    {
        foreach ($ids as $id) {
            $media = $this->mediaRepository->find((int) $id);
            if ($media !== null) {
                $this->mediaService->delete($media);
            }
        }
    }
 
    /**
     * @param UploadedFile[] $files
     */
    private function handleNewMedias(array $files, Entry $entry, User $user): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
 
            try {
                $this->mediaService->upload($file, $entry, $user);
            } catch (Throwable $th) {
                $this->generateLog(
                    LoggerLevelEnum::Warning,
                    ['message' => 'Échec upload média', 'file' => $file->getClientOriginalName(), 'error' => $th->getMessage()],
                    ['action' => 'app.entry.media.upload.error']
                );
            }
        }
    }
}