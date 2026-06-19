// =============================================================================
// components/BottomNav.ts
// Barre de navigation inférieure mobile.
//
// HTML attendu :
//   <nav class="bottom-nav bottom-nav--stacked" id="bottomNav">
//     <ul class="bottom-nav__list" role="list">
//
//       <!-- Lien ancre (défilement fluide) -->
//       <li class="bottom-nav__item">
//         <a class="bottom-nav__link active" href="#hero" aria-current="page">
//           <span class="bottom-nav__icon" aria-hidden="true"><i class="mdi mdi-home"></i></span>
//           <span class="bottom-nav__label">Accueil</span>
//         </a>
//       </li>
//
//       <!-- Lien tab Bootstrap (changement de panneau) -->
//       <li class="bottom-nav__item">
//         <a class="bottom-nav__link" data-bs-toggle="tab" data-bs-target="#my-tab" href="#my-tab">
//           <span class="bottom-nav__icon" aria-hidden="true"><i class="mdi mdi-cog"></i></span>
//           <span class="bottom-nav__label">Réglages</span>
//         </a>
//       </li>
//
//     </ul>
//   </nav>
//
// Comportement :
//   - Clic sur un lien : bascule .active + aria-current sur tous les liens du groupe.
//   - Lien avec ancre (#id) sans data-bs-toggle :
//       • preventDefault()
//       • défilement fluide vers la cible, décalé de la hauteur de la barre
//         pour qu'elle ne masque pas l'en-tête de la section.
//   - Lien avec data-bs-toggle="tab" | "list" :
//       • NE PAS appeler preventDefault() → le composant Tab.ts prend le relais
//         via DataBsBridge pour gérer le changement de panneau.
//
// Gestion de l'URL (ancres) :
//   L'identifiant de l'ancre est pushState dans l'historique pour que le
//   bouton "Précédent" du navigateur fonctionne normalement.
// =============================================================================

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Retourne tous les liens d'une même `.bottom-nav`. */
function getSiblingLinks(link: HTMLElement): HTMLElement[] {
  const nav = link.closest<HTMLElement>('.bottom-nav');
  if (!nav) return [];
  return Array.from(nav.querySelectorAll<HTMLElement>('.bottom-nav__link'));
}

/** Active un lien (classe CSS + attribut aria). */
function activateLink(link: HTMLElement): void {
  link.classList.add('active');
  link.setAttribute('aria-current', 'page');
}

/** Désactive un lien (classe CSS + attribut aria). */
function deactivateLink(link: HTMLElement): void {
  link.classList.remove('active');
  link.removeAttribute('aria-current');
}

/**
 * Défile en douceur vers `target` en compensant la hauteur de la barre fixe
 * afin que la section ne soit pas masquée par celle-ci.
 *
 * @param target  - Élément DOM vers lequel défiler.
 * @param anchorId - Identifiant (sans #) pour la mise à jour de l'URL.
 */
function scrollToTarget(target: HTMLElement, anchorId: string): void {
  const nav = document.querySelector<HTMLElement>('.bottom-nav');
  // Hauteur totale de la barre (inclut la safe-area si le CSS utilise padding-bottom)
  const offset = nav?.getBoundingClientRect().height ?? 0;
  // 8 px de respiration supplémentaire
  const top =
    target.getBoundingClientRect().top + window.scrollY - offset - 8;

  window.scrollTo({ top, behavior: 'smooth' });

  // Met à jour le hash dans l'URL sans déclencher un second scroll natif
  if (history.pushState) {
    history.pushState(null, '', `#${anchorId}`);
  }
}

// ---------------------------------------------------------------------------
// Classe principale
// ---------------------------------------------------------------------------

export class BottomNav {
  /**
   * Initialise toutes les `.bottom-nav` présentes dans le document.
   * Doit être appelé après `DOMContentLoaded`.
   */
  static init(): void {
    const navs = document.querySelectorAll<HTMLElement>('.bottom-nav');
    if (!navs.length) return;

    navs.forEach((nav) => BottomNav.bindNav(nav));
  }

  // ---------------------------------------------------------------------------
  // Liaison des événements sur une barre
  // ---------------------------------------------------------------------------

  private static bindNav(nav: HTMLElement): void {
    nav.addEventListener('click', (e: Event) => {
      const link = (e.target as HTMLElement).closest<HTMLElement>(
        '.bottom-nav__link',
      );

      // Ignore les clics hors d'un lien ou sur un lien désactivé
      if (!link || link.classList.contains('disabled')) return;

      const toggleType = link.getAttribute('data-bs-toggle');
      const isTabToggle = toggleType === 'tab' || toggleType === 'list';
      const href = link.getAttribute('href') ?? '';
      const isAnchorOnly = href.startsWith('#') && !isTabToggle;

      if (isAnchorOnly) {
        // Empêche le saut natif du navigateur (comportement saccadé)
        e.preventDefault();

        const anchorId = href.slice(1);
        const targetEl = document.getElementById(anchorId);

        if (targetEl) {
          scrollToTarget(targetEl, anchorId);
        }
      }
      // Pour data-bs-toggle="tab" : on laisse l'événement se propager
      // jusqu'au DataBsBridge (listener sur `document`) qui gère le panneau.

      // ── Mise à jour de l'état actif dans la barre ──────────────────────
      const siblings = getSiblingLinks(link);
      siblings.forEach(deactivateLink);
      activateLink(link);
    });
  }
}
