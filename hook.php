<?php

/**
 * ---------------------------------------------------------------------
 * Ticket Report — installation and display hook.
 * ---------------------------------------------------------------------
 * Sends a styled HTML report (ticket + followups + tasks + solution,
 * chronological, excluding private items) by email to chosen recipients.
 *
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

/**
 * Installation: nothing to create in the database.
 *
 * @return bool
 */
function plugin_ticketreport_install()
{
    return true;
}

/**
 * Uninstallation.
 *
 * @return bool
 */
function plugin_ticketreport_uninstall()
{
    Config::deleteConfigurationValues('ticketreport', [
        'include_description',
        'include_followups',
        'include_tasks',
        'include_solution',
    ]);
    return true;
}

/**
 * Hook pre_show_item: render the button at the top of a ticket form.
 *
 * @param array $params ['item' => CommonDBTM, 'options' => array]
 */
function plugin_ticketreport_pre_show_item(array $params)
{
    $item = $params['item'] ?? null;

    if (!$item instanceof Ticket || !$item->getID()) {
        return;
    }

    // Reserved to people who can act on the ticket (techs, admins).
    if (!$item->canUpdateItem()) {
        return;
    }

    global $CFG_GLPI;
    $url = $CFG_GLPI['root_doc']
        . '/plugins/ticketreport/front/report.form.php?id=' . (int) $item->getID();

    echo '<div class="ticketreport-toolbar" style="display: inline-block; vertical-align: middle; margin: 0 10px 15px 0;">';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    echo '<i class="fa-solid fa-paper-plane"></i>&nbsp;' . htmlspecialchars(__('Send report by email', 'ticketreport'), ENT_QUOTES, 'UTF-8');
    echo '</a>';
    echo '</div>';
}

/**
 * Read the notification subject tag for the ticket entity.
 *
 * @return string
 */
function plugin_ticketreport_get_subject_tag(Ticket $ticket): string
{
    $tag = trim((string) Entity::getUsedConfig(
        'notification_subject_tag',
        $ticket->getEntityID(),
        '',
        ''
    ));

    return $tag !== '' ? $tag : 'GLPI';
}

/**
 * Actors of the ticket grouped by role, with email and friendly name.
 *
 * @return array ['requester' => [[email,name]...], 'observer' => [...], 'assign' => [...]]
 */
function plugin_ticketreport_get_actors(Ticket $ticket): array
{
    $result = ['requester' => [], 'observer' => [], 'assign' => []];

    $actor  = new Ticket_User();
    $actors = $actor->getActors((int) $ticket->getID());

    $map = [
        CommonITILActor::REQUESTER => 'requester',
        CommonITILActor::OBSERVER  => 'observer',
        CommonITILActor::ASSIGN    => 'assign',
    ];

    foreach ($map as $type => $key) {
        foreach (($actors[$type] ?? []) as $a) {
            $email = '';
            $name  = '';
            if ((int) ($a['users_id'] ?? 0) > 0) {
                $email = UserEmail::getDefaultForUser((int) $a['users_id']);
                $name  = User::getFriendlyNameById((int) $a['users_id']);
            } elseif (!empty($a['alternative_email'])) {
                $email = $a['alternative_email'];
                $name  = $a['alternative_email'];
            }
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $result[$key][] = ['email' => $email, 'name' => (string) $name];
            }
        }
    }

    return $result;
}

/**
 * Human-readable label for an actor.
 *
 * @return string
 */
function plugin_ticketreport_actor_label(array $a): string
{
    if (!empty($a['name']) && $a['name'] !== $a['email']) {
        return $a['name'] . ' <' . $a['email'] . '>';
    }

    return $a['email'];
}

/**
 * HTML row for an editable recipient input.
 *
 * @return string
 */
function plugin_ticketreport_recipient_row(string $name, string $email): string
{
    $html  = '<div class="input-group mb-1">';
    $html .= '<input type="text" name="' . $name . '" value="' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '" class="form-control">';
    $html .= '<button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">&times;</button>';
    $html .= '</div>';
    return $html;
}

/**
 * Plugin configuration with defaults applied.
 *
 * @return array
 */
