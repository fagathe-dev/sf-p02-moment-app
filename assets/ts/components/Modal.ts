// =============================================================================
// components/Modal.ts
// Gestion de data-bs-toggle="modal" + data-bs-dismiss="modal"
//
// HTML attendu (balise sémantique <dialog>) :
//   <dialog class="modal fade" id="myModal">
//     <div class="modal-dialog">
//       <div class="modal-content">
//         <div class="modal-header">
//           <h5 class="modal-title">Titre</h5>
//           <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
//         </div>
//         <div class="modal-body">...</div>
//         <div class="modal-footer">...</div>
//       </div>
//     </div>
//   </dialog>
//
// Attributs supportés :
//   data-bs-backdrop  : "true" (défaut) | "static" | "false"
//   data-bs-keyboard  : "true" (défaut) | "false"
//   data-bs-focus     : "true" (défaut) | "false"
//
// Événements Bootstrap :
//   show.bs.modal    — annulable, detail.relatedTarget = trigger
//   shown.bs.modal
//   hide.bs.modal    — annulable
//   hidden.bs.modal
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

// Éléments focusables pour le focus trap
const FOCUSABLE_SELECTORS =
  'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

// =============================================================================
// État interne
// =============================================================================

let activeModal: HTMLElement | null = null;
let previouslyFocused: HTMLElement | null = null;

// =============================================================================
// Helpers
// =============================================================================

function isDialog(el: HTMLElement): el is HTMLDialogElement {
  return el.tagName.toLowerCase() === 'dialog';
}

function getFocusableElements(el: HTMLElement): HTMLElement[] {
  return Array.from(
    el.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTORS),
  ).filter(
    (e) => !e.closest('[hidden]') && getComputedStyle(e).display !== 'none',
  );
}

