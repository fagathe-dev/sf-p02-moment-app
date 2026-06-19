// =============================================================================
// components/Alert.ts
// Gestion du dismiss d'une alerte : data-bs-dismiss="alert"
//
// HTML attendu :
//   <div class="alert alert-dismissible fade show" role="alert">
//     ...
//     <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
//   </div>
//
// Événements dispatché :
//   close.bs.alert  — annulable (preventDefault() bloque la fermeture)
//   closed.bs.alert — après suppression du DOM
// =============================================================================

import { DataBsBridge } from '@/core/DataBsBridge';

export class Alert {
  static init(): void {
    const bridge = DataBsBridge.getInstance();

    bridge.registerDismiss('alert', (_trigger, component) => {
      if (!component) return;

      // Événement annulable
      const closeEvent = DataBsBridge.dispatch(component, 'close.bs.alert');
      if (closeEvent.defaultPrevented) return;

      // Si l'alerte a la classe .fade, animer avant suppression
      if (component.classList.contains('fade')) {
        component.classList.remove('show');

        const onTransitionEnd = () => {
          component.removeEventListener('transitionend', onTransitionEnd);
          component.remove();
          DataBsBridge.dispatch(component, 'closed.bs.alert');
        };

        component.addEventListener('transitionend', onTransitionEnd, {
          once: true,
        });

        // Fallback si pas de transition CSS
        setTimeout(() => {
          if (document.contains(component)) {
            component.remove();
            DataBsBridge.dispatch(component, 'closed.bs.alert');
          }
        }, 300);
      } else {
        component.remove();
        DataBsBridge.dispatch(component, 'closed.bs.alert');
      }
    });
  }
}
