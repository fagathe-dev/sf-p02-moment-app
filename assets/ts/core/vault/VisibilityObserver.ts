export class VisibilityObserver {
  private onHiddenCallback: () => void;
  private isNavigating: boolean = false;

  constructor(onHiddenCallback: () => void) {
    this.onHiddenCallback = onHiddenCallback;
  }

  watch(): void {
    // Détecte si l'utilisateur quitte la page (navigation interne ou rechargement)
    window.addEventListener('beforeunload', () => {
      this.isNavigating = true;
    });

    document.addEventListener('visibilitychange', () => {
      // Si l'onglet est caché ET que ce n'est pas dû à un changement de page
      if (document.visibilityState === 'hidden' && !this.isNavigating) {
        this.onHiddenCallback();
      }
    });
  }
}