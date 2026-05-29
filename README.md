# NBD Suite

Suite tout-en-un pour **NatureBioDental** : gestion des masterclass (avec card sticky d'achat) et des événements (agenda automatique). Édition WordPress + Elementor, SEO et schema.org intégrés.

- **Auteur** : Nahim Salami — [ahime.net](https://ahime.net)
- **Version actuelle** : 1.0.3
- **Licence** : Propriétaire / NatureBioDental
- **PHP** : ≥ 7.4
- **WordPress** : ≥ 6.0

## Fonctionnalités

### Module Masterclass / Formations / Produits
- Pages produit avec image hero + card sticky d'achat (style System.io)
- 3 types : Formation, Masterclass, Autre produit
- Catégories cross-filtering (pro-santé, dentiste, etc.)
- Sections riches : Description, Apprentissages, Formateur, Modules, Bonus, Inclus, Vidéos, Témoignages
- Édition WordPress (Gutenberg) **OU** Elementor au choix
- Auto-régénération du contenu à la sauvegarde
- Backup automatique avant chaque régénération
- Page catalogue avec 4 layouts au choix

### Module Événements
- CPT dédié avec agenda automatique
- Widgets Elementor : Hero, Grid
- Shortcodes pour intégration libre
- Schema.org Event

### Outils
- Dashboard avec stats + shortcodes copiables
- Bouton "Modifier" frontend (admin)
- Régénération en lot depuis le dashboard / réglages
- Hide automatique du titre Hello Elementor

## Installation

1. Téléchargez le ZIP depuis la dernière [release](../../releases/latest)
2. WordPress > Extensions > Ajouter > Téléverser
3. Activez le plugin
4. Menu **🦷 NBD Suite** dans la sidebar admin

## Structure

```
nbd-masterclass-plugin/
├── nbd-masterclass.php          # Bootstrap, hooks globaux, CSS/JS forcé
├── includes/
│   ├── class-meta-fields.php    # Définition des méta + sanitisation
│   ├── class-admin.php          # Menu, dashboard, formulaire d'édition
│   ├── class-page-builder.php   # Génération contenu Gutenberg + Elementor
│   ├── class-shortcodes.php     # Tous les [nbd_mc_*]
│   ├── class-schema.php         # JSON-LD Product/Course
│   ├── class-seo.php            # OpenGraph, meta description
│   ├── class-elementor.php      # Intégration widgets Elementor
│   ├── class-edit-bar.php       # Bouton "Modifier" frontend
│   └── events/                  # Module Événements (4 classes)
├── assets/
│   ├── css/frontend.css         # Styles publics
│   ├── css/admin.css            # Styles admin
│   └── js/admin.js              # Repeaters, media uploader, etc.
└── templates/                   # Templates de fallback
```

## Changelog

Voir les [releases](../../releases) pour le détail version par version.

## Support

Pour signaler un bug ou suggérer une amélioration, [ouvre une issue](../../issues).
