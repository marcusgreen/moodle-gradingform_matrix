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

namespace gradingform_matrix\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload form for importing a grading form definition.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /** @var int largest file accepted; exports embed the description's files base64 encoded */
    const MAXBYTES = 20 * 1024 * 1024;

    /**
     * Form element definition.
     */
    public function definition() {
        $form = $this->_form;

        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);

        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_LOCALURL);

        if (!empty($this->_customdata['replaces'])) {
            $form->addElement(
                'static',
                'replacewarning',
                '',
                \html_writer::div(get_string('importreplaces', 'gradingform_matrix'), 'alert alert-warning')
            );
        }

        $form->addElement(
            'filepicker',
            'importfile',
            get_string('importfile', 'gradingform_matrix'),
            null,
            ['accepted_types' => ['.json', '.csv', '.xlsx', '.ods'], 'maxbytes' => self::MAXBYTES]
        );
        $form->addHelpButton('importfile', 'importfile', 'gradingform_matrix');
        $form->addRule('importfile', null, 'required');

        $this->add_action_buttons(true, get_string('import', 'gradingform_matrix'));
    }
}
