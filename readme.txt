=== NBD Suite ===
Contributors: nahimsalami
Author: Nahim Salami
Author URI: https://ahime.net
Tags: masterclass, course, events, catalog, elementor, naturebiodental
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later

Suite tout-en-un pour NatureBioDental : formations, masterclass, produits, événements, catalogue. Éditeur visuel complet, édition WordPress + Elementor.

== Description ==

Plugin sur-mesure pour le site du Dr Catherine ROSSI.

= Modules =
* 🎓 Formations
* 📺 Masterclass
* 🛒 Autres produits
* 📅 Événements (agenda automatique)
* 📋 Page catalogue avec 4 layouts au choix

= Fonctionnalités principales =
* Tableau de bord avec stats et raccourcis
* Création automatique de pages WordPress (éditables avec WordPress ou Elementor)
* Card sticky d'achat (reste visible au scroll)
* Éditeur visuel complet (TinyMCE) avec couleurs, médias, tableaux, balises H1-H6
* Page catalogue 4 layouts : Sections, Onglets, Filtres pills, Combiné
* Agenda événements avec filtrage automatique à venir/passés
* Schema.org Course / Product / Event
* Synchronisation Yoast SEO / Rank Math
* Open Graph (Facebook / LinkedIn / Twitter)
* Widgets Elementor : Card sticky, Grille, Hero événement, Grille événements
* Mode édition intelligent : modifier les métas ne casse pas le contenu

== Installation ==

1. WP Admin > Extensions > Ajouter > Téléverser
2. Choisir nbd-masterclass.zip > Installer > Activer
3. Le menu "🦷 NBD Suite" apparaît dans la sidebar admin

== Première utilisation ==

1. 🦷 NBD Suite → Tableau de bord (vue d'ensemble)
2. Créer un produit via 🎓 Formations / 📺 Masterclass / 🛒 Autres produits
3. Créer un événement via 📅 Événements
4. Configurer le catalogue via 📋 Page catalogue (choix layout + textes)

== Shortcodes ==

* `[nbd_catalog]` — Catalogue 3 sections (layout configuré en admin)
* `[nbd_catalog layout="A|B|C|D"]` — Forcer un layout précis
* `[nbd_mc_grid]` — Grille de masterclass avec filtres
* `[nbd_mc_sticky_card]` — Card sticky d'achat
* `[nbd_mc_title]` — Titre dynamique
* `[nbd_mc_short_description]` — Description courte
* `[nbd_mc_learnings]` — Liste "Ce que vous allez apprendre"
* `[nbd_mc_included]` — Liste "Ce qui est inclus"
* `[nbd_mc_trainer]` — Bloc formateur
* `[nbd_event_next_featured]` — Hero du prochain événement à la une
* `[nbd_event_hero id="123"]` — Hero d'un événement précis
* `[nbd_events_upcoming]` — Grille des prochains événements
* `[nbd_events_archive]` — Page actualité complète

== Widgets Elementor ==

Catégorie "NBD Masterclass" :
* Card achat Masterclass — Card sticky d'achat
* Grille Masterclass — Grille de produits
* Prochain événement (hero) — Hero événement pour la home
* Grille événements — Liste ou archive d'événements

== Changelog ==

= 1.0 =
* Première version stable
* Modules : Formations, Masterclass, Autres produits, Événements, Catalogue
* Tableau de bord avec stats, actions rapides et liste des shortcodes
* Éditeur visuel TinyMCE complet (couleurs, tableaux, balises H1-H6, médias)
* 4 layouts de catalogue activables (Sections / Onglets / Filtres / Combiné)
* Édition WordPress ET Elementor (au choix par page)
* Card sticky d'achat sur les pages produits
* Agenda événements avec filtrage automatique par date
* Schema.org Course / Product / Event
* Sync Yoast / Rank Math + Open Graph
* Mode édition intelligent (métas séparées du contenu)
