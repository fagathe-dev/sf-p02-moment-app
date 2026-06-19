// =============================================================================
// components/Tab.ts
// Gestion de data-bs-toggle="tab" et data-bs-toggle="list"
// (nav-tabs, nav-pills, list-group avec onglets)
//
// HTML attendu :
//   <ul class="nav nav-tabs" role="tablist" id="myTab">
//     <li class="nav-item">
//       <button class="nav-link active" data-bs-toggle="tab"
//               data-bs-target="#home" role="tab">Home</button>
//     </li>
//     <li class="nav-item">
//       <button class="nav-link" data-bs-toggle="tab"
//               data-bs-target="#profile" role="tab">Profile</button>
//     </li>
//   </ul>
//   <div class="tab-content">
//     <div class="tab-pane fade show active" id="home" role="tabpanel">...</div>
//     <div class="tab-pane fade" id="profile" role="tabpanel">...</div>
//   </div>
//
// Événements :
//   hide.bs.tab   — sur le tab qui se désactive (detail.relatedTarget = nouveau tab)
//   hidden.bs.tab — après désactivation
//   show.bs.tab   — annulable, sur le tab qui s'active (detail.relatedTarget = ancien tab)
//   shown.bs.tab  — après activation
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

// =============================================================================
// Helpers
// =============================================================================

/** Récupère tous les triggers du même groupe de tabs (même .nav ou [role="tablist"]) */
function getSiblingTriggers(trigger: HTMLElement): HTMLElement[] {
  const nav = trigger.closest<HTMLElement>(
    '.nav, [role="tablist"], .list-group',
  );
  if (!nav) return [];

  return Array.from(
    nav.querySelectorAll<HTMLElement>(
      '[data-bs-toggle="tab"], [data-bs-toggle="list"]',
    ),
  );
}

/** Trouve le panneau actuellement actif dans le même .tab-content */
function getActivePanel(newPanel: HTMLElement): HTMLElement | null {
  const tabContent = newPanel.closest<HTMLElement>('.tab-content');
  if (!tabContent) return null;

  return tabContent.querySelector<HTMLElement>('.tab-pane.active');
}

/** Désactive un trigger tab */
function deactivateTrigger(trigger: HTMLElement): void {
  trigger.classList.remove('active');
  trigger.setAttribute('aria-selected', 'false');
  trigger.setAttribute('tabindex', '-1');
}

/** Active un trigger tab */
function activateTrigger(trigger: HTMLElement): void {
  trigger.classList.add('active');
  trigger.setAttribute('aria-selected', 'true');
  trigger.removeAttribute('tabindex');
}

/** Cache un panneau */
function hidePanel(
  panel: HTMLElement,
  relatedTarget: HTMLElement | null,
): void {
  DataBsBridge.dispatch(panel, 'hide.bs.tab', { relatedTarget });

  panel.classList.remove('active');
  if (panel.classList.contains('fade')) {
    panel.classList.remove('show');
  }

  DataBsBridge.dispatch(panel, 'hidden.bs.tab', { relatedTarget });
}

/** Affiche un panneau */
function showPanel(
  panel: HTMLElement,
  trigger: HTMLElement,
  previousPanel: HTMLElement | null,
): void {
  const showEvent = DataBsBridge.dispatch(panel, 'show.bs.tab', {
    relatedTarget: previousPanel,
  });
  if (showEvent.defaultPrevented) return;

  panel.classList.add('active');

  if (panel.classList.contains('fade')) {
    // Force reflow avant d'ajouter .show pour déclencher la transition CSS
    void panel.offsetHeight;
    panel.classList.add('show');
  }

  DataBsBridge.dispatch(trigger, 'shown.bs.tab', {
    relatedTarget: previousPanel,
  });
}

// =============================================================================
// Handler commun tab + list
// =============================================================================

