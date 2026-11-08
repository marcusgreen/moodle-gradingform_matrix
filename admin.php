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
 * Shows and saves the settings in settings.php.
 *
 * Moodle core does not load settings.php for grading methods, so this page does it, with the same widgets and
 * permission (moodle/site:config) as a normal settings page. It is linked from the grading form editor.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use gradingform_matrix\local\plugin_settings;

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$url = new moodle_url('/grade/grading/form/matrix/admin.php', $returnurl ? ['returnurl' => $returnurl] : []);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginsettings', 'gradingform_matrix'));
$PAGE->set_heading(get_string('pluginsettings', 'gradingform_matrix'));

if (($data = data_submitted()) && confirm_sesskey()) {
    $errors = plugin_settings::save((array)$data);
    if (!$errors) {
        redirect(
            $returnurl ? new moodle_url($returnurl) : $url,
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$params = [];
foreach ($url->params() as $name => $value) {
    $params[] = ['name' => $name, 'value' => $value];
}

echo $OUTPUT->header();
if (!empty($errors)) {
    echo $OUTPUT->notification(get_string('errorwithsettings', 'admin') . ' ' . implode(' ', $errors));
}
echo $OUTPUT->render_from_template('core_admin/settings', [
    'actionurl' => $url->out(false),
    'params' => $params,
    'sesskey' => sesskey(),
    'return' => '',
    'title' => null,
    'settings' => plugin_settings::page()->output_html(),
    'showsave' => true,
]);
echo $OUTPUT->footer();
