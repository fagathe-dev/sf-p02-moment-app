import { $, fetchAPI, convertMarkdownToHtml, router } from 'core-ts';
import { SelectableField } from 'core-ts';
import { ROUTES } from '@/constants';
import {
  VaultController,
  VaultStorage,
  VisibilityObserver,
} from '@/core/vault';
import { initMarkdownPreview } from '../preview-mardown-editor';
import { renderMarkdownContent } from './entry';

// ─────────────────────────────────────────────────────────────────────────────
// État local
// ─────────────────────────────────────────────────────────────────────────────

let isHydrated = false;
const emptyEntriesContainer = $('#vault-entries-empty') as HTMLElement;

// ─────────────────────────────────────────────────────────────────────────────
// Initialisation
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', (): void => {
  const lockScreen = $<HTMLElement>('#vault-lock-screen');
  if (!(lockScreen instanceof HTMLElement)) return;

  // 1. Initialisation du moteur Core
  const storage = new VaultStorage();
  const observer = new VisibilityObserver(() => controller.lockVault());
  const controller = new VaultController(storage, observer, lockScreen);

  // 2. Initialisation de la logique UI de la page
  initUnlockForm(controller, lockScreen);
  initVaultStateObserver(lockScreen, storage);

  // 3. 🟢 Initialisation du SelectableField (Mode Radio pour l'humeur)
  const selectableContainers = $<HTMLElement>(
    '.js-selectable-container',
    true,
  ) as NodeListOf<HTMLElement> | null;

  if (selectableContainers) {
    selectableContainers.forEach((container) => {
      const mode = container.dataset.mode === 'checkbox' ? 'checkbox' : 'radio';
      new SelectableField(container, { mode } as unknown as {
        mode: 'radio' | 'nullable';
      });
    });
  }

  initMarkdownPreview();
  initVaultTriggersSync();
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Gestion du formulaire de déverrouillage
// ─────────────────────────────────────────────────────────────────────────────

const initUnlockForm = (
  controller: VaultController,
  lockScreen: HTMLElement,
): void => {
  const form = $<HTMLFormElement>('#vault-unlock-form');
  const pinInput = $<HTMLInputElement>('#vault-pin-input');
  const errorMsg = $<HTMLElement>('#vault-error-msg');
  const submitBtn = $<HTMLButtonElement>('#vault-submit-btn');

  if (
    !(form instanceof HTMLFormElement) ||
    !(pinInput instanceof HTMLInputElement) ||
    !(submitBtn instanceof HTMLButtonElement) ||
    !(errorMsg instanceof HTMLElement)
  )
    return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pin = pinInput.value.trim();

    if (pin.length < 4) {
      if (errorMsg) {
        errorMsg.textContent = 'Le code doit faire au moins 4 caractères.';
        errorMsg.classList.remove('d-none');
      }
      return;
    }

    if (errorMsg) errorMsg.classList.add('d-none');
    lockScreen.classList.remove('is-error');

    if (submitBtn) submitBtn.disabled = true;

    await controller.unlockVault(pin);

    if (submitBtn) submitBtn.disabled = false;
    pinInput.value = '';
  });
};

// ─────────────────────────────────────────────────────────────────────────────
// 2. Observation de l'état du Coffre-fort
// ─────────────────────────────────────────────────────────────────────────────

function initVaultStateObserver(
  lockScreen: HTMLElement,
  storage: VaultStorage,
): void {
  const checkState = () => {
    if (lockScreen.classList.contains('is-unlocked')) {
      const token = storage.getToken();
      if (token && !isHydrated) {
        isHydrated = true;
        hydrateCurrentPage(token);
      }
    } else {
      isHydrated = false;
    }

    const errorMsg = $<HTMLElement>('#vault-error-msg');
    if (errorMsg instanceof HTMLElement) {
      if (lockScreen.classList.contains('is-error')) {
        errorMsg.classList.add('d-none');
        errorMsg.textContent = 'Code confidentiel invalide.';
        errorMsg.classList.remove('d-none');
      }
    }
  };

  const mutationObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === 'class') {
        checkState();
      }
    });
  });

  mutationObserver.observe(lockScreen, { attributes: true });
  checkState();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Hydratation dynamique des pages (Feed, Show, Edit)
// ─────────────────────────────────────────────────────────────────────────────