/** Piège le focus dans la modal */
function trapFocus(modal: HTMLElement, e: KeyboardEvent): void {
  if (e.key !== 'Tab') return;

  const focusable = getFocusableElements(modal);
  if (!focusable.length) {
    e.preventDefault();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (e.shiftKey) {
    if (document.activeElement === first) {
      e.preventDefault();
      last.focus();
    }
  } else {
    if (document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
}

// =============================================================================
// Ouverture
// =============================================================================

function openModal(modal: HTMLElement, trigger: HTMLElement | null): void {
  const showEvent = DataBsBridge.dispatch(modal, 'show.bs.modal', {
    relatedTarget: trigger,
  });
  if (showEvent.defaultPrevented) return;

  // Ferme l'éventuelle modal déjà ouverte
  if (activeModal && activeModal !== modal) {
    closeModal(activeModal, false);
  }

  previouslyFocused = document.activeElement as HTMLElement;
  activeModal = modal;

  // Bloque le scroll du body
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';

  if (isDialog(modal)) {
    // Utilise l'API native <dialog>
    (modal as HTMLDialogElement).showModal();
    modal.classList.add('show');
  } else {
    // Approche classe (div.modal)
    modal.style.display = 'block';
    modal.classList.add('show');
    modal.setAttribute('aria-modal', 'true');
    modal.removeAttribute('aria-hidden');
    Backdrop.show(modal);
  }

  // Focus le premier élément focusable après animation
  const handleTransitionEnd = () => {
    modal.removeEventListener('transitionend', handleTransitionEnd);
    const focusTarget =
      modal.querySelector<HTMLElement>('[autofocus]') ??
      getFocusableElements(modal)[0] ??
      (modal as HTMLElement);
    focusTarget.focus();
    DataBsBridge.dispatch(modal, 'shown.bs.modal', { relatedTarget: trigger });
  };

  modal.addEventListener('transitionend', handleTransitionEnd, { once: true });

  // Fallback si pas d'animation
  setTimeout(() => {
    if (
      modal.classList.contains('show') &&
      document.activeElement === previouslyFocused
    ) {
      const focusTarget = getFocusableElements(modal)[0] ?? modal;
      focusTarget.focus();
    }
  }, 300);

  // Focus trap
  modal._trapHandler = (e: KeyboardEvent) => trapFocus(modal, e);
  modal.addEventListener('keydown', modal._trapHandler);
}

// =============================================================================
// Fermeture
// =============================================================================

function closeModal(modal: HTMLElement, restoreFocus = true): void {
  const hideEvent = DataBsBridge.dispatch(modal, 'hide.bs.modal');
  if (hideEvent.defaultPrevented) return;

  modal.classList.remove('show');

  // Retire le focus trap
  if (modal._trapHandler) {
    modal.removeEventListener('keydown', modal._trapHandler);
    delete modal._trapHandler;
  }

  const handleTransitionEnd = () => {
    modal.removeEventListener('transitionend', handleTransitionEnd);
    finalizeClose(modal, restoreFocus);
  };

  modal.addEventListener('transitionend', handleTransitionEnd, { once: true });

  // Fallback
  setTimeout(() => {
    if (!modal.classList.contains('show')) {
      finalizeClose(modal, restoreFocus);
    }
  }, 300);
}

function finalizeClose(modal: HTMLElement, restoreFocus: boolean): void {
  if (!modal.classList.contains('show')) {
    if (isDialog(modal)) {
      (modal as HTMLDialogElement).close();
    } else {
      modal.style.display = '';
      modal.setAttribute('aria-hidden', 'true');
      modal.removeAttribute('aria-modal');
      Backdrop.hide();
    }

    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    activeModal = null;

    if (restoreFocus && previouslyFocused) {
      previouslyFocused.focus();
      previouslyFocused = null;
    }

    DataBsBridge.dispatch(modal, 'hidden.bs.modal');
  }
}

// =============================================================================
// Backdrop (pour les div.modal — les <dialog> ont ::backdrop natif)
// =============================================================================

const Backdrop = {
  el: null as HTMLElement | null,

  show(modal: HTMLElement): void {
    if (this.el) return;

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(backdrop);
    this.el = backdrop;

    // Fermeture au clic sur le backdrop
    backdrop.addEventListener('click', () => {
      const backdropMode = modal.dataset.bsBackdrop ?? 'true';
      if (backdropMode === 'static') {
        // Animation "static" : secousse de la dialog
        modal.classList.add('modal-static');
        setTimeout(() => modal.classList.remove('modal-static'), 300);
      } else {
        closeModal(modal);
      }
    });
  },

  hide(): void {
    this.el?.remove();
    this.el = null;
  },
};

// =============================================================================
// Clic sur le fond de la <dialog> native (zone hors modal-dialog)
// =============================================================================

function handleDialogBackdropClick(modal: HTMLElement, e: MouseEvent): void {
  const backdropMode = modal.dataset.bsBackdrop ?? 'true';
  if (backdropMode === 'false') return;

  const rect = modal.getBoundingClientRect();
  const clickedInsideContent =
    e.clientX >= rect.left &&
    e.clientX <= rect.right &&
    e.clientY >= rect.top &&
    e.clientY <= rect.bottom;

  if (!clickedInsideContent) {
    if (backdropMode === 'static') {
      modal.classList.add('modal-static');
      setTimeout(() => modal.classList.remove('modal-static'), 300);
    } else {
      closeModal(modal);
    }
  }
}

// =============================================================================
// Augmentation du type HTMLElement pour le focus trap
// =============================================================================

declare global {
  interface HTMLElement {
    _trapHandler?: (e: KeyboardEvent) => void;
  }
}

// =============================================================================
// Enregistrement dans le Bridge
// =============================================================================

export class Modal {
  static init(): void {
    const bridge = DataBsBridge.getInstance();

    // ── Ouverture via data-bs-toggle="modal" ──────────────────────────────
    bridge.registerToggle('modal', (trigger, target) => {
      if (!target) return;
      openModal(target, trigger);
    });

    // ── Fermeture via data-bs-dismiss="modal" ────────────────────────────
    bridge.registerDismiss('modal', (_trigger, component) => {
      if (!component) return;
      closeModal(component);
    });

    // ── Escape pour fermer ────────────────────────────────────────────────
    bridge.registerKeyHandler((e: KeyboardEvent) => {
      if (e.key !== 'Escape' || !activeModal) return;

      const keyboardMode = activeModal.dataset.bsKeyboard ?? 'true';
      if (keyboardMode === 'false') return;

      closeModal(activeModal);
    });

    // ── Clic sur le fond des <dialog> natives ────────────────────────────
    document.addEventListener('click', (e: MouseEvent) => {
      const dialog = (e.target as HTMLElement).closest<HTMLElement>(
        'dialog.modal',
      );
      if (!dialog || !dialog.classList.contains('show')) return;
      handleDialogBackdropClick(dialog, e);
    });

    // ── Événement natif de fermeture de la <dialog> (bouton ESC natif) ───
    document.addEventListener('cancel', (e: Event) => {
      const dialog = e.target as HTMLDialogElement;
      if (!dialog.classList.contains('modal')) return;

      e.preventDefault(); // On prend la main pour dispatcher nos propres events
      closeModal(dialog as unknown as HTMLElement);
    });
  }

  /** API publique */
  static show(el: HTMLElement | string, relatedTarget?: HTMLElement): void {
    const modal =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (modal) openModal(modal, relatedTarget ?? null);
  }

  static hide(el?: HTMLElement | string): void {
    if (el) {
      const modal =
        typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
      if (modal) closeModal(modal);
    } else if (activeModal) {
      closeModal(activeModal);
    }
  }
}
