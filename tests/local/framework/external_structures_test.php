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

use core_external\external_multiple_structure;

/**
 * Tests for the web service structures of definitions and fillings.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(external_structures::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\gradingform_matrix_controller::class)]
final class external_structures_test extends \basic_testcase {
    public function test_definition_is_keyed_by_method(): void {
        $details = external_structures::definition('matrix');
        $this->assertSame(['matrix_criteria'], array_keys($details));
        $criteria = $details['matrix_criteria'];
        $this->assertInstanceOf(external_multiple_structure::class, $criteria);
        $this->assertSame(
            ['id', 'sortorder', 'description', 'descriptionformat', 'levels'],
            array_keys($criteria->content->keys)
        );
        $this->assertSame(
            ['id', 'score', 'definition', 'definitionformat'],
            array_keys($criteria->content->keys['levels']->content->keys)
        );
    }

    public function test_filling(): void {
        $details = external_structures::filling();
        $this->assertSame(['criteria'], array_keys($details));
        $this->assertSame(
            ['id', 'criterionid', 'levelid', 'remark', 'remarkformat'],
            array_keys($details['criteria']->content->keys)
        );
    }

    public function test_controller_uses_its_method_name(): void {
        $this->assertSame(['matrix_criteria'], array_keys(\gradingform_matrix_controller::get_external_definition_details()));
        $this->assertSame(['criteria'], array_keys(\gradingform_matrix_controller::get_external_instance_filling_details()));
    }
}