async function hydrateCurrentPage(token: string): Promise<void> {
  const feedContainer = $<HTMLElement>('#vault-entries-container');
  if (feedContainer instanceof HTMLElement) {
    feedContainer.classList.remove('d-none');
    await hydrateFeed(feedContainer, token);
    return;
  }

  const showContainer = $<HTMLElement>('#vault-show-container');
  if (showContainer instanceof HTMLElement) {
    const entryId = showContainer.getAttribute('data-entry-id');
    if (entryId) await hydrateShow(entryId, token);
    return;
  }

  const editContainer = $<HTMLElement>('#vault-edit-container');
  if (editContainer instanceof HTMLElement) {
    const entryId = editContainer.getAttribute('data-entry-id');
    if (entryId) await hydrateEdit(entryId, token);
    return;
  }
}

async function hydrateFeed(
  container: HTMLElement,
  token: string,
): Promise<void> {
  try {
    const res = await fetchAPI<{ html: string }>(
      router(ROUTES.API.VAULT.ENTRIES),
      {
        method: 'GET',
        headers: { Authorization: `Bearer ${token}` },
      },
    );

    if (res.data.html.length > 0) {
      emptyEntriesContainer.classList.add('d-none');
    } else {
      emptyEntriesContainer.classList.remove('d-none');
    }

    // Injection des cartes en HTML brut
    container.innerHTML = res.data.html;

    // 🟢 AJOUT : On force le parsing Markdown -> HTML sur le conteneur fraîchement injecté
    renderMarkdownContent(container);
  } catch (err) {
    container.innerHTML = `
      <div class="text-center py-5 text-danger">
        <i class="ri-error-warning-line fs-2 mb-2 d-block"></i>
        Impossible de déchiffrer les souvenirs.
      </div>`;
  }
}
async function hydrateShow(entryId: string, token: string): Promise<void> {
  try {
    const res = await fetchAPI<{ data: { title: string; content: string } }>(
      router(ROUTES.API.VAULT.ENTRY_DATA, { id: entryId }),
      {
        method: 'GET',
        headers: { Authorization: `Bearer ${token}` },
      },
    );

    const titleEl = $<HTMLElement>('#vault-entry-title');
    const contentEl = $<HTMLElement>('#vault-entry-content');

    if (titleEl instanceof HTMLElement) {
      titleEl.classList.remove('placeholder-glow');
      titleEl.innerHTML = res.data.data.title || '<em>Sans titre</em>';
    }

    if (contentEl instanceof HTMLElement) {
      contentEl.innerHTML = '';
      if (res.data.data.content) {
        contentEl.innerHTML = convertMarkdownToHtml(res.data.data.content);
      }
    }
  } catch (err) {
    console.error('Erreur de déchiffrement du souvenir', err);
  }
}

async function hydrateEdit(entryId: string, token: string): Promise<void> {
  try {
    const res = await fetchAPI<{ data: { title: string; content: string } }>(
      router(ROUTES.API.VAULT.ENTRY_DATA, { id: entryId }),
      {
        method: 'GET',
        headers: { Authorization: `Bearer ${token}` },
      },
    );

    const titleInput = $<HTMLInputElement>('#entry-title');
    const contentInput = $<HTMLTextAreaElement>('#entry-content');

    if (titleInput instanceof HTMLInputElement)
      titleInput.value = res.data.data.title || '';
    if (contentInput instanceof HTMLTextAreaElement)
      contentInput.value = res.data.data.content || '';
  } catch (err) {
    console.error('Erreur de récupération des données pour édition', err);
  }
}

function initVaultTriggersSync(): void {
  const form = document.querySelector<HTMLFormElement>('.vault-form');
  if (!form) return;

  // Cible les boutons radio générés par le form builder pour le champ 'mood'
  const moodRadios = form.querySelectorAll<HTMLInputElement>(
    'input[type="radio"][name*="[mood]"]',
  );
  const moodTrigger = form.querySelector<HTMLElement>('.js-mood-trigger');

  moodRadios.forEach((radio) => {
    radio.addEventListener('change', () => {
      if (!moodTrigger) return;
      if (radio.checked) {
        // Trouve le label correspondant pour récupérer l'émoji
        const label = form.querySelector<HTMLElement>(
          `label[for="${radio.id}"]`,
        );
        if (label) {
          moodTrigger.innerHTML = label.innerHTML.trim();
        }
      }
    });
  });
}
