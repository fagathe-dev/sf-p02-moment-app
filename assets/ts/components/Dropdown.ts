// =============================================================================
// components/Dropdown.ts
// Gestion de data-bs-toggle="dropdown"
//
// HTML attendu :
//   <div class="dropdown">
//     <button data-bs-toggle="dropdown" aria-expanded="false">Toggle</button>
//     <ul class="dropdown-menu">...</ul>
//   </div>
//
// Comportements :
//   - Clic sur le trigger → toggle .show sur le menu
//   - Clic en dehors     → ferme tous les dropdowns ouverts
//   - Escape             → ferme le dropdown actif et remet le focus
//   - data-bs-offset     → décalage [x, y] (optionnel)
//   - data-bs-auto-close → "true" | "inside" | "outside" | "false"
//
// Événements :
//   show.bs.dropdown    — annulable
//   shown.bs.dropdown
//   hide.bs.dropdown    — annulable
//   hidden.bs.dropdown
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

// =============================================================================
// Helpers
// =============================================================================

/** Trouve le menu associé à un trigger */
function resolveMenu(trigger: HTMLElement): HTMLElement | null {
  // 1. Via data-bs-target
  const targetSel = trigger.dataset.bsTarget;
  if (targetSel && targetSel !== '#') {
    return document.querySelector<HTMLElement>(targetSel);
  }

  // 2. Sibling direct .dropdown-menu
  const next = trigger.nextElementSibling;
  if (next?.classList.contains('dropdown-menu')) {
    return next as HTMLElement;
  }

  // 3. Dans le parent .dropdown / .btn-group
  const parent = trigger.parentElement;
  return parent?.querySelector<HTMLElement>('.dropdown-menu') ?? null;
}

/** Ferme un dropdown ouvert */
function closeDropdown(trigger: HTMLElement, menu: HTMLElement): void {
  const hideEvent = DataBsBridge.dispatch(trigger, 'hide.bs.dropdown', {
    relatedTarget: trigger,
  });
  if (hideEvent.defaultPrevented) return;

  menu.classList.remove('show');
  trigger.setAttribute('aria-expanded', 'false');
  trigger
    .closest('.dropdown, .btn-group, .dropup, .dropend, .dropstart')
    ?.classList.remove('show');

  DataBsBridge.dispatch(trigger, 'hidden.bs.dropdown', {
    relatedTarget: trigger,
  });
}

/** Ferme tous les dropdowns ouverts dans le document */
function closeAllDropdowns(except?: HTMLElement): void {
  document
    .querySelectorAll<HTMLElement>(
      '[data-bs-toggle="dropdown"][aria-expanded="true"]',
    )
    .forEach((trigger) => {
      if (trigger === except) return;
      const menu = resolveMenu(trigger);
      if (menu) closeDropdown(trigger, menu);
    });
}

// =============================================================================
// Enregistrement dans le Bridge
// =============================================================================

export class Dropdown {
  private static outsideListener: ((e: MouseEvent) => void) | null = null;

  static init(): void {
    const bridge = DataBsBridge.getInstance();

    // ── Toggle ─────────────────────────────────────────────────────────────
    bridge.registerToggle('dropdown', (trigger, _target, e) => {
      e.preventDefault();
      e.stopPropagation();

      const menu = resolveMenu(trigger);
      if (!menu) return;

      const isOpen = menu.classList.contains('show');

      // Ferme tous les autres dropdowns d'abord
      closeAllDropdowns(trigger);

      if (isOpen) {
        closeDropdown(trigger, menu);
      } else {
        const showEvent = DataBsBridge.dispatch(trigger, 'show.bs.dropdown', {
          relatedTarget: trigger,
        });
        if (showEvent.defaultPrevented) return;

        menu.classList.add('show');
        trigger.setAttribute('aria-expanded', 'true');
        trigger
          .closest('.dropdown, .btn-group, .dropup, .dropend, .dropstart')
          ?.classList.add('show');

        // Focus le premier item actif du menu
        const firstItem = menu.querySelector<HTMLElement>(
          '.dropdown-item:not(.disabled):not([disabled])',
        );
        firstItem?.focus();

        DataBsBridge.dispatch(trigger, 'shown.bs.dropdown', {
          relatedTarget: trigger,
        });
      }
    });

    // ── Fermeture sur clic extérieur ────────────────────────────────────────
    if (!Dropdown.outsideListener) {
      Dropdown.outsideListener = (e: MouseEvent) => {
        const target = e.target as HTMLElement;

        document
          .querySelectorAll<HTMLElement>(
            '[data-bs-toggle="dropdown"][aria-expanded="true"]',
          )
          .forEach((trigger) => {
            const menu = resolveMenu(trigger);
            if (!menu) return;

            const autoClose = trigger.dataset.bsAutoClose ?? 'true';

            const clickedInsideTrigger = trigger.contains(target);
            const clickedInsideMenu = menu.contains(target);

            if (autoClose === 'false') return;
            if (autoClose === 'inside' && !clickedInsideMenu) return;
            if (autoClose === 'outside' && clickedInsideTrigger) return;
            if (clickedInsideTrigger || clickedInsideMenu) return;

            closeDropdown(trigger, menu);
          });
      };

      document.addEventListener('click', Dropdown.outsideListener, true);
    }

    // ── Fermeture Escape + navigation clavier ───────────────────────────────
    bridge.registerKeyHandler((e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        const trigger = document.querySelector<HTMLElement>(
          '[data-bs-toggle="dropdown"][aria-expanded="true"]',
        );
        if (!trigger) return;

        const menu = resolveMenu(trigger);
        if (menu) closeDropdown(trigger, menu);

        trigger.focus();
        return;
      }

      // Navigation clavier dans le menu ouvert
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        const menu = document.querySelector<HTMLElement>('.dropdown-menu.show');
        if (!menu) return;

        const items = Array.from(
          menu.querySelectorAll<HTMLElement>(
            '.dropdown-item:not(.disabled):not([disabled])',
          ),
        );
        if (!items.length) return;

        const focused = document.activeElement as HTMLElement;
        const currentIndex = items.indexOf(focused);
        let nextIndex: number;

        if (e.key === 'ArrowDown') {
          nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
        } else {
          nextIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
        }

        e.preventDefault();
        items[nextIndex]?.focus();
      }
    });
  }
}
