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

use context_module;
use gradingform_controller;
use gradingform_instance;
use gradingform_matrix_controller;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

// The TestWith attributes use gradingform_instance constants, and are read before setUp() runs.
global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * Tests exporting and importing definitions through the controller.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gradingform_matrix_controller::class)]
final class import_export_test extends \advanced_testcase {
    /** @var context_module */
    protected $context;

    /** @var array criterion description => [level definition => score] */
    private const CRITERIA = [
        'Spelling' => ['Many errors' => 0, 'Few errors' => 1, 'No errors' => 2],
        'Grammar' => ['Poor' => 0, 'Good' => 3],
    ];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $module = $generator->create_module('assign', ['course' => $course]);
        $this->context = context_module::instance($module->cmid);
        $this->setUser($generator->create_user());
    }

    /**
     * Creates a matrix definition in a new area.
     *
     * @return gradingform_matrix_controller
     */
    private function create_matrix(): gradingform_matrix_controller {
        return $this->getDataGenerator()->get_plugin_generator('gradingform_matrix')
            ->create_instance($this->context, 'mod_assign', 'submissions', 'Essay', 'Essay description', self::CRITERIA);
    }

    /**
     * Returns an empty matrix controller for another area.
     *
     * @return gradingform_matrix_controller
     */
    private function empty_controller(): gradingform_matrix_controller {
        $courseid = $this->context->get_course_context()->instanceid;
        $module = $this->getDataGenerator()->create_module('assign', ['course' => $courseid]);
        return $this->getDataGenerator()->get_plugin_generator('core_grading')
            ->create_instance(context_module::instance($module->cmid), 'mod_assign', 'submissions', 'matrix');
    }

    /**
     * Returns criterion description => [level definition => score] of a controller's definition.
     *
     * @param gradingform_matrix_controller $controller
     * @return array
     */
    private function criteria_of(gradingform_matrix_controller $controller): array {
        $result = [];
        foreach ($controller->get_definition(true)->matrix_criteria as $criterion) {
            foreach ($criterion['levels'] as $level) {
                $result[$criterion['description']][$level['definition']] = (int)$level['score'];
            }
        }
        return $result;
    }

    public function test_json_round_trip_into_new_area(): void {
        $source = $this->create_matrix();
        $json = $source->export_definition();

        $target = $this->empty_controller();
        $target->import_definition($target->definition_importer()->from_json($json), 'fallback');

        $definition = $target->get_definition(true);
        $this->assertSame('Essay', $definition->name);
        $this->assertSame('Essay description', $definition->description);
        $this->assertEquals(gradingform_controller::DEFINITION_STATUS_DRAFT, $definition->status);
        $this->assertSame(self::CRITERIA, $this->criteria_of($target));
        $this->assertEquals($source->get_options(), $target->get_options());
    }

    /**
     * Returns filename => content of the files in a definition's description area.
     *
     * @param gradingform_matrix_controller $controller
     * @return array
     */
    private function description_files(gradingform_matrix_controller $controller): array {
        $files = get_file_storage()->get_area_files(
            $controller->get_context()->id,
            'grading',
            'description',
            $controller->get_definition()->id,
            'filepath, filename',
            false
        );
        $result = [];
        foreach ($files as $file) {
            $result[$file->get_filepath() . $file->get_filename()] = $file->get_content();
        }
        return $result;
    }

    public function test_json_carries_description_images(): void {
        global $DB;
        $source = $this->create_matrix();
        $definition = $source->get_definition();
        $text = '<p><img src="@@PLUGINFILE@@/pic.png" alt="x"></p>';
        $DB->set_field('grading_definitions', 'description', $text, ['id' => $definition->id]);
        get_file_storage()->create_file_from_string([
            'contextid' => $source->get_context()->id,
            'component' => 'grading',
            'filearea' => 'description',
            'itemid' => $definition->id,
            'filepath' => '/',
            'filename' => 'pic.png',
        ], 'imagedata');
        $source->get_definition(true);

        $json = $source->export_definition();
        $this->assertStringContainsString(base64_encode('imagedata'), $json);

        $target = $this->empty_controller();
        $target->import_definition($target->definition_importer()->from_json($json), 'fallback');

        $this->assertSame($text, $target->get_definition(true)->description);
        $this->assertSame(['/pic.png' => 'imagedata'], $this->description_files($target));

        // Importing a description without files removes the old ones.
        $data = $source->definition_importer()->from_json($json);
        $data['description'] = 'Plain';
        $data['files'] = [];
        $source->import_definition($data, 'fallback');
        $this->assertSame('Plain', $source->get_definition(true)->description);
        $this->assertSame([], $this->description_files($source));
    }

    public function test_missing_values_kept_or_defaulted(): void {
        $json = json_encode(['formatversion' => 1, 'criteria' => [
            ['description' => 'Structure', 'levels' => [
                ['score' => 0, 'definition' => 'Missing'],
                ['score' => 4, 'definition' => 'Clear'],
            ]],
        ]]);

        $controller = $this->create_matrix();
        $controller->import_definition($controller->definition_importer()->from_json($json), 'fallback');
        $definition = $controller->get_definition(true);
        $this->assertSame('Essay', $definition->name);
        $this->assertSame('Essay description', $definition->description);
        $this->assertEquals(gradingform_controller::DEFINITION_STATUS_DRAFT, $definition->status);
        $this->assertSame(['Structure' => ['Missing' => 0, 'Clear' => 4]], $this->criteria_of($controller));

        $empty = $this->empty_controller();
        $empty->import_definition($empty->definition_importer()->from_json($json), 'myform');
        $this->assertSame('myform', $empty->get_definition(true)->name);
    }

    /**
     * Import is refused once a grade exists, whether active or waiting for regrade.
     *
     * @param int $status instance status
     */
    #[\PHPUnit\Framework\Attributes\TestWith([gradingform_instance::INSTANCE_STATUS_ACTIVE])]
    #[\PHPUnit\Framework\Attributes\TestWith([gradingform_instance::INSTANCE_STATUS_NEEDUPDATE])]
    public function test_import_refused_when_graded(int $status): void {
        global $DB, $USER;
        $controller = $this->create_matrix();
        $this->assertTrue($controller->can_import());

        $instance = $controller->create_instance($USER->id, 1);
        $DB->set_field('grading_instances', 'status', $status, ['id' => $instance->get_id()]);

        $this->assertFalse($controller->can_import());
        $data = $controller->definition_importer()->from_json($controller->export_definition());
        try {
            $controller->import_definition($data, 'a');
            $this->fail('Expected moodle_exception');
        } catch (moodle_exception $e) {
            $this->assertSame('importerrorused', $e->errorcode);
        }
        $this->assertSame(self::CRITERIA, $this->criteria_of($controller));
    }

    public function test_import_allowed_with_incomplete_instance(): void {
        global $USER;
        $controller = $this->create_matrix();
        $controller->create_instance($USER->id, 1);

        $this->assertTrue($controller->can_import());
    }
}