function plugin_ticketreport_get_config(): array
{
    $defaults = [
        'include_description' => 1,
        'include_followups'   => 1,
        'include_tasks'       => 1,
        'include_solution'    => 0,
    ];

    $cfg = Config::getConfigurationValues('ticketreport', $defaults);

    return array_merge($defaults, array_map('intval', $cfg));
}

/**
 * Collect the chronological timeline events, respecting the given config
 * (or the default config when null), excluding private items.
 *
 * @return array
 */
function plugin_ticketreport_get_events(Ticket $ticket, ?array $config = null): array
{
    $cfg = $config ?? plugin_ticketreport_get_config();
    $id  = (int) $ticket->getID();

    $events = [];
    if ($cfg['include_followups']) {
        foreach (plugin_ticketreport_get_followups($id) as $e) {
            $events[] = $e;
        }
    }
    if ($cfg['include_tasks']) {
        foreach (plugin_ticketreport_get_tasks($id) as $e) {
            $events[] = $e;
        }
    }
    if ($cfg['include_solution']) {
        foreach (plugin_ticketreport_get_solutions($id) as $e) {
            $events[] = $e;
        }
    }

    usort($events, static function (array $a, array $b) {
        return strcmp($a['datetime'], $b['datetime']);
    });

    return $events;
}

/**
 * Build the HTML report body (styled with GLPI colors).
 *
 * @return string
 */
