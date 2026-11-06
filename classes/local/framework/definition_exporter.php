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
 * Writes a level-based definition as JSON.
 *
 * The file holds the whole definition (name, description, options, criteria and levels) and the files
 * embedded in the description, base64 encoded. {@see definition_importer} reads it back.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class definition_exporter {
    /** Version of the JSON format, increased when its structure changes. */
    public const FORMAT_VERSION = 1;


    /**
     * Constructor.
     *
     * @param string $method grading method name written to the JSON file
     */
    public function __construct(
        /** @var string grading method name written to the JSON file */
        protected readonly string $method,
    ) {
    }

    /**
     * Builds the exported structure.
     *
     * @param string $name
     * @param string $description description, with @@PLUGINFILE@@ references to $files
     * @param int $descriptionformat
     * @param array $options display and calculation options
     * @param array $criteria criteria in sort order, each with 'levels'
     * @param array $files files embedded in the description, list of ['filepath', 'filename', 'mimetype', 'content'] with
     *     binary content
     * @return array
     */
    public function to_array(
        string $name,
        string $description,
        int $descriptionformat,
        array $options,
        array $criteria,
        array $files = []
    ): array {
        return [
            'formatversion' => self::FORMAT_VERSION,
            'method' => $this->method,
            'name' => $name,
            'description' => $description,
            'descriptionformat' => $descriptionformat,
            'options' => $options,
            'criteria' => $this->criteria_to_list($criteria),
            'files' => array_map(fn($file) => [
                'filepath' => (string)$file['filepath'],
                'filename' => (string)$file['filename'],
                'mimetype' => (string)$file['mimetype'],
                'content' => base64_encode($file['content']),
            ], array_values($files)),
        ];
    }

    /**
     * Writes the whole definition as JSON.
     *
     * @param string $name
     * @param string $description description, with @@PLUGINFILE@@ references to $files
     * @param int $descriptionformat
     * @param array $options display and calculation options
     * @param array $criteria criteria in sort order, each with 'levels'
     * @param array $files files embedded in the description, see {@see to_array()}
     * @return string
     */
    public function to_json(
        string $name,
        string $description,
        int $descriptionformat,
        array $options,
        array $criteria,
        array $files = []
    ): string {
        return json_encode(
            $this->to_array($name, $description, $descriptionformat, $options, $criteria, $files),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Drops ids and formats and orders levels by ascending score.
     *
     * @param array $criteria criteria keyed by id, each with 'levels' keyed by id
     * @return array list of ['description' => string, 'levels' => list of ['score' => float, 'definition' => string]]
     */
    protected function criteria_to_list(array $criteria): array {
        $list = [];
        foreach ($criteria as $criterion) {
            $levels = [];
            foreach ($criterion['levels'] ?? [] as $level) {
                $levels[] = ['score' => (float)$level['score'], 'definition' => (string)$level['definition']];
            }
            usort($levels, fn($a, $b) => $a['score'] <=> $b['score']);
            $list[] = ['description' => (string)$criterion['description'], 'levels' => $levels];
        }
        return $list;
    }
}
