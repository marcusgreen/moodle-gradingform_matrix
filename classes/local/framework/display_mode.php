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

namespace gradingform_matrix\local\framework;

/**
 * How a grading form is shown, and what that means for each part of it.
 *
 * The values are the integers of the controller's DISPLAY_* constants, which stay as aliases.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum display_mode: int {
    // Editing the definition.
    case EDIT_FULL = 1;
    // Confirming changes to the definition: read-only, with the values in hidden inputs.
    case EDIT_FROZEN = 2;
    // Previewing the definition, for people who can manage it.
    case PREVIEW = 3;
    // Grading.
    case EVAL = 4;
    // Confirming a grade: read-only, with the values in hidden inputs.
    case EVAL_FROZEN = 5;
    // A grader reviewing a grade.
    case REVIEW = 6;
    // The person graded viewing their grade.
    case VIEW = 7;
    // The person graded previewing the form before being graded.
    case PREVIEW_GRADED = 8;

    /**
     * Returns the mode for a value that may still be one of the controller's DISPLAY_* integers.
     *
     * @param int|self $mode
     * @return self
     */
    public static function of(int|self $mode): self {
        return $mode instanceof self ? $mode : self::from($mode);
    }

    /**
     * Whether the definition is being edited, so texts are raw rather than formatted.
     *
     * @return bool
     */
    public function is_editing(): bool {
        return $this === self::EDIT_FULL || $this === self::EDIT_FROZEN;
    }

    /**
     * Whether each criterion's levels are a choice: picked by a grader or showing the one picked.
     *
     * @return bool
     */
    public function is_selectable(): bool {
        return !$this->is_editing() && $this !== self::PREVIEW && $this !== self::PREVIEW_GRADED;
    }

    /**
     * Whether the server marks the chosen level. When grading, JavaScript marks it instead.
     *
     * @return bool
     */
    public function marks_chosen_level(): bool {
        return in_array($this, [self::EVAL_FROZEN, self::REVIEW, self::VIEW], true);
    }

    /**
     * Whether level scores are shown, for this mode's audience.
     *
     * @param array $options display options of the form
     * @return bool
     */
    public function shows_scores(array $options): bool {
        return match ($this) {
            self::EVAL, self::EVAL_FROZEN, self::REVIEW => (bool)$options['showscoreteacher'],
            self::VIEW, self::PREVIEW_GRADED => (bool)$options['showscorestudent'],
            default => true,
        };
    }

    /**
     * Whether remarks are shown, for this mode's audience.
     *
     * @param array $options display options of the form
     * @return bool
     */
    public function shows_remarks(array $options): bool {
        return $options['enableremarks'] && ($this !== self::VIEW || $options['showremarksstudent']);
    }

    /**
     * Whether the form's options are shown. Only people who can manage the form see them.
     *
     * @return bool
     */
    public function shows_options(): bool {
        return $this === self::EDIT_FULL || $this === self::EDIT_FROZEN || $this === self::PREVIEW;
    }

    /**
     * The CSS classes of the form's wrapper, after gradingform_matrix.
     *
     * @return string
     */
    public function css_class(): string {
        return match ($this) {
            self::EDIT_FULL => 'editor editable',
            self::EDIT_FROZEN => 'editor frozen',
            self::PREVIEW, self::PREVIEW_GRADED => 'editor preview',
            self::EVAL => 'evaluate editable',
            self::EVAL_FROZEN => 'evaluate frozen',
            self::REVIEW => 'review',
            self::VIEW => 'view',
        };
    }
}
