// =============================================================================
// components/Toast.ts
// Gestion des toasts : auto-affichage, auto-dismiss, dismiss manuel.
//
// HTML attendu :
//   <div class="toast" role="alert" aria-live="assertive" aria-atomic="true"
//        data-bs-autohide="true" data-bs-delay="5000">
//     <div class="toast-header">
//       <strong class="me-auto">Titre</strong>
//       <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
//     </div>
//     <div class="toast-body">Message</div>
//   </div>
//
// Attributs :
//   data-bs-autohide  : "true" (défaut) | "false"
//   data-bs-delay     : millisecondes avant fermeture auto (défaut : 5000)
//   data-bs-animation : "true" (défaut) | "false"
//
// Événements :
//   show.bs.toast    — annulable
//   shown.bs.toast
//   hide.bs.toast    — annulable
//   hidden.bs.toast
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

const DEFAULT_DELAY = 5000;

// Stocke les timers par élément toast
const timers = new WeakMap<HTMLElement, ReturnType<typeof setTimeout>>();

// =============================================================================
// Logique show / hide
// =============================================================================

function showToast(toast: HTMLElement): void {
  const showEvent = DataBsBridge.dispatch(toast, 'show.bs.toast');
  if (showEvent.defaultPrevented) return;

  toast.classList.remove('hide');
  toast.classList.add('showing');

  // Force reflow
  void toast.offsetHeight;

  toast.classList.add('show');
  toast.classList.remove('showing');
  toast.removeAttribute('hidden');

  DataBsBridge.dispatch(toast, 'shown.bs.toast');

  // Auto-dismiss
  const autohide = toast.dataset.bsAutohide !== 'false';
  if (autohide) {
    const delay = parseInt(toast.dataset.bsDelay ?? String(DEFAULT_DELAY), 10);
    const timer = setTimeout(() => hideToast(toast), delay);
    timers.set(toast, timer);
  }
}

function hideToast(toast: HTMLElement): void {
  const hideEvent = DataBsBridge.dispatch(toast, 'hide.bs.toast');
  if (hideEvent.defaultPrevented) return;

  // Annule le timer auto s'il existe
  const timer = timers.get(toast);
  if (timer !== undefined) {
    clearTimeout(timer);
    timers.delete(toast);
  }

  const animated = toast.dataset.bsAnimation !== 'false';

  if (animated) {
    toast.classList.add('hiding');
    toast.classList.remove('show');

    const onEnd = () => {
      toast.classList.remove('hiding');
      toast.setAttribute('hidden', '');
      DataBsBridge.dispatch(toast, 'hidden.bs.toast');
    };

    toast.addEventListener('transitionend', onEnd, { once: true });

    // Fallback
    setTimeout(() => {
      if (toast.classList.contains('hiding')) {
        toast.classList.remove('hiding');
        toast.setAttribute('hidden', '');
        DataBsBridge.dispatch(toast, 'hidden.bs.toast');
      }
    }, 300);
  } else {
    toast.classList.remove('show');
    toast.setAttribute('hidden', '');
    DataBsBridge.dispatch(toast, 'hidden.bs.toast');
  }
}

// =============================================================================
// Enregistrement dans le Bridge
// =============================================================================

export class Toast {
  static init(): void {
    const bridge = DataBsBridge.getInstance();

    // Dismiss manuel
    bridge.registerDismiss('toast', (_trigger, component) => {
      if (!component) return;
      hideToast(component);
    });

    // Auto-affichage des toasts présents dans le DOM au chargement
    Toast.initAll();
  }

  /**
   * Affiche automatiquement tous les .toast sans [hidden] ni .show
   * présents dans le DOM au moment de l'appel.
   */
  static initAll(): void {
    document.querySelectorAll<HTMLElement>('.toast').forEach((toast) => {
      // Ne touche pas les toasts déjà affichés ou explicitement cachés
      if (toast.classList.contains('show') || toast.hasAttribute('hidden'))
        return;

      // Affichage auto si data-bs-autohide != false
      const autoshow = toast.dataset.bsAutoshow !== 'false';
      if (autoshow) showToast(toast);
    });
  }

  /** API publique */
  static show(el: HTMLElement | string): void {
    const toast =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (toast) showToast(toast);
  }

  static hide(el: HTMLElement | string): void {
    const toast =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (toast) hideToast(toast);
  }

  /**
   * Crée et affiche un toast dynamiquement.
   * Le toast est injecté dans le .toast-container cible (ou dans le body).
   */
  static create(options: {
    message: string;
    title?: string;
    type?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info';
    delay?: number;
    container?: string | HTMLElement;
  }): HTMLElement {
    const {
      message,
      title,
      type = 'primary',
      delay = DEFAULT_DELAY,
      container,
    } = options;

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.dataset.bsDelay = String(delay);

    toast.innerHTML =
      (title
        ? `<div class="toast-header"><span class="badge badge-soft-${type} me-2">${type}</span><strong class="me-auto">${title}</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button></div>`
        : '') +
      `<div class="toast-body${!title ? ' d-flex align-items-center gap-2' : ''}">${message}${!title ? '<button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>' : ''}</div>`;

    // Conteneur cible
    let targetContainer: HTMLElement | null = null;

    if (container) {
      targetContainer =
        typeof container === 'string'
          ? document.querySelector<HTMLElement>(container)
          : container;
    }

    if (!targetContainer) {
      // Cherche un conteneur par défaut ou crée un
      targetContainer = document.querySelector<HTMLElement>('.toast-container');
      if (!targetContainer) {
        targetContainer = document.createElement('div');
        targetContainer.className = 'toast-container top-0 end-0 p-3';
        document.body.appendChild(targetContainer);
      }
    }

    targetContainer.appendChild(toast);

    // Supprime du DOM après fermeture
    toast.addEventListener('hidden.bs.toast', () => toast.remove(), {
      once: true,
    });

    showToast(toast);

    return toast;
  }
}
