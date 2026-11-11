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
 * Downloads the grading results with the level, points and remark for each criterion.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

$areaid = required_param('areaid', PARAM_INT);
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

$manager = get_grading_manager($areaid);

[$context, $course, $cm] = get_context_info_array($manager->get_context()->id);

require_login($course, true, $cm);
require_capability('moodle/grade:viewall', $context);

$controller = $manager->get_controller('matrix');
if (!$controller->is_form_defined()) {
    throw new moodle_exception('exportnothing', 'gradingform_matrix');
}

$exporter = $controller->results_exporter();
$resolver = $controller->item_resolver();
$identityfields = \core_user\fields::get_identity_fields($context, false);

if ($dataformat !== '') {
    $name = core_text::substr($controller->get_definition()->name, 0, 100) ?: 'gradingform';
    $filename = clean_filename($name . ' ' . get_string('results', 'gradingform_matrix'));
    \core\dataformat::download_data(
        $filename,
        $dataformat,
        $exporter->columns($identityfields, !$resolver->supported()),
        $exporter->rows($controller->get_definition()->id, $resolver, $identityfields)
    );
    die();
}

$url = new moodle_url('/grade/grading/form/matrix/results.php', ['areaid' => $areaid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('results', 'gradingform_matrix'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('results', 'gradingform_matrix'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('results', 'gradingform_matrix'));
echo html_writer::tag('p', get_string('resultsintro', 'gradingform_matrix'));
if (!$resolver->supported()) {
    echo $OUTPUT->notification(get_string('resultsunsupported', 'gradingform_matrix'), 'info');
}
echo $OUTPUT->download_dataformat_selector(get_string('downloadas', 'table'), $url, 'dataformat', ['areaid' => $areaid]);
echo $OUTPUT->single_button($manager->get_management_url(), get_string('back'), 'get');
echo $OUTPUT->footer();
