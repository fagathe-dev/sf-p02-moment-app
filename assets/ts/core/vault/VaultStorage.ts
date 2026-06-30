import { StorageService } from 'core-ts'; //[cite: 3]

export class VaultStorage {
  public readonly STORAGE_KEY = 'ffr_vtx.v4'; //[cite: 3]
  private storage: StorageService;

  constructor() {
    // Initialisation du service pour utiliser spécifiquement le sessionStorage[cite: 3]
    this.storage = new StorageService('session'); //[cite: 3]
  }

  saveToken(token: string): void {
    // Stocke la valeur via la méthode set du service[cite: 3]
    this.storage.set(this.STORAGE_KEY, token); //[cite: 3]
  }

  getToken(): string | null {
    // Lit et désérialise automatiquement la valeur[cite: 3]
    // Comme c'est une chaîne de caractères simple, la chaîne brute sera retournée[cite: 3]
    return this.storage.get(this.STORAGE_KEY) as string | null; //[cite: 3]
  }

  lock(): void {
    // Supprime l'élément du stockage pour verrouiller l'espace intime[cite: 3]
    this.storage.remove(this.STORAGE_KEY); //[cite: 3]
  }

  isUnlocked(): boolean {
    return this.getToken() !== null;
  }
}