function handleTabToggle(
  trigger: HTMLElement,
  target: HTMLElement | null,
): void {
  if (!target) return;
  if (trigger.classList.contains('active')) return; // Déjà actif

  const siblings = getSiblingTriggers(trigger);
  const activePanel = getActivePanel(target);

  // Trouve le trigger actif pour l'événement relatedTarget
  const activeTrigger =
    siblings.find((t) => t.classList.contains('active')) ?? null;

  // Désactive tous les triggers du groupe
  siblings.forEach(deactivateTrigger);

  // Cache le panneau actif
  if (activePanel) hidePanel(activePanel, target);

  // Active le nouveau trigger
  activateTrigger(trigger);

  // Affiche le nouveau panneau
  showPanel(target, trigger, activePanel);

  // Met à jour aria-controls
  if (trigger.id && !target.getAttribute('aria-labelledby')) {
    target.setAttribute('aria-labelledby', trigger.id);
  }

  void activeTrigger; // évite le warning TS
}

// =============================================================================
// Navigation clavier dans les tabs (accessibilité)
// =============================================================================

function handleTabKeydown(e: KeyboardEvent): void {
  const trigger = e.target as HTMLElement;
  if (
    !trigger.matches('[data-bs-toggle="tab"], [data-bs-toggle="list"]') ||
    trigger.classList.contains('disabled')
  ) {
    return;
  }

  const siblings = getSiblingTriggers(trigger).filter(
    (t) => !t.classList.contains('disabled'),
  );
  if (!siblings.length) return;

  const currentIndex = siblings.indexOf(trigger);

  let targetTrigger: HTMLElement | undefined;

  if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
    e.preventDefault();
    targetTrigger = siblings[(currentIndex + 1) % siblings.length];
  } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
    e.preventDefault();
    targetTrigger =
      siblings[(currentIndex - 1 + siblings.length) % siblings.length];
  } else if (e.key === 'Home') {
    e.preventDefault();
    targetTrigger = siblings[0];
  } else if (e.key === 'End') {
    e.preventDefault();
    targetTrigger = siblings[siblings.length - 1];
  }

  if (targetTrigger) {
    targetTrigger.focus();
    targetTrigger.click(); // Active l'onglet
  }
}

// =============================================================================
// Enregistrement dans le Bridge
// =============================================================================

export class Tab {
  static init(): void {
    const bridge = DataBsBridge.getInstance();

    bridge.registerToggle('tab', (trigger, target) => {
      handleTabToggle(trigger, target);
    });

    bridge.registerToggle('list', (trigger, target) => {
      handleTabToggle(trigger, target);
    });

    // Navigation clavier
    document.addEventListener('keydown', handleTabKeydown);

    // Auto-initialisation des aria sur les tabs déjà dans le DOM
    Tab.initAria();
  }

  /** Ajoute les attributs ARIA manquants sur les tabs existants */
  static initAria(): void {
    document
      .querySelectorAll<HTMLElement>(
        '[data-bs-toggle="tab"], [data-bs-toggle="list"]',
      )
      .forEach((trigger) => {
        if (!trigger.hasAttribute('role')) trigger.setAttribute('role', 'tab');
        if (!trigger.hasAttribute('aria-selected')) {
          trigger.setAttribute(
            'aria-selected',
            trigger.classList.contains('active') ? 'true' : 'false',
          );
        }
        if (!trigger.classList.contains('active')) {
          trigger.setAttribute('tabindex', '-1');
        }
      });

    document.querySelectorAll<HTMLElement>('.tab-pane').forEach((panel) => {
      if (!panel.hasAttribute('role')) panel.setAttribute('role', 'tabpanel');
      if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '0');
    });
  }

  /** API publique */
  static show(el: HTMLElement | string): void {
    const trigger =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (!trigger) return;

    const targetSel = trigger.dataset.bsTarget ?? trigger.getAttribute('href');
    if (!targetSel) return;

    const target = document.querySelector<HTMLElement>(targetSel);
    handleTabToggle(trigger, target);
  }
}
