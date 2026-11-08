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

use gradingform_matrix\local\framework\definition_importer;

/**
 * Unit tests for the reply parser.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reply_parser::class)]
final class reply_parser_test extends \basic_testcase {
    /** @var string a valid proposal */
    private const FORM = '{"name": "Essay", "description": "Use it.", "criteria": [
        {"description": "Content", "levels": [{"score": 0, "definition": "Weak"}, {"score": 2, "definition": "Strong"}]}
    ]}';

    /**
     * Returns a parser.
     *
     * @return reply_parser
     */
    private function parser(): reply_parser {
        return new reply_parser(new definition_importer(['sortlevelsasc' => 1, 'lockzeropoints' => 1], 'gradingform_matrix'));
    }

    public function test_message_and_fenced_form(): void {
        $result = $this->parser()->parse("Here is a draft.\n\n```json\n" . self::FORM . "\n```\n\nTell me what to change.");

        $this->assertSame("Here is a draft.\n\n\n\nTell me what to change.", $result['message']);
        $this->assertNull($result['error']);
        $this->assertSame('Essay', $result['proposal']['name']);
        $this->assertSame('Use it.', $result['proposal']['description']);
        $this->assertSame([
            ['description' => 'Content', 'levels' => [
                ['score' => 0.0, 'definition' => 'Weak'],
                ['score' => 2.0, 'definition' => 'Strong'],
            ]],
        ], $result['proposal']['criteria']);
    }

    public function test_reply_without_form(): void {
        $result = $this->parser()->parse('Three levels is common for short tasks.');
        $this->assertSame('Three levels is common for short tasks.', $result['message']);
        $this->assertNull($result['proposal']);
        $this->assertNull($result['error']);
    }

    public function test_reply_that_is_only_json(): void {
        $result = $this->parser()->parse(self::FORM);
        $this->assertSame('', $result['message']);
        $this->assertSame('Essay', $result['proposal']['name']);
    }

    public function test_options_and_files_are_ignored(): void {
        $form = json_decode(self::FORM, true);
        $form['options'] = ['showscorestudent' => 0];
        $form['files'] = [['filepath' => '/', 'filename' => 'x.html', 'content' => base64_encode('<script>')]];
        $result = $this->parser()->parse("```json\n" . json_encode($form) . "\n```");
        $this->assertNull($result['error']);
        $this->assertSame(['name', 'description', 'criteria'], array_keys($result['proposal']));
    }

    public function test_invalid_form_is_reported(): void {
        $result = $this->parser()->parse("Draft:\n```json\n{\"name\": \"Essay\", \"criteria\": []}\n```");
        $this->assertSame('Draft:', $result['message']);
        $this->assertNull($result['proposal']);
        $this->assertSame(get_string('err_nocriteria', 'gradingform_matrix'), $result['error']);

        $result = $this->parser()->parse("```json\n{\"criteria\": [{\"description\": \"A\", \"levels\": [{\"score\": \"lots\","
            . " \"definition\": \"B\"}]}]}\n```");
        $this->assertNull($result['proposal']);
        $this->assertNotNull($result['error']);
    }
}
