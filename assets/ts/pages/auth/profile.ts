import { FileUploader } from 'core-ts';

document.addEventListener('DOMContentLoaded', (): void => {
  const inputElement = document.querySelector<HTMLInputElement>('#profile-img-file-input');

  if (!inputElement) {
    return;
  }

  new FileUploader({
    uploadUrl: '/auth/profile/upload/avatar',
    inputElement,
    fieldName: 'avatar',
    maxSize: '15M',
    allowedMimes: [
      'image/bmp',
      'image/webp',
      'image/svg+xml',
      'image/tiff',
      'image/png',
      'image/gif',
      'image/x-icon',
      'image/jpeg',
    ],
    onPreview(base64Url: string): void {
      const profileUser = document.querySelector<HTMLElement>('.profile-user');
      if (!profileUser) return;

      const existingImg = profileUser.querySelector<HTMLImageElement>('.user-profile-image');

      if (existingImg) {
        existingImg.src = base64Url;
        return;
      }

      // Cas du fallback (initiales) : créer l'image et supprimer le div fallback
      const img = document.createElement('img');
      img.src = base64Url;
      img.alt = 'user-profile-image';
      img.classList.add('rounded-circle', 'avatar-xl', 'img-thumbnail', 'user-profile-image');

      const fileInputWrapper = inputElement.closest('.profile-photo-edit');
      if (fileInputWrapper) {
        profileUser.insertBefore(img, fileInputWrapper);
      } else {
        profileUser.prepend(img);
      }

      const fallback = profileUser.querySelector<HTMLElement>('.avatar-xl.shadow.rounded-circle');
      if (fallback) {
        fallback.remove();
      }
    },
    onError(message: string): void {
      alert(message);
    },
  });
});
