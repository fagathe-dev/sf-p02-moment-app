import { convertMarkdownToHtml, $ } from 'core-ts';

// ─────────────────────────────────────────────────────────────────────────────
// Vue détail / Feed — rendu Markdown
//
// Principe :
//   Le Twig injecte le contenu brut dans data-entry-md-content (attribut HTML).
//   Au chargement, on parse le Markdown via convertMarkdownToHtml() de core-ts,
//   on injecte le HTML résultant comme innerHTML, puis on retire l'attribut
//   pour ne pas laisser le Markdown brut lisible dans le DOM.
// ─────────────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', (): void => {
  renderMarkdownContent();
});

export function renderMarkdownContent(context: Document | HTMLElement = document): void {
  // 🟢 On passe "true" pour récupérer une NodeList (toutes les cartes)
  const containers = $<HTMLElement>(
    '[data-entry-md-content]',
    true,
    context
  ) as NodeListOf<HTMLElement> | null;

  console.log('renderMarkdownContent', containers);

  if (!containers) return;

  containers.forEach((container) => {
    const raw = container.getAttribute('data-entry-md-content') ?? '';

    if (raw.trim()) {
      container.innerHTML = convertMarkdownToHtml(raw);
    }
    
    // On nettoie l'attribut
    container.removeAttribute('data-entry-md-content');
  });
}