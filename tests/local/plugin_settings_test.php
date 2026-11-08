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
 * Tests for loading the plugin's settings.php.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plugin_settings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ai_settings::class)]
final class plugin_settings_test extends \advanced_testcase {
    public function test_page_holds_the_settings_from_settings_php(): void {
        $page = plugin_settings::page();
        $this->assertSame(plugin_settings::SECTION, $page->name);
        $this->assertSame(['gradingform_matrixenableai'], array_keys((array)$page->settings));
        $this->assertSame('s_gradingform_matrix_enableai', $page->settings->gradingform_matrixenableai->get_full_name());
    }

    public function test_defaults_turn_the_assistant_off(): void {
        $this->resetAfterTest();
        unset_config('enableai', 'gradingform_matrix');
        plugin_settings::apply_defaults();
        $this->assertSame('0', get_config('gradingform_matrix', 'enableai'));
        $this->assertFalse(ai_settings::enabled());

        // A value that is already set is kept.
        set_config('enableai', 1, 'gradingform_matrix');
        plugin_settings::apply_defaults();
        $this->assertTrue(ai_settings::enabled());
    }

    public function test_save(): void {
        $this->resetAfterTest();
        set_config('enableai', 0, 'gradingform_matrix');

        $this->assertSame([], plugin_settings::save(['s_gradingform_matrix_enableai' => '1', 'unrelated' => 'x']));
        $this->assertTrue(ai_settings::enabled());

        // Settings that were not submitted are left alone.
        $this->assertSame([], plugin_settings::save([]));
        $this->assertTrue(ai_settings::enabled());

        $this->assertSame([], plugin_settings::save(['s_gradingform_matrix_enableai' => '0']));
        $this->assertFalse(ai_settings::enabled());
    }
}
