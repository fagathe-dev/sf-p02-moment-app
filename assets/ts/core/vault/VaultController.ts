import { fetchAPI, ApiError, router } from 'core-ts';
import { ROUTES } from '@/constants';
import { VaultStorage } from './VaultStorage';
import { VisibilityObserver } from './VisibilityObserver';

export class VaultController {
  constructor(
    private storage: VaultStorage,
    private visibilityObserver: VisibilityObserver,
    private uiElement: HTMLElement, // Conteneur UI de la modale de code
  ) {
    this.init();
  }

  private init(): void {
    // Empêche la fermeture de la modale native avec la touche "Échap"
    if (this.uiElement instanceof HTMLDialogElement) {
      this.uiElement.addEventListener('cancel', (e) => e.preventDefault());
    }

    // Verrouille l'espace intime dès que l'onglet n'est plus actif
    this.visibilityObserver = new VisibilityObserver(() => this.lockVault());
    this.visibilityObserver.watch();

    // Au montage, si le storage possède déjà le base64, on vérifie sa validité silencieusement
    if (this.storage.isUnlocked()) {
      this.verifyVault();
    } else {
      this.renderLockedView();
    }
  }

  /**
   * Déverrouille le vault après saisie du code PIN
   */
  public async unlockVault(pinCode: string): Promise<void> {
    try {
      const response = await fetchAPI<{ token: string }>(
        router(ROUTES.API.VAULT.CHECK),
        {
          method: 'POST',
          body: { pin: pinCode } as unknown as BodyInit,
        },
      );

      // On stocke le jeton encodé en base64 retourné par Symfony
      this.storage.saveToken(response.data.token);
      this.renderUnlockedView();
    } catch (error) {
      if (error instanceof ApiError) {
        this.showErrorView(error.getErrorMessage() || 'Code invalide');
      } else {
        this.showErrorView('Une erreur réseau est survenue');
      }
    }
  }

  /**
   * Vérifie la validité du token courant auprès du back-end
   */
  public async verifyVault(): Promise<void> {
    const token = this.storage.getToken();

    if (!token) {
      this.lockVault();
      return;
    }

    try {
      await fetchAPI(router(ROUTES.API.VAULT.VERIFY), {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      // Si la requête HTTP renvoie 200 (OK), la session est valide
      this.renderUnlockedView();
    } catch (error) {
      // Si la vérification échoue (ex: 403), le token est corrompu ou expiré
      this.lockVault();
    }
  }

  public lockVault(): void {
    this.storage.lock();
    this.renderLockedView();
  }

  private renderUnlockedView(): void {
    // On conserve les classes pour le MutationObserver dans vault.ts
    this.uiElement.classList.remove('is-error');
    this.uiElement.classList.add('is-unlocked');

    // Fermeture de la modale native <dialog>
    const dialog = this.uiElement as HTMLDialogElement;
    if (dialog.open) {
      dialog.close();
    }
  }

  private renderLockedView(): void {
    this.uiElement.classList.remove('is-unlocked');

    // Ouverture de la modale native <dialog> qui fige l'écran avec son blur
    const dialog = this.uiElement as HTMLDialogElement;
    if (!dialog.open) {
      dialog.showModal();
    }
  }

  private showErrorView(message: string): void {
    this.uiElement.classList.add('is-error');
    console.error('Vault Lock Error:', message);
  }
}
