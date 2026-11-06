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
 * Unit tests for the definition exporter.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(definition_exporter::class)]
final class definition_exporter_test extends \basic_testcase {
    /** @var array stored criteria, levels in descending order as loaded with sortlevelsasc = 0 */
    private const CRITERIA = [
        5 => ['id' => 5, 'sortorder' => 1, 'description' => 'Spelling', 'descriptionformat' => 0, 'levels' => [
            52 => ['id' => 52, 'score' => 2.0, 'definition' => 'No errors', 'definitionformat' => 0],
            51 => ['id' => 51, 'score' => 1.5, 'definition' => 'Some "minor", errors', 'definitionformat' => 0],
            50 => ['id' => 50, 'score' => 0.0, 'definition' => 'Many errors', 'definitionformat' => 0],
        ]],
        6 => ['id' => 6, 'sortorder' => 2, 'description' => 'Grammar', 'descriptionformat' => 0, 'levels' => [
            60 => ['id' => 60, 'score' => 0.0, 'definition' => 'Poor', 'definitionformat' => 0],
            61 => ['id' => 61, 'score' => 3.0, 'definition' => 'Good', 'definitionformat' => 0],
        ]],
    ];

    public function test_to_array(): void {
        $result = (new definition_exporter('matrix'))->to_array(
            'Essay',
            '<p>Desc</p>',
            FORMAT_HTML,
            ['sortlevelsasc' => 0],
            self::CRITERIA
        );

        $this->assertSame([
            'formatversion' => definition_exporter::FORMAT_VERSION,
            'method' => 'matrix',
            'name' => 'Essay',
            'description' => '<p>Desc</p>',
            'descriptionformat' => (int)FORMAT_HTML,
            'options' => ['sortlevelsasc' => 0],
            'criteria' => [
                ['description' => 'Spelling', 'levels' => [
                    ['score' => 0.0, 'definition' => 'Many errors'],
                    ['score' => 1.5, 'definition' => 'Some "minor", errors'],
                    ['score' => 2.0, 'definition' => 'No errors'],
                ]],
                ['description' => 'Grammar', 'levels' => [
                    ['score' => 0.0, 'definition' => 'Poor'],
                    ['score' => 3.0, 'definition' => 'Good'],
                ]],
            ],
            'files' => [],
        ], $result);
    }

    public function test_files_are_base64_encoded(): void {
        $files = [['filepath' => '/', 'filename' => 'a.png', 'mimetype' => 'image/png', 'content' => "\x89PNG\0"]];

        $result = (new definition_exporter('matrix'))->to_array('n', '', FORMAT_HTML, [], [], $files);

        $this->assertSame(
            [['filepath' => '/', 'filename' => 'a.png', 'mimetype' => 'image/png', 'content' => base64_encode("\x89PNG\0")]],
            $result['files']
        );
    }

    public function test_to_json(): void {
        $exporter = new definition_exporter('matrix');
        $json = $exporter->to_json('Ünïcode', '', FORMAT_HTML, [], self::CRITERIA);

        $this->assertStringContainsString('"name": "Ünïcode"', $json);
        $this->assertEquals(
            $exporter->to_array('Ünïcode', '', FORMAT_HTML, [], self::CRITERIA),
            json_decode($json, true)
        );
    }
}
