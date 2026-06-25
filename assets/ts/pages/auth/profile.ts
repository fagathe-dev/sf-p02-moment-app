import { FileUploader } from 'core-ts';

document.addEventListener('DOMContentLoaded', (): void => {
  const inputElement = document.querySelector<HTMLInputElement>(
    '#profile-img-file-input',
  );

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

      const img = profileUser.querySelector<HTMLImageElement>('img');
      if (!img) return;

      img.src = base64Url;
      img.classList.remove('d-none');

      const fallback = profileUser.querySelector<HTMLElement>(
        '.profile-avatar__fallback',
      );
      if (fallback) fallback.classList.add('d-none');
    },
    onError(message: string): void {
      alert(message);
    },
  });
});
