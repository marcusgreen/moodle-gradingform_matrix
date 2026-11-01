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

use advanced_testcase;
use context_module;
use gradingform_controller;
use gradingform_matrix_controller;
use tests\gradingform_matrix\generator\criterion;
use tests\gradingform_matrix\generator\matrix;

/**
 * End-to-end tests that pin the grade calculation, regrade detection and persistence behaviour.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\gradingform_matrix_controller::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\gradingform_matrix_instance::class)]
final class behaviour_test extends advanced_testcase {
    /** @var context_module */
    protected $context;

    /** @var \stdClass */
    protected $user;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Loads the generator classes used below.
        $generator->get_plugin_generator('gradingform_matrix');
        $course = $generator->create_course();
        $module = $generator->create_module('assign', ['course' => $course]);
        $this->context = context_module::instance($module->cmid);
        $this->user = $generator->create_user();
        $this->setUser($this->user);
    }

    /**
     * Creates a definition from criterion description => [level definition => score].
     *
     * @param array $criteria
     * @param array $options option overrides
     * @return gradingform_matrix_controller
     */
    protected function create_controller(array $criteria, array $options = []): gradingform_matrix_controller {
        $controller = $this->getDataGenerator()->get_plugin_generator('core_grading')
            ->create_instance($this->context, 'mod_assign', 'submissions', 'matrix');
        $matrix = new matrix('Test', 'Description');
        foreach ($options as $key => $value) {
            $matrix->set_option($key, $value);
        }
        foreach ($criteria as $description => $levels) {
            $matrix->add_criteria(new criterion($description, $levels));
        }
        $controller->update_definition($matrix->get_definition());
        return $controller;
    }

    /**
     * Returns [criterion description => [score => levelid]] for a controller.
     *
     * @param gradingform_matrix_controller $controller
     * @return array
     */
    protected function level_ids(gradingform_matrix_controller $controller): array {
        $result = [];
        foreach ($controller->get_definition()->matrix_criteria as $criterion) {
            foreach ($criterion['levels'] as $level) {
                $result[$criterion['description']][(string)(float)$level['score']] = $level['id'];
            }
        }
        return $result;
    }

    /**
     * Fills a new instance with the given criterion description => score, returns the grade.
     *
     * @param gradingform_matrix_controller $controller
     * @param array $selection
     * @param array $graderange
     * @param bool $allowdecimals
     * @return float|int
     */
    protected function grade(
        gradingform_matrix_controller $controller,
        array $selection,
        array $graderange,
        bool $allowdecimals = false
    ) {
        $controller->set_grade_range($graderange, $allowdecimals);
        $ids = $this->level_ids($controller);
        $criteria = [];
        foreach ($controller->get_definition()->matrix_criteria as $cid => $criterion) {
            if (array_key_exists($criterion['description'], $selection)) {
                $criteria[$cid] = [
                    'levelid' => $ids[$criterion['description']][(string)(float)$selection[$criterion['description']]],
                    'remark' => '',
                ];
            }
        }
        $instance = $controller->create_instance($this->user->id, 1);
        $instance->update(['criteria' => $criteria]);
        return $instance->get_grade();
    }

    /**
     * Grade range 0..$max as used by points grading.
     *
     * @param int $max
     * @return array
     */
    protected function points(int $max): array {
        return array_combine(range(0, $max), range(0, $max));
    }

    /**
     * Creates the standard definition: two criteria, each with levels of 0, 1 and 2 points.
     *
     * @param array $options option overrides
     * @return gradingform_matrix_controller
     */
    protected function simple(array $options = []): gradingform_matrix_controller {
        return $this->create_controller([
            'A' => ['a0' => 0, 'a1' => 1, 'a2' => 2],
            'B' => ['b0' => 0, 'b1' => 1, 'b2' => 2],
        ], $options);
    }

    public function test_grade_lockzeropoints(): void {
        $controller = $this->simple();
        $this->assertEquals(75, $this->grade($controller, ['A' => 2, 'B' => 1], $this->points(100)));
        $this->assertEquals(0, $this->grade($controller, ['A' => 0, 'B' => 0], $this->points(100)));
        $this->assertEquals(100, $this->grade($controller, ['A' => 2, 'B' => 2], $this->points(100)));
    }

    public function test_grade_rounding_and_decimals(): void {
        $controller = $this->simple();
        // 1 of 4 points on a 0..10 range is 2.5.
        $this->assertEquals(3, $this->grade($controller, ['A' => 1, 'B' => 0], $this->points(10)));
        $this->assertEquals(2.5, $this->grade($controller, ['A' => 1, 'B' => 0], $this->points(10), true));
    }

    public function test_grade_without_lockzeropoints(): void {
        $controller = $this->create_controller([
            'A' => ['a1' => 1, 'a2' => 2, 'a3' => 3],
            'B' => ['b1' => 1, 'b2' => 2, 'b3' => 3],
        ], ['lockzeropoints' => 0]);
        // Min 2, max 6: score 4 maps to the middle.
        $this->assertEquals(50, $this->grade($controller, ['A' => 2, 'B' => 2], $this->points(100)));
        $this->assertEquals(0, $this->grade($controller, ['A' => 1, 'B' => 1], $this->points(100)));
    }

    public function test_grade_with_lockzeropoints_and_nonzero_minimum(): void {
        $controller = $this->create_controller([
            'A' => ['a1' => 1, 'a2' => 2, 'a3' => 3],
            'B' => ['b1' => 1, 'b2' => 2, 'b3' => 3],
        ]);
        // Score 2 of 6 is 33.3, rounded.
        $this->assertEquals(33, $this->grade($controller, ['A' => 1, 'B' => 1], $this->points(100)));
    }

    public function test_grade_scale(): void {
        $scale = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];
        $controller = $this->simple();
        $this->assertEquals(1, $this->grade($controller, ['A' => 1, 'B' => 0], $scale));
        $this->assertEquals(3, $this->grade($controller, ['A' => 2, 'B' => 1], $scale));

        $controller = $this->simple(['lockzeropoints' => 0]);
        // Offset (3 - 0) / 4 * (4 - 1) = 2.25, rounded 2, plus minimum 1.
        $this->assertEquals(3, $this->grade($controller, ['A' => 2, 'B' => 1], $scale));
    }

    public function test_grade_negative_points(): void {
        $controller = $this->create_controller([
            'A' => ['a-1' => -1, 'a0' => 0, 'a2' => 2],
            'B' => ['b-1' => -1, 'b0' => 0, 'b2' => 2],
        ]);
        $this->assertEquals(25, $this->grade($controller, ['A' => -1, 'B' => 2], $this->points(100)));
        // Negative totals are clamped to the minimum grade.
        $this->assertEquals(0, $this->grade($controller, ['A' => -1, 'B' => -1], $this->points(100)));
    }

    public function test_grade_invalid(): void {
        // All levels equal: max score does not exceed min score.
        $controller = $this->create_controller(['A' => ['x' => 1, 'y' => 1]]);
        $this->assertEquals(-1, $this->grade($controller, ['A' => 1], $this->points(100)));

        $controller = $this->simple();
        $this->assertEquals(-1, $this->grade($controller, ['A' => 1, 'B' => 1], []));
    }

    public function test_grade_partial_filling(): void {
        $controller = $this->simple();
        // Unfilled criteria count as zero.
        $this->assertEquals(50, $this->grade($controller, ['A' => 2], $this->points(100)));
    }

    /**
     * Returns the change level reported for a modified copy of the current definition.
     *
     * @param gradingform_matrix_controller $controller
     * @param callable $modify receives and returns the editor definition
     * @return int
     */
    protected function change_level(gradingform_matrix_controller $controller, callable $modify): int {
        $definition = $controller->get_definition_for_editing();
        $definition = $modify($definition);
        return $controller->update_or_check_matrix($definition, null, false);
    }

    /**
     * Returns the id of the first criterion and its levels.
     *
     * @param \stdClass $definition editor definition
     * @return array [criterionid, levelids]
     */
    protected function first(\stdClass $definition): array {
        $cid = array_key_first($definition->matrix['criteria']);
        return [$cid, array_keys($definition->matrix['criteria'][$cid]['levels'])];
    }

    public function test_change_levels(): void {
        $controller = $this->simple();

        // The generator stores an incomplete options set; save once as the editor form would.
        $controller->update_definition($controller->get_definition_for_editing());

        $this->assertEquals(0, $this->change_level($controller, fn($d) => $d));

        $this->assertEquals(1, $this->change_level($controller, function ($d) {
            [$cid] = $this->first($d);
            $d->matrix['criteria'][$cid]['description'] = 'Changed';
            return $d;
        }));

        $this->assertEquals(1, $this->change_level($controller, function ($d) {
            [$cid, $lids] = $this->first($d);
            $d->matrix['criteria'][$cid]['levels'][$lids[0]]['definition'] = 'Changed';
            return $d;
        }));

        // New level not above the current maximum.
        $this->assertEquals(2, $this->change_level($controller, function ($d) {
            [$cid] = $this->first($d);
            $d->matrix['criteria'][$cid]['levels']['NEWID1'] = ['score' => 1, 'definition' => 'new'];
            return $d;
        }));

        // New level above the current maximum.
        $this->assertEquals(3, $this->change_level($controller, function ($d) {
            [$cid] = $this->first($d);
            $d->matrix['criteria'][$cid]['levels']['NEWID1'] = ['score' => 5, 'definition' => 'new'];
            return $d;
        }));

        $this->assertEquals(3, $this->change_level($controller, function ($d) {
            [$cid, $lids] = $this->first($d);
            $d->matrix['criteria'][$cid]['levels'][$lids[0]]['score'] = 7;
            return $d;
        }));

        $this->assertEquals(3, $this->change_level($controller, function ($d) {
            [$cid] = $this->first($d);
            unset($d->matrix['criteria'][$cid]);
            return $d;
        }));

        $this->assertEquals(3, $this->change_level($controller, function ($d) {
            $d->matrix['options']['lockzeropoints'] = 0;
            return $d;
        }));

        $this->assertEquals(4, $this->change_level($controller, function ($d) {
            [$cid, $lids] = $this->first($d);
            unset($d->matrix['criteria'][$cid]['levels'][$lids[0]]);
            return $d;
        }));

        $this->assertEquals(5, $this->change_level($controller, function ($d) {
            $d->matrix['criteria']['NEWID1'] = ['description' => 'New', 'sortorder' => 9,
                'levels' => ['NEWID1' => ['score' => 0, 'definition' => 'x'], 'NEWID2' => ['score' => 1, 'definition' => 'y']]];
            return $d;
        }));

        // Checking must not have changed anything.
        $this->assertEquals(0, $this->change_level($controller, fn($d) => $d));
    }

    public function test_update_definition_persists_changes(): void {
        $controller = $this->simple();
        $definition = $controller->get_definition_for_editing();
        [$cid, $lids] = $this->first($definition);
        $definition->matrix['criteria'][$cid]['description'] = 'Changed';
        $definition->matrix['criteria'][$cid]['levels'][$lids[0]]['score'] = '0.5';
        unset($definition->matrix['criteria'][$cid]['levels'][$lids[2]]);
        $definition->matrix['criteria'][$cid]['levels']['NEWID1'] = ['score' => 3, 'definition' => 'new'];
        $definition->matrix['criteria']['NEWID1'] = ['description' => 'Third', 'sortorder' => 3,
            'levels' => ['NEWID1' => ['score' => 0, 'definition' => 'x'], 'NEWID2' => ['score' => 4, 'definition' => 'y']]];
        $controller->update_definition($definition);

        $criteria = $controller->get_definition(true)->matrix_criteria;
        $this->assertCount(3, $criteria);
        $this->assertEquals('Changed', $criteria[$cid]['description']);
        $scores = array_column($criteria[$cid]['levels'], 'score');
        sort($scores);
        $this->assertEquals([0.5, 1, 3], $scores);
        $third = array_values(array_filter($criteria, fn($c) => $c['description'] === 'Third'))[0];
        $this->assertEquals([0, 4], array_values(array_column($third['levels'], 'score')));
        $this->assertEquals(['min' => 0.5, 'max' => 9], [
            'min' => $controller->get_min_max_score()['minscore'],
            'max' => $controller->get_min_max_score()['maxscore'],
        ]);
    }

    public function test_instance_update_copy_and_clear(): void {
        global $DB;
        $controller = $this->simple();
        $ids = $this->level_ids($controller);
        $cids = array_keys($controller->get_definition()->matrix_criteria);

        $instance = $controller->create_instance($this->user->id, 1);
        $instance->update(['criteria' => [
            $cids[0] => ['levelid' => $ids['A']['1'], 'remark' => 'r1'],
            $cids[1] => ['levelid' => $ids['B']['2'], 'remark' => 'r2'],
        ]]);
        $instance->update(['criteria' => [
            $cids[0] => ['levelid' => $ids['A']['2'], 'remark' => 'r1'],
        ]]);
        $filling = $instance->get_matrix_filling(true);
        $this->assertEquals([$cids[0]], array_keys($filling['criteria']));
        $this->assertEquals($ids['A']['2'], $filling['criteria'][$cids[0]]['levelid']);

        $copyid = $instance->copy($this->user->id, 2);
        $this->assertEquals(1, $DB->count_records('gradingform_matrix_fillings', ['instanceid' => $copyid]));

        $instance->clear_attempt(['criteria' => [$cids[0] => []]]);
        $this->assertEquals(0, $DB->count_records('gradingform_matrix_fillings', ['instanceid' => $instance->get_id()]));
    }

    public function test_validate_and_empty(): void {
        $controller = $this->simple();
        $ids = $this->level_ids($controller);
        $cids = array_keys($controller->get_definition()->matrix_criteria);
        $instance = $controller->create_instance($this->user->id, 1);

        $complete = ['criteria' => [
            $cids[0] => ['levelid' => $ids['A']['1']],
            $cids[1] => ['levelid' => $ids['B']['0']],
        ]];
        $this->assertTrue($instance->validate_grading_element($complete));
        $this->assertFalse($instance->is_empty_form($complete));

        $wronglevel = $complete;
        $wronglevel['criteria'][$cids[1]]['levelid'] = $ids['A']['1'];
        $this->assertFalse($instance->validate_grading_element($wronglevel));

        $partial = ['criteria' => [$cids[0] => ['levelid' => $ids['A']['1']]]];
        $this->assertFalse($instance->validate_grading_element($partial));
        $this->assertFalse($instance->validate_grading_element([]));

        $this->assertTrue($instance->is_empty_form(['criteria' => [$cids[0] => ['remark' => '']]]));
        $this->assertFalse($instance->is_empty_form(['criteria' => [$cids[0] => ['remark' => 'x']]]));
    }

    public function test_delete_definition(): void {
        global $DB;
        $controller = $this->simple();
        $ids = $this->level_ids($controller);
        $cids = array_keys($controller->get_definition()->matrix_criteria);
        $instance = $controller->create_instance($this->user->id, 1);
        $instance->update(['criteria' => [$cids[0] => ['levelid' => $ids['A']['1']]]]);

        $controller->delete_definition();

        $this->assertEquals(0, $DB->count_records('gradingform_matrix_criteria'));
        $this->assertEquals(0, $DB->count_records('gradingform_matrix_levels'));
        $this->assertEquals(0, $DB->count_records('gradingform_matrix_fillings'));
        $this->assertEquals(0, $DB->count_records('grading_instances'));
    }
}
