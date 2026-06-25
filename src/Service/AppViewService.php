<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\MoodEnum;
use App\Repository\CategoryRepository;
use App\Repository\EntryRepository;
use App\Repository\LocationRepository;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppViewService
{
    use LoggerTrait;

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly LocationRepository $locationRepository,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    // =========================================================================
    // Vue Feed
    // =========================================================================

    /**
     * @return array{entries: \App\Entity\Entry[], breadcrumb: Breadcrumb}
     */
    public function feedView(): array
    {
        $user = $this->getCurrentUser();

        return [
            'entries'    => $user ? $this->entryRepository->findByOwnerOrderedByDate($user) : [],
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
    // Vue Insights
    // =========================================================================

    /**
     * @return array{
     *   stats:             array<string, int>,
     *   moodDistribution:  array<string, array{label: string, emoji: string, count: int}>,
     *   topCategories:     \App\Entity\Category[],
     *   topLocations:      \App\Entity\Location[],
     * }
     */
    public function insights(): array
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return [
                'stats'            => [],
                'moodDistribution' => [],
                'topCategories'    => [],
                'topLocations'     => [],
            ];
        }

        $entries   = $this->entryRepository->findByOwnerOrderedByDate($user);
        $locations = $this->locationRepository->findByOwner($user);

        return [
            'stats'            => $this->buildStats($entries, $locations),
            'moodDistribution' => $this->buildMoodDistribution($entries),
            'topCategories'    => $this->categoryRepository->findTopByOwner(5),
            'topLocations'     => $locations,
        ];
    }

    // =========================================================================
    // Helpers privés
    // =========================================================================

    /**
     * @param \App\Entity\Entry[]    $entries
     * @param \App\Entity\Location[] $locations
     *
     * @return array<string, int>
     */
    private function buildStats(array $entries, array $locations): array
    {
        $entriesWithMedia    = array_filter($entries, fn($e) => $e->getEntryMedia()->count() > 0);
        $entriesWithLocation = array_filter($entries, fn($e) => $e->getLocation() !== null);

        return [
            'total_entries'            => count($entries),
            'total_locations_visited'  => count($locations),
            'entries_with_media'       => count($entriesWithMedia),
            'entries_with_location'    => count($entriesWithLocation),
        ];
    }

    /**
     * @param \App\Entity\Entry[] $entries
     *
     * @return array<string, array{label: string, emoji: string, count: int}>
     */
    private function buildMoodDistribution(array $entries): array
    {
        $distribution = [];

        foreach ($entries as $entry) {
            $mood = $entry->getMood();

            if (!$mood instanceof MoodEnum) {
                continue;
            }

            $key = $mood->value;

            if (!isset($distribution[$key])) {
                $distribution[$key] = [
                    'label' => $mood->getLabel(),
                    'emoji' => $mood->getEmoji(),
                    'count' => 0,
                ];
            }

            $distribution[$key]['count']++;
        }

        uasort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);

        return $distribution;
    }
}
