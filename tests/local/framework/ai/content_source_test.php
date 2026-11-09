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

namespace gradingform_matrix\local\framework\ai;

/**
 * Unit tests for reading course content.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(content_source::class)]
final class content_source_test extends \advanced_testcase {
    /**
     * Creates a course with one module of each kind.
     *
     * @return array [course, teacher, cms keyed by a short name]
     */
    private function create_course(): array {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $cms = [];
        $cms['assign'] = $generator->create_module('assign', ['course' => $course->id, 'name' => 'Essay',
            'intro' => '<p>Write <strong>500 words</strong> on climate.</p>']);
        $cms['page'] = $generator->create_module('page', ['course' => $course->id, 'name' => 'Guide',
            'content' => '<p>Use three sources.</p>']);
        $cms['label'] = $generator->create_module('label', ['course' => $course->id, 'intro' => '<p>Label text</p>']);
        $cms['book'] = $generator->create_module('book', ['course' => $course->id, 'name' => 'Handbook']);
        $bookgenerator = $generator->get_plugin_generator('mod_book');
        $bookgenerator->create_chapter(['bookid' => $cms['book']->id, 'title' => 'Referencing', 'content' => '<p>Use APA.</p>']);
        $bookgenerator->create_chapter(['bookid' => $cms['book']->id, 'title' => 'Hidden', 'content' => 'Secret', 'hidden' => 1]);
        $cms['text'] = $generator->create_module('resource', ['course' => $course->id, 'name' => 'Notes',
            'defaultfilename' => 'notes.txt']);
        $cms['pdf'] = $generator->create_module('resource', ['course' => $course->id, 'name' => 'Slides',
            'defaultfilename' => 'slides.pdf']);
        $cms['quiz'] = $generator->create_module('quiz', ['course' => $course->id]);
        $cms['hidden'] = $generator->create_module('page', ['course' => $course->id, 'name' => 'Draft',
            'content' => 'Not ready', 'visible' => 0]);
        return [$course, $teacher, $cms];
    }

    public function test_list(): void {
        [$course, , $cms] = $this->create_course();
        $source = new content_source(get_fast_modinfo($course), (int)$cms['assign']->cmid);
        $list = $source->list();

        // The graded activity comes first; the quiz and the PDF cannot be read. Teachers see hidden modules.
        $this->assertSame(
            [$cms['assign']->cmid, $cms['page']->cmid, $cms['label']->cmid, $cms['book']->cmid, $cms['text']->cmid,
                $cms['hidden']->cmid],
            array_column($list, 'cmid')
        );
        $this->assertTrue($list[0]['graded']);
        $this->assertFalse($list[1]['graded']);
        $this->assertSame('Guide', $list[1]['name']);
        $this->assertSame('page', $list[1]['modname']);
        $this->assertSame(get_string('modulename', 'page'), $list[1]['type']);
    }

    public function test_hidden_modules_are_not_offered_to_students(): void {
        [$course, , $cms] = $this->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $source = new content_source(get_fast_modinfo($course, $student->id));

        $this->assertNotContains($cms['hidden']->cmid, array_column($source->list(), 'cmid'));
        $this->assertSame([], $source->extract_many([$cms['hidden']->cmid]));
    }

    public function test_extract_many(): void {
        [$course, , $cms] = $this->create_course();
        $source = new content_source(get_fast_modinfo($course), (int)$cms['assign']->cmid);
        $texts = $source->extract_many([
            $cms['assign']->cmid, $cms['page']->cmid, $cms['book']->cmid, $cms['text']->cmid, $cms['quiz']->cmid,
            $cms['pdf']->cmid, 999999,
        ]);

        // Unreadable and unknown modules are skipped.
        $this->assertSame(['Essay', 'Guide', 'Handbook', 'Notes'], array_keys($texts));
        $this->assertSame('Write 500 WORDS on climate.', $texts['Essay']);
        $this->assertSame('Use three sources.', $texts['Guide']);
        $this->assertStringContainsString('Use APA.', $texts['Handbook']);
        $this->assertStringNotContainsString('Secret', $texts['Handbook']);
        $this->assertSame('Test resource notes.txt file', $texts['Notes']);
    }

    public function test_modules_of_another_course_are_skipped(): void {
        [$course] = $this->create_course();
        $other = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $other->id, 'content' => 'Elsewhere']);
        $source = new content_source(get_fast_modinfo($course));
        $this->assertSame([], $source->extract_many([$page->cmid]));
    }

    public function test_modules_without_view_capability_are_skipped(): void {
        global $DB;
        [$course, $teacher, $cms] = $this->create_course();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/page:view', CAP_PROHIBIT, $roleid, \context_module::instance($cms['page']->cmid));

        $source = new content_source(get_fast_modinfo($course, $teacher->id));
        $this->assertNotContains((int)$cms['page']->cmid, array_column($source->list(), 'cmid'));
        $this->assertSame([], $source->extract_many([$cms['page']->cmid]));
    }
}
