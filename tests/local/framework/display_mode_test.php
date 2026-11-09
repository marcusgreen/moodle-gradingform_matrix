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
 * Tests for what each display mode shows.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(display_mode::class)]
final class display_mode_test extends \basic_testcase {
    /**
     * For each mode: editing, selectable, marks chosen level, shows options, CSS class.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return [
            'edit full' => [display_mode::EDIT_FULL, true, false, false, true, 'editor editable'],
            'edit frozen' => [display_mode::EDIT_FROZEN, true, false, false, true, 'editor frozen'],
            'preview' => [display_mode::PREVIEW, false, false, false, true, 'editor preview'],
            'preview graded' => [display_mode::PREVIEW_GRADED, false, false, false, false, 'editor preview'],
            'eval' => [display_mode::EVAL, false, true, false, false, 'evaluate editable'],
            'eval frozen' => [display_mode::EVAL_FROZEN, false, true, true, false, 'evaluate frozen'],
            'review' => [display_mode::REVIEW, false, true, true, false, 'review'],
            'view' => [display_mode::VIEW, false, true, true, false, 'view'],
        ];
    }

    /**
     * Tests the answers that do not depend on the form's options.
     *
     * @param display_mode $mode
     * @param bool $editing
     * @param bool $selectable
     * @param bool $markschosen
     * @param bool $showsoptions
     * @param string $cssclass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('modes_provider')]
    public function test_modes(
        display_mode $mode,
        bool $editing,
        bool $selectable,
        bool $markschosen,
        bool $showsoptions,
        string $cssclass
    ): void {
        $this->assertSame($editing, $mode->is_editing());
        $this->assertSame($selectable, $mode->is_selectable());
        $this->assertSame($markschosen, $mode->marks_chosen_level());
        $this->assertSame($showsoptions, $mode->shows_options());
        $this->assertSame($cssclass, $mode->css_class());
    }

    public function test_scores_follow_the_audience(): void {
        $teacheronly = ['showscoreteacher' => 1, 'showscorestudent' => 0];
        $studentonly = ['showscoreteacher' => 0, 'showscorestudent' => 1];
        foreach ([display_mode::EVAL, display_mode::EVAL_FROZEN, display_mode::REVIEW] as $mode) {
            $this->assertTrue($mode->shows_scores($teacheronly));
            $this->assertFalse($mode->shows_scores($studentonly));
        }
        foreach ([display_mode::VIEW, display_mode::PREVIEW_GRADED] as $mode) {
            $this->assertFalse($mode->shows_scores($teacheronly));
            $this->assertTrue($mode->shows_scores($studentonly));
        }
        // People who can manage the form always see the scores.
        foreach ([display_mode::EDIT_FULL, display_mode::EDIT_FROZEN, display_mode::PREVIEW] as $mode) {
            $this->assertTrue($mode->shows_scores(['showscoreteacher' => 0, 'showscorestudent' => 0]));
        }
    }

    public function test_remarks(): void {
        $this->assertFalse(display_mode::EVAL->shows_remarks(['enableremarks' => 0, 'showremarksstudent' => 1]));
        $this->assertTrue(display_mode::EVAL->shows_remarks(['enableremarks' => 1, 'showremarksstudent' => 0]));
        $this->assertFalse(display_mode::VIEW->shows_remarks(['enableremarks' => 1, 'showremarksstudent' => 0]));
        $this->assertTrue(display_mode::VIEW->shows_remarks(['enableremarks' => 1, 'showremarksstudent' => 1]));
    }

    public function test_of_accepts_the_controller_constants(): void {
        $this->assertSame(display_mode::REVIEW, display_mode::of(\gradingform_matrix_controller::DISPLAY_REVIEW));
        $this->assertSame(display_mode::VIEW, display_mode::of(display_mode::VIEW));
        $this->expectException(\ValueError::class);
        display_mode::of(99);
    }
}
