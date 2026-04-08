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
 * Contains the edit-mode HTML generation helper for the matrix grading form.
 *
 * This class is not a Moodle renderer and is not registered with $PAGE.
 * It is instantiated and used internally by gradingform_matrix_renderer
 * to generate HTML that is specific to DISPLAY_EDIT_FULL and DISPLAY_EDIT_FROZEN modes.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_matrix\output;

defined('MOODLE_INTERNAL') || die();

use gradingform_matrix_controller;
use html_writer;
use plugin_renderer_base;

/**
 * Generates HTML fragments used exclusively in the rubric definition editor
 * (DISPLAY_EDIT_FULL and DISPLAY_EDIT_FROZEN modes).
 */
class edit_renderer {

    /** @var plugin_renderer_base The parent Moodle renderer, used for help_icon() calls. */
    protected plugin_renderer_base $parentrenderer;

    /**
     * Constructor.
     *
     * @param plugin_renderer_base $parentrenderer The registered Moodle renderer for this plugin.
     */
    public function __construct(plugin_renderer_base $parentrenderer) {
        $this->parentrenderer = $parentrenderer;
    }

    // -------------------------------------------------------------------------
    // Criterion helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the controls <td> (moveup/delete/movedown/duplicate buttons + sortorder hidden input)
     * for DISPLAY_EDIT_FULL mode.
     *
     * @param array $criterion Criterion data, must include 'sortorder' and 'id'.
     * @return string HTML fragment.
     */
    public function criterion_controls_td(array $criterion): string {
        $html = html_writer::start_tag('td', ['class' => 'controls']);
        foreach (['moveup', 'delete', 'movedown', 'duplicate'] as $key) {
            $label = get_string('criterion' . $key, 'gradingform_matrix');
            $button = html_writer::empty_tag('input', [
                'type'  => 'submit',
                'name'  => '{NAME}[criteria][{CRITERION-id}][' . $key . ']',
                'id'    => '{NAME}-criteria-{CRITERION-id}-' . $key,
                'value' => $label,
            ]);
            $html .= html_writer::tag('div', $button, ['class' => $key]);
        }
        $html .= html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][sortorder]',
            'value' => $criterion['sortorder'],
        ]);
        $html .= html_writer::end_tag('td'); // .controls
        return $html;
    }

    /**
     * Returns the description <textarea> for DISPLAY_EDIT_FULL mode.
     *
     * @param array $criterion Criterion data.
     * @return string HTML textarea element.
     */
    public function criterion_description_textarea(array $criterion): string {
        $params = [
            'name'       => '{NAME}[criteria][{CRITERION-id}][description]',
            'id'         => '{NAME}-criteria-{CRITERION-id}-description',
            'aria-label' => get_string('criterion', 'gradingform_matrix', ''),
            'cols'       => '10',
            'rows'       => '5',
        ];
        return html_writer::tag('textarea', s($criterion['description']), $params);
    }

    /**
     * Returns hidden inputs for sortorder and description for DISPLAY_EDIT_FROZEN mode.
     *
     * @param array $criterion Criterion data.
     * @return string HTML fragment.
     */
    public function criterion_frozen_hidden_inputs(array $criterion): string {
        $html  = html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][sortorder]',
            'value' => $criterion['sortorder'],
        ]);
        $html .= html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][description]',
            'value' => $criterion['description'],
        ]);
        return $html;
    }

    /**
     * Returns the "Add level" <td> button for DISPLAY_EDIT_FULL mode.
     *
     * @return string HTML fragment.
     */
    public function criterion_addlevel_td(): string {
        $label  = get_string('criterionaddlevel', 'gradingform_matrix');
        $button = html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levels][addlevel]',
            'id'    => '{NAME}-criteria-{CRITERION-id}-levels-addlevel',
            'value' => $label,
            'class' => 'btn btn-secondary',
        ]);
        return html_writer::tag('td', $button, ['class' => 'addlevel']);
    }

    // -------------------------------------------------------------------------
    // Level helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the definition textarea and score input for DISPLAY_EDIT_FULL mode.
     *
     * @param array $level Level data.
     * @param int|string $levelindex Human-readable index for aria labels.
     * @return array Associative array with keys 'definition' (string) and 'score' (string).
     */
    public function level_definition_and_score(array $level, $levelindex): array {
        $definitionparams = [
            'id'         => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-definition',
            'name'       => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][definition]',
            'aria-label' => get_string('leveldefinition', 'gradingform_matrix', $levelindex),
            'cols'       => '10',
            'rows'       => '4',
        ];
        $definition = html_writer::tag('textarea', s($level['definition']), $definitionparams);

        $scoreparams = [
            'type'       => 'text',
            'id'         => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][score]',
            'name'       => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][score]',
            'aria-label' => get_string('scoreinputforlevel', 'gradingform_matrix', $levelindex),
            'size'       => '3',
            'value'      => $level['score'],
        ];
        $score = html_writer::empty_tag('input', $scoreparams);

        return ['definition' => $definition, 'score' => $score];
    }

    /**
     * Returns hidden inputs for definition and score for DISPLAY_EDIT_FROZEN mode.
     *
     * @param array $level Level data.
     * @return string HTML fragment.
     */
    public function level_frozen_hidden_inputs(array $level): string {
        $html  = html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][definition]',
            'value' => $level['definition'],
        ]);
        $html .= html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][score]',
            'value' => $level['score'],
        ]);
        return $html;
    }

    /**
     * Returns the delete button div for DISPLAY_EDIT_FULL mode.
     *
     * @param array $level Level data.
     * @param int|string $levelindex Human-readable index for aria label.
     * @return string HTML fragment.
     */
    public function level_delete_button(array $level, $levelindex): string {
        $label  = get_string('leveldelete', 'gradingform_matrix', $levelindex);
        $button = html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levels][{LEVEL-id}][delete]',
            'id'    => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-delete',
            'value' => $label,
        ]);
        return html_writer::tag('div', $button, ['class' => 'delete']);
    }

    // -------------------------------------------------------------------------
    // Matrix helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the "Add criterion" div for DISPLAY_EDIT_FULL mode.
     *
     * @return string HTML fragment.
     */
    public function matrix_addcriterion_div(): string {
        $label = get_string('addcriterion', 'gradingform_matrix');
        $input = html_writer::empty_tag('input', [
            'type'  => 'submit',
            'name'  => '{NAME}[criteria][addcriterion]',
            'id'    => '{NAME}-criteria-addcriterion',
            'class' => 'btn btn-secondary',
            'value' => $label,
        ]);
        return html_writer::tag('div', $input, ['class' => 'addcriterion my-2']);
    }

    /**
     * Generates the rubric options panel HTML.
     *
     * Only rendered for DISPLAY_EDIT_FULL, DISPLAY_EDIT_FROZEN, and DISPLAY_PREVIEW.
     * Returns an empty string for all other modes.
     *
     * @param int $mode Rubric display mode constant.
     * @param array $options Display options array.
     * @return string HTML fragment, or empty string if options panel is not shown.
     */
    public function matrix_options(int $mode, array $options): string {
        if ($mode != gradingform_matrix_controller::DISPLAY_EDIT_FULL
                && $mode != gradingform_matrix_controller::DISPLAY_EDIT_FROZEN
                && $mode != gradingform_matrix_controller::DISPLAY_PREVIEW) {
            return '';
        }
        $html = html_writer::start_tag('div', ['class' => 'options']);
        $html .= html_writer::tag('div', get_string('rubricoptions', 'gradingform_matrix'), ['class' => 'optionsheading']);
        foreach ($options as $option => $value) {
            $html .= html_writer::start_tag('div', ['class' => 'option ' . $option]);
            $attrs = ['name' => '{NAME}[options][' . $option . ']', 'id' => '{NAME}-options-' . $option];
            switch ($option) {
                case 'sortlevelsasc':
                    // Display option as dropdown.
                    $html .= html_writer::label(get_string($option, 'gradingform_matrix'), $attrs['id'], false);
                    $value = (int)(!!$value); // ensure 0 or 1
                    if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FULL) {
                        $selectoptions = [
                            0 => get_string($option . '0', 'gradingform_matrix'),
                            1 => get_string($option . '1', 'gradingform_matrix'),
                        ];
                        $valuestr = html_writer::select($selectoptions, $attrs['name'], $value, false, ['id' => $attrs['id']]);
                        $html .= html_writer::tag('span', $valuestr, ['class' => 'value']);
                    } else {
                        $html .= html_writer::tag('span', get_string($option . $value, 'gradingform_matrix'), ['class' => 'value']);
                        if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FROZEN) {
                            $html .= html_writer::empty_tag('input', $attrs + ['type' => 'hidden', 'value' => $value]);
                        }
                    }
                    break;
                default:
                    if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FROZEN && $value) {
                        // Id should be different from the actual input added later.
                        $hiddenattrs = $attrs;
                        $hiddenattrs['id'] .= '_hidden';
                        $html .= html_writer::empty_tag('input', $hiddenattrs + ['type' => 'hidden', 'value' => $value]);
                    }
                    // Display option as checkbox.
                    $attrs['type']  = 'checkbox';
                    $attrs['value'] = 1;
                    if ($value) {
                        $attrs['checked'] = 'checked';
                    }
                    if ($mode == gradingform_matrix_controller::DISPLAY_EDIT_FROZEN
                            || $mode == gradingform_matrix_controller::DISPLAY_PREVIEW) {
                        $attrs['disabled'] = 'disabled';
                        unset($attrs['name']);
                        // Id should be different from the actual input added later.
                        $attrs['id'] .= '_disabled';
                    }
                    $html .= html_writer::empty_tag('input', $attrs);
                    $html .= html_writer::tag('label', get_string($option, 'gradingform_matrix'), ['for' => $attrs['id']]);
                    break;
            }
            if (get_string_manager()->string_exists($option . '_help', 'gradingform_matrix')) {
                $html .= $this->parentrenderer->help_icon($option, 'gradingform_matrix');
            }
            $html .= html_writer::end_tag('div'); // .option
        }
        $html .= html_writer::end_tag('div'); // .options
        return $html;
    }
}
