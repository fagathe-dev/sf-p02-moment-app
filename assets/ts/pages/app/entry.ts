
import { convertMarkdownToHtml, $ } from 'core-ts';
 
// ─────────────────────────────────────────────────────────────────────────────
// Vue détail d'une entrée — rendu Markdown
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
 
function renderMarkdownContent(): void {
  const container = $<HTMLElement>(
    '[data-entry-md-content]',
    false,
  ) as HTMLElement | null;
 
  if (!container) return;
 
  const raw = container.getAttribute('data-entry-md-content') ?? '';
 
  if (!raw.trim()) return;
 
  container.innerHTML = convertMarkdownToHtml(raw);
  container.removeAttribute('data-entry-md-content');
}