import { CustomSelector } from '@/core/forms/custom-selector';
import { $ } from 'core-ts';

document.addEventListener('DOMContentLoaded', (): void => {
  const dropdownSelectContainers = $<HTMLElement>(
    '.js-dropdown-select',
    true,
  ) as NodeListOf<HTMLElement> | null;

  if (!dropdownSelectContainers) {
    return;
  }

  dropdownSelectContainers.forEach((container: HTMLElement): void => {
    const mode =
      (container.getAttribute('data-dcs-mode') as any) || 'single-nullable';

    const placeholder =
      container.getAttribute('data-dcs-placeholder') || 'Sélectionner...';

    new CustomSelector(container, { mode, placeholder });
  });
});
