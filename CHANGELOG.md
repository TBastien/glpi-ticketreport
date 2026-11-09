# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [1.0.1] - 2026-11-09

### Security
- Check the ticket update right on the report endpoint before GET or POST processing.
- Limit each send request to ten recipient entries, including direct mailer calls.

### Fixed
- Remove the four plugin configuration keys during uninstall.
- Remove the broken screenshot link from the marketplace descriptor.
- Escape displayed translations in forms and correct the plugin description header.

## [1.0.0] - 2026-11-07

### Added
- Bouton « Envoyer le rapport par email » dans la fiche ticket.
- Formulaire de préparation : sections cochables + destinataires éditables (À/CC).
- Rapport HTML aux couleurs GLPI (description, suivis, tâches, solution, hors privés, ordre chronologique).
- Page de configuration des sections.
- Traductions anglais (en_GB) et français (fr_FR).
