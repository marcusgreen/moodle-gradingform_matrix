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

use core_text;
use moodle_exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * Reads the cells of a CSV file or of the first sheet of an Excel (.xlsx) or OpenDocument (.ods) spreadsheet.
 *
 * Spreadsheets are read with core's PhpSpreadsheet, values only: formulas are never calculated, their
 * results as saved by the spreadsheet program are used. CSV files may use commas, semicolons or tabs,
 * and UTF-8 or Windows-1252 text.
 *
 * Old Excel files (.xls) are not supported, because Moodle's copy of PhpSpreadsheet has no reader for them.
 *
 * Error messages come from the lang component passed in, which must define the strings importerrortype,
 * importerrorspreadsheet and importerrorsize.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class spreadsheet_reader {
    /** File extensions this reader accepts, with the PhpSpreadsheet reader for each, or null for CSV. */
    public const TYPES = ['csv' => null, 'xlsx' => 'Xlsx', 'ods' => 'Ods'];

    /**
     * Constructor.
     *
     * @param string $component lang component that holds the error strings
     * @param int $maxrows most rows read, empty ones included
     * @param int $maxcolumns most columns read
     */
    public function __construct(
        /** @var string lang component that holds the error strings */
        protected readonly string $component,
        /** @var int most rows read, empty ones included */
        protected readonly int $maxrows = 200,
        /** @var int most columns read */
        protected readonly int $maxcolumns = 41,
    ) {
    }

    /**
     * Whether a file name has an extension this reader accepts.
     *
     * @param string $filename
     * @return bool
     */
    public static function supports(string $filename): bool {
        return array_key_exists(self::extension($filename), self::TYPES);
    }

    /**
     * Reads a file's cells.
     *
     * @param string $path the file
     * @param string $filename its original name, whose extension says how to read it
     * @return array list of rows, each a list of cells (string, int or float); empty rows are kept, so a row's
     *     index + 1 is its row number in the spreadsheet
     * @throws moodle_exception if the type is not supported, the file can't be read or it is too big
     */
    public function read(string $path, string $filename): array {
        $extension = self::extension($filename);
        if (!array_key_exists($extension, self::TYPES)) {
            throw new moodle_exception('importerrortype', $this->component);
        }
        $rows = self::TYPES[$extension] === null
            ? $this->read_csv((string)file_get_contents($path))
            : $this->read_spreadsheet($path, self::TYPES[$extension]);

        // Drop empty rows at the end, and empty cells at the end of each row.
        $rows = array_map(function (array $row): array {
            while ($row && trim((string)end($row)) === '') {
                array_pop($row);
            }
            return $row;
        }, $rows);
        while ($rows && !end($rows)) {
            array_pop($rows);
        }

        $width = $rows ? max(array_map('count', $rows)) : 0;
        if ($width > $this->maxcolumns) {
            $this->too_big();
        }
        return $rows;
    }

    /**
     * Fails because the file has more rows or columns than allowed.
     *
     * @throws moodle_exception
     */
    protected function too_big(): never {
        throw new moodle_exception('importerrorsize', $this->component, '', [
            'rows' => $this->maxrows,
            'columns' => $this->maxcolumns,
        ]);
    }

    /**
     * Reads CSV text.
     *
     * @param string $content
     * @return array
     */
    protected function read_csv(string $content): array {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } else if (!mb_check_encoding($content, 'UTF-8')) {
            // Excel on Windows saves CSV in the system code page unless told otherwise.
            $content = core_text::convert($content, 'windows-1252', 'utf-8');
        }
        $delimiter = self::delimiter($content);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $row = $row === [null] ? [] : array_map('strval', $row);
            if (count($rows) >= $this->maxrows) {
                // Past the limit only check for more content, without keeping it.
                if (array_filter($row, fn($cell) => trim($cell) !== '')) {
                    fclose($handle);
                    $this->too_big();
                }
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Reads the first sheet of a spreadsheet.
     *
     * @param string $path
     * @param string $type PhpSpreadsheet reader name
     * @return array
     * @throws moodle_exception if the file can't be read
     */
    protected function read_spreadsheet(string $path, string $type): array {
        try {
            $reader = IOFactory::createReader($type);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new moodle_exception('importerrorspreadsheet', $this->component, '', null, $e->getMessage());
        }
        $lastrow = $sheet->getHighestDataRow();
        $lastcolumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        if ($lastrow > $this->maxrows || $lastcolumn > $this->maxcolumns) {
            $this->too_big();
        }
        try {
            $rows = [];
            for ($r = 1; $r <= $lastrow; $r++) {
                $row = [];
                for ($c = 1; $c <= $lastcolumn; $c++) {
                    $cell = $sheet->getCell([$c, $r]);
                    // Use the result the spreadsheet program saved, so nothing is calculated here.
                    $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                    if ($value instanceof RichText) {
                        $value = $value->getPlainText();
                    }
                    $row[] = is_int($value) || is_float($value) ? $value : (string)($value ?? '');
                }
                $rows[] = $row;
            }
            return $rows;
        } catch (\Throwable $e) {
            throw new moodle_exception('importerrorspreadsheet', $this->component, '', null, $e->getMessage());
        }
    }

    /**
     * Guesses a CSV file's delimiter from its first line: comma, semicolon or tab.
     *
     * Excel uses semicolons where the decimal separator is a comma.
     *
     * @param string $content
     * @return string
     */
    protected static function delimiter(string $content): string {
        $line = strtok($content, "\r\n") ?: '';
        // Quoted text may hold any of them, so leave it out of the count.
        $line = preg_replace('/"[^"]*"/', '', $line);
        $counts = ["\t" => substr_count($line, "\t"), ';' => substr_count($line, ';'), ',' => substr_count($line, ',')];
        arsort($counts);
        return reset($counts) > 0 ? key($counts) : ',';
    }

    /**
     * Returns a file name's extension, in lower case.
     *
     * @param string $filename
     * @return string
     */
    protected static function extension(string $filename): string {
        return core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}
