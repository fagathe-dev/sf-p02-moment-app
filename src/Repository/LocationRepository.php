<?php

namespace App\Repository;

use App\Entity\Location;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    use DatetimeTrait;

    public function __construct(ManagerRegistry $registry, private readonly Security $security)
    {
        parent::__construct($registry, Location::class);
    }

    public function save(Location $location, bool $flush = true, bool $isCreation = false): bool
    {
        if ($isCreation) {
            $location->setOwner($this->getUser());
        }

        try {
            $this->getEntityManager()->persist($location);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    public function remove(Location $location, bool $flush = true): bool
    {
        try {
            $this->getEntityManager()->remove($location);

            if ($flush) {
                $this->getEntityManager()->flush();
            }

            return true;
        } catch (ORMException) {
            return false;
        }
    }

    /**
     * @return Location[]
     */
    public function findByOwner(User $user): array
    {
        return $this->findBy(
            ['owner' => $user],
            ['name' => 'ASC'],
        );
    }

    /**
     * Trouve une location existante par coordonnées approximatives pour éviter les doublons.
     * Tolérance : ~11 mètres (0.0001 degré).
     * ROUND n'est pas supporté en DQL — on utilise BETWEEN avec un delta calculé en PHP.
     */
    public function findOneByOwnerAndCoordinates(User $user, float $lat, float $lng): ?Location
    {
        $delta = 0.0001;
 
        return $this->createQueryBuilder('l')
            ->andWhere('l.owner = :owner')
            ->andWhere('l.latitude  BETWEEN :lat_min AND :lat_max')
            ->andWhere('l.longitude BETWEEN :lng_min AND :lng_max')
            ->setParameter('owner',   $user)
            ->setParameter('lat_min', $lat - $delta)
            ->setParameter('lat_max', $lat + $delta)
            ->setParameter('lng_min', $lng - $delta)
            ->setParameter('lng_max', $lng + $delta)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }


    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        if (!($user instanceof User)) {
            return null;
        }

        return $user;
    }
}
