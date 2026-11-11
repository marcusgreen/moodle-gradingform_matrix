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

use context_module;
use gradingform_instance;
use gradingform_matrix_controller;
use tests\gradingform_matrix\generator\criterion;
use tests\gradingform_matrix\generator\matrix;

/**
 * Tests for the grading results export and the item resolver.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(results_exporter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(item_resolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(filling_repository::class)]
final class results_exporter_test extends \advanced_testcase {
    /** @var \stdClass the assignment */
    protected $assign;

    /** @var context_module */
    protected $context;

    /** @var \stdClass */
    protected $teacher;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Loads the generator classes used below.
        $generator->get_plugin_generator('gradingform_matrix');
        $course = $generator->create_course();
        $this->assign = $generator->create_module('assign', ['course' => $course]);
        $this->context = context_module::instance($this->assign->cmid);
        $this->teacher = $generator->create_user(['firstname' => 'Tess', 'lastname' => 'Teacher']);
        $this->setUser($this->teacher);
    }

    /**
     * Creates the definition: two criteria, each with levels of 0, 1 and 2 points.
     *
     * @param string $component
     * @param string $area
     * @param int $enableremarks
     * @return gradingform_matrix_controller
     */
    protected function create_controller(
        string $component = 'mod_assign',
        string $area = 'submissions',
        int $enableremarks = 1
    ): gradingform_matrix_controller {
        $controller = $this->getDataGenerator()->get_plugin_generator('core_grading')
            ->create_instance($this->context, $component, $area, 'matrix');
        $matrix = new matrix('Test', 'Description');
        $matrix->set_option('enableremarks', $enableremarks);
        $matrix->add_criteria(new criterion('**Spelling**', ['Poor' => 0, 'Fair' => 1, 'Good' => 2]));
        $matrix->add_criteria(new criterion('Layout', ['Poor' => 0, 'Fair' => 1, 'Good' => 2]));
        $controller->update_definition($matrix->get_definition());
        $controller->set_grade_range(array_combine(range(0, 100), range(0, 100)), true);
        return $controller;
    }

    /**
     * Returns [criterion description => [score => levelid]].
     *
     * @param gradingform_matrix_controller $controller
     * @return array
     */
    protected function level_ids(gradingform_matrix_controller $controller): array {
        $result = [];
        foreach ($controller->get_definition()->matrix_criteria as $criterion) {
            foreach ($criterion['levels'] as $level) {
                $result[$criterion['description']][(int)$level['score']] = $level['id'];
            }
        }
        return $result;
    }

    /**
     * Grades an item and returns the instance.
     *
     * @param gradingform_matrix_controller $controller
     * @param int $itemid
     * @param array $scores criterion description => score
     * @param array $remarks criterion description => remark
     * @return gradingform_instance
     */
    protected function grade(
        gradingform_matrix_controller $controller,
        int $itemid,
        array $scores,
        array $remarks = []
    ): gradingform_instance {
        $ids = $this->level_ids($controller);
        $criteria = [];
        foreach ($controller->get_definition()->matrix_criteria as $cid => $criterion) {
            $description = $criterion['description'];
            $criteria[$cid] = ['levelid' => $ids[$description][$scores[$description]], 'remark' => $remarks[$description] ?? ''];
        }
        $instance = $controller->create_instance($this->teacher->id, $itemid);
        $instance->submit_and_get_grade(['criteria' => $criteria], $itemid);
        return $instance;
    }

    /**
     * Creates a student with an assignment grade record and returns the record's id.
     *
     * @param string $firstname
     * @param string $lastname
     * @return int
     */
    protected function assign_item(string $firstname, string $lastname): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['firstname' => $firstname, 'lastname' => $lastname]);
        return $DB->insert_record('assign_grades', [
            'assignment' => $this->assign->id,
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'grader' => $this->teacher->id,
            'grade' => -1,
            'attemptnumber' => 0,
        ]);
    }

    public function test_columns(): void {
        $exporter = $this->create_controller()->results_exporter();
        $columns = $exporter->columns(['email'], false);
        $this->assertSame([
            'fullname', 'email', 'grader', 'timemodified', 'status',
            'c%level', 'c%points', 'c%remark', 'c%level', 'c%points', 'c%remark', 'total', 'grade',
        ], array_map(fn($key) => preg_replace('/^c\d+/', 'c%', $key), array_keys($columns)));
        // Criterion names are plain text, not Markdown.
        $this->assertContains('Spelling: level', $columns);
        $this->assertContains('Layout: remark', $columns);

        $exporter = $this->create_controller('mod_assign', 'submissions', 0)->results_exporter();
        $columns = $exporter->columns([], true);
        $this->assertArrayHasKey('itemid', $columns);
        $this->assertCount(0, preg_grep('/remark$/', array_keys($columns)));
    }

    public function test_rows(): void {
        global $DB;
        $controller = $this->create_controller();
        $zoe = $this->assign_item('Zoe', 'Zebra');
        $amy = $this->assign_item('Amy', 'Aardvark');
        $bob = $this->assign_item('Bob', 'Badger');

        // Amy's first grade becomes history when she is graded again.
        $this->grade($controller, $amy, ['**Spelling**' => 0, 'Layout' => 0]);
        $this->grade($controller, $amy, ['**Spelling**' => 2, 'Layout' => 1], ['Layout' => '<p>Tidy enough</p>']);
        $regrade = $this->grade($controller, $zoe, ['**Spelling**' => 1, 'Layout' => 1]);
        $needupdate = gradingform_instance::INSTANCE_STATUS_NEEDUPDATE;
        $DB->set_field('grading_instances', 'status', $needupdate, ['id' => $regrade->get_id()]);
        // Bob only has a draft.
        $controller->create_instance($this->teacher->id, $bob);

        $exporter = $controller->results_exporter();
        $rows = $exporter->rows($controller->get_definition()->id, $controller->item_resolver(), ['email']);

        $this->assertSame(['Amy Aardvark', 'Zoe Zebra'], array_column($rows, 'fullname'));
        $this->assertStringContainsString('@', $rows[0]['email']);
        $this->assertSame('Tess Teacher', $rows[0]['grader']);
        $this->assertSame(get_string('resultsgraded', 'gradingform_matrix'), $rows[0]['status']);
        $this->assertSame(get_string('resultsneedregrade', 'gradingform_matrix'), $rows[1]['status']);

        $cells = array_values(array_slice($rows[0], 5));
        $this->assertSame(['Good', 2.0, '', 'Fair', 1.0, 'Tidy enough', 3.0, 75.0], $cells);
        $this->assertSame(50.0, $rows[1]['grade']);
    }

    public function test_criteria_cells_with_missing_level(): void {
        $controller = $this->create_controller();
        $criteria = $controller->get_definition()->matrix_criteria;
        [$first, $second] = array_keys($criteria);
        $ids = $this->level_ids($controller);
        $cells = $controller->results_exporter()->criteria_cells([
            $first => ['levelid' => $ids['**Spelling**'][2], 'remark' => '', 'remarkformat' => FORMAT_MOODLE],
            $second => ['levelid' => 999999, 'remark' => '', 'remarkformat' => FORMAT_MOODLE],
        ]);
        $this->assertSame('', $cells["c{$second}level"]);
        $this->assertSame('', $cells["c{$second}points"]);
        $this->assertSame(2.0, $cells['total']);
        $this->assertSame(50.0, $cells['grade']);
    }

    public function test_unsupported_area_lists_item_ids(): void {
        $controller = $this->create_controller('mod_assign', 'unknownarea');
        $this->grade($controller, 42, ['**Spelling**' => 1, 'Layout' => 1]);
        $resolver = $controller->item_resolver();
        $this->assertFalse($resolver->supported());

        $rows = $controller->results_exporter()->rows($controller->get_definition()->id, $resolver, []);
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['fullname']);
        $this->assertSame(42, $rows[0]['itemid']);
    }

    public function test_item_resolver_forum(): void {
        global $DB;
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $this->assign->course]);
        $user = $this->getDataGenerator()->create_user();
        $itemid = $DB->insert_record('forum_grades', [
            'forum' => $forum->id,
            'itemnumber' => 1,
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $resolver = new item_resolver('mod_forum', 'forum');
        $this->assertTrue($resolver->supported());
        $this->assertSame([$itemid => (int)$user->id], $resolver->user_ids([$itemid, $itemid + 1000]));
    }

    public function test_load_for_instances(): void {
        $controller = $this->create_controller();
        $first = $this->grade($controller, 1, ['**Spelling**' => 1, 'Layout' => 2]);
        $second = $this->grade($controller, 2, ['**Spelling**' => 0, 'Layout' => 0]);
        $fillings = $controller->filling_repository()->load_for_instances([$first->get_id(), $second->get_id()]);
        $this->assertCount(2, $fillings);
        $this->assertCount(2, $fillings[$first->get_id()]);
        $this->assertSame([], $controller->filling_repository()->load_for_instances([]));
    }
}
