// =============================================================================
// components/Collapse.ts
// Gestion de data-bs-toggle="collapse"
// Alimente également : Accordion (via data-bs-parent) et Navbar mobile.
//
// HTML attendu :
//   <button data-bs-toggle="collapse" data-bs-target="#myCollapse">Toggle</button>
//   <div class="collapse" id="myCollapse">...</div>
//
//   Accordion :
//   <div class="accordion-collapse collapse" data-bs-parent="#myAccordion">
//
// Événements :
//   show.bs.collapse    — annulable
//   shown.bs.collapse
//   hide.bs.collapse    — annulable
//   hidden.bs.collapse
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

// =============================================================================
// Helpers d'animation
// =============================================================================

function showElement(el: HTMLElement): void {
  if (el.classList.contains('collapsing')) return;

  const showEvent = DataBsBridge.dispatch(el, 'show.bs.collapse');
  if (showEvent.defaultPrevented) return;

  // Hauteur de départ = 0, on retire d'abord le display:none (.collapse sans .show)
  el.classList.remove('collapse');
  el.classList.add('collapsing');
  el.style.height = '0';

  // Force reflow pour que la transition parte vraiment de 0
  void el.offsetHeight;

  el.style.height = `${el.scrollHeight}px`;

  const onEnd = () => {
    el.classList.remove('collapsing');
    el.classList.add('collapse', 'show');
    el.style.height = '';
    updateTriggerAria(el, true);
    DataBsBridge.dispatch(el, 'shown.bs.collapse');
  };

  el.addEventListener('transitionend', onEnd, { once: true });
}

function hideElement(el: HTMLElement): void {
  if (el.classList.contains('collapsing')) return;
  if (!el.classList.contains('show')) return;

  const hideEvent = DataBsBridge.dispatch(el, 'hide.bs.collapse');
  if (hideEvent.defaultPrevented) return;

  // Fixe la hauteur courante avant de lancer la transition vers 0
  el.style.height = `${el.getBoundingClientRect().height}px`;

  // Force reflow
  void el.offsetHeight;

  el.classList.remove('show');
  el.classList.add('collapsing');
  el.classList.remove('collapse');
  el.style.height = '0';

  const onEnd = () => {
    el.classList.remove('collapsing');
    el.classList.add('collapse');
    el.style.height = '';
    updateTriggerAria(el, false);
    DataBsBridge.dispatch(el, 'hidden.bs.collapse');
  };

  el.addEventListener('transitionend', onEnd, { once: true });
}

/** Met à jour aria-expanded sur tous les triggers pointant vers cet élément */
function updateTriggerAria(el: HTMLElement, expanded: boolean): void {
  const id = el.id;
  if (!id) return;

  document
    .querySelectorAll<HTMLElement>(
      `[data-bs-toggle="collapse"][data-bs-target="#${id}"],
       [data-bs-toggle="collapse"][href="#${id}"]`,
    )
    .forEach((trigger) => {
      trigger.setAttribute('aria-expanded', String(expanded));
      trigger.classList.toggle('collapsed', !expanded);
    });
}

/** Ferme tous les autres éléments ouverts dans le même parent (Accordion) */
function closeParentSiblings(el: HTMLElement): void {
  const parentSelector = el.dataset.bsParent;
  if (!parentSelector) return;

  const parent = document.querySelector(parentSelector);
  if (!parent) return;

  parent.querySelectorAll<HTMLElement>('.collapse.show').forEach((sibling) => {
    if (sibling !== el) hideElement(sibling);
  });
}

// =============================================================================
// Enregistrement dans le Bridge
// =============================================================================

export class Collapse {
  static init(): void {
    const bridge = DataBsBridge.getInstance();

    bridge.registerToggle('collapse', (_trigger, target) => {
      if (!target) return;

      if (target.classList.contains('show')) {
        hideElement(target);
      } else {
        // Accord : fermer les frères avant d'ouvrir
        closeParentSiblings(target);
        showElement(target);
      }
    });
  }

  /** API publique : ouvre un élément collapse par sélecteur ou référence */
  static show(el: HTMLElement | string): void {
    const element =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (element) showElement(element);
  }

  /** API publique : ferme un élément collapse */
  static hide(el: HTMLElement | string): void {
    const element =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (element) hideElement(element);
  }

  /** API publique : bascule l'état */
  static toggle(el: HTMLElement | string): void {
    const element =
      typeof el === 'string' ? document.querySelector<HTMLElement>(el) : el;
    if (!element) return;

    if (element.classList.contains('show')) {
      hideElement(element);
    } else {
      closeParentSiblings(element);
      showElement(element);
    }
  }
}
