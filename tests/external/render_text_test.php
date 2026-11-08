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

namespace gradingform_matrix\external;

use core_external\external_api;

/**
 * Tests for rendering editor texts.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(render_text::class)]
final class render_text_test extends \advanced_testcase {
    /**
     * Creates an assignment and returns its context, as a user with the role.
     *
     * @param string $role
     * @return \context_module
     */
    private function setup_context(string $role): \context_module {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, $role));
        return \context_module::instance($assign->cmid);
    }

    public function test_renders_markdown_and_cleans_html(): void {
        $context = $this->setup_context('editingteacher');
        $html = external_api::clean_returnvalue(render_text::execute_returns(), render_text::execute($context->id, [
            "**Competent**\n\nExplains the idea.",
            '<script>alert(1)</script>Plain',
        ]));

        $this->assertCount(2, $html);
        $this->assertStringContainsString('<strong>Competent</strong>', $html[0]);
        $this->assertStringContainsString('<p>Explains the idea.</p>', $html[0]);
        $this->assertStringNotContainsString('<script', $html[1]);
        $this->assertStringContainsString('Plain', $html[1]);
    }

    public function test_requires_capability(): void {
        $context = $this->setup_context('student');
        $this->expectException(\required_capability_exception::class);
        render_text::execute($context->id, ['Text']);
    }
}
