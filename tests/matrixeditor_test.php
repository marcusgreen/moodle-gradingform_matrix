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

namespace gradingform_matrix;

use MoodleQuickForm_matrixeditor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/matrix/matrixeditor.php');

/**
 * Unit tests for the matrix editor form element.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(MoodleQuickForm_matrixeditor::class)]
final class matrixeditor_test extends \advanced_testcase {
    /**
     * A criterion with a level for each score.
     *
     * @param array $scores
     * @return array
     */
    private static function criterion(array $scores): array {
        $levels = [];
        foreach ($scores as $i => $score) {
            $levels['NEWID' . $i] = ['definition' => 'Level ' . $i, 'score' => $score];
        }
        return ['description' => 'Criterion', 'levels' => $levels];
    }

    public function test_non_js_button_pressed_without_prior_processing(): void {
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $value = ['criteria' => ['NEWID1' => self::criterion([0, 1]), 'addcriterion' => []]];
        $this->assertTrue($element->non_js_button_pressed($value));

        $element = new MoodleQuickForm_matrixeditor('matrix');
        $this->assertFalse($element->non_js_button_pressed(['criteria' => ['NEWID1' => self::criterion([0, 1])]]));
    }

    public function test_validate_valid_definition(): void {
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $this->assertFalse($element->validate(['criteria' => ['NEWID1' => self::criterion([0, 2])]]));
    }

    public function test_deleted_criterion_does_not_count(): void {
        $deleted = self::criterion([0, 5]) + ['delete' => 1];
        $deleted['levels']['NEWID0']['definition'] = '';
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $errors = $element->validate([
            'options' => ['lockzeropoints' => 1],
            'criteria' => ['NEWID1' => self::criterion([0, 0]), 'NEWID2' => $deleted],
        ]);
        // Only the zero total of the remaining criterion; the deleted one's points and empty level are ignored.
        $this->assertSame(get_string('err_totalscore', 'gradingform_matrix'), $errors);
    }

    public function test_to_html_requests_javascript_once(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $element->setValue(['criteria' => ['NEWID1' => self::criterion([0, 1])]]);

        $amdcalls = function () use ($PAGE): int {
            $code = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
            return count(array_filter($code, fn($call) => str_contains($call, 'gradingform_matrix/matrixeditor')));
        };

        $first = $element->toHtml();
        $this->assertSame(1, $amdcalls());
        // A second render gives the same markup but adds no second initialisation.
        $this->assertSame($first, $element->toHtml());
        $this->assertSame(1, $amdcalls());
    }

    public function test_to_html_shows_validation_without_revalidating(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $criterion = self::criterion([0, 1]);
        $criterion['description'] = '';
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $element->setValue(['criteria' => ['NEWID1' => self::criterion([0, 1])]]);

        $errors = $element->validate(['criteria' => ['NEWID1' => $criterion]]);
        $html = $element->toHtml();

        // The validated data is shown, not the element value, and the errors are unchanged.
        $this->assertStringContainsString(get_string('err_nodescription', 'gradingform_matrix'), $html);
        $this->assertMatchesRegularExpression('/class="description[^"]* error/', $html);
        $this->assertSame($errors, $element->validate([]));
    }

    public function test_deleted_level_does_not_count(): void {
        $criterion = self::criterion([0, 2, 'abc']);
        $criterion['levels']['NEWID2']['delete'] = 1;
        $element = new MoodleQuickForm_matrixeditor('matrix');
        $this->assertFalse($element->validate(['criteria' => ['NEWID1' => $criterion]]));
    }
}
