<?php

/**
 * ---------------------------------------------------------------------
 * Ticket Report — configuration form.
 * ---------------------------------------------------------------------
 *
 * @license https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

Session::checkRight(Config::$rightname, READ);
global $CFG_GLPI;
$action = $CFG_GLPI['root_doc'] . '/plugins/ticketreport/front/config.form.php';

$defaults = [
    'include_description' => 1,
    'include_followups'   => 1,
    'include_tasks'       => 1,
    'include_solution'    => 0,
];

if (isset($_POST['update'])) {
    Session::checkRight(Config::$rightname, UPDATE);
    Config::setConfigurationValues('ticketreport', [
        'include_description' => isset($_POST['include_description']) ? 1 : 0,
        'include_followups'   => isset($_POST['include_followups']) ? 1 : 0,
        'include_tasks'       => isset($_POST['include_tasks']) ? 1 : 0,
        'include_solution'    => isset($_POST['include_solution']) ? 1 : 0,
    ]);
    Session::addMessageAfterRedirect(__('Saved', 'ticketreport'));
    Html::back();
}

$config = array_merge($defaults, Config::getConfigurationValues('ticketreport', $defaults));

Html::header(__('Ticket Report configuration', 'ticketreport'), $action);

$fields = [
    'include_description' => __('Include ticket description', 'ticketreport'),
    'include_followups'   => __('Include followups', 'ticketreport'),
    'include_tasks'       => __('Include tasks', 'ticketreport'),
    'include_solution'    => __('Include solution', 'ticketreport'),
];

echo '<form method="post" action="' . $action . '">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo '<div class="card"><div class="card-body">';

foreach ($fields as $key => $label) {
    $checked = (int) ($config[$key] ?? 0) ? 'checked' : '';
    echo '<div class="form-check">';
    echo '<input class="form-check-input" type="checkbox" name="' . $key . '" id="' . $key . '" ' . $checked . '>';
    echo '<label class="form-check-label" for="' . $key . '">' . htmlspecialchars($label) . '</label>';
    echo '</div>';
}

echo '</div></div>';

echo '<div style="margin-top: 12px;">';
echo '<button type="submit" name="update" class="btn btn-primary">' . __('Save', 'ticketreport') . '</button>';
echo '</div>';

echo '</form>';

Html::footer();
