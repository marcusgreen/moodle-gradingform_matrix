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

use moodle_exception;

/**
 * Unit tests for the definition importer.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(definition_importer::class)]
final class definition_importer_test extends \basic_testcase {
    /** @var array default options passed to the importer */
    private const DEFAULTS = ['sortlevelsasc' => 1, 'lockzeropoints' => 1, 'enableremarks' => 1];

    /** @var array criteria used across tests */
    private const CRITERIA = [
        ['description' => 'Spelling', 'levels' => [
            ['score' => 0.0, 'definition' => 'Many errors'],
            ['score' => 1.5, 'definition' => 'Some, "minor" errors'],
        ]],
        ['description' => 'Grammar', 'levels' => [
            ['score' => 0.0, 'definition' => 'Poor'],
            ['score' => 3.0, 'definition' => 'Good'],
        ]],
    ];

    /**
     * Returns a new importer.
     *
     * @return definition_importer
     */
    private function importer(): definition_importer {
        return new definition_importer(self::DEFAULTS);
    }

    /**
     * Asserts that parsing fails with the given error code.
     *
     * @param string $errorcode
     * @param callable $parse
     */
    private function assert_import_error(string $errorcode, callable $parse): void {
        try {
            $parse();
            $this->fail('Expected moodle_exception ' . $errorcode);
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    public function test_json_round_trip(): void {
        $exporter = new definition_exporter('matrix');
        $json = $exporter->to_json(
            'Essay',
            '<p>Desc</p>',
            FORMAT_MARKDOWN,
            ['sortlevelsasc' => 0, 'lockzeropoints' => 1, 'enableremarks' => 0],
            self::CRITERIA,
            [['filepath' => '/', 'filename' => 'a.png', 'mimetype' => 'image/png', 'content' => "\x89PNG\0"]]
        );

        $this->assertSame([
            'name' => 'Essay',
            'description' => '<p>Desc</p>',
            'descriptionformat' => (int)FORMAT_MARKDOWN,
            'options' => ['sortlevelsasc' => 0, 'lockzeropoints' => 1, 'enableremarks' => 0],
            'criteria' => self::CRITERIA,
            'files' => [['filepath' => '/', 'filename' => 'a.png', 'content' => "\x89PNG\0"]],
        ], $this->importer()->from_json($json));
    }

    public function test_json_cleans_options_and_name(): void {
        $json = json_encode([
            'formatversion' => 1,
            'name' => '<b>Essay</b>',
            'descriptionformat' => 99,
            'description' => 'D',
            'options' => ['sortlevelsasc' => '0', 'enableremarks' => 'yes', 'unknown' => 1],
            'criteria' => [['description' => 'A', 'levels' => [['score' => '2', 'definition' => 'x']]]],
        ]);

        $result = $this->importer()->from_json($json);

        $this->assertSame('Essay', $result['name']);
        $this->assertSame((int)FORMAT_HTML, $result['descriptionformat']);
        $this->assertSame(['sortlevelsasc' => 0, 'lockzeropoints' => 1, 'enableremarks' => 1], $result['options']);
        $this->assertSame(2.0, $result['criteria'][0]['levels'][0]['score']);
    }

    public function test_json_without_optional_fields(): void {
        $json = json_encode(['formatversion' => 1, 'criteria' => [['description' => 'A', 'levels' => []]]]);

        $result = $this->importer()->from_json($json);

        $this->assertNull($result['name']);
        $this->assertNull($result['description']);
        $this->assertNull($result['descriptionformat']);
        $this->assertNull($result['options']);
        $this->assertSame([], $result['files']);
        $this->assertSame([['description' => 'A', 'levels' => []]], $result['criteria']);
    }

    public function test_json_with_byte_order_mark(): void {
        $json = "\xEF\xBB\xBF" . json_encode(['formatversion' => 1, 'criteria' => [['description' => 'A', 'levels' => []]]]);

        $this->assertSame('A', $this->importer()->from_json($json)['criteria'][0]['description']);
    }

    public function test_errors(): void {
        $importer = $this->importer();
        $this->assert_import_error('importerrorjson', fn() => $importer->from_json('{nope'));
        $this->assert_import_error('importerrorversion', fn() => $importer->from_json('{"criteria": []}'));
        $this->assert_import_error('importerrorversion', fn() => $importer->from_json('{"formatversion": 2, "criteria": []}'));
        $this->assert_import_error('importerrorstructure', fn() => $importer->from_json('{"formatversion": 1}'));
        $this->assert_import_error(
            'importerrorstructure',
            fn() => $importer->from_json('{"formatversion": 1, "criteria": [{"description": "A"}]}')
        );
        $this->assert_import_error(
            'importerrorstructure',
            fn() => $importer->from_json('{"formatversion": 1, "criteria": [{"description": "A", "levels": [{"score": 1}]}]}')
        );
        $this->assert_import_error('err_nocriteria', fn() => $importer->from_json('{"formatversion": 1, "criteria": []}'));
        $this->assert_import_error('importerrorscore', fn() => $importer->from_json(
            '{"formatversion": 1, "criteria": [{"description": "A", "levels": [{"score": "lots", "definition": "x"}]}]}'
        ));
        $this->assert_import_error('importerrorscore', fn() => $importer->from_json(
            '{"formatversion": 1, "criteria": [{"description": "A", "levels": [{"score": null, "definition": "x"}]}]}'
        ));

        $json = fn(array $files) => json_encode(['formatversion' => 1, 'description' => 'D', 'files' => $files,
            'criteria' => [['description' => 'A', 'levels' => []]]]);
        $png = base64_encode('png');
        $this->assert_import_error('importerrorstructure', fn() => $importer->from_json($json(['x' => 1])));
        $this->assert_import_error('importerrorfile', fn() => $importer->from_json($json([['filename' => 'a.png']])));
        $this->assert_import_error(
            'importerrorfile',
            fn() => $importer->from_json($json([['filename' => 'a.png', 'content' => 'not base64!']]))
        );
        $this->assert_import_error(
            'importerrorfile',
            fn() => $importer->from_json($json([['filename' => '../a.png', 'content' => $png]]))
        );
        $this->assert_import_error(
            'importerrorfile',
            fn() => $importer->from_json($json([['filepath' => 'img', 'filename' => 'a.png', 'content' => $png]]))
        );
        $this->assert_import_error(
            'importerrorfile',
            fn() => $importer->from_json($json([['filepath' => '/img/', 'filename' => 'a.png', 'content' => $png]]))
        );
        $this->assert_import_error('importerrorfile', fn() => $importer->from_json($json([
            ['filename' => 'a.png', 'content' => $png],
            ['filepath' => '/', 'filename' => 'a.png', 'content' => $png],
        ])));

        $this->assert_import_error('importerrorjson', fn() => $importer->from_json("{\"formatversion\": 1, \"name\": \"\xE9\"}"));
    }

    public function test_score_error_names_location(): void {
        try {
            $this->importer()->from_json(json_encode(['formatversion' => 1, 'criteria' => [
                ['description' => 'A', 'levels' => [['score' => 1, 'definition' => 'x'], ['score' => 'two', 'definition' => 'y']]],
            ]]));
            $this->fail('Expected moodle_exception');
        } catch (moodle_exception $e) {
            $this->assertStringContainsString('"two"', $e->getMessage());
            $this->assertStringContainsString('criterion 1, level 2', $e->getMessage());
        }
    }
}
