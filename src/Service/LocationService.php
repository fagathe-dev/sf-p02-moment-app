<?php

namespace App\Service;

use App\Entity\Location;
use App\Entity\User;
use App\Repository\LocationRepository;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Throwable;

final class LocationService
{
    use LoggerTrait, DatetimeTrait;

    public function __construct(
        private readonly LocationRepository $repository,
        private readonly Security $security
    ) {
    }

    /**
     * Trouve ou crée une Location à partir de coordonnées GPS et du display_name Nominatim.
     * Retourne null si la persistance échoue (ne bloque pas la sauvegarde de l'entrée).
     *
     * Le $name attendu est le display_name Nominatim :
     *   "Indigo, Avenue de la Résistance, Gare, Le Raincy, Seine-Saint-Denis, Île-de-France, France métropolitaine, 93340, France"
     * On extrait city (index 3), country (dernier segment).
     */
    public function createFromCoordinates(float $lat, float $lng, string $name, User $user): ?Location
    {
        try {
            // Dédoublonnage par coordonnées
            $existing = $this->repository->findOneByOwnerAndCoordinates($user, $lat, $lng);

            if ($existing !== null) {
                return $existing;
            }

            // Parsing du display_name Nominatim : segments séparés par ", "
            $segments = array_map('trim', explode(',', $name));
            $total = count($segments);

            // Heuristique : ville ≈ index 3, pays ≈ dernier segment
            $city = $total >= 4 ? $segments[3] : ($segments[0] ?? null);
            $country = $total >= 1 ? $segments[$total - 1] : null;

            $location = (new Location())
                ->setLatitude($lat)
                ->setLongitude($lng)
                ->setName($name)
                ->setCity($city)
                ->setCountry($country)
                ->setOwner($user)
            ;

            $this->repository->save($location, true, false);

            $this->generateLog(
                LoggerLevelEnum::Info,
                [
                    'message' => 'Location créée',
                    'lat' => $lat,
                    'lng' => $lng,
                    'city' => $city,
                    'country' => $country,
                    'user' => $user->getId(),
                ],
                ['action' => 'app.location.create.success']
            );

            return $location;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Warning,
                [
                    'message' => 'Impossible de créer la location',
                    'lat' => $lat,
                    'lng' => $lng,
                    'error' => $th->getMessage(),
                ],
                ['action' => 'app.location.create.error']
            );

            return null;
        }
    }
}