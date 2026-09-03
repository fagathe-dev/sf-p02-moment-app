import { convertMarkdownToHtml, $, fetchAPI } from 'core-ts';
import { VaultStorage } from '@/core/vault';

// ─────────────────────────────────────────────────────────────────────────────
// Init
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', (): void => {
  renderMarkdownContent();
  initDeleteEntry(); // 🟢 Initialisation de la logique de suppression
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Rendu Markdown
// ─────────────────────────────────────────────────────────────────────────────

const renderMarkdownContent = (
  context: Document | HTMLElement = document,
): void => {
  const containers = $<HTMLElement>(
    '[data-entry-md-content]',
    true,
    context,
  ) as NodeListOf<HTMLElement> | null;

  if (!containers) return;

  containers.forEach((container) => {
    const raw = container.getAttribute('data-entry-md-content') ?? '';

    if (raw.trim()) {
      container.innerHTML = convertMarkdownToHtml(raw);
    }

    container.removeAttribute('data-entry-md-content');
  });
};

// ─────────────────────────────────────────────────────────────────────────────
// 2. Suppression de l'entrée
// ─────────────────────────────────────────────────────────────────────────────

const initDeleteEntry = (): void => {
  const deleteBtn = $<HTMLButtonElement>(
    '.js-delete-entry-btn',
  ) as HTMLButtonElement | null;
  if (!deleteBtn) return;

  deleteBtn.addEventListener('click', async () => {
    // 🟢 Le garde-fou
    const isConfirmed = confirm(
      'Êtes-vous sûr de vouloir supprimer ce souvenir ? Cette action est irréversible.',
    );
    if (!isConfirmed) return;

    const url = deleteBtn.getAttribute('data-url');
    const csrf = deleteBtn.getAttribute('data-csrf');

    if (!url || !csrf) return;

    // 🟢 Préparation dynamique des en-têtes
    const headers: Record<string, string> = {
      'X-CSRF-Token': csrf,
    };

    // Vérification de l'URL pour savoir si on est dans le contexte Vault
    const isVault = window.location.pathname.includes('/app/vault/journal');

    if (isVault) {
      const vaultStorage = new VaultStorage();
      const token = vaultStorage.getToken();

      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
    }

    // État de chargement visuel
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.disabled = true;
    deleteBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Suppression...';

    try {
      const res = await fetchAPI<{ success: boolean; redirectUrl: string }>(
        url,
        {
          method: 'DELETE',
          headers: headers, // Injection des en-têtes avec ou sans le token Bearer
          isAPIAuthenticated: false,
        },
      );

      if (res.data.success && res.data.redirectUrl) {
        // Redirection vers le flux une fois supprimé
        window.location.href = res.data.redirectUrl;
      }
    } catch (err) {
      alert('Une erreur est survenue lors de la suppression.');
      deleteBtn.disabled = false;
      deleteBtn.innerHTML = originalContent;
    }
  });
};

export { renderMarkdownContent, initDeleteEntry };
