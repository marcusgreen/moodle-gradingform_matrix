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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Unit tests for the spreadsheet reader.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(spreadsheet_reader::class)]
final class spreadsheet_reader_test extends \advanced_testcase {
    /** @var string folder for the test files */
    private string $dir;

    protected function setUp(): void {
        parent::setUp();
        $this->dir = make_request_directory();
    }

    /**
     * Writes a file for the reader and returns its path.
     *
     * @param string $name
     * @param string $content
     * @return string
     */
    private function file(string $name, string $content): string {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Saves a spreadsheet with the given cells, and a formula in E2, and returns its path.
     *
     * @param string $type PhpSpreadsheet writer, Xlsx or Ods
     * @param string $name
     * @return string
     */
    private function spreadsheet(string $type, string $name): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Criterion', 'Level 1', 'Points', 'Level 2', 'Points'],
            ['Spelling', 'Many errors', 0, 'Few errors', 1.5],
        ], null, 'A1', true);
        $sheet->setCellValue('E2', '=1+1.5');
        $sheet->setCellValue('A4', 'Grammar');
        // A second sheet is not read.
        $spreadsheet->createSheet()->setCellValue('A1', 'Ignored');
        $path = $this->dir . '/' . $name;
        IOFactory::createWriter($spreadsheet, $type)->save($path);
        return $path;
    }

    /**
     * Returns a new reader.
     *
     * @param int $maxrows
     * @param int $maxcolumns
     * @return spreadsheet_reader
     */
    private function reader(int $maxrows = 200, int $maxcolumns = 41): spreadsheet_reader {
        return new spreadsheet_reader('gradingform_matrix', $maxrows, $maxcolumns);
    }

    /**
     * Asserts that reading fails with the given error code.
     *
     * @param string $errorcode
     * @param callable $read
     */
    private function assert_read_error(string $errorcode, callable $read): void {
        try {
            $read();
            $this->fail('Expected moodle_exception ' . $errorcode);
        } catch (moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    public function test_supports(): void {
        $this->assertTrue(spreadsheet_reader::supports('form.CSV'));
        $this->assertTrue(spreadsheet_reader::supports('form.xlsx'));
        $this->assertTrue(spreadsheet_reader::supports('form.ods'));
        $this->assertFalse(spreadsheet_reader::supports('form.xls'));
        $this->assertFalse(spreadsheet_reader::supports('form.json'));
    }

    /**
     * CSV cases.
     *
     * @return array
     */
    public static function csv_provider(): array {
        $expected = [['Spelling', 'Many, "few" errors', '0'], [], ['Grammar', 'Poor', '1,5']];
        return [
            'comma' => ["Spelling,\"Many, \"\"few\"\" errors\",0\n\nGrammar,Poor,\"1,5\"\n", $expected],
            'semicolon' => ["Spelling;\"Many, \"\"few\"\" errors\";0\r\n\r\nGrammar;Poor;1,5\r\n", $expected],
            'tab' => ["Spelling\t\"Many, \"\"few\"\" errors\"\t0\n\nGrammar\tPoor\t1,5\n", $expected],
            'byte order mark' => ["\xEF\xBB\xBFSpelling;\"Many, \"\"few\"\" errors\";0\n\nGrammar;Poor;1,5", $expected],
            'trailing empty cells and rows' => ["A,B,,\n\n,,\n", [['A', 'B']]],
            'quoted line break' => ["A,\"two\nlines\",1\n", [['A', "two\nlines", '1']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('csv_provider')]
    public function test_csv(string $content, array $expected): void {
        $this->assertSame($expected, $this->reader()->read($this->file('form.csv', $content), 'form.csv'));
    }

    public function test_csv_windows_encoding(): void {
        $content = "Fran\xE7ais;Tr\xE8s bien;2\n";
        $this->assertSame([['Français', 'Très bien', '2']], $this->reader()->read($this->file('f.csv', $content), 'f.csv'));
    }

    /**
     * Spreadsheet types.
     *
     * @return array
     */
    public static function spreadsheet_provider(): array {
        return [
            'Excel' => ['Xlsx', 'form.xlsx'],
            'OpenDocument' => ['Ods', 'form.ods'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('spreadsheet_provider')]
    public function test_spreadsheet(string $type, string $name): void {
        $rows = $this->reader()->read($this->spreadsheet($type, $name), $name);
        $this->assertSame(['Criterion', 'Level 1', 'Points', 'Level 2', 'Points'], $rows[0]);
        $this->assertSame(['Spelling', 'Many errors'], array_slice($rows[1], 0, 2));
        $this->assertEquals(0, $rows[1][2]);
        // The formula's saved result is used.
        $this->assertEquals(2.5, $rows[1][4]);
        $this->assertSame([], $rows[2]);
        $this->assertSame(['Grammar'], $rows[3]);
        $this->assertCount(4, $rows);
    }

    public function test_spreadsheet_into_definition(): void {
        $rows = $this->reader()->read($this->spreadsheet('Xlsx', 'form.xlsx'), 'form.xlsx');
        $importer = new definition_importer(['sortlevelsasc' => 1, 'lockzeropoints' => 1], 'gradingform_matrix');
        $criteria = $importer->from_rows($rows)['criteria'];
        $this->assertSame(['Spelling', 'Grammar'], array_column($criteria, 'description'));
        $this->assertSame([0.0, 2.5], array_column($criteria[0]['levels'], 'score'));
        $this->assertSame([], $criteria[1]['levels']);
    }

    public function test_errors(): void {
        $this->assert_read_error('importerrortype', fn() => $this->reader()->read($this->file('a.xls', 'x'), 'a.xls'));
        $this->assert_read_error(
            'importerrorspreadsheet',
            fn() => $this->reader()->read($this->file('a.xlsx', 'not a zip'), 'a.xlsx')
        );
        $this->assert_read_error(
            'importerrorspreadsheet',
            fn() => $this->reader()->read($this->file('a.ods', 'not a zip'), 'a.ods')
        );
    }

    public function test_size_limits(): void {
        $this->assertCount(3, $this->reader(3, 3)->read($this->file('a.csv', "1,2,3\n4\n5\n"), 'a.csv'));
        $csv = fn(string $content) => $this->reader(3, 3)->read($this->file('x.csv', $content), 'x.csv');
        $this->assert_read_error('importerrorsize', fn() => $csv("1\n2\n3\n4\n"));
        $this->assert_read_error('importerrorsize', fn() => $csv("1,2,3,4\n"));
        // Content after an empty row past the limit is not dropped silently.
        $this->assert_read_error('importerrorsize', fn() => $csv("1\n2\n3\n\n5\n"));
        // Empty rows past the limit are fine.
        $this->assertCount(3, $csv("1\n2\n3\n\n,,\n"));
        $this->assert_read_error(
            'importerrorsize',
            fn() => $this->reader(2, 41)->read($this->spreadsheet('Xlsx', 'big.xlsx'), 'big.xlsx')
        );
    }
}
