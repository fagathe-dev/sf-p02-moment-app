import { convertMarkdownToHtml, $ } from 'core-ts';

const initMarkdownPreview = (
  context: HTMLElement | Document = document,
): void => {
  // On récupère tous les éditeurs présents dans le contexte
  const wrappers = $<HTMLElement>(
    '.markdown-editor-wrapper',
    true,
    context,
  ) as NodeListOf<HTMLElement> | null;

  if (!wrappers) return;

  wrappers.forEach((wrapper) => {
    // Éléments interactifs spécifiques à ce wrapper
    const tabButtons =
      wrapper.querySelectorAll<HTMLButtonElement>('.js-md-tab');
    const tabPanes = wrapper.querySelectorAll<HTMLElement>('.js-md-pane');
    const textarea =
      wrapper.querySelector<HTMLTextAreaElement>('.js-md-textarea');
    const previewContainer = wrapper.querySelector<HTMLElement>(
      '.js-md-preview-container',
    );

    tabButtons.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();

        // 1. Basculement des états des boutons d'onglets
        tabButtons.forEach((b) => {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        // 2. Basculement de l'affichage des panneaux
        const targetId = btn.getAttribute('data-md-target');
        tabPanes.forEach((pane) => {
          if (pane.id === targetId) {
            pane.classList.add('show', 'active');
          } else {
            pane.classList.remove('show', 'active');
          }
        });

        // 3. Rendu Markdown si l'onglet activé est l'Aperçu
        if (
          btn.classList.contains('js-md-preview-tab') &&
          textarea &&
          previewContainer
        ) {
          const rawMarkdown = textarea.value.trim();

          if (rawMarkdown === '') {
            previewContainer.innerHTML =
              '<em class="text-muted">Rien à prévisualiser.</em>';
          } else {
            previewContainer.innerHTML = convertMarkdownToHtml(rawMarkdown);
          }
        }
      });
    });
  });
};

export { initMarkdownPreview };
