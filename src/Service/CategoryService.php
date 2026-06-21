<?php
 
namespace App\Service;
 
use App\Entity\Category;
use App\Entity\User;
use App\Enum\ColorEnum;
use App\Repository\CategoryRepository;
use Fagathe\CorePhp\Breadcrumb\Breadcrumb;
use Fagathe\CorePhp\Breadcrumb\BreadcrumbItem;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Trait\PaginationTrait;
use Fagathe\CorePhp\Trait\SessionFlashTrait;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
 
final class CategoryService
{
    use LoggerTrait, DatetimeTrait, SessionFlashTrait, PaginationTrait;
 
    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        private readonly UrlGeneratorInterface $urlGenerator,
        PaginatorInterface $paginator,
        private readonly string $projectDir,
    ) {
    }
 
    /**
     * Trouve un utilisateur par son identifiant.
     *
     * @param int $id L'identifiant de la catégorie
     *
     * @return Category|null La catégorie trouvée ou null si non trouvée
     */
    public function findById(int $id): ?Category
    {
        return $this->repository->find($id);
    }
 
    /**
     * Sauvegarde un utilisateur en base de données.
     *
     * Gère automatiquement les dates de création/mise à jour et
     * les propriétés par défaut selon le type d'opération.
     *
     * @param Category $category   La catégorie à sauvegarder
     * @param bool     $isCreation True pour une création, false pour une mise à jour
     *
     * @return bool
     */
    public function saveCategory(Category $category, bool $isCreation = false): bool
    {
        return $this->repository->save($category, true, $isCreation);
    }
 
    /**
     * @param int $id
     *
     * @return bool
     */
    public function deleteCategory(int $id): bool
    {
        $category = $this->repository->find($id);
        if ($category === null) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message'     => 'Catégorie introuvable pour la suppression',
                    'category_id' => $id,
                ],
                ['action' => 'app.category.delete.not_found']
            );
 
            return false;
        }
 
        try {
            $this->repository->remove($category, true);
 
            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message'     => 'Catégorie supprimée avec succès',
                    'category_id' => $category->getId(),
                    'name'        => $category->getName(),
                ],
                ['action' => 'app.category.delete.success']
            );
 
            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                [
                    'message'     => 'Erreur lors de la suppression de la catégorie',
                    'category_id' => $category->getId(),
                    'name'        => $category->getName(),
                    'error'       => $th->getMessage(),
                ],
                ['action' => 'app.category.delete.error']
            );
 
            return false;
        }
    }
 
    /**
     * Génère le fil d'Ariane pour la gestion des catégories.
     *
     * @param BreadcrumbItem[] $items Les éléments du fil d'Ariane
     *
     * @return Breadcrumb
     */
    public function breadcrumb(array $items = []): Breadcrumb
    {
        return new Breadcrumb([
            new BreadcrumbItem(name: 'Mes catégories', link: $this->urlGenerator->generate('app_category_index')),
            ...$items,
        ]);
    }
 
    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
 
        if ($user instanceof User) {
            return $user;
        }
 
        return null;
    }
 
    public function manage(): array
    {
        $categories = $this->repository->findByOwner();
 
        // ── Mock ──────────────────────────────────────────────────────────────
        // À retirer dès que la base de données contient des catégories réelles.
        // if (empty($categories)) {
        //     $categories = $this->getMockCategories();
        // }
        // ─────────────────────────────────────────────────────────────────────
 
        $breadcrumb = $this->breadcrumb();
 
        return compact('categories', 'breadcrumb');
    }
 
    /**
     * Données de substitution pour le développement front-end.
     * Simule un jeu de catégories sans passer par la base de données.
     *
     * @return Category[]
     *
     * @internal À supprimer une fois les fixtures / la BDD en place.
     */
    private function getMockCategories(): array
    {
        $now = $this->now();
 
        $data = [
            ['id' => 1,  'name' => 'Personnel',    'color' => ColorEnum::Blue,   'description' => 'Notes personnelles et journal intime.'],
            ['id' => 2,  'name' => 'Travail',       'color' => ColorEnum::Indigo, 'description' => 'Tâches et réflexions professionnelles.'],
            ['id' => 3,  'name' => 'Projets',       'color' => ColorEnum::Purple, 'description' => 'Suivi de mes projets en cours.'],
            ['id' => 4,  'name' => 'Santé',         'color' => ColorEnum::Green,  'description' => null],
            ['id' => 5,  'name' => 'Finances',      'color' => ColorEnum::Teal,   'description' => 'Budget, dépenses et objectifs financiers.'],
            ['id' => 6,  'name' => 'Lecture',       'color' => ColorEnum::Orange, 'description' => null],
            ['id' => 7,  'name' => 'Voyages',       'color' => ColorEnum::Cyan,   'description' => 'Destinations, souvenirs et envies.'],
            ['id' => 8,  'name' => 'Idées',         'color' => ColorEnum::Yellow, 'description' => 'Idées en vrac à ne pas perdre.'],
            ['id' => 9,  'name' => 'Famille',       'color' => ColorEnum::Pink,   'description' => null],
            ['id' => 10, 'name' => 'Non classé',    'color' => ColorEnum::Dark,   'description' => 'Entrées sans catégorie définie.'],
        ];
 
        return array_map(function (array $item) use ($now): Category {
            $category = new Category();
 
            // Injection de l'id via réflexion (champ privé généré par Doctrine)
            $ref = new \ReflectionProperty(Category::class, 'id');
            $ref->setValue($category, $item['id']);
 
            $category
                ->setName($item['name'])
                ->setColor($item['color'])
                ->setDescription($item['description'])
                ->setCreatedAt($now)
                ->setSlug(strtolower(str_replace(' ', '-', $item['name'])));
 
            return $category;
        }, $data);
    }
}