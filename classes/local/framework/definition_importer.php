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

/**
 * Reads a level-based definition written by {@see definition_exporter}, or criteria from a spreadsheet.
 *
 * Only the structure is checked here: that the file parses, has criteria and that every score is a number.
 * Content rules (two levels per criterion, non-empty texts, ...) are left to the editor, because an
 * import is saved as a draft and the teacher makes it ready from there.
 *
 * Error messages come from the lang component passed in, which must define the strings importerrorjson,
 * importerrorversion, importerrorstructure, importerrorfile, importerrorscore, importerrornoscore, importwherelevel,
 * importwhererow and err_nocriteria.
 *
 * {@see from_json()} returns:
 * - 'name', 'description', 'descriptionformat', 'options': values from the file, or null when absent
 * - 'criteria': list of ['description' => string, 'levels' => list of ['score' => float, 'definition' => string]]
 * - 'files': files embedded in the description, list of ['filepath' => string, 'filename' => string,
 *   'content' => binary string]; always empty when there is no description
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class definition_importer {
    /** Formats accepted for the description, as integers (the FORMAT_ constants are strings). */
    private const DESCRIPTION_FORMATS = [0, 1, 2, 4];

    /** @var int deepest nesting accepted; an export nests about six levels */
    private const MAXDEPTH = 32;

    /**
     * Constructor.
     *
     * @param array $defaultoptions the method's options with default values; other option keys are dropped
     * @param string $component lang component that holds the error strings
     */
    public function __construct(
        /** @var array the method's options with default values; other option keys are dropped */
        protected readonly array $defaultoptions,
        /** @var string lang component that holds the error strings */
        protected readonly string $component,
    ) {
    }

    /**
     * Parses a JSON export.
     *
     * @param string $content
     * @return array
     * @throws moodle_exception if the file is not a valid export
     */
    public function from_json(string $content): array {
        $data = json_decode(self::strip_bom($content), true, self::MAXDEPTH);
        if (!is_array($data)) {
            throw new moodle_exception('importerrorjson', $this->component);
        }
        $version = $data['formatversion'] ?? null;
        if ($version !== definition_exporter::FORMAT_VERSION) {
            throw new moodle_exception('importerrorversion', $this->component, '', s(json_encode($version)));
        }
        if (!isset($data['criteria']) || !is_array($data['criteria']) || !array_is_list($data['criteria'])) {
            throw new moodle_exception('importerrorstructure', $this->component);
        }

        $criteria = [];
        foreach ($data['criteria'] as $c => $criterion) {
            if (
                !is_array($criterion) || !self::is_text($criterion['description'] ?? null)
                    || !is_array($criterion['levels'] ?? null) || !array_is_list($criterion['levels'])
            ) {
                throw new moodle_exception('importerrorstructure', $this->component);
            }
            $levels = [];
            foreach ($criterion['levels'] as $l => $level) {
                if (!is_array($level) || !self::is_text($level['definition'] ?? null) || !array_key_exists('score', $level)) {
                    throw new moodle_exception('importerrorstructure', $this->component);
                }
                $where = get_string('importwherelevel', $this->component, ['criterion' => $c + 1, 'level' => $l + 1]);
                $levels[] = [
                    'score' => $this->parse_score($level['score'], $where),
                    'definition' => trim((string)$level['definition']),
                ];
            }
            $criteria[] = ['description' => trim((string)$criterion['description']), 'levels' => $levels];
        }
        $this->require_criteria($criteria);

        $name = self::is_text($data['name'] ?? null) ? core_text::substr(trim(clean_param($data['name'], PARAM_TEXT)), 0, 255) : '';
        $description = self::is_text($data['description'] ?? null) ? (string)$data['description'] : null;
        $format = (int)($data['descriptionformat'] ?? FORMAT_HTML);
        if (!in_array($format, self::DESCRIPTION_FORMATS, true)) {
            $format = (int)FORMAT_HTML;
        }
        return [
            'name' => $name === '' ? null : $name,
            'description' => $description,
            'descriptionformat' => $description === null ? null : $format,
            'options' => is_array($data['options'] ?? null) ? $this->clean_options($data['options']) : null,
            'criteria' => $criteria,
            'files' => $description === null ? [] : $this->parse_files($data['files'] ?? []),
        ];
    }

    /**
     * Reads criteria from spreadsheet rows, as {@see spreadsheet_reader::read()} returns them.
     *
     * Each row is a criterion: its description, then a pair of cells for each level, the level's
     * definition and its points. A first row whose first points cell is not a number is a header and is
     * skipped. Empty rows, and empty pairs, are skipped too.
     *
     * Returns the same structure as {@see from_json()}, with no name, description, options or files.
     *
     * @param array $rows list of rows, each a list of cells
     * @return array
     * @throws moodle_exception if a level has text but no points, a score is not a number, or there are no criteria
     */
    public function from_rows(array $rows): array {
        $criteria = [];
        $first = true;
        foreach ($rows as $r => $row) {
            $row = array_map(fn($cell) => is_string($cell) ? trim($cell) : $cell, array_values($row));
            if (!array_filter($row, fn($cell) => $cell !== '' && $cell !== null)) {
                continue;
            }
            if ($first) {
                $first = false;
                $points = $row[2] ?? '';
                if ($points !== '' && unformat_float((string)$points, true) === false) {
                    continue;
                }
            }
            $levels = [];
            $l = 0;
            for ($i = 1; $i < count($row); $i += 2) {
                $definition = (string)($row[$i] ?? '');
                $score = $row[$i + 1] ?? '';
                if ($definition === '' && $score === '') {
                    continue;
                }
                $l++;
                $where = get_string('importwhererow', $this->component, ['row' => $r + 1, 'level' => $l]);
                if ($score === '') {
                    throw new moodle_exception('importerrornoscore', $this->component, '', $where);
                }
                $levels[] = ['score' => $this->parse_score($score, $where), 'definition' => $definition];
            }
            $criteria[] = ['description' => (string)($row[0] ?? ''), 'levels' => $levels];
        }
        $this->require_criteria($criteria);
        return [
            'name' => null,
            'description' => null,
            'descriptionformat' => null,
            'options' => null,
            'criteria' => $criteria,
            'files' => [],
        ];
    }

    /**
     * Decodes the files embedded in the description.
     *
     * The mime type in the file is ignored; Moodle works it out from the file name. Only the top folder is accepted.
     *
     * @param mixed $files
     * @return array list of ['filepath' => string, 'filename' => string, 'content' => binary string]
     * @throws moodle_exception if a file entry is invalid
     */
    protected function parse_files(mixed $files): array {
        if (!is_array($files) || !array_is_list($files)) {
            throw new moodle_exception('importerrorstructure', $this->component);
        }
        $result = [];
        foreach ($files as $file) {
            $filename = is_array($file) && is_string($file['filename'] ?? null) ? $file['filename'] : '';
            $filepath = is_array($file) && is_string($file['filepath'] ?? null) ? $file['filepath'] : '/';
            $content = is_array($file) && is_string($file['content'] ?? null) ? base64_decode($file['content'], true) : false;
            // The description editor has no folders, and saving it would drop files in one.
            $valid = $filename !== '' && $filename === clean_param($filename, PARAM_FILE)
                && $filepath === '/' && $content !== false;
            if (!$valid || isset($result[$filepath . $filename])) {
                throw new moodle_exception('importerrorfile', $this->component, '', s($filepath . $filename));
            }
            $result[$filepath . $filename] = ['filepath' => $filepath, 'filename' => $filename, 'content' => $content];
        }
        return array_values($result);
    }

    /**
     * Keeps only the method's own options, as 0 or 1, filling missing ones with defaults.
     *
     * @param array $options
     * @return array
     */
    protected function clean_options(array $options): array {
        $clean = $this->defaultoptions;
        foreach (array_keys($this->defaultoptions) as $key) {
            if (array_key_exists($key, $options)) {
                $clean[$key] = empty($options[$key]) ? 0 : 1;
            }
        }
        return $clean;
    }

    /**
     * Converts a score to a float, accepting the user's decimal separator.
     *
     * @param mixed $score
     * @param string $where location of the score, used in the error message
     * @return float
     * @throws moodle_exception if the score is not a number
     */
    protected function parse_score(mixed $score, string $where): float {
        $value = is_int($score) || is_float($score) || is_string($score) ? unformat_float((string)$score, true) : false;
        if ($value === false || $value === null || !is_finite($value)) {
            $a = ['score' => s(is_scalar($score) ? (string)$score : json_encode($score)), 'where' => $where];
            throw new moodle_exception('importerrorscore', $this->component, '', $a);
        }
        return $value;
    }

    /**
     * Fails when there is nothing to import.
     *
     * @param array $criteria
     * @throws moodle_exception if the list is empty
     */
    protected function require_criteria(array $criteria): void {
        if (!$criteria) {
            throw new moodle_exception('err_nocriteria', $this->component);
        }
    }

    /**
     * Whether a decoded JSON value can be used as text.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function is_text(mixed $value): bool {
        return is_string($value) || is_int($value) || is_float($value);
    }

    /**
     * Removes a UTF-8 byte order mark, which some editors add and json_decode() rejects.
     *
     * @param string $content
     * @return string
     */
    protected static function strip_bom(string $content): string {
        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }
}
