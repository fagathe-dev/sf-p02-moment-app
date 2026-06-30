<?php

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Entry>
 */
class EntryRepository extends ServiceEntityRepository
{
    use DatetimeTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly Security $security
    ) {
        parent::__construct($registry, Entry::class);
    }

    public function remove(Entry $entry, bool $flush = true): bool
    {
        try {
            $this->getEntityManager()->remove($entry);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    public function save(Entry $entry, bool $flush = true, bool $isCreation = false): bool
    {
        $now = $this->now();

        if ($isCreation) {
            $entry->setCreatedAt($now);
        } else {
            $entry->setUpdatedAt($now);
        }

        try {
            $this->getEntityManager()->persist($entry);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    /**
     * @return Entry[]
     */
    public function findByOwnerOrderedByDate(): array
    {
        $user = $this->getCurrentUser();

        if (!$user instanceof User) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.owner = :owner')
            ->andWhere('e.is_private = false')
            ->setParameter('owner', $user)
            ->orderBy('e.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Entry[]
     */
    public function findPrivateByOwnerOrderedByDate(): array
    {
        $user = $this->getCurrentUser();

        if (!$user instanceof User) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.owner = :owner')
            ->andWhere('e.is_private = true') // Le filtre magique
            ->setParameter('owner', $user)
            ->orderBy('e.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            return $user;
        }

        return null;
    }
}
