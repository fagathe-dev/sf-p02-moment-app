// =============================================================================
// core/types.ts
// Types partagés du Design System TypeScript.
// =============================================================================

/** Handler appelé lorsqu'un [data-bs-toggle="<type>"] est cliqué */
export type BsToggleHandler = (
  trigger: HTMLElement,
  target: HTMLElement | null,
  event: MouseEvent,
) => void;

/** Handler appelé lorsqu'un [data-bs-dismiss="<type>"] est cliqué */
export type BsDismissHandler = (
  trigger: HTMLElement,
  component: HTMLElement | null,
  event: MouseEvent,
) => void;

/** Handler global clavier (ex : Escape pour fermer le composant actif) */
export type BsKeyHandler = (event: KeyboardEvent) => void;
