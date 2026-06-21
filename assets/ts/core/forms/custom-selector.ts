import { $ } from 'core-ts';

export type SelectorMode =
  | 'single'
  | 'single-nullable'
  | 'multiple'
  | 'multiple-nullable';

export interface CustomSelectorOptions {
  mode: SelectorMode;
  placeholder?: string;
  onChange?: (values: string[]) => void;
}

export class CustomSelector {
  private nativeSelect: HTMLSelectElement | null;
  private buttonContent: HTMLElement | null;
  private options: NodeListOf<HTMLElement> | null;
  private searchInput: HTMLInputElement | null;

  private selectedValues: Set<string> = new Set();

  constructor(
    private container: HTMLElement,
    private config: CustomSelectorOptions,
  ) {
    this.nativeSelect = $<HTMLSelectElement>(
      'select',
      false,
      container,
    ) as HTMLSelectElement | null;
    this.buttonContent = $<HTMLElement>(
      '.js-selector-button-content',
      false,
      container,
    ) as HTMLElement | null;
    this.options = $<HTMLElement>(
      '.js-selector-option',
      true,
      container,
    ) as NodeListOf<HTMLElement> | null;
    this.searchInput = $<HTMLInputElement>(
      '.js-dropdown-search',
      false,
      container,
    ) as HTMLInputElement | null;

    if (!this.nativeSelect || !this.options) {
      console.warn(
        "CustomSelector: Éléments manquants pour l'initialisation",
        container,
      );
      return;
    }

    this.init();
  }

  private init(): void {
    Array.from(this.nativeSelect!.options).forEach((opt) => {
      if (opt.selected) this.selectedValues.add(opt.value);
    });

    this.options!.forEach((option) => {
      option.addEventListener('click', (e) => {
        e.preventDefault();

        if (this.config.mode.includes('multiple')) {
          e.stopPropagation();
        }

        const value = option.getAttribute('data-value');
        if (value) this.handleSelection(value);
      });
    });

    if (this.searchInput) {
      this.searchInput.addEventListener('input', (e) => {
        const query = (e.target as HTMLInputElement).value.toLowerCase().trim();
        this.handleSearch(query);
      });
    }

    this.render();
  }

  /**
   * Permet de forcer une sélection depuis l'extérieur (ex: ouverture de modale)
   */
  public setValues(values: string[]): void {
    this.selectedValues = new Set(values);
    this.syncNativeSelect();
    this.render();
  }

  private handleSelection(value: string): void {
    const isAlreadySelected = this.selectedValues.has(value);

    switch (this.config.mode) {
      case 'single':
        if (isAlreadySelected) return;
        this.selectedValues.clear();
        this.selectedValues.add(value);
        break;

      case 'single-nullable':
        this.selectedValues.clear();
        if (!isAlreadySelected) this.selectedValues.add(value);
        break;

      case 'multiple':
        if (isAlreadySelected) {
          if (this.selectedValues.size > 1) {
            this.selectedValues.delete(value);
          }
        } else {
          this.selectedValues.add(value);
        }
        break;

      case 'multiple-nullable':
        if (isAlreadySelected) {
          this.selectedValues.delete(value);
        } else {
          this.selectedValues.add(value);
        }
        break;
    }

    this.syncNativeSelect();
    this.render();

    if (this.config.onChange) {
      this.config.onChange(Array.from(this.selectedValues));
    }
  }

  private handleSearch(query: string): void {
    if (!this.options) return;

    this.options.forEach((option) => {
      const textContent = option.textContent?.toLowerCase() || '';
      if (textContent.includes(query)) {
        option.classList.remove('d-none');
        option.classList.add('d-flex');
      } else {
        option.classList.remove('d-flex');
        option.classList.add('d-none');
      }
    });
  }

  private render(): void {
    if (!this.options) return;

    this.options.forEach((option) => {
      const value = option.getAttribute('data-value');
      const isSelected = value ? this.selectedValues.has(value) : false;
      option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });

    if (this.buttonContent) {
      const buttonTextSpan = $<HTMLElement>(
        'span:first-child',
        false,
        this.buttonContent,
      ) as HTMLElement | null;
      if (!buttonTextSpan) return;

      if (this.selectedValues.size === 0) {
        buttonTextSpan.textContent =
          this.config.placeholder || 'Sélectionner...';
        buttonTextSpan.className = 'text-muted';
        return;
      }

      if (this.config.mode.startsWith('single')) {
        const selectedValue = Array.from(this.selectedValues)[0];
        const activeOption = Array.from(this.options).find(
          (opt) => opt.getAttribute('data-value') === selectedValue,
        );
        const titleElement = activeOption?.querySelector('.tag-name');
        buttonTextSpan.textContent =
          titleElement?.textContent?.trim() || '1 élément';
        buttonTextSpan.className = 'text-body fw-medium';
      } else {
        buttonTextSpan.textContent = `${this.selectedValues.size} étiquette(s) sélectionnée(s)`;
        buttonTextSpan.className = 'text-body fw-medium';
      }
    }
  }

  private syncNativeSelect(): void {
    if (!this.nativeSelect) return;

    let hasChanged = false;
    Array.from(this.nativeSelect.options).forEach((opt) => {
      const shouldBeSelected = this.selectedValues.has(opt.value);
      if (opt.selected !== shouldBeSelected) {
        opt.selected = shouldBeSelected;
        hasChanged = true;
      }
    });

    if (hasChanged) {
      this.nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }
}
