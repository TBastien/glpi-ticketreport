# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [1.0.2] - 2026-11-10

### Security
- Restrict the report endpoint and button to the central (technician) interface:
  in the helpdesk interface, a Self-Service user's CREATE right satisfies
  `Ticket::canUpdateItem()`, so a requester could previously send a report to
  arbitrary addresses without limit. The endpoint now requires
  `Session::getCurrentInterface() === 'central'` plus `Ticket::canUpdateItem()`.
- Add a per-user send rate limit (20 sends/hour) backed by a dedicated table.

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
