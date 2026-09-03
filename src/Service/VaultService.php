<?php

namespace App\Service;

use App\Entity\Entry;
use App\Entity\User;
use App\Repository\EntryRepository;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Request;

final class VaultService
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly UserService $userService,
        private readonly Environment $twig
    ) {
    }

    public function isVaultTokenValid(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $base64Token = substr($authHeader, 7);

        return $this->userService->verifyVaultSession($base64Token);
    }

    /**
     * @return string Rendered HTML for private entries feed
     */
    public function renderEntriesHtml(User $owner): string
    {
        $entries = $this->entryRepository->findBy(
            ['owner' => $owner, 'is_private' => true],
            ['created_at' => 'DESC']
        );

        $html = '';
        foreach ($entries as $entry) {
            $html .= '<div class="entry-masonry-item">';
            $html .= $this->twig->render('app/entry/_component.html.twig', [
                'entry' => $entry,
                'fromVault' => true,
            ]);
            $html .= '</div>';
        }

        return $html;
    }

    public function getEntryData(Entry $entry): array
    {
        return [
            'title' => $entry->getTitle(),
            'content' => $entry->getContent(),
        ];
    }
}
