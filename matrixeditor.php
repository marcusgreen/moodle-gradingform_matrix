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
 * File contains definition of class MoodleQuickForm_matrixeditor
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("HTML/QuickForm/input.php");

/**
 * Form element for handling rubric editor
 *
 * The rubric editor is defined as a separate form element. This allows us to render
 * criteria, levels and buttons using the rubric's own renderer. Also, the required
 * Javascript library is included, which processes, on the client, buttons needed
 * for reordering, adding and deleting criteria.
 *
 * If Javascript is disabled when one of those special buttons is pressed, the form
 * element is not validated and, instead of submitting the form, we process button presses.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleQuickForm_matrixeditor extends HTML_QuickForm_input {
    /** @var string help message */
    public $_helpbutton = '';
    /** @var bool if element has already been validated **/
    protected $wasvalidated = false;
    /** @var bool Message to display in front of the editor (that there exist grades on this rubric being edited) */
    protected $regradeconfirmation = false;
    /** @var \gradingform_matrix\editor\data_processor|null Lazy-loaded data processing helper. */
    private ?\gradingform_matrix\editor\data_processor $processor = null;

    /**
     * Constructor for rubric editor
     *
     * @param string $elementName
     * @param string $elementLabel
     * @param array $attributes
     */
    public function __construct($elementName=null, $elementLabel=null, $attributes=null) {
        parent::__construct($elementName, $elementLabel, $attributes);
    }

    /**
     * get html for help button
     *
     * @return string html for help button
     */
    public function getHelpButton() {
        return $this->_helpbutton;
    }

    /**
     * The renderer will take care itself about different display in normal and frozen states
     *
     * @return string
     */
    public function getElementTemplateType() {
        return 'default';
    }

    /**
     * Specifies that confirmation about re-grading needs to be added to this rubric editor.
     * $changelevel is saved in $this->regradeconfirmation and retrieved in toHtml()
     *
     * @see gradingform_matrix_controller::update_or_check_matrix()
     * @param int $changelevel
     */
    public function add_regrade_confirmation($changelevel) {
        $this->regradeconfirmation = $changelevel;
    }

    /**
     * Returns the data processing helper, creating it on first use.
     *
     * @return \gradingform_matrix\editor\data_processor
     */
    private function get_processor(): \gradingform_matrix\editor\data_processor {
        if ($this->processor === null) {
            $this->processor = new \gradingform_matrix\editor\data_processor();
        }
        return $this->processor;
    }

    /**
     * Returns html string to display this element
     *
     * @return string
     */
    public function toHtml() {
        global $PAGE;
        $html = $this->_getTabs();
        $renderer = $PAGE->get_renderer('gradingform_matrix');
        $data = $this->get_processor()->prepare($this->getValue(), $this->wasvalidated);
        if (!$this->_flagFrozen) {
            $mode = gradingform_matrix_controller::DISPLAY_EDIT_FULL;
            $module = array('name'=>'gradingform_matrixeditor', 'fullpath'=>'/grade/grading/form/matrix/js/matrixeditor.js',
                'requires' => array('base', 'dom', 'event', 'event-touch', 'escape'),
                'strings' => array(array('confirmdeletecriterion', 'gradingform_matrix'), array('confirmdeletelevel', 'gradingform_matrix'),
                    array('criterionempty', 'gradingform_matrix'), array('levelempty', 'gradingform_matrix')
                ));
            $PAGE->requires->js_init_call('M.gradingform_matrixeditor.init', array(
                array('name' => $this->getName(),
                    'criteriontemplate' => $renderer->criterion_template($mode, $data['options'], $this->getName()),
                    'leveltemplate' => $renderer->level_template($mode, $data['options'], $this->getName())
                   )),
                true, $module);
        } else {
            // Rubric is frozen, no javascript needed
            if ($this->_persistantFreeze) {
                $mode = gradingform_matrix_controller::DISPLAY_EDIT_FROZEN;
            } else {
                $mode = gradingform_matrix_controller::DISPLAY_PREVIEW;
            }
        }
        if ($this->regradeconfirmation) {
            if (!isset($data['regrade'])) {
                $data['regrade'] = 1;
            }
            $html .= $renderer->display_regrade_confirmation($this->getName(), $this->regradeconfirmation, $data['regrade']);
        }
        if ($this->get_processor()->validationerrors) {
            $html .= html_writer::div($renderer->notification($this->get_processor()->validationerrors));
        }
        $html .= $renderer->display_matrix($data['criteria'], $data['options'], $mode, $this->getName());
        return $html;
    }

    /**
     * Checks if a submit button was pressed which is supposed to be processed on client side by JS
     * but user seem to have disabled JS in the browser.
     * (buttons 'add criteria', 'add level', 'move up', 'move down', etc.)
     * In this case the form containing this element is prevented from being submitted.
     *
     * Delegates to {@see \gradingform_matrix\editor\data_processor}.
     *
     * @param array $value
     * @return boolean true if non-submit button was pressed and not processed by JS
     */
    public function non_js_button_pressed($value) {
        $this->get_processor()->prepare($value);
        return $this->get_processor()->nonjsbuttonpressed;
    }

    /**
     * Validates that rubric has at least one criterion, at least two levels within one criterion,
     * each level has a valid score, all levels have filled definitions and all criteria
     * have filled descriptions.
     *
     * Delegates to {@see \gradingform_matrix\editor\data_processor}.
     *
     * @param array $value
     * @return string|false error text or false if no errors found
     */
    public function validate($value) {
        if (!$this->wasvalidated) {
            $this->get_processor()->prepare($value, true);
            $this->wasvalidated = true;
        }
        return $this->get_processor()->validationerrors;
    }

    /**
     * Prepares the data for saving.
     *
     * Delegates to {@see \gradingform_matrix\editor\data_processor}.
     *
     * @param array $submitValues
     * @param boolean $assoc
     * @return array
     */
    public function exportValue(&$submitValues, $assoc = false) {
        $value = $this->get_processor()->prepare($this->_findValue($submitValues));
        return $this->_prepareValue($value, $assoc);
    }
}
