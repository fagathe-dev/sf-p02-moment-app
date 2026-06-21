<?php
 
namespace App\Security\Voter;
 
use App\Entity\Category;
use App\Entity\Entry;
use App\Entity\EntryMedia;
use App\Entity\Location;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
 
/**
 * Vérifie que l'utilisateur connecté est bien le propriétaire du sujet.
 *
 * Le propriétaire est détecté en construisant un getter à partir de OWNER_PROPERTIES :
 *   'get' . ucfirst($prop) → getOwner(), getUser(), getAuthor(), getCreator()…
 *
 * Retourne false si :
 *   - aucune de ces méthodes n'existe sur le sujet
 *   - la méthode retourne null
 *   - l'utilisateur n'est pas connecté ou non identifiable
 *
 * Utilisation :
 *   $this->denyAccessUnlessGranted('OWNER', $entity);
 */
final class OwnerVoter extends Voter
{
    private const ATTRIBUTE = 'OWNER';
 
    private const OWNER_PROPERTIES = [
        'owner',
        'user',
        'author',
        'creator',
        'createdBy',
        'updatedBy',
        'assignee',
        'member',
        'manager',
        'responsible',
    ];
 
    private const SUPPORTED_ENTITIES = [Category::class, Entry::class, Location::class, EntryMedia::class];
 
    public function __construct(private readonly Security $security) {}
 
    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute !== self::ATTRIBUTE) {
            return false;
        }
 
        foreach (self::SUPPORTED_ENTITIES as $class) {
            if ($subject instanceof $class) {
                return true;
            }
        }
 
        return false;
    }
 
    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     * 
     * @return bool
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $connectedUser = $this->security->getUser();
 
        if (!$connectedUser instanceof UserInterface) {
            return false;
        }
 
        $owner = $this->resolveOwner($subject);
 
        if (!$owner instanceof UserInterface) {
            return false;
        }
 
        return $owner === $connectedUser;
    }
 
    private function resolveOwner(object $subject): ?UserInterface
    {
        foreach (self::OWNER_PROPERTIES as $prop) {
            $method = 'get' . ucfirst($prop);
 
            if (method_exists($subject, $method)) {
                $result = $subject->$method();
 
                return $result instanceof UserInterface ? $result : null;
            }
        }
 
        return null;
    }
}