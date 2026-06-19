// =============================================================================
// components/TopButton.ts
// Bouton "Retour en haut de page".
//
// HTML attendu :
//   <button class="top-button" id="top-button" type="button"
//           aria-label="Retour en haut de page" hidden>
//     <i class="ri-arrow-up-line" aria-hidden="true"></i>
//   </button>
//
// Comportement :
//   - Le bouton est masqué par défaut (attribut `hidden` + CSS opacity/visibility).
//   - Il apparaît après un défilement de SCROLL_THRESHOLD px depuis le haut.
//   - Un clic déclenche un défilement fluide (smooth) vers le haut de la page.
//   - Le listener de scroll utilise `requestAnimationFrame` pour éviter les
//     appels excessifs pendant le défilement (throttling léger).
// =============================================================================

/** Seuil de scroll (px) à partir duquel le bouton devient visible. */
const SCROLL_THRESHOLD = 300;

export class TopButton {
  private readonly el: HTMLButtonElement;
  private rafPending = false;

  private constructor(el: HTMLButtonElement) {
    this.el = el;
    this.bindEvents();
  }

  /**
   * Initialise le TopButton si l'élément #top-button est présent dans le DOM.
   * Doit être appelé après `DOMContentLoaded`.
   */
  static init(): void {
    const el = document.querySelector<HTMLButtonElement>('#top-button');
    if (!el) return;

    new TopButton(el);
  }

  // ---------------------------------------------------------------------------
  // Liaison des événements
  // ---------------------------------------------------------------------------

  private bindEvents(): void {
    window.addEventListener('scroll', this.onScroll, { passive: true });
    this.el.addEventListener('click', this.scrollToTop);
  }

  // ---------------------------------------------------------------------------
  // Gestionnaires d'événements
  // ---------------------------------------------------------------------------

  private readonly onScroll = (): void => {
    if (this.rafPending) return;
    this.rafPending = true;

    requestAnimationFrame(() => {
      this.rafPending = false;
      this.toggle(window.scrollY >= SCROLL_THRESHOLD);
    });
  };

  private readonly scrollToTop = (): void => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // ---------------------------------------------------------------------------
  // Affichage / masquage
  // ---------------------------------------------------------------------------

  private toggle(visible: boolean): void {
    if (visible) {
      this.el.removeAttribute('hidden');
      // Délai d'une frame pour que le navigateur applique `display` avant la transition
      requestAnimationFrame(() => {
        this.el.classList.add('top-button--visible');
      });
    } else {
      this.el.classList.remove('top-button--visible');
      this.el.setAttribute('hidden', '');
    }
  }
}
