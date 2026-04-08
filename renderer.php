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
 * Contains renderer used for displaying rubric
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Grading method plugin renderer
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_matrix_renderer extends plugin_renderer_base {

    /** @var \gradingform_matrix\output\edit_renderer|null Lazy-loaded edit-mode HTML helper. */
    private ?\gradingform_matrix\output\edit_renderer $editrenderer = null;

    /** @var \gradingform_matrix\output\view_renderer|null Lazy-loaded view/eval-mode HTML helper. */
    private ?\gradingform_matrix\output\view_renderer $viewrenderer = null;

    /**
     * Returns the edit-mode renderer helper, creating it on first use.
     *
     * @return \gradingform_matrix\output\edit_renderer
     */
    private function get_edit_renderer(): \gradingform_matrix\output\edit_renderer {
        if ($this->editrenderer === null) {
            $this->editrenderer = new \gradingform_matrix\output\edit_renderer($this);
        }
        return $this->editrenderer;
    }

    /**
     * Returns the view/eval-mode renderer helper, creating it on first use.
     *
     * @return \gradingform_matrix\output\view_renderer
     */
    private function get_view_renderer(): \gradingform_matrix\output\view_renderer {
        if ($this->viewrenderer === null) {
            $this->viewrenderer = new \gradingform_matrix\output\view_renderer();
        }
        return $this->viewrenderer;
    }

    /**
     * This function returns html code for displaying criterion. Depending on $mode it may be the
     * code to edit rubric, to preview the rubric, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_matrix() to display the whole rubric, or it can be
     * called by itself to return a template used by JavaScript to add new empty criteria to the
     * rubric being designed.
     * In this case it will use macros like {NAME}, {LEVELS}, {CRITERION-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode rubric display mode, see {@link gradingform_matrix_controller}
     * @param array $options display options for this rubric, defaults are: {@link gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array|null $criterion criterion data
     * @param string $levelsstr evaluated templates for this criterion levels
     * @param array|null $value (only in view mode) teacher's feedback on this criterion
     * @return string
     */
    public function criterion_template($mode, $options, $elementname = '{NAME}', $criterion = null, $levelsstr = '{LEVELS}', $value = null) {
        // TODO MDL-31235 description format, remark format
        if ($criterion === null || !is_array($criterion) || !array_key_exists('id', $criterion)) {
            $criterion = array('id' => '{CRITERION-id}', 'description' => '{CRITERION-description}', 'sortorder' => '{CRITERION-sortorder}', 'class' => '{CRITERION-class}');
        } else {
            foreach (array('sortorder', 'description', 'class') as $key) {
                // set missing array elements to empty strings to avoid warnings
                if (!array_key_exists($key, $criterion)) {
                    $criterion[$key] = '';
                }
            }
        }
        $criteriontemplate = html_writer::start_tag('tr', array('class' => 'criterion'. $criterion['class'], 'id' => '{NAME}-criteria-{CRITERION-id}'));
        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
            // Controls cell (move/delete/duplicate buttons + sortorder hidden input).
            $criteriontemplate .= $this->get_edit_renderer()->criterion_controls_td($criterion);
            // Description textarea.
            $description = $this->get_edit_renderer()->criterion_description_textarea($criterion);
        } else {
            if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FROZEN) {
                // Hidden inputs to preserve sortorder and description through frozen form submission.
                $criteriontemplate .= $this->get_edit_renderer()->criterion_frozen_hidden_inputs($criterion);
            }
            $description = s($criterion['description']);
        }
        $descriptionclass = 'description';
        if (isset($criterion['error_description'])) {
            $descriptionclass .= ' error';
        }

        // Description cell params.
        $descriptiontdparams = array(
            'class' => $descriptionclass,
            'id' => '{NAME}-criteria-{CRITERION-id}-description-cell'
        );
        if ($mode != gradingform_matrix_controller::DISPLAY_EDIT_FULL &&
            $mode != gradingform_matrix_controller::DISPLAY_EDIT_FROZEN) {
            // Set description's cell as tab-focusable.
            $descriptiontdparams['tabindex'] = '0';
            // Set label for the criterion cell.
            $descriptiontdparams['aria-label'] = get_string('criterion', 'gradingform_matrix', s($criterion['description']));
        }

        // Default value for criterion ids.
        // Edge case: submitting empty grade when remark field is disabled.
        // Reason: we need the criteria keys for the clear_attempt to clear the rubric fillings.
        if ($mode == gradingform_matrix_controller::DISPLAY_EVAL) {
            $criteriontemplate .= $this->get_view_renderer()->criterion_eval_key_input();
        }

        // Description cell.
        $criteriontemplate .= html_writer::tag('td', $description, $descriptiontdparams);

        // Levels table.
        $levelsrowparams = [
            'id' => '{NAME}-criteria-{CRITERION-id}-levels',
            'aria-label' => get_string('levelsgroup', 'gradingform_matrix'),
        ];
        // Add radiogroup role only when not previewing or editing.
        $isradiogroup = !in_array($mode, [
            gradingform_matrix_controller::DISPLAY_EDIT_FULL,
            gradingform_matrix_controller::DISPLAY_EDIT_FROZEN,
            gradingform_matrix_controller::DISPLAY_PREVIEW,
            gradingform_matrix_controller::DISPLAY_PREVIEW_GRADED,
        ]);
        $levelsrowparams['role'] = $isradiogroup ? 'radiogroup' : 'list';
        $levelsrow = html_writer::tag('tr', $levelsstr, $levelsrowparams);

        $levelstableparams = [
            'id' => '{NAME}-criteria-{CRITERION-id}-levels-table',
            'role' => 'none',
        ];
        $levelsstrtable = html_writer::tag('table', $levelsrow, $levelstableparams);
        $levelsclass = 'levels';
        if (isset($criterion['error_levels'])) {
            $levelsclass .= ' error';
        }
        $criteriontemplate .= html_writer::tag('td', $levelsstrtable, array('class' => $levelsclass));
        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
            // "Add level" button cell.
            $criteriontemplate .= $this->get_edit_renderer()->criterion_addlevel_td();
        }
        // Remark cell (eval textarea, frozen hidden input, or review/view plain text).
        $criteriontemplate .= $this->get_view_renderer()->criterion_remark_cell($mode, $criterion, $value, $options);
        $criteriontemplate .= html_writer::end_tag('tr'); // .criterion

        $criteriontemplate = str_replace('{NAME}', $elementname, $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-id}', $criterion['id'], $criteriontemplate);
        return $criteriontemplate;
    }

    /**
     * This function returns html code for displaying one level of one criterion. Depending on $mode
     * it may be the code to edit rubric, to preview the rubric, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_matrix() to display the whole rubric, or it can be
     * called by itself to return a template used by JavaScript to add new empty level to the
     * criterion during the design of rubric.
     * In this case it will use macros like {NAME}, {CRITERION-id}, {LEVEL-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode rubric display mode see {@link gradingform_matrix_controller}
     * @param array $options display options for this rubric, defaults are: {@link gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param string|int $criterionid either id of the nesting criterion or a macro for template
     * @param array|null $level level data, also in view mode it might also have property $level['checked'] whether this level is checked
     * @return string
     */
    public function level_template($mode, $options, $elementname = '{NAME}', $criterionid = '{CRITERION-id}', $level = null) {
        // TODO MDL-31235 definition format
        if (!isset($level['id'])) {
            $level = array('id' => '{LEVEL-id}', 'definition' => '{LEVEL-definition}', 'score' => '{LEVEL-score}', 'class' => '{LEVEL-class}', 'checked' => false);
        } else {
            foreach (array('score', 'definition', 'class', 'checked', 'index') as $key) {
                // set missing array elements to empty strings to avoid warnings
                if (!array_key_exists($key, $level)) {
                    $level[$key] = '';
                }
            }
        }

        // Get level index.
        $levelindex = isset($level['index']) ? $level['index'] : '{LEVEL-index}';

        // Template for one level within one criterion
        $tdattributes = array(
            'id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}',
            'class' => 'text-break level' . $level['class']
        );
        if (isset($level['tdwidth'])) {
            $tdattributes['style'] = "width: " . round($level['tdwidth']).'%;';
        }

        $leveltemplate = html_writer::start_tag('div', array('class' => 'level-wrapper'));
        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
            // Definition textarea and score input for the rubric editor.
            $editparts = $this->get_edit_renderer()->level_definition_and_score($level, $levelindex);
            $definition = $editparts['definition'];
            $score      = $editparts['score'];
        } else {
            if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FROZEN) {
                // Hidden inputs to preserve definition and score through frozen form submission.
                $leveltemplate .= $this->get_edit_renderer()->level_frozen_hidden_inputs($level);
            }
            $definition = s($level['definition']);
            $score = $level['score'];
        }
        if ($mode == gradingform_matrix_controller::DISPLAY_EVAL) {
            // Radio button for selecting this level during grading.
            $leveltemplate .= $this->get_view_renderer()->level_radio_div($level);
        }
        if ($mode == gradingform_matrix_controller::DISPLAY_EVAL_FROZEN) {
            // Hidden input to preserve selected level id when form is frozen.
            $leveltemplate .= $this->get_view_renderer()->level_frozen_checked_input($level);
        }
        $score = html_writer::tag('span', $score, array('id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-score', 'class' => 'scorevalue'));
        $definitionclass = 'definition';
        if (isset($level['error_definition'])) {
            $definitionclass .= ' error';
        }

        if ($mode != gradingform_matrix_controller::DISPLAY_EDIT_FULL &&
            $mode != gradingform_matrix_controller::DISPLAY_EDIT_FROZEN) {

            $tdattributes['tabindex'] = '0';
            $levelinfo = new stdClass();
            $levelinfo->definition = s($level['definition']);
            $levelinfo->score = $level['score'];
            $tdattributes['aria-label'] = get_string('level', 'gradingform_matrix', $levelinfo);

            if ($mode != gradingform_matrix_controller::DISPLAY_PREVIEW &&
                $mode != gradingform_matrix_controller::DISPLAY_PREVIEW_GRADED) {
                // Add role of radio button to level cell if not in edit and preview mode.
                $tdattributes['role'] = 'radio';
                if ($level['checked']) {
                    $tdattributes['aria-checked'] = 'true';
                } else {
                    $tdattributes['aria-checked'] = 'false';
                }
            } else {
                $tdattributes['role'] = 'listitem';
            }
        } else {
            $tdattributes['role'] = 'listitem';
        }

        $leveltemplateparams = array(
            'id' => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-definition-container'
        );
        $leveltemplate .= html_writer::div($definition, $definitionclass, $leveltemplateparams);
        $displayscore = true;
        if (!$options['showscoreteacher'] && in_array($mode, array(gradingform_matrix_controller::DISPLAY_EVAL, gradingform_matrix_controller::DISPLAY_EVAL_FROZEN, gradingform_matrix_controller::DISPLAY_REVIEW))) {
            $displayscore = false;
        }
        if (!$options['showscorestudent'] && in_array($mode, array(gradingform_matrix_controller::DISPLAY_VIEW, gradingform_matrix_controller::DISPLAY_PREVIEW_GRADED))) {
            $displayscore = false;
        }
        if ($displayscore) {
            $scoreclass = 'score d-inline';
            if (isset($level['error_score'])) {
                $scoreclass .= ' error';
            }
            $leveltemplate .= html_writer::tag('div', get_string('scorepostfix', 'gradingform_matrix', $score), array('class' => $scoreclass));
        }
        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
            // Delete button for removing this level in the editor.
            $leveltemplate .= $this->get_edit_renderer()->level_delete_button($level, $levelindex);
        }
        $leveltemplate .= html_writer::end_tag('div'); // .level-wrapper

        $leveltemplate = html_writer::tag('td', $leveltemplate, $tdattributes); // The .level cell.

        $leveltemplate = str_replace('{NAME}', $elementname, $leveltemplate);
        $leveltemplate = str_replace('{CRITERION-id}', $criterionid, $leveltemplate);
        $leveltemplate = str_replace('{LEVEL-id}', $level['id'], $leveltemplate);
        return $leveltemplate;
    }

    /**
     * This function returns html code for displaying rubric template (content before and after
     * criteria list). Depending on $mode it may be the code to edit rubric, to preview the rubric,
     * to evaluate somebody or to review the evaluation.
     *
     * This function is called from display_matrix() to display the whole rubric.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode rubric display mode see {@link gradingform_matrix_controller}
     * @param array $options display options for this rubric, defaults are: {@link gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param string $criteriastr evaluated templates for this rubric's criteria
     * @return string
     */
    protected function matrix_template($mode, $options, $elementname, $criteriastr) {
        $classsuffix = ''; // CSS suffix for class of the main div. Depends on the mode
        switch ($mode) {
            case gradingform_matrix_controller::DISPLAY_EDIT_FULL:
                $classsuffix = ' editor editable'; break;
            case gradingform_matrix_controller::DISPLAY_EDIT_FROZEN:
                $classsuffix = ' editor frozen';  break;
            case gradingform_matrix_controller::DISPLAY_PREVIEW:
            case gradingform_matrix_controller::DISPLAY_PREVIEW_GRADED:
                $classsuffix = ' editor preview';  break;
            case gradingform_matrix_controller::DISPLAY_EVAL:
                $classsuffix = ' evaluate editable'; break;
            case gradingform_matrix_controller::DISPLAY_EVAL_FROZEN:
                $classsuffix = ' evaluate frozen';  break;
            case gradingform_matrix_controller::DISPLAY_REVIEW:
                $classsuffix = ' review';  break;
            case gradingform_matrix_controller::DISPLAY_VIEW:
                $classsuffix = ' view';  break;
        }

        $rubrictemplate = html_writer::start_tag('div', array('id' => 'matrix-{NAME}', 'class' => 'clearfix gradingform_matrix'.$classsuffix));

        // Rubric table.
        $rubrictableparams = [
            'class' => 'criteria',
            'id' => '{NAME}-criteria',
        ];
        $caption = html_writer::tag('caption', get_string('rubric', 'gradingform_matrix'), ['class' => 'visually-hidden']);
        $rubrictable = html_writer::tag('table', $caption . $criteriastr, $rubrictableparams);
        $rubrictemplate .= $rubrictable;
        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
            // "Add criterion" button below the criteria table.
            $rubrictemplate .= $this->get_edit_renderer()->matrix_addcriterion_div();
        }
        $rubrictemplate .= $this->get_edit_renderer()->matrix_options($mode, $options);
        $rubrictemplate .= html_writer::end_tag('div');

        return str_replace('{NAME}', $elementname, $rubrictemplate);
    }

    /**
     * Generates html template to view/edit the rubric options. Expression {NAME} is used in
     * template for the form element name.
     *
     * Delegates to {@see \gradingform_matrix\output\edit_renderer::matrix_options()}.
     *
     * @param int $mode rubric display mode see {@link gradingform_matrix_controller}
     * @param array $options display options for this rubric, defaults are: {@link gradingform_matrix_controller::get_default_options()}
     * @return string
     */
    protected function matrix_edit_options($mode, $options) {
        return $this->get_edit_renderer()->matrix_options($mode, $options);
    }

    /**
     * This function returns html code for displaying rubric. Depending on $mode it may be the code
     * to edit rubric, to preview the rubric, to evaluate somebody or to review the evaluation.
     *
     * It is very unlikely that this function needs to be overriden by theme. It does not produce
     * any html code, it just prepares data about rubric design and evaluation, adds the CSS
     * class to elements and calls the functions level_template, criterion_template and
     * matrix_template
     *
     * @param array $criteria data about the rubric design
     * @param array $options display options for this rubric, defaults are: {@link gradingform_matrix_controller::get_default_options()}
     * @param int $mode rubric display mode, see {@link gradingform_matrix_controller}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array $values evaluation result
     * @return string
     */
    public function display_matrix($criteria, $options, $mode, $elementname = null, $values = null) {
        $criteriastr = '';
        $cnt = 0;
        foreach ($criteria as $id => $criterion) {
            $criterion['class'] = $this->get_css_class_suffix($cnt++, sizeof($criteria) -1);
            $criterion['id'] = $id;
            $levelsstr = '';
            $levelcnt = 0;
            if (isset($values['criteria'][$id])) {
                $criterionvalue = $values['criteria'][$id];
            } else {
                $criterionvalue = null;
            }
            $index = 1;
            foreach ($criterion['levels'] as $levelid => $level) {
                $level['id'] = $levelid;
                $level['class'] = $this->get_css_class_suffix($levelcnt++, sizeof($criterion['levels']) -1);
                $level['checked'] = (isset($criterionvalue['levelid']) && ((int)$criterionvalue['levelid'] === $levelid));
                if ($level['checked'] && ($mode == gradingform_matrix_controller::DISPLAY_EVAL_FROZEN || $mode == gradingform_matrix_controller::DISPLAY_REVIEW || $mode == gradingform_matrix_controller::DISPLAY_VIEW)) {
                    $level['class'] .= ' checked';
                    //in mode DISPLAY_EVAL the class 'checked' will be added by JS if it is enabled. If JS is not enabled, the 'checked' class will only confuse
                }
                if (isset($criterionvalue['savedlevelid']) && ((int)$criterionvalue['savedlevelid'] === $levelid)) {
                    $level['class'] .= ' currentchecked';
                }
                $level['tdwidth'] = 100/count($criterion['levels']);
                $level['index'] = $index;
                $levelsstr .= $this->level_template($mode, $options, $elementname, $id, $level);
                $index++;
            }
            $criteriastr .= $this->criterion_template($mode, $options, $elementname, $criterion, $levelsstr, $criterionvalue);
        }
        return $this->matrix_template($mode, $options, $elementname, $criteriastr);
    }

    /**
     * Help function to return CSS class names for element (first/last/even/odd) with leading space
     *
     * @param int $idx index of this element in the row/column
     * @param int $maxidx maximum index of the element in the row/column
     * @return string
     */
    protected function get_css_class_suffix($idx, $maxidx) {
        $class = '';
        if ($idx == 0) {
            $class .= ' first';
        }
        if ($idx == $maxidx) {
            $class .= ' last';
        }
        if ($idx%2) {
            $class .= ' odd';
        } else {
            $class .= ' even';
        }
        return $class;
    }

    /**
     * Displays for the student the list of instances or default content if no instances found
     *
     * @param array $instances array of objects of type gradingform_matrix_instance
     * @param string $defaultcontent default string that would be displayed without advanced grading
     * @param boolean $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function display_instances($instances, $defaultcontent, $cangrade) {
        $return = '';
        if (sizeof($instances)) {
            $return .= html_writer::start_tag('div', array('class' => 'advancedgrade'));
            $idx = 0;
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance, $idx++, $cangrade);
            }
            $return .= html_writer::end_tag('div');
        }
        return $return. $defaultcontent;
    }

    /**
     * Displays one grading instance
     *
     * @param gradingform_matrix_instance $instance
     * @param int $idx unique number of instance on page
     * @param bool $cangrade whether current user has capability to grade in this context
     */
    public function display_instance(gradingform_matrix_instance $instance, $idx, $cangrade) {
        $criteria = $instance->get_controller()->get_definition()->matrix_criteria;
        $options = $instance->get_controller()->get_options();
        $values = $instance->get_matrix_filling();
        if ($cangrade) {
            $mode = gradingform_matrix_controller::DISPLAY_REVIEW;
            $showdescription = $options['showdescriptionteacher'];
        } else {
            $mode = gradingform_matrix_controller::DISPLAY_VIEW;
            $showdescription = $options['showdescriptionstudent'];
        }
        $output = '';
        if ($showdescription) {
            $output .= $this->box($instance->get_controller()->get_formatted_description(), 'gradingform_matrix-description');
        }
        $output .= $this->display_matrix($criteria, $options, $mode, 'matrix'.$idx, $values);
        return $output;
    }

    /**
     * Displays confirmation that students require re-grading
     *
     * @param string $elementname
     * @param int $changelevel
     * @param string $value
     * @return string
     */
    public function display_regrade_confirmation($elementname, $changelevel, $value) {
        $html = html_writer::start_tag('div', array('class' => 'gradingform_matrix-regrade', 'role' => 'alert'));
        if ($changelevel<=2) {
            $html .= html_writer::label(get_string('regrademessage1', 'gradingform_matrix'), 'menu' . $elementname . 'regrade');
            $selectoptions = array(
                0 => get_string('regradeoption0', 'gradingform_matrix'),
                1 => get_string('regradeoption1', 'gradingform_matrix')
            );
            $html .= html_writer::select($selectoptions, $elementname.'[regrade]', $value, false);
        } else {
            $html .= get_string('regrademessage5', 'gradingform_matrix');
            $html .= html_writer::empty_tag('input', array('name' => $elementname.'[regrade]', 'value' => 1, 'type' => 'hidden'));
        }
        $html .= html_writer::end_tag('div');
        return $html;
    }

    /**
     * Generates and returns HTML code to display information box about how rubric score is converted to the grade
     *
     * @param array $scores
     * @return string
     */
    public function display_matrix_mapping_explained($scores) {
        $html = '';
        if (!$scores) {
            return $html;
        }
        if ($scores['minscore'] <> 0) {
            $html .= $this->output->notification(get_string('zerolevelsabsent', 'gradingform_matrix'), 'error');
        }
        $html .= $this->output->notification(get_string('rubricmappingexplained', 'gradingform_matrix', (object)$scores), 'info');
        return $html;
    }
}
