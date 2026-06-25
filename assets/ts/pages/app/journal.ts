import {
  $,
  fetchAPI,
  ApiError,
  FileSizeFormatter,
  StorageService,
  router,
} from 'core-ts';
import { CustomSelector } from '@/core/forms/custom-selector';
import { SelectableField } from 'core-ts';
 
// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────
 
const DRAFT_KEY   = 'journal-draft';
const MAX_SIZE    = '50M';
const ALLOWED_TYPES = ['image/', 'video/', 'audio/'];
 
const storage = new StorageService('local');
 
// ─────────────────────────────────────────────────────────────────────────────
// Init
// ─────────────────────────────────────────────────────────────────────────────
 
document.addEventListener('DOMContentLoaded', (): void => {
  const form = $<HTMLFormElement>('#journal-form') as HTMLFormElement | null;
  if (!form) return;
 
  initMoodSelector(form);
  initColorSelector(form);
  initCategorySelector(form);
  initLocation(form);
  initMediaUpload(form);
  initDraft(form);
  initSubmit(form);
});
 
// ─────────────────────────────────────────────────────────────────────────────
// 1. Humeur
// ─────────────────────────────────────────────────────────────────────────────
 
function initMoodSelector(form: HTMLFormElement): void {
  const buttons = $<HTMLButtonElement>(
    '.js-mood-btn',
    true,
    form,
  ) as NodeListOf<HTMLButtonElement> | null;
  const hiddenInput = $<HTMLInputElement>(
    '#entry-mood',
    false,
    form,
  ) as HTMLInputElement | null;
 
  if (!buttons || !hiddenInput) return;
 
  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const value    = btn.getAttribute('data-value') ?? '';
      const isPressed = btn.getAttribute('aria-pressed') === 'true';
 
      buttons.forEach((b) => b.setAttribute('aria-pressed', 'false'));
 
      if (!isPressed) {
        btn.setAttribute('aria-pressed', 'true');
        hiddenInput.value = value;
      } else {
        hiddenInput.value = '';
      }
    });
  });
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 2. Couleur de la card — SelectableField (radio, décoché possible via "aucune")
// ─────────────────────────────────────────────────────────────────────────────
 
