<?php

/**
 * ---------------------------------------------------------------------
 * Ticket Report — preparation form (sections + recipients) and send.
 * ---------------------------------------------------------------------
 *
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

include_once(__DIR__ . '/../hook.php');

$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$ticket = new Ticket();

if ($id <= 0 || !$ticket->getFromDB($id)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}

// Reserved to the central (technician) interface only. In the helpdesk
// interface, a Self-Service user's CREATE right satisfies canUpdateItem(), which
// would let the requester of a just-opened ticket send the report to arbitrary
// addresses without limit. Requiring the central interface plus the ticket
// update right closes this, on both GET and POST.
if (Session::getCurrentInterface() !== 'central' || !$ticket->canUpdateItem()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$redirect = Ticket::getFormURLWithID($id);
global $CFG_GLPI;
$action = $CFG_GLPI['root_doc'] . '/plugins/ticketreport/front/report.form.php';

// ---------------------------------------------------------------------
// POST : send the report
// ---------------------------------------------------------------------
if (isset($_POST['send'])) {
    // Per-user rate limit (defense in depth; the central-interface gate above is
    // the primary access control).
    if (!plugin_ticketreport_rate_limit_ok(Session::getLoginUserID())) {
        Session::addMessageAfterRedirect(
            __('Too many reports sent recently. Please retry later.', 'ticketreport'),
            false,
            ERROR
        );
        Html::redirect($redirect);
        exit;
    }

    $config = [
        'include_description' => isset($_POST['include_description']) ? 1 : 0,
        'include_followups'   => isset($_POST['include_followups']) ? 1 : 0,
        'include_tasks'       => isset($_POST['include_tasks']) ? 1 : 0,
        'include_solution'    => isset($_POST['include_solution']) ? 1 : 0,
    ];

    $to = isset($_POST['to']) && is_array($_POST['to']) ? $_POST['to'] : [];
    $cc = isset($_POST['cc']) && is_array($_POST['cc']) ? $_POST['cc'] : [];
    if (count($to) + count($cc) > 10) {
        Session::addMessageAfterRedirect(__('Too many recipients (maximum 10)', 'ticketreport'), false, ERROR);
        Html::redirect($redirect);
        exit;
    }

    $to = array_values(array_unique(array_filter(array_map('trim', $to), static function ($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    })));
    $cc = array_values(array_unique(array_filter(array_map('trim', $cc), static function ($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
    })));
    $cc = array_values(array_diff($cc, $to));

    if (empty($to) && empty($cc)) {
        Session::addMessageAfterRedirect(__('No recipient selected', 'ticketreport'), false, ERROR);
        Html::redirect($redirect);
    }

    $tag     = plugin_ticketreport_get_subject_tag($ticket);
    $subject = trim(sprintf('[%s #%07d] %s', $tag, $id, trim((string) $ticket->fields['name'])));
    $html    = plugin_ticketreport_build_html($ticket, $config);
    $text    = plugin_ticketreport_build_text($ticket, $config);

    $success = plugin_ticketreport_send_email($to, $cc, $subject, $html, $text);

    if ($success) {
        plugin_ticketreport_record_send(Session::getLoginUserID());
        Session::addMessageAfterRedirect(__('Report sent', 'ticketreport'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Failed to send report', 'ticketreport'), false, ERROR);
    }

    Html::redirect($redirect);
}

// ---------------------------------------------------------------------
// GET : show the preparation form
// ---------------------------------------------------------------------
$actors = plugin_ticketreport_get_actors($ticket);
$config = plugin_ticketreport_get_config();

$toEmails = array_values(array_unique(array_column($actors['requester'], 'email')));
$ccEmails = array_values(array_unique(array_diff(
    array_merge(
        array_column($actors['observer'], 'email'),
        array_column($actors['assign'], 'email')
    ),
    $toEmails
)));

$sections = [
    'include_description' => __('Include ticket description', 'ticketreport'),
    'include_followups'   => __('Include followups', 'ticketreport'),
    'include_tasks'       => __('Include tasks', 'ticketreport'),
    'include_solution'    => __('Include solution', 'ticketreport'),
];

Html::header(
    __('Send report', 'ticketreport') . ' — ' . $ticket->fields['name'],
    $action
);

echo '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" id="report-form">';
echo Html::hidden('id', ['value' => $id]);
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

// --- Sections ---
echo '<div class="card mb-3"><div class="card-body">';
echo '<h5 class="card-title">' . htmlspecialchars(__('Sections to include', 'ticketreport'), ENT_QUOTES, 'UTF-8') . '</h5>';
foreach ($sections as $key => $label) {
    $checked = (int) ($config[$key] ?? 0) ? 'checked' : '';
    echo '<div class="form-check">';
    echo '<input class="form-check-input" type="checkbox" name="' . $key . '" id="' . $key . '" ' . $checked . '>';
    echo '<label class="form-check-label" for="' . $key . '">' . htmlspecialchars($label) . '</label>';
    echo '</div>';
}
echo '</div></div>';

// --- Recipients (To) ---
echo '<div class="card mb-3"><div class="card-body">';
echo '<h5 class="card-title">' . htmlspecialchars(__('Recipients (To)', 'ticketreport'), ENT_QUOTES, 'UTF-8') . '</h5>';
echo '<div id="to-list">';
foreach ($toEmails as $email) {
    echo plugin_ticketreport_recipient_row('to[]', $email);
}
echo '</div>';
echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="trAddRow(\'to-list\', \'to[]\')">';
echo '<i class="fa-solid fa-plus"></i>&nbsp;' . htmlspecialchars(__('Add recipient', 'ticketreport'), ENT_QUOTES, 'UTF-8');
echo '</button>';
echo '</div></div>';

// --- Copy (Cc) ---
echo '<div class="card mb-3"><div class="card-body">';
echo '<h5 class="card-title">' . htmlspecialchars(__('Copy (Cc)', 'ticketreport'), ENT_QUOTES, 'UTF-8') . '</h5>';
echo '<div id="cc-list">';
foreach ($ccEmails as $email) {
    echo plugin_ticketreport_recipient_row('cc[]', $email);
}
echo '</div>';
echo '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="trAddRow(\'cc-list\', \'cc[]\')">';
echo '<i class="fa-solid fa-plus"></i>&nbsp;' . htmlspecialchars(__('Add recipient', 'ticketreport'), ENT_QUOTES, 'UTF-8');
echo '</button>';
echo '</div></div>';

// --- Actions ---
echo '<div class="mt-3">';
echo '<input type="hidden" name="send" value="1">';
echo '<button type="submit" id="send-btn" class="btn btn-primary">';
echo '<i class="fa-solid fa-paper-plane"></i>&nbsp;' . htmlspecialchars(__('Send report', 'ticketreport'), ENT_QUOTES, 'UTF-8');
echo '</button>';
echo ' <a class="btn btn-secondary" href="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__('Cancel', 'ticketreport'), ENT_QUOTES, 'UTF-8') . '</a>';
echo '</div>';

echo '</form>';

// --- Inline JS for add/remove recipients ---
echo '<script>
function trAddRow(containerId, name) {
    var c = document.getElementById(containerId);
    var row = document.createElement("div");
    row.className = "input-group mb-1";
    row.innerHTML = "<input type=\"text\" name=\"" + name + "\" class=\"form-control\">"
        + "<button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"this.parentElement.remove()\">&times;</button>";
    c.appendChild(row);
}
</script>';

// --- Disable submit button on submit (prevent double-click) ---
echo '<script>
(function () {
    var form = document.getElementById("report-form");
    if (form) {
        form.addEventListener("submit", function () {
            var btn = document.getElementById("send-btn");
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.innerHTML = "<i class=\"fa-solid fa-spinner fa-spin\"></i>&nbsp;" + ' . json_encode(__('Sending...', 'ticketreport')) . ';
            }
        });
    }
})();
</script>';

Html::footer();
