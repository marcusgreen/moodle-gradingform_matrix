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

namespace gradingform_matrix\local;

/**
 * Tests for the layout settings.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(layout_settings::class)]
final class layout_settings_test extends \advanced_testcase {
    public function test_stack_narrow(): void {
        $this->resetAfterTest();
        // A site that installed the plugin before the setting existed has no value: stack, the default.
        unset_config('stacknarrow', 'gradingform_matrix');
        $this->assertTrue(layout_settings::stack_narrow());
        set_config('stacknarrow', 0, 'gradingform_matrix');
        $this->assertFalse(layout_settings::stack_narrow());
        set_config('stacknarrow', 1, 'gradingform_matrix');
        $this->assertTrue(layout_settings::stack_narrow());
    }

    public function test_setting_reaches_the_form(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $renderer = $PAGE->get_renderer('gradingform_matrix');
        $criteria = [1 => ['description' => 'C', 'sortorder' => 1, 'levels' => [
            1 => ['score' => 0, 'definition' => 'Low'],
            2 => ['score' => 1, 'definition' => 'High'],
        ]]];
        $options = \gradingform_matrix_controller::get_default_options();
        $view = \gradingform_matrix_controller::DISPLAY_VIEW;

        set_config('stacknarrow', 1, 'gradingform_matrix');
        $html = $renderer->display_matrix($criteria, $options, $view, 'm');
        $this->assertMatchesRegularExpression('/class="[^"]*\breflow\b(?!-)/', $html);
        set_config('stacknarrow', 0, 'gradingform_matrix');
        $html = $renderer->display_matrix($criteria, $options, $view, 'm');
        $this->assertStringContainsString('reflow-popout', $html);
        $this->assertDoesNotMatchRegularExpression('/\breflow\b(?!-)/', $html);
    }
}
