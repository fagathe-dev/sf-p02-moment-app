# Guide : Ajouter un nouvel email

## Table des matières

1. [Créer le template Twig](#1-créer-le-template-twig)
2. [Utiliser les composants](#2-utiliser-les-composants)
   - [Texte](#texte-_texthtmltwig)
   - [Bouton](#bouton-_buttonhtmltwig)
   - [Lien](#lien-_linkhtmltwig)
   - [Alerte](#alerte-_alerthtmltwig)
   - [Box](#box-_boxhtmltwig)
   - [Card](#card-_cardhtmltwig)
   - [Section](#section-_sectionhtmltwig)
   - [Liste](#liste-_listhtmltwig)
   - [Séparateur](#séparateur-_dividerhtmltwig)

---

## 1. Créer le template Twig

Placez votre fichier dans le dossier correspondant au domaine :

| Dossier | Usage |
|---|---|
| `templates/emails/auth/` | Emails liés à l'authentification |
| `templates/emails/admin/` | Emails liés à l'administration |

Le template **doit** étendre le layout global :

```twig
{% extends 'layouts/email.html.twig' %}

{% block content %}
  {# Contenu de l'email ici #}
{% endblock %}
```

Le layout fournit automatiquement : le logo, la card principale et le footer avec la typographie système (polices natives).

---

## 2. Utiliser les composants

Les composants réutilisables sont dans `templates/emails/components/`. On les intègre via `{% include %}` avec passage de variables.

Les couleurs par défaut sont câblées sur le **Design System** (texte principal `#262626`, boutons et accents `#404040`, bordures `#e5e5e5`).

> **Prop commune** : tous les composants acceptent `custom_style` (chaîne vide par défaut) pour injecter du CSS inline supplémentaire (ex : `margin-bottom: 20px; text-align: center;`).

---

### Texte (`_text.html.twig`)

```twig
{% include 'emails/components/_text.html.twig' with {
  level: 'h1',
  content: 'Mon titre',
  align: 'center'
} %}
```

| Prop | Valeurs | Défaut |
|---|---|---|
| `level` | `h1`–`h6`, `p`, `small` | — |
| `content` | string | `''` |
| `color` | hex | géré par le DS |
| `size` | string | géré par le DS |
| `align` | `left` \| `center` \| `right` | `left` |
| `custom_style` | string | `''` |

---

### Bouton (`_button.html.twig`)

```twig
{% include 'emails/components/_button.html.twig' with {
  url: actionUrl,
  label: 'Cliquer ici'
} %}
```

| Prop | Défaut |
|---|---|
| `url` | — |
| `label` | — |
| `bg_color` | `#404040` |
| `text_color` | `#ffffff` |
| `custom_style` | `''` |

---

### Lien (`_link.html.twig`)

```twig
{% include 'emails/components/_link.html.twig' with {
  url: 'https://example.com',
  label: 'Voir le site'
} %}
```

| Prop | Défaut |
|---|---|
| `url` | — |
| `label` | — |
| `color` | `#404040` |
| `custom_style` | `''` |

---

### Alerte (`_alert.html.twig`)

```twig
{% include 'emails/components/_alert.html.twig' with {
  type: 'danger',
  content: '<strong>Action requise</strong><br>Veuillez modifier votre mot de passe.'
} %}
```

| Prop | Valeurs | Défaut |
|---|---|---|
| `type` | `success` \| `danger` \| `warning` \| `info` | `info` |
| `content` | string (HTML accepté) | `''` |
| `custom_style` | string | `''` |

---

### Box (`_box.html.twig`)

```twig
{% include 'emails/components/_box.html.twig' with {
  background: '#f5f5f5',
  padding: '10px 15px',
  radius: '6px',
  content: '<p>Bloc avec fond gris neutre</p>'
} %}
```

| Prop | Défaut |
|---|---|
| `background` | `#ffffff` |
| `padding` | `16px` |
| `radius` | `4px` |
| `content` | `''` |
| `custom_style` | `''` |

---

### Card (`_card.html.twig`)

```twig
{% include 'emails/components/_card.html.twig' with {
  content: '<p>Contenu dans une card bordurée</p>'
} %}
```

| Prop | Défaut |
|---|---|
| `background` | `#ffffff` |
| `padding` | `24px` |
| `content` | `''` |
| `custom_style` | `''` |

> Différence avec `_box` : la card applique toujours une bordure (`1px solid #e5e5e5`) et un `border-radius: 8px`.

---

### Section (`_section.html.twig`)

```twig
{% include 'emails/components/_section.html.twig' with {
  content: '<p>Contenu de la section</p>',
  bg_color: '#fafafa',
  padding: '20px',
  align: 'center'
} %}
```

| Prop | Défaut |
|---|---|
| `bg_color` | transparent |
| `padding` | `0` |
| `align` | `left` |
| `content` | `''` |
| `custom_style` | `''` |

---

### Liste (`_list.html.twig`)

```twig
{% include 'emails/components/_list.html.twig' with {
  items: ['Élément 1', 'Élément 2', 'Élément 3']
} %}
```

| Prop | Défaut |
|---|---|
| `items` | tableau de strings |
| `bullet_color` | `#404040` |
| `custom_style` | `''` |

---

### Séparateur (`_divider.html.twig`)

```twig
{% include 'emails/components/_divider.html.twig' with {
  color: '#e5e5e5',
  height: '1px'
} %}
```

| Prop | Défaut |
|---|---|
| `color` | `#e5e5e5` |
| `height` | `1px` |
| `custom_style` | `''` |
