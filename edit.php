<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Matrix editor page
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/edit_form.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid = required_param('areaid', PARAM_INT);

$manager = get_grading_manager($areaid);

[$context, $course, $cm] = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$controller = $manager->get_controller('matrix');

// Hide the sticky navigation footer (MDL-87575). Only available from Moodle 5.3.
if (method_exists($PAGE, 'set_show_navigation_footer')) {
    $PAGE->set_show_navigation_footer(false);
}
$PAGE->set_url(new moodle_url('/grade/grading/form/matrix/edit.php', ['areaid' => $areaid]));
$PAGE->set_title(get_string('defineform', 'gradingform_matrix'));
$PAGE->set_heading(get_string('defineform', 'gradingform_matrix'));

$mform = new gradingform_matrix_editmatrix(
    null,
    ['areaid' => $areaid, 'context' => $context, 'allowdraft' => !$controller->has_active_instances()],
    'post',
    '',
    ['class' => 'gradingform_matrix_editform']
);
$data = $controller->get_definition_for_editing(true);
$returnurl = optional_param('returnurl', $manager->get_management_url(), PARAM_LOCALURL);
$data->returnurl = $returnurl;
$mform->set_data($data);
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($mform->is_submitted() && $mform->is_validated() && !$mform->need_confirm_regrading($controller)) {
    // Everything ok, validated, re-grading confirmed if needed. Make changes to the matrix.
    $data = $mform->get_data();
    $controller->update_definition($data);

    // If we do not go back to management url and the minscore warning needs to be displayed, display it during redirection.
    $warning = null;
    if (!empty($data->returnurl) && $data->returnurl !== $manager->get_management_url()->out(false)) {
        $lockzeropoints = !empty($data->matrix['options']['lockzeropoints']);
        if (!$lockzeropoints && ($scores = $controller->get_min_max_score()) && $scores['minscore'] <> 0) {
            $warning = get_string('zerolevelsabsent', 'gradingform_matrix') . '<br>' .
                html_writer::link($manager->get_management_url(), get_string('back'));
        }
    }
    redirect($returnurl, $warning, null, \core\output\notification::NOTIFY_ERROR);
}

// Try to keep the session alive on this page as it may take some time
// before significant interaction happens with the server.
\core\session\manager::keepalive();

echo $OUTPUT->header();

$actions = [];
if ($controller->can_import()) {
    $actions[] = [
        'label' => get_string('import', 'gradingform_matrix'),
        'style' => 'btn-secondary',
        'url' => (new moodle_url('/grade/grading/form/matrix/import.php', ['areaid' => $areaid, 'returnurl' => $returnurl]))
            ->out(false),
    ];
}
if ($controller->is_form_defined()) {
    $actions[] = [
        'label' => get_string('export', 'gradingform_matrix'),
        'style' => 'btn-secondary',
        'url' => (new moodle_url('/grade/grading/form/matrix/export.php', ['areaid' => $areaid]))->out(false),
    ];
}
if (\gradingform_matrix\local\ai_settings::enabled() && has_capability('gradingform/matrix:useaiassistant', $context)) {
    $actions[] = [
        'label' => get_string('aiassistant', 'gradingform_matrix'),
        'style' => 'btn-secondary',
        'action' => 'gradingform_matrix-aiassistant',
    ];
    $PAGE->requires->js_call_amd('gradingform_matrix/local/aiassistant', 'init', [$areaid]);
}
if (has_capability('moodle/site:config', context_system::instance())) {
    // Grading methods have no place in the site administration tree, so link the settings from here.
    $actions[] = [
        'label' => get_string('pluginsettings', 'gradingform_matrix'),
        'style' => 'btn-link',
        'url' => \gradingform_matrix\local\plugin_settings::url($PAGE->url)->out(false),
    ];
}
if ($actions) {
    echo $OUTPUT->render_from_template('gradingform_matrix/edit_actions', ['actions' => $actions]);
}

$mform->display();
echo $OUTPUT->footer();
