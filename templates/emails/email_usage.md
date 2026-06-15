# Guide : Ajouter un nouvel email

## 1. Créer le template Twig

Placez votre fichier dans le dossier correspondant au domaine :
- `templates/emails/auth/` — emails liés à l'authentification
- `templates/emails/admin/` — emails liés à l'administration

Le template **doit** étendre le layout global :

```twig
{% extends 'layouts/email.html.twig' %}

{% block content %}
  {# Contenu de l'email ici #}
{% endblock %}
```

Le layout fournit automatiquement : le logo, la card principale et le footer.

## 2. Utiliser les composants

Les composants réutilisables sont dans `templates/emails/components/`. On les intègre via `{% include %}` avec passage de variables.

> **Prop commune** : tous les composants acceptent `custom_style` (chaîne vide par défaut) pour injecter du CSS inline supplémentaire (ex : `margin-bottom: 20px; text-align: center;`).

### Texte (`_text.html.twig`)

```twig
{% include 'emails/components/_text.html.twig' with {
  level: 'h1',
  content: 'Mon titre',
  color: '#212529',
  align: 'center'
} %}
```

Props : `level` (h1-h6, p, small), `content`, `color`, `size`, `align`, `custom_style`.

### Bouton (`_button.html.twig`)

```twig
{% include 'emails/components/_button.html.twig' with {
  url: actionUrl,
  label: 'Cliquer ici',
  bg_color: '#0d6efd',
  text_color: '#ffffff'
} %}
```

Props : `url`, `label`, `bg_color`, `text_color`, `custom_style`.

### Alerte (`_alert.html.twig`)

```twig
{% include 'emails/components/_alert.html.twig' with {
  type: 'warning',
  content: 'Attention, ce lien expire dans <strong>24h</strong>.'
} %}
```

Props : `type` (success, danger, info), `content`, `custom_style`.

### Séparateur (`_divider.html.twig`)

```twig
{% include 'emails/components/_divider.html.twig' %}
```

Props optionnelles : `color`, `height`, `custom_style`.

### Card (`_card.html.twig`)

```twig
{% include 'emails/components/_card.html.twig' with {
  content: '<p>Contenu HTML de la card</p>',
  padding: '20px'
} %}
```

Props : `content`, `background`, `padding`, `custom_style`.

### Box (`_box.html.twig`)

```twig
{% include 'emails/components/_box.html.twig' with {
  background: '#f8f9fa',
  padding: '10px 15px',
  content: '<p>Bloc avec fond coloré</p>'
} %}
```

Props : `background`, `padding`, `radius`, `content`, `custom_style`.

### Lien (`_link.html.twig`)

```twig
{% include 'emails/components/_link.html.twig' with {
  url: 'https://example.com',
  label: 'Voir le site',
  color: '#0066ff'
} %}
```

Props : `url`, `label`, `color`, `custom_style`.

### Liste (`_list.html.twig`)

```twig
{% include 'emails/components/_list.html.twig' with {
  items: ['Élément 1', 'Élément 2', 'Élément 3'],
  bullet_color: '#4b38b3'
} %}
```

Props : `items` (tableau), `bullet_color`, `custom_style`.

### Section (`_section.html.twig`)

```twig
{% include 'emails/components/_section.html.twig' with {
  content: '<p>Contenu de la section</p>',
  bg_color: '#f8f9fa',
  padding: '20px',
  align: 'center'
} %}
```

Props : `bg_color`, `padding`, `content`, `align`, `custom_style`.

## 3. Enregistrer l'email côté PHP

1. Ajouter un case dans `src/Utils/Mailer/Enum/EmailTypeEnum.php`.  
   La valeur doit correspondre au chemin du template : `domaine.nom-du-fichier`  
   (les `.` deviennent `/` et les `_` deviennent `-` pour résoudre le template).

2. Ajouter le mock correspondant dans `src/Utils/Mailer/Service/EmailMockFactory.php` pour la prévisualisation.

3. Créer la classe d'email dans `src/Emails/<Domaine>/` en étendant `AbstractEmail`.
