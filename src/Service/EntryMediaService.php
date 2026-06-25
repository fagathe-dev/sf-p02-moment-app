<?php
 
namespace App\Service;
 
use App\Entity\Entry;
use App\Entity\EntryMedia;
use App\Entity\User;
use App\Repository\EntryMediaRepository;
use Fagathe\CorePhp\Enum\LoggerLevelEnum;
use Fagathe\CorePhp\File\FileTypeEnum;
use Fagathe\CorePhp\File\MimeType;
use Fagathe\CorePhp\Trait\DatetimeTrait;
use Fagathe\CorePhp\Trait\LoggerTrait;
use Fagathe\CorePhp\Uploader\FileUploadException;
use Fagathe\CorePhp\Uploader\UploaderService;
use Fagathe\CorePhp\Uploader\UploaderValidationService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
 
final class EntryMediaService
{
    use LoggerTrait, DatetimeTrait;
 
    private const MAX_SIZE = 50 * 1024 * 1024; // 50 Mo
 
    public function __construct(
        private readonly EntryMediaRepository $repository,
        private readonly UploaderService $uploader,
    ) {
    }

    private function getSupportedMimeTypes(): array
    {
        return [
            ...MimeType::IMAGE_MIMES,
            ...MimeType::AUDIO_MIMES,
            ...MimeType::VIDEO_MIMES,
        ];
    }
 
    /**
     * Valide et upload un fichier, crée l'entité EntryMedia associée.
     *
     * @throws FileUploadException si le fichier est invalide ou si l'upload échoue
     */
    public function upload(UploadedFile $file, Entry $entry, User $user): EntryMedia
    {
        // Validation — types MIME complets obligatoires
        $validator = new UploaderValidationService();
        $validator
            ->setAllowedMimeTypes($this->getSupportedMimeTypes())
            ->setMaxSize(self::MAX_SIZE)
        ;
 
        $result = $validator->validate($file);
        if ($result !== true && is_array($result)) {
            throw new FileUploadException(join(' ', $result));
        }
 
        // Upload dans public/uploads/{userId}/
        $uploadResult = $this->uploader
            ->setUploadDirectory((string) $user->getId())
            ->upload($file)
        ;
 
        $media = (new EntryMedia())
            ->setOriginalName($uploadResult->originalName)
            ->setFilePath($uploadResult->relativePath)
            ->setMimeType($uploadResult->mimeType)
            ->setExtension($uploadResult->extension)
            ->setSize($uploadResult->size)
            ->setType(FileTypeEnum::matchMime($uploadResult->mimeType))
            ->setCreatedAt($this->now())
            ->setOwner($user)
            ->setEntry($entry)
        ;
 
        $this->repository->save($media);
 
        $this->generateLog(
            LoggerLevelEnum::Info,
            [
                'message'  => 'Média uploadé',
                'entry_id' => $entry->getId(),
                'file'     => $uploadResult->originalName,
                'path'     => $uploadResult->relativePath,
                'size'     => $uploadResult->size,
            ],
            ['action' => 'app.entry_media.upload.success']
        );
 
        return $media;
    }
 
    /**
     * Supprime un média (fichier physique + enregistrement BDD).
     */
    public function delete(EntryMedia $media): bool
    {
        try {
            if ($media->getFilePath()) {
                $this->uploader->delete($media->getFilePath());
            }
 
            $this->repository->remove($media);
 
            $this->generateLog(
                LoggerLevelEnum::Info,
                ['message' => 'Média supprimé', 'media_id' => $media->getId()],
                ['action' => 'app.entry_media.delete.success']
            );
 
            return true;
        } catch (Throwable $th) {
            $this->generateLog(
                LoggerLevelEnum::Error,
                ['message' => 'Erreur suppression média', 'media_id' => $media->getId(), 'error' => $th->getMessage()],
                ['action' => 'app.entry_media.delete.error']
            );
 
            return false;
        }
    }
 
    /**
     * Supprime tous les médias d'une entrée.
     */
    public function deleteByEntry(Entry $entry): void
    {
        foreach ($entry->getEntryMedia() as $media) {
            $this->delete($media);
        }
    }
}