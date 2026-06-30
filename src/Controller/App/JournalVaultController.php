<?php

namespace App\Controller\App;

use App\Entity\Entry;
use App\Entity\User;
use App\Enum\EntryColorEnum;
use App\Enum\MoodEnum;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;
use App\Service\UserService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/app/vault/journal', name: 'app_vault_journal_')]
final class JournalVaultController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    // =========================================================================
    // 1. Vues d'interface (Squelettes verrouillés par JS)
    // =========================================================================

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('app/view/vault.html.twig', [
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['GET'])]
    public function add(CategoryRepository $categoryRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('app/vault/add.html.twig', [
            'entry' => null,
            'force_private' => true,
            'categories' => $categoryRepository->findByOwner($user),
            'moods' => MoodEnum::cases(),
            'colors' => EntryColorEnum::cases(),
            'breadcrumb' => $this->breadcrumb([new BreadcrumbItem(name: 'Ajouter un souvenir intime')]),
        ]);
    }

    #[Route('/show/{slug}-{id}', name: 'show', methods: ['GET'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'id' => '\d+'])]
    public function show(#[MapEntity(mapping: ['id' => 'id'])] Entry $entry): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        // Si l'entrée n'est pas privée, on redirige vers le journal public
        if (!$entry->isPrivate()) {
            return $this->redirectToRoute('app_journal_show', ['id' => $entry->getId(), 'slug' => $entry->getSlug()]);
        }

        return $this->render('app/vault/show.html.twig', [
            'entry' => $entry,
            'breadcrumb' => $this->breadcrumb([
                new BreadcrumbItem(name: 'Souvenir intime'),
            ]),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET'])]
    public function edit(Entry $entry, CategoryRepository $categoryRepository): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$entry->isPrivate()) {
            return $this->redirectToRoute('app_journal_edit', ['id' => $entry->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('app/vault/edit.html.twig', [
            'entry' => $entry,
            'categories' => $categoryRepository->findByOwner($user),
            'moods' => MoodEnum::cases(),
            'colors' => EntryColorEnum::cases(),
            'breadcrumb' => $this->breadcrumb([new BreadcrumbItem(name: 'Modifier le souvenir')]),
        ]);
    }

    // =========================================================================
    // 2. API Sécurisées (Requiert le Token JS)
    // =========================================================================

    #[Route('/api/entries', name: 'api_entries', methods: ['GET'])]
    public function apiEntries(Request $request, UserService $userService, EntryRepository $entryRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isVaultTokenValid($request, $userService)) {
            return new JsonResponse(['error' => 'Session intime invalide ou expirée'], 403);
        }

        $entries = $entryRepository->findPrivateByOwnerOrderedByDate();

        $html = '';
        foreach ($entries as $entry) {
            // On encapsule le rendu du composant dans la structure de colonne Bootstrap
            $html .= '<div class="col-12 col-md-6 col-lg-4">';
            $html .= $this->renderView('app/entry/_component.html.twig', [
                'entry' => $entry,
                'fromVault' => true // Pour utiliser la bonne route
            ]);
            $html .= '</div>';
        }

        return new JsonResponse(['success' => true, 'html' => $html]);
    }

    #[Route('/api/entry/{id}', name: 'api_entry_data', methods: ['GET'])]
    public function apiEntryData(Entry $entry, Request $request, UserService $userService): JsonResponse
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$this->isVaultTokenValid($request, $userService)) {
            return new JsonResponse(['error' => 'Session intime invalide ou expirée'], 403);
        }

        // Cette route servira au JS pour récupérer les champs confidentiels pour les injecter
        // dans le DOM de `show.html.twig` ou `edit.html.twig` après déverrouillage
        return new JsonResponse([
            'success' => true,
            'data' => [
                'title' => $entry->getTitle(),
                'content' => $entry->getContent(),
            ]
        ]);
    }

    private function isVaultTokenValid(Request $request, UserService $userService): bool
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        $base64Token = substr($authHeader, 7);

        return $userService->verifyVaultSession($base64Token);
    }

    private function breadcrumb(array $items = []): Breadcrumb
    {
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Mon coffre-fort', link: $this->urlGenerator->generate('app_vault_journal_index')),
            ...$items,
        ]);
    }
}