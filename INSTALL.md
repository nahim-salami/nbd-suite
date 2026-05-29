# 🚀 Installation du plugin NBD Suite

> **v1.3.1** — Suite tout-en-un pour NatureBioDental : Masterclass + Événements, édition WordPress + Elementor.


## Étape 1 — Préparer le ZIP

Depuis le dossier `nbd-masterclass-plugin/`, créez un fichier `nbd-masterclass.zip` qui contient tout le dossier.

**Sur Windows (PowerShell) :**
```powershell
Compress-Archive -Path "C:\Users\nahim\OneDrive\Documents\GitHub\nbd\nbd-masterclass-plugin\*" -DestinationPath "C:\Users\nahim\OneDrive\Documents\GitHub\nbd\nbd-masterclass.zip"
```

**Sur Mac/Linux :**
```bash
cd /chemin/vers/nbd
zip -r nbd-masterclass.zip nbd-masterclass-plugin/
```

## Étape 2 — Installation sur WordPress

1. Connectez-vous à `https://naturebiodental-pro.com/wp-admin`
2. Allez dans **Extensions → Ajouter → Téléverser une extension**
3. Sélectionnez `nbd-masterclass.zip`
4. Cliquez **Installer maintenant** puis **Activer**

Un nouveau menu **"Masterclass NBD"** apparaît dans la sidebar.

## Étape 3 — Première masterclass

1. **Masterclass NBD → Créer une masterclass**
2. Remplir :
   - Titre (ex : "La Médecine Traditionnelle Chinoise en odontostomatologie")
   - Image principale (1200×675)
   - Image card (480×360)
   - Prix barré + prix actuel + devise
   - URL System.io
   - "Ce que vous allez apprendre" (liste)
   - "Ce qui est inclus" (liste)
   - Formateur, SEO, badges
3. Cliquer **✨ Créer la masterclass**
4. La page WordPress est créée avec Elementor pré-monté
5. Cliquer **Éditer dans Elementor** pour personnaliser

## Étape 4 — Page archive

1. **Masterclass NBD → Page archive (grille)**
2. Cliquer **Créer la page archive**
3. La page est disponible à `https://naturebiodental-pro.com/masterclass/`
4. Modifiable dans Elementor

## ✅ Vérifications post-installation

- [ ] Le menu "Masterclass NBD" apparaît dans l'admin
- [ ] Création d'une page test → la page apparaît dans **Pages**
- [ ] La page créée est éditable dans Elementor
- [ ] La card sticky reste visible au scroll
- [ ] Le bouton "Voir plus" redirige vers System.io
- [ ] La page archive `/masterclass/` affiche la grille
- [ ] Schema.org : vérifier avec [Rich Results Test](https://search.google.com/test/rich-results)
- [ ] Open Graph : vérifier avec [Facebook Debugger](https://developers.facebook.com/tools/debug/)

## 📅 Module Événements

### Créer un événement

1. **Masterclass NBD → Tous les événements → + Ajouter**
2. Remplir : titre, image, date, heure, lieu, type, format, rôle du Dr, URL d'inscription
3. Cocher **⭐ À la une** si vous voulez l'afficher dans le hero de la page d'accueil
4. **Créer l'événement** → page WordPress générée, éditable dans Elementor

### Afficher les événements sur la home

Insérez dans Elementor le widget **"Prochain événement (hero)"** OU le shortcode :
```
[nbd_event_next_featured]
```
→ Affiche le prochain événement marqué "à la une", ou à défaut le prochain événement.

### Afficher les événements sur la page Actualité

Insérez le widget **"Grille événements"** (mode "Archive complète") OU le shortcode :
```
[nbd_events_archive show_past="1" show_filters="1"]
```
→ Affiche les événements à venir + passés avec filtres par type.

### Shortcodes disponibles

| Shortcode | Usage |
|---|---|
| `[nbd_event_next_featured]` | Hero du prochain événement (auto) |
| `[nbd_event_hero id="123"]` | Hero d'un événement spécifique |
| `[nbd_events_upcoming limit="4" columns="3"]` | Grille des prochains événements |
| `[nbd_events_archive show_past="1" show_filters="1"]` | Page actualité complète |

### Filtrage automatique

Le plugin filtre **automatiquement** les événements par date : seuls les événements dont la date de fin (ou date de début si pas de fin) est ≥ aujourd'hui apparaissent dans la section "À venir". Les autres basculent automatiquement dans "Passés".

**Aucune action manuelle requise** quand un événement est passé.

### URLs des événements

Chaque événement a sa propre page à `/evenements/{slug-evenement}/`.

## 🛠️ Personnalisation post-création

### Modifier une masterclass
- **Méta-données** (prix, image, URL) : Masterclass NBD → Liste → Modifier
- **Contenu visuel** (textes, sections, design) : Pages → la page → Modifier avec Elementor

### Modifier le design global
Le CSS est dans `wp-content/plugins/nbd-masterclass-plugin/assets/css/frontend.css`.
Variables CSS principales (à modifier en haut du fichier) :
- `--violet-primary: #6B2C91`
- `--violet-dark: #4A1D66`

## 🐛 Dépannage

**La card sticky ne reste pas fixe au scroll :**
→ Vérifier qu'Elementor Pro est actif et que la colonne droite a "Sticky → Top" dans Avancé → Effets de mouvement.

**Le menu "Masterclass NBD" n'apparaît pas :**
→ Aller dans Extensions, vérifier que "NBD Masterclass" est bien activé.

**Erreur PHP à l'activation :**
→ Vérifier que PHP ≥ 7.4 et WordPress ≥ 6.0.

**Les shortcodes affichent du texte au lieu du rendu :**
→ Vider le cache du site (LiteSpeed, WP Rocket...).

## 📁 Structure des fichiers

```
nbd-masterclass-plugin/
├── nbd-masterclass.php          # Fichier principal du plugin
├── readme.txt                    # Métadonnées WordPress
├── INSTALL.md                    # Ce fichier
├── includes/
│   ├── class-meta-fields.php    # Définition des champs personnalisés
│   ├── class-admin.php          # Menu + formulaires admin
│   ├── class-page-builder.php   # Génération Elementor JSON
│   ├── class-shortcodes.php     # Tous les shortcodes [nbd_mc_*]
│   ├── class-elementor.php      # Hook Elementor
│   ├── class-schema.php         # Schema.org JSON-LD
│   ├── class-seo.php            # Yoast/Rank Math/OG
│   └── widgets/
│       ├── widget-sticky-card.php  # Widget Elementor "Card sticky"
│       └── widget-grid.php         # Widget Elementor "Grille"
└── assets/
    ├── css/
    │   ├── frontend.css         # Styles visibles sur le site
    │   └── admin.css            # Styles du panneau admin
    └── js/
        ├── frontend.js          # Filtres archive
        └── admin.js             # Media uploader + repeaters
```