function plugin_ticketreport_build_html(Ticket $ticket, ?array $config = null): string
{
    $cfg    = $config ?? plugin_ticketreport_get_config();
    $id     = (int) $ticket->getID();
    $title  = htmlspecialchars((string) ($ticket->fields['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $status = (int) $ticket->fields['status'];
    [$statusLabel, $statusColor] = plugin_ticketreport_status_info($status);

    $html = '<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333333; max-width: 720px; line-height: 1.5;">';

    $html .= '<div style="background: #206bc4; color: #ffffff; padding: 18px 22px; border-radius: 6px 6px 0 0;">';
    $html .= '<h2 style="margin: 0 0 8px; font-size: 18px; font-weight: bold;">Ticket #' . $id . ' : ' . $title . '</h2>';
    $html .= '<span style="display: inline-block; background: ' . $statusColor . '; color: #ffffff; padding: 3px 12px; border-radius: 12px; font-size: 12px;">' . $statusLabel . '</span>';
    $html .= '</div>';

    $html .= '<div style="border: 1px solid #e0e0e0; border-top: none; padding: 18px 22px; border-radius: 0 0 6px 6px;">';

    if ($cfg['include_description'] && !empty($ticket->fields['content'])) {
        $desc = plugin_ticketreport_clean_text((string) $ticket->fields['content']);
        $html .= '<h3 style="color: #206bc4; font-size: 13px; margin: 0 0 8px; text-transform: uppercase;">' . __('Description', 'ticketreport') . '</h3>';
        $html .= '<p style="margin: 0 0 18px; white-space: pre-wrap;">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $events = plugin_ticketreport_get_events($ticket, $cfg);
    if (!empty($events)) {
        $html .= '<h3 style="color: #206bc4; font-size: 13px; margin: 18px 0 8px; text-transform: uppercase;">' . __('Timeline', 'ticketreport') . '</h3>';

        foreach ($events as $e) {
            $style = plugin_ticketreport_type_style($e['type']);
            $html .= '<div style="border-left: 4px solid ' . $style['accent'] . '; background: ' . $style['bg'] . '; padding: 10px 14px; margin: 0 0 12px; border-radius: 0 4px 4px 0;">';
            $html .= '<div style="font-size: 12px; color: #888888; margin-bottom: 6px;">';
            $html .= htmlspecialchars(plugin_ticketreport_format_date($e['datetime']), ENT_QUOTES, 'UTF-8');
            if ($e['author'] !== '') {
                $html .= ' — <strong>' . htmlspecialchars($e['author'], ENT_QUOTES, 'UTF-8') . '</strong>';
            }
            $html .= ' <span style="color: ' . $style['accent'] . '; font-weight: bold;">(' . htmlspecialchars($e['label'], ENT_QUOTES, 'UTF-8') . ')</span>';
            $html .= '</div>';
            $html .= '<div style="white-space: pre-wrap;">' . htmlspecialchars($e['content'], ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '</div>';
        }
    }

    $html .= '</div></div>';

    return $html;
}

/**
 * Build a plain-text fallback version of the report.
 *
 * @return string
 */
function plugin_ticketreport_build_text(Ticket $ticket, ?array $config = null): string
{
    $cfg   = $config ?? plugin_ticketreport_get_config();
    $id    = (int) $ticket->getID();
    $title = trim((string) ($ticket->fields['name'] ?? ''));

    $sep  = str_repeat('=', 60);
    $thin = str_repeat('-', 60);

    $out   = [];
    $out[] = $sep;
    $out[] = sprintf('Ticket #%d : %s', $id, $title);
    $out[] = $sep;
    $out[] = '';

    if ($cfg['include_description'] && !empty($ticket->fields['content'])) {
        $out[] = mb_strtoupper(__('Description', 'ticketreport'));
        $out[] = $thin;
        $out[] = plugin_ticketreport_clean_text((string) $ticket->fields['content']);
        $out[] = '';
    }

    $events = plugin_ticketreport_get_events($ticket, $cfg);
    if (!empty($events)) {
        $out[] = mb_strtoupper(__('Timeline', 'ticketreport'));
        $out[] = $thin;
        foreach ($events as $e) {
            $author = $e['author'] !== '' ? $e['author'] : __('Unknown', 'ticketreport');
            $out[] = sprintf('[%s] %s — %s :', plugin_ticketreport_format_date($e['datetime']), $author, $e['label']);
            $out[] = $e['content'];
            $out[] = '';
        }
    }

    return trim(implode("\n", $out));
}

/**
 * Send the report by email via GLPI's mailer.
 *
 * @param string[] $to  "To" recipients
 * @param string[] $cc  "Cc" recipients
 * @return bool
 */
function plugin_ticketreport_send_email(array $to, array $cc, string $subject, string $html, string $text): bool
{
    if (count($to) + count($cc) > 10) {
        return false;
    }
    $sender    = Config::getEmailSender();
    $fromEmail = trim((string) ($sender['email'] ?? ''));
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $to = array_values(array_unique(array_filter($to)));
    $cc = array_values(array_unique(array_filter(array_diff($cc, $to))));

    if (empty($to) && empty($cc)) {
        return false;
    }

    try {
        $mmail = new GLPIMailer();
        $mail  = $mmail->getEmail();

        $mail->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
        $mail->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, NDR, RN, NRN');

        $mail->from(new \Symfony\Component\Mime\Address($fromEmail, (string) ($sender['name'] ?? '')));

        $toAddresses = [];
        foreach ($to as $email) {
            $toAddresses[] = new \Symfony\Component\Mime\Address($email);
        }
        if (!empty($toAddresses)) {
            $mail->to(...$toAddresses);
        }
        foreach ($cc as $email) {
            $mail->addCc(new \Symfony\Component\Mime\Address($email));
        }

        $mail->subject($subject);
        $mail->text($text);
        $mail->html($html);

        return $mmail->send();
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Label and color for a ticket status (GLPI 11 theme colors).
 *
 * @return array [label, color]
 */
function plugin_ticketreport_status_info(int $status): array
{
    $map = [
        1 => [__('New', 'ticketreport'), '#49bf4d'],
        2 => [__('Processing (assigned)', 'ticketreport'), '#49bf4d'],
        3 => [__('Processing (planned)', 'ticketreport'), '#1b2f62'],
        4 => [__('Pending', 'ticketreport'), '#e67e22'],
        5 => [__('Solved', 'ticketreport'), '#555555'],
        6 => [__('Closed', 'ticketreport'), '#333333'],
    ];

    return $map[$status] ?? ['', '#555555'];
}

/**
 * Accent and background colors for a timeline item type (GLPI 11 theme colors).
 *
 * @return array ['accent' => ..., 'bg' => ...]
 */
function plugin_ticketreport_type_style(string $type): array
{
    $map = [
        'followup' => ['accent' => '#206bc4', 'bg' => '#f4f8fd'],
        'task'     => ['accent' => '#d19a11', 'bg' => '#fff8e8'],
        'solution' => ['accent' => '#2e86ab', 'bg' => '#eef7fb'],
    ];

    return $map[$type] ?? ['accent' => '#206bc4', 'bg' => '#fafafa'];
}

/**
 * Followups of the ticket (excluding private ones).
 *
 * @return array
 */
function plugin_ticketreport_get_followups(int $tickets_id): array
{
    global $DB;

    $events = [];
    $it = $DB->request([
        'SELECT' => ['date', 'content', 'users_id'],
        'FROM'   => ITILFollowup::getTable(),
        'WHERE'  => [
            'items_id'   => $tickets_id,
            'itemtype'   => Ticket::getType(),
            'is_private' => 0,
        ],
        'ORDER' => ['date ASC'],
    ]);

    foreach ($it as $row) {
        $events[] = [
            'type'     => 'followup',
            'datetime' => (string) $row['date'],
            'author'   => plugin_ticketreport_author((int) $row['users_id']),
            'label'    => __('Followup', 'ticketreport'),
            'content'  => plugin_ticketreport_clean_text((string) $row['content']),
        ];
    }

    return $events;
}

/**
 * Tasks of the ticket (excluding private ones).
 *
 * @return array
 */
function plugin_ticketreport_get_tasks(int $tickets_id): array
{
    global $DB;

    $events = [];
    $it = $DB->request([
        'SELECT' => ['date', 'content', 'users_id'],
        'FROM'   => TicketTask::getTable(),
        'WHERE'  => [
            'tickets_id' => $tickets_id,
            'is_private' => 0,
        ],
        'ORDER' => ['date ASC'],
    ]);

    foreach ($it as $row) {
        $events[] = [
            'type'     => 'task',
            'datetime' => (string) $row['date'],
            'author'   => plugin_ticketreport_author((int) $row['users_id']),
            'label'    => __('Task', 'ticketreport'),
            'content'  => plugin_ticketreport_clean_text((string) $row['content']),
        ];
    }

    return $events;
}

/**
 * Solutions of the ticket.
 *
 * @return array
 */
function plugin_ticketreport_get_solutions(int $tickets_id): array
{
    global $DB;

    $events = [];
    $it = $DB->request([
        'SELECT' => ['date_creation', 'content', 'users_id'],
        'FROM'   => ITILSolution::getTable(),
        'WHERE'  => [
            'items_id' => $tickets_id,
            'itemtype' => Ticket::getType(),
        ],
        'ORDER' => ['date_creation ASC'],
    ]);

    foreach ($it as $row) {
        $events[] = [
            'type'     => 'solution',
            'datetime' => (string) $row['date_creation'],
            'author'   => plugin_ticketreport_author((int) $row['users_id']),
            'label'    => __('Solution', 'ticketreport'),
            'content'  => plugin_ticketreport_clean_text((string) $row['content']),
        ];
    }

    return $events;
}

/**
 * Friendly name of an author.
 *
 * @return string
 */
function plugin_ticketreport_author(int $users_id): string
{
    if ($users_id <= 0) {
        return '';
    }

    return (string) User::getFriendlyNameById($users_id);
}

/**
 * Format a MySQL datetime for display.
 *
 * @return string
 */
function plugin_ticketreport_format_date(string $datetime): string
{
    if (class_exists('Html') && method_exists('Html', 'convDateTime')) {
        return Html::convDateTime($datetime);
    }

    return $datetime;
}

/**
 * Convert HTML content to plain text.
 *
 * @return string
 */
function plugin_ticketreport_clean_text(string $html): string
{
    if (class_exists('Html') && method_exists('Html', 'cleanPlainText')) {
        $text = Html::cleanPlainText($html);
    } else {
        $text = strip_tags($html);
    }

    $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/\r\n?/", "\n", $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n[ \t]*\n/", "\n", $text);

    return trim($text);
}
