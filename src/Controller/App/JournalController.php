<?php

namespace App\Controller\App;

use App\Entity\Entry;
use App\Entity\User;
use App\Enum\EntryColorEnum;
use App\Enum\MoodEnum;
use App\Repository\CategoryRepository;
use App\Service\EntryMediaService;
use App\Service\EntryService;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/app/journal', name: 'app_journal_')]
final class JournalController extends AbstractController
{
    public function __construct(
        private readonly EntryService $entryService,
        private readonly EntryMediaService $mediaService,
        private readonly CategoryRepository $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    // =========================================================================
    // Vues
    // =========================================================================

    #[Route('/add', name: 'add', methods: ['GET'])]
    public function add(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('app/journal/add.html.twig', $this->formData());
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET'])]
    public function edit(Entry $entry): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        return $this->render('app/journal/edit.html.twig', $this->formData($entry));
    }

    #[Route('/{slug}-{id}', name: 'show', methods: ['GET'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'id' => '\d+'])]
    public function show(#[MapEntity(mapping: ['id' ])] Entry $entry): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        return $this->render('app/journal/show.html.twig', [
            'entry' => $entry,
            'breadcrumb' => $this->breadcrumb([
                new BreadcrumbItem(name: $entry->getTitle() ?? 'Entrée'),
            ]),
        ]);
    }

    // =========================================================================
    // API — FormData (texte + fichiers dans un seul POST)
    // =========================================================================

    #[Route('/api/save', name: 'api_save', methods: ['POST'])]
    public function apiSave(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('journal_save', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $result = $this->entryService->saveEntry(null, $request, $this->currentUser());

        return $this->json($result, $result['success'] ? Response::HTTP_CREATED : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/api/update/{id}', name: 'api_update', methods: ['POST'])]
    public function apiUpdate(Request $request, Entry $entry): JsonResponse
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$this->isCsrfTokenValid('journal_save', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $result = $this->entryService->saveEntry($entry, $request, $this->currentUser());

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/api/delete/{id}', name: 'api_delete', methods: ['DELETE'])]
    public function apiDelete(Request $request, Entry $entry): JsonResponse
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$this->isCsrfTokenValid('journal_delete_' . $entry->getId(), $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $success = $this->entryService->deleteEntry($entry->getId(), $this->currentUser());

        return $this->json(
            ['success' => $success, 'redirectUrl' => $this->generateUrl('app_view_feed')],
            $success ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    private function formData(?Entry $entry = null): array
    {
        $breadcrumb = $this->breadcrumb(
            $entry
            ? [new BreadcrumbItem(name: 'Modifier une entrée')]
            : [new BreadcrumbItem(name: 'Ajouter une entrée')]
        );

        return [
            'entry' => $entry,
            'categories' => $this->categoryRepository->findByOwner($this->currentUser()),
            'moods' => MoodEnum::cases(),
            'colors' => EntryColorEnum::cases(),
            'breadcrumb' => $breadcrumb,
        ];
    }

    private function breadcrumb(array $items = []): Breadcrumb
    {
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Mon journal', link: $this->urlGenerator->generate('app_view_feed')),
            ...$items,
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = parent::getUser();

        return $user;
    }
}