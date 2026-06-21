<?php

namespace App\Service;

final class LocationService
{
    public function getUserLocation(): array
    {
        // Récupérer la localisation de l'utilisateur via l'API de géolocalisation du navigateur
        if (isset($_SERVER['HTTP_GEOLOCATION'])) {
            $location = json_decode($_SERVER['HTTP_GEOLOCATION'], true);
            if (isset($location['latitude']) && isset($location['longitude'])) {
                return [
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                ];
            }
        }

        // Si la localisation n'est pas disponible, retourner des valeurs par défaut
        return [
            'latitude' => 0.0,
            'longitude' => 0.0,
        ];
    }
}