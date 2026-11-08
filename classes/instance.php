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
 * Grading instance for the Matrix plugin.
 *
 * @package    gradingform_matrix
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use gradingform_matrix\local\framework\filling_repository;
use gradingform_matrix\local\framework\filling_validator;
use gradingform_matrix\local\framework\grade_calculator;

defined('MOODLE_INTERNAL') || die();

// The base classes are not autoloaded. This file may be autoloaded inside a function, so $CFG must be global.
global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * Class to manage one matrix grading instance.
 *
 * Stores information and performs actions like update, copy, validate, submit, etc.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_matrix_instance extends gradingform_instance {
    /** @var array stores the matrix, has two keys: 'criteria' and 'options' */
    protected $matrix;

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        parent::cancel();
        $this->get_controller()->filling_repository()->delete($this->get_id());
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        $instanceid = parent::copy($raterid, $itemid);
        $this->get_controller()->filling_repository()->copy($this->get_matrix_filling()['criteria'], $instanceid);
        return $instanceid;
    }

    /**
     * Determines whether the submitted form was empty.
     *
     * @param array $elementvalue value of element submitted from the form
     * @return boolean true if the form is empty
     */
    public function is_empty_form($elementvalue) {
        $controller = $this->get_controller();
        return $controller->filling_validator()->is_empty($controller->get_definition()->matrix_criteria, $elementvalue);
    }

    /**
     * Removes the attempt from the fillings table
     * @param array $data the attempt data
     */
    public function clear_attempt($data) {
        $this->get_controller()->filling_repository()->delete($this->get_id(), array_keys($data['criteria'] ?? []));
    }

    /**
     * Validates that matrix is fully completed and contains valid grade on each criterion
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        $controller = $this->get_controller();
        return $controller->filling_validator()->is_complete($controller->get_definition()->matrix_criteria, $elementvalue);
    }

    /**
     * Retrieves from DB and returns the data how this matrix was filled
     *
     * @param boolean $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_matrix_filling($force = false) {
        if ($this->matrix === null || $force) {
            $this->matrix = ['criteria' => $this->get_controller()->filling_repository()->load($this->get_id())];
        }
        return $this->matrix;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;
        $currentgrade = $this->get_matrix_filling();
        $controller = $this->get_controller();
        $submitted = [];
        $criteria = $controller->filling_validator()->filter($controller->get_definition()->matrix_criteria, $data);
        foreach ($criteria as $criterionid => $record) {
            // Hardcoding/defaulting to html format for new/existing record.
            $record['remarkformat'] = FORMAT_HTML;
            $submitted[$criterionid] = $record;
        }
        $transaction = $DB->start_delegated_transaction();
        parent::update($data);
        $controller->filling_repository()->save($this->get_id(), $currentgrade['criteria'], $submitted);
        $transaction->allow_commit();
        $this->get_matrix_filling(true);
    }

    /**
     * Calculates the grade to be pushed to the gradebook
     *
     * @return float|int the valid grade from $this->get_controller()->get_grade_range()
     */
    public function get_grade() {
        $controller = $this->get_controller();
        if (!$controller->is_form_available()) {
            return -1;
        }
        return $controller->grade_calculator()->calculate(
            $controller->get_definition()->matrix_criteria,
            $this->get_matrix_filling()['criteria'],
            array_keys($controller->get_grade_range()),
            $controller->get_options(),
            (bool)$controller->get_allow_grade_decimals()
        );
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        global $USER;
        if (!$gradingformelement->_flagFrozen) {
            $page->requires->js_call_amd('gradingform_matrix/matrix', 'init', [$gradingformelement->getName()]);
            $mode = gradingform_matrix_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_matrix_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_matrix_controller::DISPLAY_REVIEW;
            }
        }
        $criteria = $this->get_controller()->get_definition()->matrix_criteria;
        $options = $this->get_controller()->get_options();
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_matrix_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::tag(
                'div',
                get_string('formnotcompleted', 'gradingform_matrix'),
                ['class' => 'gradingform_matrix-error']
            );
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::div(
                get_string('needregrademessage', 'gradingform_matrix'),
                'gradingform_matrix-regrade',
                ['role' => 'alert']
            );
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_matrix_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['savedlevelid'] = $curvalues['levelid'];
                $newremark = null;
                $newlevelid = null;
                if (isset($value['criteria'][$criterionid]['remark'])) {
                    $newremark = $value['criteria'][$criterionid]['remark'];
                }
                if (isset($value['criteria'][$criterionid]['levelid'])) {
                    $newlevelid = $value['criteria'][$criterionid]['levelid'];
                }
                if ($newlevelid != $curvalues['levelid'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag(
                'div',
                get_string('restoredfromdraft', 'gradingform_matrix'),
                ['class' => 'gradingform_matrix-restored']
            );
        }
        if (!empty($options['showdescriptionteacher'])) {
            $html .= html_writer::tag(
                'div',
                $this->get_controller()->get_formatted_description(),
                ['class' => 'gradingform_matrix-description']
            );
        }
        $renderer = $this->get_controller()->get_renderer($page);
        $html .= $renderer->display_matrix($criteria, $options, $mode, $gradingformelement->getName(), $value);
        return $html;
    }
}
