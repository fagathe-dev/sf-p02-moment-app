// =============================================================================
// main.ts — Point d'entrée du Design System
//
// Ordre d'initialisation :
//   1. DataBsBridge.init() — dispatcher central data-bs-*
//   2. Composants          — chacun s'enregistre dans le Bridge
// =============================================================================

import { DataBsBridge } from './core/DataBsBridge';
import {
  Alert,
  BottomNav,
  Collapse,
  Dropdown,
  Modal,
  Tab,
  Toast,
  TopButton,
} from './components';

document.addEventListener('DOMContentLoaded', () => {
  // 1. Initialise le dispatcher central (un seul listener délégué sur document)
  DataBsBridge.getInstance().init();

  // 2. Initialise les composants interactifs
  Alert.init();
  BottomNav.init();
  Collapse.init();
  Dropdown.init();
  Modal.init();
  Tab.init();
  Toast.init();
  TopButton.init();
});
