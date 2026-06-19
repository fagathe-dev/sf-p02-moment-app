<?php

namespace App\Enum;

enum MoodEnum: string
{
    case Angry = 'angry';       // 😡 En colère
    case Anxious = 'anxious';   // 😟 Anxieux
    case Exhausted = 'exhausted'; // 😫 Épuisé
    case Frustrated = 'frustrated'; // 😤 Frustré
    case Grateful = 'grateful';   // 🙏 Reconnaissant
    case Happy = 'happy';       // 🙂 Bien
    case Lost = 'lost';      // 😕 Perdu
    case Hopeful = 'hopeful';   // 🤞 Plein d'espoir
    case InLove = 'in_love';     // 😍 Amoureux
    case Neutral = 'neutral';   // 😐 Moyen / Neutre
    case Motivated = 'motivated'; // 💪 Motivé
    case Relaxed = 'relaxed';   // 😌 Détendu
    case Radiant = 'radiant';   // 🤩 Exceptionnel
    case Sad = 'sad';           // 😔 Triste / Déçu
    case Stressed = 'stressed'; // 😰 Stressé

    /**
     * Retourne l'emoji correspondant pour le front-end
     */
    public function getEmoji(): string
    {
        return match ($this) {
            self::Radiant => '🤩',
            self::Happy => '🙂',
            self::Neutral => '😐',
            self::Sad => '😔',
            self::Angry => '😡',
            self::Exhausted => '😫',
            self::Motivated => '💪',
            self::Stressed => '😰',
            self::Frustrated => '😤',
            self::Hopeful => '🤞',
            self::Anxious => '😟',
            self::Grateful => '🙏',
            self::InLove => '😍',
            self::Lost => '😕',
            self::Relaxed => '😌',
        };
    }

    /**
     * Pour les labels dans tes formulaires Symfony
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Radiant => 'Rayonnant',
            self::Happy => 'Heureux',
            self::Neutral => 'Neutre',
            self::Sad => 'Triste',
            self::Angry => 'En colère',
            self::Exhausted => 'Épuisé',
            self::Motivated => 'Motivé',
            self::Stressed => 'Stressé',
            self::Frustrated => 'Frustré',
            self::Hopeful => 'Plein d\'espoir',
            self::Anxious => 'Anxieux',
            self::Grateful => 'Reconnaissant',
            self::InLove => 'Amoureux',
            self::Lost => 'Perdu',
            self::Relaxed => 'Détendu',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return array_reduce(self::cases(), fn($carry, $i) => [...$carry, $i->getLabel() => $i->value], []);
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn($m) => $m->value, self::cases());
    }
}