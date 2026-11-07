# Ticket Report — plugin GLPI

Bouton **« Envoyer le rapport par email »** dans la fiche d'un ticket.

En un clic, envoie **directement au demandeur** un **email HTML** (aux couleurs de GLPI)
contenant :

- le ticket (titre + statut) ;
- la description ;
- les suivis, tâches et solution (en option), **fusionnés par ordre chronologique**,
  **hors éléments privés** (`is_private`).

Les sections incluses sont **configurables** (cases à cocher).

## Prérequis

- GLPI **11.x**.
- **Envoi d'emails configuré** dans GLPI : `Configuration > Notifications`
  (émetteur + SMTP valides), sinon l'envoi échouera.

## Installation

1. Copier le dossier `ticketreport` dans `plugins/` de l'installation GLPI.
2. **Configuration > Plugins** → « Ticket Report » → **Installer** puis **Activer**.

## Configuration

**Configuration > Plugins > Ticket Report > Configurer** :

- ☑ Description du ticket
- ☑ Suivis (hors privés)
- ☑ Tâches (hors privées)
- ☐ Solution

## Utilisation

Ouvrir un ticket (en tant que technicien) → cliquer **« Envoyer le rapport par email »** →
un formulaire de préparation s'ouvre : cocher les sections à inclure, vérifier/ajouter les
destinataires (À : demandeurs, CC : observateurs + techniciens), puis **Envoyer**. L'email
part au demandeur depuis l'adresse émettrice GLPI.

## Notes

- Le bouton n'est visible que pour les personnes pouvant modifier le ticket.
- Les éléments **privés** sont systématiquement exclus.
- Le contenu des suivis/tâches est converti en texte brut dans l'email (pas de HTML brut).
- Couleurs alignées sur le thème GLPI 11 (statuts + types de timeline).

## Licence

GPL-2.0-or-later.
