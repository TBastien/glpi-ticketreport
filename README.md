# Ticket Report — GLPI plugin

Bouton **« Envoyer le rapport par email »** dans la fiche d'un ticket GLPI. En un clic,
envoie un **email HTML** (aux couleurs de GLPI) contenant la description, les suivis, les
tâches et la solution du ticket, **fusionnés par ordre chronologique** et **hors éléments
privés**.

A GLPI plugin that adds a **"Send report by email"** button to the ticket form. One click
sends an HTML email (styled with GLPI's colors) with the ticket's description, followups,
tasks and solution, merged chronologically and excluding private items.

---

## English

### Requirements
- GLPI **11.x**.
- Email sending configured in GLPI: `Setup > Notifications` (valid sender + SMTP).

### Installation
1. Copy the `ticketreport` directory into the `plugins/` directory of your GLPI install.
2. **Setup > Plugins** → "Ticket Report" → **Install** then **Enable**.

### Configuration
**Setup > Plugins > Ticket Report > Configure**:
- ☑ Ticket description
- ☑ Followups (excluding private)
- ☑ Tasks (excluding private)
- ☐ Solution

### Usage
Open a ticket (as a technician) → click **"Send report by email"** → a preparation form
opens: tick the sections to include, check/edit the recipients (To: requesters, Cc:
observers + technicians), then **Send**.

### Notes
- The button is only visible to users who can update the ticket.
- The report endpoint requires the same ticket update right and accepts at most 10
  recipient entries per send request.
- Private items are always excluded.
- Followup/task content is converted to plain text in the email (no raw HTML).
- Colors match the GLPI 11 theme (statuses + timeline types).

## Français

### Prérequis
- GLPI **11.x**.
- **Envoi d'emails configuré** dans GLPI : `Configuration > Notifications` (émetteur + SMTP valides).

### Installation
1. Copier le dossier `ticketreport` dans `plugins/` de l'installation GLPI.
2. **Configuration > Plugins** → « Ticket Report » → **Installer** puis **Activer**.

### Configuration
**Configuration > Plugins > Ticket Report > Configurer** :
- ☑ Description du ticket
- ☑ Suivis (hors privés)
- ☑ Tâches (hors privées)
- ☐ Solution

### Utilisation
Ouvrir un ticket (en tant que technicien) → cliquer **« Envoyer le rapport par email »** →
un formulaire de préparation s'ouvre : cocher les sections à inclure, vérifier/ajouter les
destinataires (À : demandeurs, CC : observateurs + techniciens), puis **Envoyer**.

### Notes
- Le bouton n'est visible que pour les personnes pouvant modifier le ticket.
- L'envoi exige également le droit de modification sur ce ticket et accepte au
  maximum 10 entrées de destinataires par requête.
- Les éléments **privés** sont systématiquement exclus.
- Le contenu des suivis/tâches est converti en texte brut dans l'email (pas de HTML brut).
- Couleurs alignées sur le thème GLPI 11 (statuts + types de timeline).

## Licence / License

GPL-2.0-or-later.