function initColorSelector(form: HTMLFormElement): void {
  const container = $<HTMLElement>('.js-selectable-container', false, form) as HTMLElement | null;
  if (!container) return;
 
  new SelectableField(container, { mode: 'radio' });
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 3. Catégories — CustomSelector
// ─────────────────────────────────────────────────────────────────────────────
 
function initCategorySelector(form: HTMLFormElement): void {
  const containers = $<HTMLElement>(
    '.js-dropdown-select',
    true,
    form,
  ) as NodeListOf<HTMLElement> | null;
 
  if (!containers) return;
 
  containers.forEach((container) => {
    const mode        = (container.getAttribute('data-dcs-mode') as any) || 'multiple-nullable';
    const placeholder = container.getAttribute('data-dcs-placeholder') || 'Sélectionner…';
 
    new CustomSelector(container, { mode, placeholder });
  });
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 4. Géolocalisation — OpenStreetMap Nominatim
// ─────────────────────────────────────────────────────────────────────────────
 
function initLocation(form: HTMLFormElement): void {
  const trigger   = $<HTMLButtonElement>('.js-location-trigger', false, form) as HTMLButtonElement | null;
  const label     = $<HTMLElement>('.js-location-label', false, form) as HTMLElement | null;
  const inputLat  = $<HTMLInputElement>('#entry-location-lat', false, form) as HTMLInputElement | null;
  const inputLng  = $<HTMLInputElement>('#entry-location-lng', false, form) as HTMLInputElement | null;
  const inputName = $<HTMLInputElement>('#entry-location-name', false, form) as HTMLInputElement | null;
 
  if (!trigger || !label || !inputLat || !inputLng || !inputName) return;
 
  trigger.addEventListener('click', () => {
    if (!navigator.geolocation) {
      setLocationState(trigger, label, 'error', 'Géolocalisation non supportée');
      return;
    }
 
    setLocationState(trigger, label, 'loading', 'Localisation en cours…');
 
    navigator.geolocation.getCurrentPosition(
      async ({ coords }) => {
        try {
          const res = await fetch(
            `https://nominatim.openstreetmap.org/reverse?lat=${coords.latitude}&lon=${coords.longitude}&format=json`,
            { headers: { 'Accept-Language': 'fr', 'User-Agent': 'PersonalLifeOS/1.0' } },
          );
          const data = await res.json();
 
          const city    = data.address?.city ?? data.address?.town ?? data.address?.village ?? '';
          const country = data.address?.country ?? '';
          const name    = data.display_name ?? `${coords.latitude}, ${coords.longitude}`;
 
          inputLat.value  = String(coords.latitude);
          inputLng.value  = String(coords.longitude);
          inputName.value = name;
 
          setLocationState(trigger, label, 'success', [city, country].filter(Boolean).join(', ') || name);
        } catch {
          setLocationState(trigger, label, 'error', "Impossible de récupérer l'adresse");
        }
      },
      (err) => {
        const messages: Record<number, string> = {
          1: 'Localisation refusée',
          2: 'Position indisponible',
          3: 'La requête a expiré',
        };
        setLocationState(trigger, label, 'error', messages[err.code] ?? 'Erreur de localisation');
      },
      { timeout: 10_000 },
    );
  });
}
 
type LocationState = 'idle' | 'loading' | 'success' | 'error';
 
function setLocationState(btn: HTMLButtonElement, label: HTMLElement, state: LocationState, text: string): void {
  btn.disabled       = state === 'loading';
  label.textContent  = text;
  btn.dataset.state  = state;
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 5. Médias — preview + validation client
// ─────────────────────────────────────────────────────────────────────────────
 
let pendingFiles: File[] = [];
 
function initMediaUpload(form: HTMLFormElement): void {
  const input    = $<HTMLInputElement>('.js-media-input', false, form) as HTMLInputElement | null;
  const zone     = $<HTMLElement>('.js-upload-zone', false, form) as HTMLElement | null;
  const previews = $<HTMLElement>('.js-media-previews', false, form) as HTMLElement | null;
 
  if (!input || !zone || !previews) return;
 
  zone.addEventListener('dragover',  (e) => { e.preventDefault(); zone.classList.add('is-dragover'); });
  zone.addEventListener('dragleave', ()  => zone.classList.remove('is-dragover'));
  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('is-dragover');
    if (e.dataTransfer?.files) addFiles(Array.from(e.dataTransfer.files), previews);
  });
 
  input.addEventListener('change', () => {
    if (input.files) addFiles(Array.from(input.files), previews);
    input.value = '';
  });
 
  const removeButtons = $<HTMLButtonElement>('.js-remove-existing-media', true, form) as NodeListOf<HTMLButtonElement> | null;
  removeButtons?.forEach((btn) => {
    btn.addEventListener('click', () => {
      btn.closest<HTMLElement>('.journal-form__media-thumb')?.remove();
    });
  });
}
 
function addFiles(files: File[], previews: HTMLElement): void {
  files.forEach((file) => {
    const isValidType = ALLOWED_TYPES.some((t) => file.type.startsWith(t));
    const isValidSize = FileSizeFormatter.isValid(file.size, MAX_SIZE);
 
    if (!isValidType) {
      showFileError(previews, file.name, 'Type non autorisé (images, vidéos, audios uniquement)');
      return;
    }
    if (!isValidSize) {
      showFileError(previews, file.name, `Taille max : ${MAX_SIZE}`);
      return;
    }
 
    pendingFiles.push(file);
    renderPreview(file, previews);
  });
}
 
function renderPreview(file: File, container: HTMLElement): void {
  const wrap = document.createElement('div');
  wrap.className  = 'journal-form__preview-item';
  wrap.dataset.name = file.name;
 
  if (file.type.startsWith('image/')) {
    const reader   = new FileReader();
    reader.onload  = (e) => {
      const img  = document.createElement('img');
      img.src    = e.target?.result as string;
      img.alt    = file.name;
      wrap.prepend(img);
    };
    reader.readAsDataURL(file);
  } else {
    const icon      = document.createElement('i');
    icon.className  = file.type.startsWith('video/') ? 'ri-video-line' : 'ri-music-line';
    wrap.prepend(icon);
  }
 
  const nameEl       = document.createElement('span');
  nameEl.className   = 'journal-form__preview-name';
  nameEl.textContent = `${file.name} (${FileSizeFormatter.format(file.size)})`;
 
  const removeBtn = document.createElement('button');
  removeBtn.type      = 'button';
  removeBtn.className = 'journal-form__media-remove';
  removeBtn.innerHTML = '<i class="ri-close-line"></i>';
  removeBtn.addEventListener('click', () => {
    pendingFiles = pendingFiles.filter((f) => f !== file);
    wrap.remove();
  });
 
  wrap.append(nameEl, removeBtn);
  container.appendChild(wrap);
}
 
function showFileError(container: HTMLElement, name: string, message: string): void {
  const el       = document.createElement('div');
  el.className   = 'journal-form__preview-error';
  el.textContent = `${name} — ${message}`;
  container.appendChild(el);
  setTimeout(() => el.remove(), 5000);
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 6. Brouillon automatique
// ─────────────────────────────────────────────────────────────────────────────
 
function initDraft(form: HTMLFormElement): void {
  const titleInput  = $<HTMLInputElement>('#entry-title', false, form) as HTMLInputElement | null;
  const contentArea = $<HTMLTextAreaElement>('#entry-content', false, form) as HTMLTextAreaElement | null;
 
  if (!titleInput || !contentArea) return;
 
  const isNew = !form.dataset.url?.includes('/update/');
  if (isNew) {
    const draft = storage.get(DRAFT_KEY) as { title?: string; content?: string } | null;
    if (draft) {
      if (draft.title)   titleInput.value   = draft.title;
      if (draft.content) contentArea.value  = draft.content;
      showDraftBanner(form);
    }
  }
 
  setInterval(() => {
    storage.set(DRAFT_KEY, { title: titleInput.value, content: contentArea.value });
  }, 30_000);
}
 
function showDraftBanner(form: HTMLFormElement): void {
  const banner     = document.createElement('div');
  banner.className = 'journal-form__draft-banner';
  banner.innerHTML = `
    <i class="ri-save-line me-1"></i>
    Brouillon restauré.
    <button type="button" class="btn-close btn-close-sm ms-2" aria-label="Fermer"></button>
  `;
  banner.querySelector('.btn-close')?.addEventListener('click', () => banner.remove());
  form.prepend(banner);
}
 
// ─────────────────────────────────────────────────────────────────────────────
// 7. Soumission — FormData (texte + fichiers en un seul POST)
// ─────────────────────────────────────────────────────────────────────────────
 
function initSubmit(form: HTMLFormElement): void {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
 
    const url       = form.dataset.url!;
    const csrf      = form.dataset.csrf!;
    const submitBtn = $<HTMLButtonElement>('.js-submit-btn', false, form) as HTMLButtonElement | null;
    const label     = submitBtn?.querySelector<HTMLElement>('.js-submit-label');
    const spinner   = submitBtn?.querySelector<HTMLElement>('.js-submit-spinner');
 
    if (submitBtn) submitBtn.disabled = true;
    label?.classList.add('d-none');
    spinner?.classList.remove('d-none');
 
    try {
      const formData = buildFormData(form);
 
      const res = await fetchAPI<{
        success: boolean;
        errors?: Record<string, string[]>;
        redirectUrl?: string;
        entry?: { id: number };
      }>(url, {
        method:  'POST',
        body:    formData,      // FormData → fetchAPI n'ajoute pas Content-Type (laisser le navigateur)
        headers: { 'X-CSRF-Token': csrf },
        isAPIAuthenticated: false,
      });
 
      if (res.data.success) {
        storage.remove(DRAFT_KEY);
      }
      label?.classList.remove('d-none');
      spinner?.classList.add('d-none');
      if (submitBtn) submitBtn.disabled = false;
    } catch (err) {
      if (err instanceof ApiError) {
        const errors = err.getValidationErrors();
        if (errors) displayErrors(form, errors as Record<string, string[]>);
      }
 
      if (submitBtn) submitBtn.disabled = false;
      label?.classList.remove('d-none');
      spinner?.classList.add('d-none');
    }
  });
}
 
// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
 
/**
 * Construit un FormData à partir des champs du formulaire.
 * Les fichiers pendingFiles sont appendés sous la clé "medias[]".
 * Les ids des médias supprimés sont appendés sous "deleted_medias[]".
 */
function buildFormData(form: HTMLFormElement): FormData {
  const fd = new FormData();
 
  // Champs texte
  const title = (form.querySelector<HTMLInputElement>('#entry-title')?.value ?? '').trim();
  if (title) fd.append('title', title);
 
  const content = form.querySelector<HTMLTextAreaElement>('#entry-content')?.value ?? '';
  fd.append('content', content);
 
  const mood = form.querySelector<HTMLInputElement>('#entry-mood')?.value ?? '';
  if (mood) fd.append('mood', mood);
 
  // Couleur sélectionnée via SelectableField (radio)
  const color = form.querySelector<HTMLInputElement>('[name="color"]:checked')?.value ?? '';
  if (color) fd.append('color', color);
 
  const isPrivate = form.querySelector<HTMLInputElement>('[name="is_private"]');
  fd.append('is_private', isPrivate?.checked ? '1' : '0');
 
  // Catégories (select multiple)
  const select = form.querySelector<HTMLSelectElement>('#entry-categories');
  if (select) {
    Array.from(select.selectedOptions).forEach((opt) => fd.append('categories[]', opt.value));
  }
 
  // Localisation
  const lat  = form.querySelector<HTMLInputElement>('#entry-location-lat')?.value  ?? '';
  const lng  = form.querySelector<HTMLInputElement>('#entry-location-lng')?.value  ?? '';
  const name = form.querySelector<HTMLInputElement>('#entry-location-name')?.value ?? '';
  if (lat)  fd.append('location_lat',  lat);
  if (lng)  fd.append('location_lng',  lng);
  if (name) fd.append('location_name', name);
 
  // Médias supprimés (ids des thumbs retirés du DOM)
  collectDeletedMediaIds(form).forEach((id) => fd.append('deleted_medias[]', id));
 
  // Nouveaux fichiers
  pendingFiles.forEach((file) => fd.append('medias[]', file, file.name));
 
  return fd;
}
 
function collectDeletedMediaIds(form: HTMLFormElement): string[] {
  const remaining = Array.from(
    form.querySelectorAll<HTMLElement>('.journal-form__media-thumb[data-id]'),
  ).map((el) => el.dataset.id!);
 
  const all = Array.from(
    form.querySelectorAll<HTMLElement>('.js-existing-medias [data-id]'),
  ).map((el) => el.dataset.id!);
 
  return all.filter((id) => !remaining.includes(id));
}
 
function displayErrors(form: HTMLFormElement, errors: Record<string, string[]>): void {
  form.querySelectorAll('.journal-form__error').forEach((el) => el.remove());
 
  Object.entries(errors).forEach(([field, messages]) => {
    const input = form.querySelector<HTMLElement>(`[name="${field}"]`);
    if (!input) return;
 
    input.classList.add('is-invalid');
    const errorEl       = document.createElement('small');
    errorEl.className   = 'journal-form__error text-danger';
    errorEl.textContent = messages[0] ?? 'Champ invalide';
    input.insertAdjacentElement('afterend', errorEl);
  });
}