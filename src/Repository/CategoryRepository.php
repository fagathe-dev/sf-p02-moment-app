<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\User;
use Cocur\Slugify\Slugify;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    use DatetimeTrait;

    public function __construct(ManagerRegistry $registry, private readonly Security $security)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Supprime une catégorie
     * @param Category $category L'entité à supprimer
     * @param bool $flush Faut-il exécuter la requête tout de suite ?
     * @return bool Succès de l'opération
     */
    public function remove(Category $category, bool $flush = true): bool
    {
        try {
            $this->getEntityManager()->remove($category);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException $ormException) {
            return false;
        }
    }

    /**
     * Sauvegarde une catégorie (Création ou Mise à jour)
     * @param Category $category L'entité à sauvegarder
     * @param bool $flush Faut-il envoyer en base tout de suite ?
     * @return bool Succès de l'opération
     */
    public function save(Category $category, bool $flush = true, bool $isCreation = false): bool
    {
        $now = $this->now();
        $slugify = new Slugify();
        $slug = $slugify->slugify($category->getSlug() ?: $category->getName() ?: '');

        // Petit bonus pédagogique : Hashage automatique si le mot de passe est en clair
        // Cela évite d'oublier de le faire dans le Controller
        if ($isCreation) {
            $category->setCreatedAt($now)
                ->setOwner($this->getUser())
                ->setSlug($slug)  // null → généré automatiquement par l'EventSubscriber
            ;
        } else {
            $category->setUpdatedAt($now)
                ->setSlug($slug)  // null → généré automatiquement par l'EventSubscriber
            ;
        }

        try {
            $this->getEntityManager()->persist($category);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException $ormException) {
            return false;
        }
    }

    /**
     * Retourne toutes les catégories appartenant à un utilisateur,
     * triées par nom alphabétique.
     *
     * @return Category[]
     */
    public function findByOwner(): array
    {
        $user = $this->getUser();
        if ($user === null) {
            return [];
        }

        return $this->findBy(
            ['owner' => $user],
            ['name' => 'ASC'],
        );
    }


    /**
     * Retourne les N catégories les plus utilisées par un utilisateur
     * (triées par nombre d'entrées associées décroissant).
     *
     * @return Category[]
     */
    public function findTopByOwner(int $limit = 5): array
    {
        $user = $this->getUser();
        if ($user === null) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->leftJoin('c.entries', 'e')
            ->andWhere('c.owner = :owner')
            ->setParameter('owner', $user)
            ->groupBy('c.id')
            ->orderBy('COUNT(e.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return User|null
     */
    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            return null;
        }

        return $user;
    }

    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
