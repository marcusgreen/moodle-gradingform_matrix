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
 * Contains the view/eval-mode HTML generation helper for the matrix grading form.
 *
 * This class is not a Moodle renderer and is not registered with $PAGE.
 * It is instantiated and used internally by gradingform_matrix_renderer
 * to generate HTML that is specific to DISPLAY_EVAL, DISPLAY_EVAL_FROZEN,
 * DISPLAY_REVIEW, and DISPLAY_VIEW modes.
 *
 * @package    gradingform_matrix
 * @copyright  2011 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_matrix\output;

defined('MOODLE_INTERNAL') || die();

use gradingform_matrix_controller;
use html_writer;

/**
 * Generates HTML fragments used in the rubric evaluation, review, and student-view modes
 * (DISPLAY_EVAL, DISPLAY_EVAL_FROZEN, DISPLAY_REVIEW, DISPLAY_VIEW).
 */
class view_renderer {

    // -------------------------------------------------------------------------
    // Criterion helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the hidden input that preserves the criterion key when an empty grade is submitted
     * in DISPLAY_EVAL mode.
     *
     * @return string HTML fragment.
     */
    public function criterion_eval_key_input(): string {
        return html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => '{NAME}[criteria][{CRITERION-id}][]',
        ]);
    }

    /**
     * Returns the remark cell HTML (or hidden input, or empty string) for view/eval modes.
     *
     * Returns an empty string when remarks are disabled or when the current mode does not
     * render a remark element (e.g. DISPLAY_EDIT_FULL / DISPLAY_PREVIEW).
     *
     * @param int $mode Rubric display mode constant.
     * @param array $criterion Criterion data (must include 'description').
     * @param array|null $value The graded criterion value array; may include 'remark'.
     * @param array $options Display options; keys 'enableremarks' and 'showremarksstudent' are used.
     * @return string HTML fragment.
     */
    public function criterion_remark_cell(int $mode, array $criterion, ?array $value, array $options): string {
        $displayremark = ($options['enableremarks'] &&
            ($mode != gradingform_matrix_controller::DISPLAY_VIEW || $options['showremarksstudent']));
        if (!$displayremark) {
            return '';
        }

        $currentremark = '';
        if (isset($value['remark'])) {
            $currentremark = $value['remark'];
        }

        $remarkinfo              = new \stdClass();
        $remarkinfo->description = s($criterion['description']);
        $remarkinfo->remark      = $currentremark;
        $remarklabeltext         = get_string('criterionremark', 'gradingform_matrix', $remarkinfo);

        if ($mode == gradingform_matrix_controller::DISPLAY_EVAL) {
            $remarkparams = [
                'name'       => '{NAME}[criteria][{CRITERION-id}][remark]',
                'id'         => '{NAME}-criteria-{CRITERION-id}-remark',
                'cols'       => '10',
                'rows'       => '5',
                'aria-label' => $remarklabeltext,
            ];
            $input = html_writer::tag('textarea', s($currentremark), $remarkparams);
            return html_writer::tag('td', $input, ['class' => 'remark']);
        }

        if ($mode == gradingform_matrix_controller::DISPLAY_EVAL_FROZEN) {
            return html_writer::empty_tag('input', [
                'type'  => 'hidden',
                'name'  => '{NAME}[criteria][{CRITERION-id}][remark]',
                'value' => $currentremark,
            ]);
        }

        if ($mode == gradingform_matrix_controller::DISPLAY_REVIEW
                || $mode == gradingform_matrix_controller::DISPLAY_VIEW) {
            $remarkparams = [
                'class'      => 'remark',
                'tabindex'   => '0',
                'id'         => '{NAME}-criteria-{CRITERION-id}-remark',
                'aria-label' => $remarklabeltext,
            ];
            return html_writer::tag('td', s($currentremark), $remarkparams);
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // Level helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the radio-button div for DISPLAY_EVAL mode.
     *
     * @param array $level Level data; must include 'id' and 'checked'.
     * @return string HTML fragment.
     */
    public function level_radio_div(array $level): string {
        $radioparams = [
            'type'  => 'radio',
            'id'    => '{NAME}-criteria-{CRITERION-id}-levels-{LEVEL-id}-definition',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levelid]',
            'value' => $level['id'],
        ];
        if ($level['checked']) {
            $radioparams['checked'] = 'checked';
        }
        $input = html_writer::empty_tag('input', $radioparams);
        return html_writer::div($input, 'radio');
    }

    /**
     * Returns the hidden input that preserves the selected level id for DISPLAY_EVAL_FROZEN mode.
     *
     * Only emits HTML when the level is checked; returns empty string otherwise.
     *
     * @param array $level Level data; must include 'id' and 'checked'.
     * @return string HTML fragment.
     */
    public function level_frozen_checked_input(array $level): string {
        if (!$level['checked']) {
            return '';
        }
        return html_writer::empty_tag('input', [
            'type'  => 'hidden',
            'name'  => '{NAME}[criteria][{CRITERION-id}][levelid]',
            'value' => $level['id'],
        ]);
    }
}
