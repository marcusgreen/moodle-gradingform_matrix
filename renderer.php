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
 * Contains renderer used for displaying matrix
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Grading method plugin renderer
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_matrix_renderer extends plugin_renderer_base {
    /**
     * This function returns html code for displaying criterion. Depending on $mode it may be the
     * code to edit matrix, to preview the matrix, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_matrix() to display the whole matrix, or it can be
     * called by itself to return a template used by JavaScript to add new empty criteria to the
     * matrix being designed.
     * In this case it will use macros like {NAME}, {LEVELS}, {CRITERION-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode matrix display mode, see {@see gradingform_matrix_controller}
     * @param array $options display options for this matrix, defaults are:
     *     {@see gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array|null $criterion criterion data
     * @param string $levelsstr evaluated templates for this criterion levels
     * @param array|null $value (only in view mode) teacher's feedback on this criterion
     * @return string
     */
    public function criterion_template(
        $mode,
        $options,
        $elementname = '{NAME}',
        $criterion = null,
        $levelsstr = '{LEVELS}',
        $value = null
    ) {
        return $this->render_from_template(
            'gradingform_matrix/criterion',
            $this->criterion_context($mode, $options, $elementname, $criterion, $levelsstr, $value)
        );
    }

    /**
     * Builds the context for the gradingform_matrix/criterion template, see {@see criterion_template()}.
     *
     * @param int $mode matrix display mode
     * @param array $options display options for this matrix
     * @param string $elementname the name of the form element or the prefix for ids
     * @param array|null $criterion criterion data, or null for the template with placeholders
     * @param string $levelsstr rendered levels of this criterion
     * @param array|null $value teacher's feedback on this criterion
     * @return array
     */
    protected function criterion_context($mode, $options, $elementname, $criterion, $levelsstr, $value): array {
        // TODO MDL-31235 description format, remark format.
        // Until per-field formats are stored, criterion descriptions and level definitions are
        // rendered as Markdown in display modes. Edit textareas and round-trip hidden inputs keep
        // the raw text, and aria-labels stay plain text.
        if ($criterion === null || !is_array($criterion) || !array_key_exists('id', $criterion)) {
            $criterion = [
                'id' => '{CRITERION-id}',
                'description' => '{CRITERION-description}',
                'sortorder' => '{CRITERION-sortorder}',
                'class' => '{CRITERION-class}',
            ];
        } else {
            foreach (['sortorder', 'description', 'class'] as $key) {
                // Set missing array elements to empty strings to avoid warnings.
                if (!array_key_exists($key, $criterion)) {
                    $criterion[$key] = '';
                }
            }
        }
        $c = gradingform_matrix_controller::class;
        $editing = in_array($mode, [$c::DISPLAY_EDIT_FULL, $c::DISPLAY_EDIT_FROZEN]);
        $prefix = "{$elementname}-criteria-{$criterion['id']}";
        $field = "{$elementname}[criteria][{$criterion['id']}]";

        $context = [
            'id' => $prefix,
            'class' => 'criterion' . $criterion['class'],
            'hiddeninputs' => [],
        ];
        $description = [
            'id' => "{$prefix}-description-cell",
            'class' => 'description' . (isset($criterion['error_description']) ? ' error' : ''),
        ];
        if ($mode == $c::DISPLAY_EDIT_FULL) {
            $context['controls'] = [
                'buttons' => array_map(fn($key) => [
                    'key' => $key,
                    'name' => "{$field}[{$key}]",
                    'id' => "{$prefix}-{$key}",
                    'value' => get_string('criterion' . $key, 'gradingform_matrix'),
                ], ['moveup', 'delete', 'movedown', 'duplicate']),
                'sortorder' => ['name' => "{$field}[sortorder]", 'value' => $criterion['sortorder']],
            ];
            $description['textarea'] = [
                'name' => "{$field}[description]",
                'id' => "{$prefix}-description",
                'arialabel' => get_string('criterion', 'gradingform_matrix', ''),
                'text' => $criterion['description'],
            ];
        } else {
            if ($mode == $c::DISPLAY_EDIT_FROZEN) {
                $context['hiddeninputs'][] = ['name' => "{$field}[sortorder]", 'hasvalue' => true,
                    'value' => $criterion['sortorder']];
                $context['hiddeninputs'][] = ['name' => "{$field}[description]", 'hasvalue' => true,
                    'value' => $criterion['description']];
            }
            $description['html'] = format_text($criterion['description'], FORMAT_MARKDOWN);
        }
        if (!$editing) {
            $description['focusable'] = true;
            $description['arialabel'] = get_string('criterion', 'gradingform_matrix', $this->plain_text($criterion['description']));
        }
        $context['description'] = $description;

        // Edge case: submitting empty grade when remark field is disabled.
        // Reason: we need the criteria keys for the clear_attempt to clear the matrix fillings.
        if ($mode == $c::DISPLAY_EVAL) {
            $context['hiddeninputs'][] = ['name' => "{$field}[]", 'hasvalue' => false];
        }

        // Add radiogroup role only when not previewing or editing.
        $isradiogroup = !in_array($mode, [$c::DISPLAY_EDIT_FULL, $c::DISPLAY_EDIT_FROZEN, $c::DISPLAY_PREVIEW,
            $c::DISPLAY_PREVIEW_GRADED]);
        $context['levels'] = [
            'class' => 'levels' . (isset($criterion['error_levels']) ? ' error' : ''),
            'tableid' => "{$prefix}-levels-table",
            'rowid' => "{$prefix}-levels",
            'role' => $isradiogroup ? 'radiogroup' : 'list',
            // Named after its criterion, so moving between criteria says which one this is.
            'arialabelledby' => $isradiogroup ? "{$prefix}-description-cell" : null,
            'arialabel' => $isradiogroup ? null : get_string('levelsgroup', 'gradingform_matrix'),
            'html' => $levelsstr,
        ];
        if ($mode == $c::DISPLAY_EDIT_FULL) {
            $context['addlevel'] = [
                'name' => "{$field}[levels][addlevel]",
                'id' => "{$prefix}-levels-addlevel",
                'value' => get_string('criterionaddlevel', 'gradingform_matrix'),
            ];
        }

        $displayremark = $options['enableremarks'] && ($mode != $c::DISPLAY_VIEW || $options['showremarksstudent']);
        if ($displayremark) {
            $currentremark = $value['remark'] ?? '';
            $remarkinfo = (object)['description' => $this->plain_text($criterion['description']), 'remark' => $currentremark];
            $remarklabel = get_string('criterionremark', 'gradingform_matrix', $remarkinfo);
            if ($mode == $c::DISPLAY_EVAL) {
                $context['remark'] = ['textarea' => ['name' => "{$field}[remark]", 'id' => "{$prefix}-remark",
                    'arialabel' => $remarklabel, 'text' => $currentremark]];
            } else if ($mode == $c::DISPLAY_EVAL_FROZEN) {
                $context['remark'] = ['hidden' => ['name' => "{$field}[remark]", 'value' => $currentremark]];
            } else if (in_array($mode, [$c::DISPLAY_REVIEW, $c::DISPLAY_VIEW])) {
                $context['remark'] = ['cell' => ['id' => "{$prefix}-remark", 'arialabel' => $remarklabel,
                    'text' => $currentremark]];
            }
        }
        return $context;
    }

    /**
     * This function returns html code for displaying one level of one criterion. Depending on $mode
     * it may be the code to edit matrix, to preview the matrix, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_matrix() to display the whole matrix, or it can be
     * called by itself to return a template used by JavaScript to add new empty level to the
     * criterion during the design of matrix.
     * In this case it will use macros like {NAME}, {CRITERION-id}, {LEVEL-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode matrix display mode see {@see gradingform_matrix_controller}
     * @param array $options display options for this matrix, defaults are:
     *     {@see gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param string|int $criterionid either id of the nesting criterion or a macro for template
     * @param array|null $level level data, also in view mode it might also have property $level['checked'] whether this
     *     level is checked
     * @return string
     */
    public function level_template($mode, $options, $elementname = '{NAME}', $criterionid = '{CRITERION-id}', $level = null) {
        return $this->render_from_template(
            'gradingform_matrix/level',
            $this->level_context($mode, $options, $elementname, $criterionid, $level)
        );
    }

    /**
     * Builds the context for the gradingform_matrix/level template, see {@see level_template()}.
     *
     * @param int $mode matrix display mode
     * @param array $options display options for this matrix
     * @param string $elementname the name of the form element or the prefix for ids
     * @param string|int $criterionid id of the criterion, or a placeholder
     * @param array|null $level level data, or null for the template with placeholders
     * @return array
     */
    protected function level_context($mode, $options, $elementname, $criterionid, $level): array {
        // TODO MDL-31235 definition format.
        if (!isset($level['id'])) {
            $level = [
                'id' => '{LEVEL-id}',
                'definition' => '{LEVEL-definition}',
                'score' => '{LEVEL-score}',
                'class' => '{LEVEL-class}',
                'checked' => false,
            ];
        } else {
            foreach (['score', 'definition', 'class', 'checked', 'index'] as $key) {
                // Set missing array elements to empty strings to avoid warnings.
                if (!array_key_exists($key, $level)) {
                    $level[$key] = '';
                }
            }
        }
        $c = gradingform_matrix_controller::class;
        $levelindex = isset($level['index']) ? $level['index'] : '{LEVEL-index}';
        $prefix = "{$elementname}-criteria-{$criterionid}-levels-{$level['id']}";
        $field = "{$elementname}[criteria][{$criterionid}][levels][{$level['id']}]";

        $context = [
            'id' => $prefix,
            'class' => 'text-break level' . $level['class'],
            'role' => 'listitem',
            'hiddeninputs' => [],
        ];
        if (isset($level['tdwidth'])) {
            $context['style'] = 'width: ' . round($level['tdwidth']) . '%;';
        }
        $definition = [
            'id' => "{$prefix}-definition-container",
            'class' => 'definition' . (isset($level['error_definition']) ? ' error' : ''),
        ];
        $score = ['spanid' => "{$prefix}-score"];
        if ($mode == $c::DISPLAY_EDIT_FULL) {
            $definition['textarea'] = [
                'id' => "{$prefix}-definition",
                'name' => "{$field}[definition]",
                'arialabel' => get_string('leveldefinition', 'gradingform_matrix', $levelindex),
                'text' => $level['definition'],
            ];
            $score['input'] = [
                'id' => "{$field}[score]",
                'name' => "{$field}[score]",
                'arialabel' => get_string('scoreinputforlevel', 'gradingform_matrix', $levelindex),
                'value' => $level['score'],
            ];
        } else {
            if ($mode == $c::DISPLAY_EDIT_FROZEN) {
                $context['hiddeninputs'][] = ['name' => "{$field}[definition]", 'value' => $level['definition']];
                $context['hiddeninputs'][] = ['name' => "{$field}[score]", 'value' => $level['score']];
            }
            $definition['html'] = format_text($level['definition'], FORMAT_MARKDOWN);
            $score['value'] = $level['score'];
        }
        if ($mode == $c::DISPLAY_EVAL) {
            $context['radio'] = [
                'id' => "{$prefix}-definition",
                'name' => "{$elementname}[criteria][{$criterionid}][levelid]",
                'value' => $level['id'],
                'checked' => (bool)$level['checked'],
            ];
        }
        if ($mode == $c::DISPLAY_EVAL_FROZEN && $level['checked']) {
            $context['hiddeninputs'][] = ['name' => "{$elementname}[criteria][{$criterionid}][levelid]",
                'value' => $level['id']];
        }
        if (!in_array($mode, [$c::DISPLAY_EDIT_FULL, $c::DISPLAY_EDIT_FROZEN])) {
            $context['focusable'] = true;
            $levelinfo = (object)['definition' => $this->plain_text($level['definition']), 'score' => $level['score']];
            $context['arialabel'] = get_string('level', 'gradingform_matrix', $levelinfo);
            if (!in_array($mode, [$c::DISPLAY_PREVIEW, $c::DISPLAY_PREVIEW_GRADED])) {
                // Add role of radio button to level cell if not in edit and preview mode.
                $context['role'] = 'radio';
                $context['ariachecked'] = $level['checked'] ? 'true' : 'false';
            }
        }
        $context['definition'] = $definition;

        $displayscore = true;
        if (!$options['showscoreteacher'] && in_array($mode, [$c::DISPLAY_EVAL, $c::DISPLAY_EVAL_FROZEN, $c::DISPLAY_REVIEW])) {
            $displayscore = false;
        }
        if (!$options['showscorestudent'] && in_array($mode, [$c::DISPLAY_VIEW, $c::DISPLAY_PREVIEW_GRADED])) {
            $displayscore = false;
        }
        if ($displayscore) {
            // The language string places the score, so split it around the score's position.
            $marker = '{SCORE}';
            [$score['before'], $score['after']] = explode($marker, get_string('scorepostfix', 'gradingform_matrix', $marker), 2);
            $score['class'] = 'score d-inline' . (isset($level['error_score']) ? ' error' : '');
            $context['score'] = $score;
        }
        if ($mode == $c::DISPLAY_EDIT_FULL) {
            $context['delete'] = [
                'name' => "{$field}[delete]",
                'id' => "{$prefix}-delete",
                'value' => get_string('leveldelete', 'gradingform_matrix', $levelindex),
            ];
        }
        return $context;
    }

    /**
     * This function returns html code for displaying matrix template (content before and after
     * criteria list). Depending on $mode it may be the code to edit matrix, to preview the matrix,
     * to evaluate somebody or to review the evaluation.
     *
     * This function is called from display_matrix() to display the whole matrix.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode matrix display mode see {@see gradingform_matrix_controller}
     * @param array $options display options for this matrix, defaults are:
     *     {@see gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param string $criteriastr evaluated templates for this matrix's criteria
     * @return string
     */
    protected function matrix_template($mode, $options, $elementname, $criteriastr) {
        $c = gradingform_matrix_controller::class;
        $classsuffixes = [
            $c::DISPLAY_EDIT_FULL => ' editor editable',
            $c::DISPLAY_EDIT_FROZEN => ' editor frozen',
            $c::DISPLAY_PREVIEW => ' editor preview',
            $c::DISPLAY_PREVIEW_GRADED => ' editor preview',
            $c::DISPLAY_EVAL => ' evaluate editable',
            $c::DISPLAY_EVAL_FROZEN => ' evaluate frozen',
            $c::DISPLAY_REVIEW => ' review',
            $c::DISPLAY_VIEW => ' view',
        ];
        $context = [
            'id' => "matrix-{$elementname}",
            'class' => 'clearfix gradingform_matrix' . ($classsuffixes[$mode] ?? ''),
            'tableid' => "{$elementname}-criteria",
            'criteria' => $criteriastr,
            'options' => $this->options_context($mode, $options, $elementname),
        ];
        if ($mode == $c::DISPLAY_EDIT_FULL) {
            $context['addcriterion'] = [
                'name' => "{$elementname}[criteria][addcriterion]",
                'id' => "{$elementname}-criteria-addcriterion",
                'value' => get_string('addcriterion', 'gradingform_matrix'),
            ];
        }
        return $this->render_from_template('gradingform_matrix/matrix', $context);
    }

    /**
     * Builds the context for the gradingform_matrix/options template: the options, for people who can manage the form.
     *
     * @param int $mode matrix display mode see {@see gradingform_matrix_controller}
     * @param array $options display options for this matrix, defaults are:
     *     {@see gradingform_matrix_controller::get_default_options()}
     * @param string $elementname the name of the form element
     * @return array|null null when the options are not shown in this mode
     */
    protected function options_context($mode, $options, $elementname): ?array {
        $c = gradingform_matrix_controller::class;
        if (!in_array($mode, [$c::DISPLAY_EDIT_FULL, $c::DISPLAY_EDIT_FROZEN, $c::DISPLAY_PREVIEW])) {
            return null;
        }
        $items = [];
        foreach ($options as $option => $value) {
            $name = "{$elementname}[options][{$option}]";
            $id = "{$elementname}-options-{$option}";
            $item = ['class' => 'option ' . $option];
            if ($option === 'sortlevelsasc') {
                // Display option as dropdown.
                $value = (int)(!!$value);
                $item['select'] = ['id' => $id, 'label' => get_string($option, 'gradingform_matrix')];
                if ($mode == $c::DISPLAY_EDIT_FULL) {
                    $choices = [
                        0 => get_string($option . '0', 'gradingform_matrix'),
                        1 => get_string($option . '1', 'gradingform_matrix'),
                    ];
                    $item['select']['selecthtml'] = html_writer::select($choices, $name, $value, false, ['id' => $id]);
                } else {
                    $item['select']['valuetext'] = get_string($option . $value, 'gradingform_matrix');
                    if ($mode == $c::DISPLAY_EDIT_FROZEN) {
                        $item['hidden'] = ['name' => $name, 'id' => $id, 'value' => $value];
                    }
                }
            } else {
                // Display option as checkbox.
                if ($mode == $c::DISPLAY_EDIT_FROZEN && $value) {
                    // Id should be different then the actual input added later.
                    $item['hidden'] = ['name' => $name, 'id' => "{$id}_hidden", 'value' => $value];
                }
                $disabled = in_array($mode, [$c::DISPLAY_EDIT_FROZEN, $c::DISPLAY_PREVIEW]);
                $item['checkbox'] = [
                    // Disabled checkboxes are not submitted; the hidden input above carries the value.
                    'name' => $disabled ? null : $name,
                    'id' => $disabled ? "{$id}_disabled" : $id,
                    'checked' => (bool)$value,
                    'disabled' => $disabled,
                    'label' => get_string($option, 'gradingform_matrix'),
                ];
            }
            if (get_string_manager()->string_exists($option . '_help', 'gradingform_matrix')) {
                $item['helphtml'] = $this->help_icon($option, 'gradingform_matrix');
            }
            $items[] = $item;
        }
        return ['items' => $items];
    }

    /**
     * This function returns html code for displaying matrix. Depending on $mode it may be the code
     * to edit matrix, to preview the matrix, to evaluate somebody or to review the evaluation.
     *
     * It is very unlikely that this function needs to be overriden by theme. It does not produce
     * any html code, it just prepares data about matrix design and evaluation, adds the CSS
     * class to elements and calls the functions level_template, criterion_template and
     * matrix_template
     *
     * @param array $criteria data about the matrix design
     * @param array $options display options for this matrix, defaults are:
     *     {@see gradingform_matrix_controller::get_default_options()}
     * @param int $mode matrix display mode, see {@see gradingform_matrix_controller}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array $values evaluation result
     * @return string
     */
    public function display_matrix($criteria, $options, $mode, $elementname = null, $values = null) {
        $criteriastr = '';
        $cnt = 0;
        foreach ($criteria as $id => $criterion) {
            $criterion['class'] = $this->get_css_class_suffix($cnt++, count($criteria) - 1);
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
                $level['class'] = $this->get_css_class_suffix($levelcnt++, count($criterion['levels']) - 1);
                $level['checked'] = (isset($criterionvalue['levelid']) && ((int)$criterionvalue['levelid'] === $levelid));
                $checkedmodes = [
                    gradingform_matrix_controller::DISPLAY_EVAL_FROZEN,
                    gradingform_matrix_controller::DISPLAY_REVIEW,
                    gradingform_matrix_controller::DISPLAY_VIEW,
                ];
                if ($level['checked'] && in_array($mode, $checkedmodes)) {
                    $level['class'] .= ' checked';
                    // In mode DISPLAY_EVAL the class 'checked' will be added by JS if it is enabled. If JS is not enabled,
                    // the 'checked' class will only confuse.
                }
                if (isset($criterionvalue['savedlevelid']) && ((int)$criterionvalue['savedlevelid'] === $levelid)) {
                    $level['class'] .= ' currentchecked';
                }
                $level['tdwidth'] = 100 / count($criterion['levels']);
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
        if ($idx % 2) {
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
        if (count($instances)) {
            $return .= html_writer::start_tag('div', ['class' => 'advancedgrade']);
            $idx = 0;
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance, $idx++, $cangrade);
            }
            $return .= html_writer::end_tag('div');
        }
        return $return . $defaultcontent;
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
        $output .= $this->display_matrix($criteria, $options, $mode, 'matrix' . $idx, $values);
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
        $context = [];
        if ($changelevel <= 2) {
            $choices = [
                0 => get_string('regradeoption0', 'gradingform_matrix'),
                1 => get_string('regradeoption1', 'gradingform_matrix'),
            ];
            $context['choice'] = [
                'selectid' => 'menu' . $elementname . 'regrade',
                'selecthtml' => html_writer::select($choices, $elementname . '[regrade]', $value, false),
            ];
        } else {
            $context['forced'] = ['name' => $elementname . '[regrade]'];
        }
        return $this->render_from_template('gradingform_matrix/regrade_confirmation', $context);
    }

    /**
     * Generates and returns HTML code to display information box about how matrix score is converted to the grade
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
        $html .= $this->output->notification(get_string('scoremappingexplained', 'gradingform_matrix', (object)$scores), 'info');
        return $html;
    }

    /**
     * Converts criterion or level text to plain text for an accessible name.
     *
     * The text is Markdown, so the raw value would make screen readers read the markup
     * ("star star"). Blocks such as paragraphs are joined with commas. The result is not
     * escaped, because html_writer escapes attribute values.
     *
     * @param string $text
     * @return string
     */
    protected function plain_text(string $text): string {
        $html = format_text($text, FORMAT_MARKDOWN, ['context' => $this->page->context]);
        $html = preg_replace('~</(p|li|h[1-6]|div|tr|td|th)>|<br\s*/?>~i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_filter(array_map(fn($line) => trim(preg_replace('/\s+/u', ' ', $line)), explode("\n", $text)));
        return implode(', ', $lines);
    }
}
