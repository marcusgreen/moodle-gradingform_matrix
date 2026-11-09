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

// The QuickForm methods this element overrides (toHtml(), exportValue() and others) are camelCase.
// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

/**
 * Form element for handling matrix editor
 *
 * The matrix editor is defined as a separate form element. This allows us to render
 * criteria, levels and buttons using the matrix's own renderer. Also, the required
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
    public $_helpbutton = ''; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
    /** @var string|bool stores the result of the last validation: null - undefined, false - no errors, string - error(s) text */
    protected $validationerrors = null;
    /** @var bool if element has already been validated **/
    protected $wasvalidated = false;
    /** @var bool|null If non-submit (JS) button was pressed: null - unknown, true/false - button was/wasn't pressed */
    protected $nonjsbuttonpressed = null;
    /** @var bool Message to display in front of the editor (that there exist grades on this matrix being edited) */
    protected $regradeconfirmation = false;
    /** @var array|null data from the last validation, with error flags for display; null - not validated */
    protected $validateddata = null;
    /** @var bool if the editor JavaScript has been requested, so it is initialised only once */
    protected $jsrequested = false;

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
     * Specifies that confirmation about re-grading needs to be added to this matrix editor.
     * $changelevel is saved in $this->regradeconfirmation and retrieved in toHtml()
     *
     * @see gradingform_matrix_controller::update_or_check_matrix()
     * @param int $changelevel
     */
    public function add_regrade_confirmation($changelevel) {
        $this->regradeconfirmation = $changelevel;
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
        // Render what was validated, if anything, so the fields with errors are marked.
        // Rendering itself never validates, so it does not change the validation errors.
        $data = $this->validateddata ?? $this->prepare_data();
        if (!$this->_flagFrozen) {
            $mode = gradingform_matrix_controller::DISPLAY_EDIT_FULL;
            // The criterion/level templates are too long to pass as JS arguments, so they are
            // rendered into hidden <script> holders and read from the DOM by the AMD module.
            $name = $this->getName();
            $html .= html_writer::tag(
                'script',
                $renderer->criterion_template($mode, $data['options'], $name),
                ['type' => 'text/template', 'id' => 'matrix-' . $name . '-criterion-template']
            );
            $html .= html_writer::tag(
                'script',
                $renderer->level_template($mode, $data['options'], $name),
                ['type' => 'text/template', 'id' => 'matrix-' . $name . '-level-template']
            );
            // Initialising twice would attach every editor handler twice.
            if (!$this->jsrequested) {
                $PAGE->requires->js_call_amd('gradingform_matrix/matrixeditor', 'init', [$name]);
                $this->jsrequested = true;
            }
        } else {
            // Matrix is frozen, no javascript needed.
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
        if ($this->validationerrors) {
            $html .= html_writer::div($renderer->notification($this->validationerrors));
        }
        $html .= $renderer->display_matrix($data['criteria'], $data['options'], $mode, $this->getName());
        return $html;
    }

    /**
     * Prepares the data passed in $_POST:
     * - processes the pressed buttons 'addlevel', 'addcriterion', 'moveup', 'movedown', 'delete' (when JavaScript is disabled)
     *   sets $this->nonjsbuttonpressed to true/false if such button was pressed
     * - if options not passed (i.e. we create a new matrix) fills the options array with the default values
     * - if options are passed completes the options array with unchecked checkboxes
     * - if $withvalidation is set, adds 'error_xxx' attributes to elements that contain errors and creates an error string
     *   and stores it in $this->validationerrors, see {@see \gradingform_matrix\local\framework\definition_validator}
     *
     * @param array $value
     * @param boolean $withvalidation whether to enable data validation
     * @return array
     */
    protected function prepare_data($value = null, $withvalidation = false) {
        if (null === $value) {
            $value = $this->getValue();
        }
        if ($this->nonjsbuttonpressed === null) {
            $this->nonjsbuttonpressed = false;
        }
        $return = ['criteria' => [], 'options' => gradingform_matrix_controller::get_default_options()];
        if (!isset($value['criteria'])) {
            $value['criteria'] = [];
        }
        // If options are present in $value, replace default values with submitted values.
        if (!empty($value['options'])) {
            foreach (array_keys($return['options']) as $option) {
                // Special treatment for checkboxes.
                if (!empty($value['options'][$option])) {
                    $return['options'][$option] = $value['options'][$option];
                } else {
                    $return['options'][$option] = null;
                }
            }
        }
        if (is_array($value)) {
            // For other array keys of $value no special treatment needed, copy them to return value as is.
            foreach (array_keys($value) as $key) {
                if ($key != 'options' && $key != 'criteria') {
                    $return[$key] = $value[$key];
                }
            }
        }

        // Iterate through criteria.
        $lastaction = null;
        $lastid = null;
        foreach ($value['criteria'] as $id => $criterion) {
            if ($id == 'addcriterion') {
                $id = $this->get_next_id(array_keys($value['criteria']));
                $criterion = ['description' => '', 'levels' => []];
                $i = 0;
                // When adding new criterion copy the number of levels and their scores from the last criterion.
                if (!empty($value['criteria'][$lastid]['levels'])) {
                    foreach ($value['criteria'][$lastid]['levels'] as $lastlevel) {
                        $criterion['levels']['NEWID' . ($i++)]['score'] = $lastlevel['score'];
                    }
                } else {
                    $criterion['levels']['NEWID' . ($i++)]['score'] = 0;
                }
                // Add more levels so there are at least 3 in the new criterion. Increment by 1 the score for each next one.
                for ($i = $i; $i < 3; $i++) {
                    $criterion['levels']['NEWID' . $i]['score'] = $criterion['levels']['NEWID' . ($i - 1)]['score'] + 1;
                }
                // Set other necessary fields (definition) for the levels in the new criterion.
                foreach (array_keys($criterion['levels']) as $i) {
                    $criterion['levels'][$i]['definition'] = '';
                }
                $this->nonjsbuttonpressed = true;
            }
            $levels = [];
            if (array_key_exists('levels', $criterion)) {
                foreach ($criterion['levels'] as $levelid => $level) {
                    if ($levelid == 'addlevel') {
                        $levelid = $this->get_next_id(array_keys($criterion['levels']));
                        $level = [
                            'definition' => '',
                            'score' => 0,
                        ];
                        foreach ($criterion['levels'] as $lastlevel) {
                            if (isset($lastlevel['score'])) {
                                $level['score'] = max($level['score'], ceil(unformat_float($lastlevel['score'])) + 1);
                            }
                        }
                        $this->nonjsbuttonpressed = true;
                    }
                    if (!array_key_exists('delete', $level)) {
                        $levels[$levelid] = $level;
                    } else {
                        $this->nonjsbuttonpressed = true;
                    }
                }
            }
            $criterion['levels'] = $levels;
            if (array_key_exists('moveup', $criterion) || $lastaction == 'movedown') {
                unset($criterion['moveup']);
                if ($lastid !== null) {
                    $lastcriterion = $return['criteria'][$lastid];
                    unset($return['criteria'][$lastid]);
                    $return['criteria'][$id] = $criterion;
                    $return['criteria'][$lastid] = $lastcriterion;
                } else {
                    $return['criteria'][$id] = $criterion;
                }
                $lastaction = null;
                $lastid = $id;
                $this->nonjsbuttonpressed = true;
            } else if (array_key_exists('delete', $criterion)) {
                $this->nonjsbuttonpressed = true;
            } else {
                if (array_key_exists('movedown', $criterion)) {
                    unset($criterion['movedown']);
                    $lastaction = 'movedown';
                    $this->nonjsbuttonpressed = true;
                }
                $return['criteria'][$id] = $criterion;
                $lastid = $id;
            }
        }

        // Add sort order field to criteria.
        $csortorder = 1;
        foreach (array_keys($return['criteria']) as $id) {
            $return['criteria'][$id]['sortorder'] = $csortorder++;
        }

        // Create validation error string (if needed).
        if ($withvalidation) {
            $validator = new \gradingform_matrix\local\framework\definition_validator();
            $result = $validator->validate($return['criteria'], $return['options']);
            $return['criteria'] = $result['criteria'];
            if ($result['errors']) {
                $rv = [];
                foreach ($result['errors'] as $error) {
                    $rv[] = get_string($error, 'gradingform_matrix');
                }
                $this->validationerrors = join('<br/ >', $rv);
            } else {
                $this->validationerrors = false;
            }
            $this->wasvalidated = true;
            $this->validateddata = $return;
        }
        return $return;
    }

    /**
     * Scans array $ids to find the biggest element ! NEWID*, increments it by 1 and returns
     *
     * @param array $ids
     * @return string
     */
    protected function get_next_id($ids) {
        $maxid = 0;
        foreach ($ids as $id) {
            if (preg_match('/^NEWID(\d+)$/', $id, $matches) && ((int)$matches[1]) > $maxid) {
                $maxid = (int)$matches[1];
            }
        }
        return 'NEWID' . ($maxid + 1);
    }

    /**
     * Checks if a submit button was pressed which is supposed to be processed on client side by JS
     * but user seem to have disabled JS in the browser.
     * (buttons 'add criteria', 'add level', 'move up', 'move down', etc.)
     * In this case the form containing this element is prevented from being submitted
     *
     * @param array $value
     * @return boolean true if non-submit button was pressed and not processed by JS
     */
    public function non_js_button_pressed($value) {
        if ($this->nonjsbuttonpressed === null) {
            $this->prepare_data($value);
        }
        return $this->nonjsbuttonpressed;
    }

    /**
     * Validates that matrix has at least one criterion, at least two levels within one criterion,
     * each level has a valid score, all levels have filled definitions and all criteria
     * have filled descriptions
     *
     * @param array $value
     * @return string|false error text or false if no errors found
     */
    public function validate($value) {
        if (!$this->wasvalidated) {
            $this->prepare_data($value, true);
        }
        return $this->validationerrors;
    }

    /**
     * Prepares the data for saving
     *
     * @see prepare_data()
     * @param array $submitvalues
     * @param boolean $assoc
     * @return array
     */
    public function exportValue(&$submitvalues, $assoc = false) {
        $value = $this->prepare_data($this->_findValue($submitvalues));
        return $this->_prepareValue($value, $assoc);
    }
}
