<?php

namespace App\Service\UserRequest;

use App\Service\UserRequest\UserRequestTypeEnum;

/**
 * Résultat de la validation d'une demande utilisateur.
 * 
 * Encapsule le résultat de la validation avec un statut,
 * un message et optionnellement le type de demande validé.
 * 
 * @author fagathe-dev
 */
final readonly class UserRequestValidationResult
{
    /**
     * @param bool                      $valid   Indique si la validation a réussi
     * @param string                    $message Message décrivant le résultat
     * @param UserRequestTypeEnum|null  $type    Type de la demande (si valide)
     */
    public function __construct(
        private bool $valid,
        private string $message,
        private ?UserRequestTypeEnum $type = null
    ) {
    }

    /**
     * Indique si la demande est valide.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Retourne le message de validation.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Retourne le type de la demande (si valide).
     */
    public function getType(): ?UserRequestTypeEnum
    {
        return $this->type;
    }
}
