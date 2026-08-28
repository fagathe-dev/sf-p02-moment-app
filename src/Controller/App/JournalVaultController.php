<?php

namespace App\Controller\App;

use App\Entity\Entry;
use App\Form\App\VaultEntryType;
use App\Repository\EntryRepository;
use App\Service\UserService;
use App\Service\VaultService;
use Cocur\Slugify\Slugify;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/vault/journal', name: 'app_vault_journal_')]
#[IsGranted('ROLE_USER')]
final class JournalVaultController extends AbstractController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntryRepository $entryRepository,
        private readonly UserService $userService,
        private readonly VaultService $vaultService
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->userService->getCurrentUser();
        $entries = $this->entryRepository->findBy(
            ['owner' => $user, 'is_private' => true],
            ['created_at' => 'DESC']
        );

        return $this->render('app/view/vault.html.twig', [
            'entries' => $entries,
            'breadcrumb' => $this->breadcrumb(),
        ]);
    }

    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();

        $entry = new Entry();
        $entry->setIsPrivate(true);
        $entry->setOwner($user);

        $form = $this->createForm(VaultEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slugify = new Slugify();
            $entry->setSlug($entry->getTitle() ? $slugify->slugify($entry->getTitle()) : date('Y-m-d-His'));
            $entry->setCreatedAt(new \DateTimeImmutable());

            $this->entryRepository->save($entry, true, true);

            $this->addFlash('success', 'Souvenir privé scellé avec succès.');
            return $this->redirectToRoute('app_vault_journal_index');
        }

        return $this->render('app/vault/add.html.twig', [
            'form' => $form->createView(),
            'breadcrumb' => $this->breadcrumb([new BreadcrumbItem(name: 'Ajouter un souvenir privé')]),
        ]);
    }

    #[Route('/show/{slug}-{id}', name: 'show', methods: ['GET'], requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'id' => '\d+'])]
    public function show(#[MapEntity(mapping: ['id' => 'id'])] Entry $entry): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$entry->isPrivate()) {
            return $this->redirectToRoute('app_journal_show', ['id' => $entry->getId(), 'slug' => $entry->getSlug()]);
        }

        return $this->render('app/vault/show.html.twig', [
            'entry' => $entry,
            'breadcrumb' => $this->breadcrumb([new BreadcrumbItem(name: 'Souvenir privé')]),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Entry $entry, Request $request): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$entry->isPrivate()) {
            return $this->redirectToRoute('app_journal_edit', ['id' => $entry->getId()]);
        }

        $form = $this->createForm(VaultEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slugify = new Slugify();
            $entry->setSlug($entry->getTitle() ? $slugify->slugify($entry->getTitle()) : $entry->getSlug());
            $entry->setUpdatedAt(new \DateTimeImmutable());

            $this->entryRepository->save($entry, true, false);

            $this->addFlash('success', 'Souvenir privé mis à jour.');
            return $this->redirectToRoute('app_vault_journal_index');
        }

        return $this->render('app/vault/edit.html.twig', [
            'form' => $form->createView(),
            'entry' => $entry,
            'breadcrumb' => $this->breadcrumb([new BreadcrumbItem(name: 'Modifier le souvenir')]),
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Entry $entry, Request $request): Response
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        $submittedToken = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $entry->getId(), $submittedToken)) {
            $this->entryRepository->remove($entry, true);
            $this->addFlash('success', 'Le souvenir a été supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token de sécurité invalide, impossible de supprimer.');
        }

        return $this->redirectToRoute('app_vault_journal_index');
    }

    #[Route('/api/entries', name: 'api_entries', methods: ['GET'])]
    public function apiEntries(Request $request): JsonResponse
    {
        if (!$this->vaultService->isVaultTokenValid($request)) {
            return new JsonResponse(['error' => 'Session intime invalide ou expirée'], 403);
        }

        $user = $this->userService->getCurrentUser();
        $html = $this->vaultService->renderEntriesHtml($user);

        return new JsonResponse(['success' => true, 'html' => $html]);
    }

    #[Route('/api/entry/{id}', name: 'api_entry_data', methods: ['GET'])]
    public function apiEntryData(#[MapEntity(mapping: ['id' => 'id'])] Entry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('OWNER', $entry);

        if (!$this->vaultService->isVaultTokenValid($request)) {
            return new JsonResponse(['error' => 'Session intime invalide ou expirée'], 403);
        }

        $data = $this->vaultService->getEntryData($entry);

        return new JsonResponse(['success' => true, 'data' => $data]);
    }

    private function breadcrumb(array $items = []): Breadcrumb
    {
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Mon coffre-fort', link: $this->urlGenerator->generate('app_vault_journal_index')),
            ...$items,
        ]);
    }
}