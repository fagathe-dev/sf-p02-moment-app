<?php

namespace App\Repository;

use App\Entity\EntryMedia;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Fagathe\CorePhp\Trait\DatetimeTrait;

/**
 * @extends ServiceEntityRepository<EntryMedia>
 */
class EntryMediaRepository extends ServiceEntityRepository
{
    use DatetimeTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntryMedia::class);
    }

    public function save(EntryMedia $media, bool $flush = true): bool
    {
        try {
            $this->getEntityManager()->persist($media);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    public function remove(EntryMedia $media, bool $flush = true): bool
    {
        try {
            $this->getEntityManager()->remove($media);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    /**
     * @return EntryMedia[]
     */
    public function findByOwner(User $user): array
    {
        return $this->findBy(['owner' => $user]);
    }
}
