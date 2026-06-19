// =============================================================================
// core/DataBsBridge.ts
// Dispatcher central pour tous les attributs data-bs-* de Bootstrap.
//
// Principe :
//   Un seul listener délégué sur `document` capte les clics et les touches.
//   Chaque composant s'enregistre via registerToggle() / registerDismiss().
//   Le bridge résout la cible (data-bs-target ou href) avant de déléguer.
//
// Usage dans un composant :
//   import { DataBsBridge } from '@/core/DataBsBridge';
//   const bridge = DataBsBridge.getInstance();
//   bridge.registerToggle('modal', (trigger, target, e) => { ... });
//   bridge.registerDismiss('modal', (trigger, component, e) => { ... });
// =============================================================================

import type { BsDismissHandler, BsKeyHandler, BsToggleHandler } from './types';

export class DataBsBridge {
  private static instance: DataBsBridge | null = null;

  /** Handlers indexés par valeur de data-bs-toggle (ex: 'modal', 'collapse') */
  private readonly toggleHandlers = new Map<string, BsToggleHandler>();

  /** Handlers indexés par valeur de data-bs-dismiss (ex: 'modal', 'alert') */
  private readonly dismissHandlers = new Map<string, BsDismissHandler>();

  /** Handlers clavier globaux (Escape, etc.) */
  private readonly keyHandlers: BsKeyHandler[] = [];

  private initialized = false;

  private constructor() {}

  // ---------------------------------------------------------------------------
  // Singleton
  // ---------------------------------------------------------------------------

  static getInstance(): DataBsBridge {
    if (!DataBsBridge.instance) {
      DataBsBridge.instance = new DataBsBridge();
    }
    return DataBsBridge.instance;
  }

  // ---------------------------------------------------------------------------
  // Enregistrement des handlers par composant
  // ---------------------------------------------------------------------------

  /**
   * Enregistre le handler d'un composant pour data-bs-toggle="<type>".
   * Ex : registerToggle('modal', handler) sera appelé sur tout clic
   * sur [data-bs-toggle="modal"].
   */
  registerToggle(type: string, handler: BsToggleHandler): void {
    this.toggleHandlers.set(type, handler);
  }

  /**
   * Enregistre le handler d'un composant pour data-bs-dismiss="<type>".
   * Ex : registerDismiss('alert', handler) sera appelé sur tout clic
   * sur [data-bs-dismiss="alert"].
   */
  registerDismiss(type: string, handler: BsDismissHandler): void {
    this.dismissHandlers.set(type, handler);
  }

  /**
   * Enregistre un handler clavier global.
   * Typiquement utilisé pour fermer un composant sur Escape.
   */
  registerKeyHandler(handler: BsKeyHandler): void {
    this.keyHandlers.push(handler);
  }

  // ---------------------------------------------------------------------------
  // Utilitaire : dispatch d'un événement Bootstrap custom
  // Ex : DataBsBridge.dispatch(modalEl, 'show.bs.modal', { relatedTarget })
  // ---------------------------------------------------------------------------

  static dispatch(
    element: Element,
    eventName: string,
    detail: Record<string, unknown> = {},
  ): CustomEvent {
    const event = new CustomEvent(eventName, {
      bubbles: true,
      cancelable: true,
      detail,
    });
    element.dispatchEvent(event);
    return event;
  }

  // ---------------------------------------------------------------------------
  // Résolution interne de la cible d'un trigger
  // Lit data-bs-target en priorité, puis href (pour les <a>).
  // ---------------------------------------------------------------------------

  private resolveTarget(trigger: HTMLElement): HTMLElement | null {
    const selector =
      trigger.dataset.bsTarget ?? trigger.getAttribute('href') ?? null;

    if (!selector || selector === '#') return null;

    try {
      return document.querySelector<HTMLElement>(selector);
    } catch {
      // Sélecteur CSS invalide
      return null;
    }
  }

  // ---------------------------------------------------------------------------
  // Résolution du composant parent d'un bouton dismiss
  // Ex : [data-bs-dismiss="modal"] → remonte jusqu'à .modal
  //      [data-bs-dismiss="alert"] → remonte jusqu'à .alert
  // ---------------------------------------------------------------------------

  private resolveComponent(
    trigger: HTMLElement,
    type: string,
  ): HTMLElement | null {
    return trigger.closest<HTMLElement>(`.${type}`);
  }

  // ---------------------------------------------------------------------------
  // Initialisation — à appeler une seule fois au DOMContentLoaded
  // ---------------------------------------------------------------------------

  init(): void {
    if (this.initialized) return;
    this.initialized = true;

    // ----- Délégation : data-bs-toggle ----------------------------------------
    document.addEventListener('click', (e: MouseEvent) => {
      const trigger = (e.target as HTMLElement).closest<HTMLElement>(
        '[data-bs-toggle]',
      );
      if (!trigger) return;

      const type = trigger.dataset.bsToggle;
      if (!type) return;

      const handler = this.toggleHandlers.get(type);
      if (!handler) return;

      const target = this.resolveTarget(trigger);
      handler(trigger, target, e);
    });

    // ----- Délégation : data-bs-dismiss ----------------------------------------
    document.addEventListener('click', (e: MouseEvent) => {
      const trigger = (e.target as HTMLElement).closest<HTMLElement>(
        '[data-bs-dismiss]',
      );
      if (!trigger) return;

      const type = trigger.dataset.bsDismiss;
      if (!type) return;

      const handler = this.dismissHandlers.get(type);
      if (!handler) return;

      const component = this.resolveComponent(trigger, type);
      handler(trigger, component, e);
    });

    // ----- Délégation clavier globale ------------------------------------------
    document.addEventListener('keydown', (e: KeyboardEvent) => {
      for (const handler of this.keyHandlers) {
        handler(e);
      }
    });
  }
}
