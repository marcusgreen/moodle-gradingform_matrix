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
 * Imports a grading form definition from a JSON file made by export.php.
 *
 * The import replaces the criteria and levels and is saved as a draft, then the editor opens.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid = required_param('areaid', PARAM_INT);

$manager = get_grading_manager($areaid);

[$context, $course, $cm] = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:managegradingforms', $context);

$controller = $manager->get_controller('matrix');
$returnurl = optional_param('returnurl', $manager->get_management_url(), PARAM_LOCALURL);
$editurl = new moodle_url('/grade/grading/form/matrix/edit.php', ['areaid' => $areaid, 'returnurl' => $returnurl]);

$url = new moodle_url('/grade/grading/form/matrix/import.php', ['areaid' => $areaid]);
if (method_exists($PAGE, 'set_show_navigation_footer')) {
    $PAGE->set_show_navigation_footer(false);
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('importform', 'gradingform_matrix'));
$PAGE->set_heading(get_string('importform', 'gradingform_matrix'));

if (!$controller->can_import()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('importerrorused', 'gradingform_matrix'), 'error');
    echo $OUTPUT->continue_button($editurl);
    echo $OUTPUT->footer();
    die();
}

$mform = new \gradingform_matrix\form\import_form($url, ['replaces' => $controller->is_form_defined()]);
$mform->set_data(['areaid' => $areaid, 'returnurl' => $returnurl]);

$error = null;
if ($mform->is_cancelled()) {
    redirect($editurl);
} else if ($data = $mform->get_data()) {
    $filename = $mform->get_new_filename('importfile');
    try {
        $imported = $controller->definition_importer()->from_json((string)$mform->get_file_content('importfile'));
        $controller->import_definition($imported, pathinfo($filename, PATHINFO_FILENAME));
        redirect(
            $editurl,
            get_string('importsuccess', 'gradingform_matrix', count($imported['criteria'])),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
if ($error !== null) {
    echo $OUTPUT->notification($error, 'error');
}
$mform->display();
echo $OUTPUT->footer();
