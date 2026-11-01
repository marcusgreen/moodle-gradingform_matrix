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
 * Compares a stored definition with submitted editor data, without touching the database.
 *
 * Criteria and levels use the editor array shape: criteria keyed by id (or NEWIDn for new ones),
 * each with 'levels' keyed the same way. Level scores must already be normalised to numbers.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class definition_diff {
    /** Definition fields whose change counts as a text change. */
    private const DEFINITION_FIELDS = ['status', 'description', 'descriptionformat', 'name', 'options'];

    /**
     * Constructor.
     *
     * @param string[] $criterionfields criterion fields that can be edited
     * @param string[] $levelfields level fields that can be edited; 'score' changes count as score changes
     */
    public function __construct(
        /** @var string[] criterion fields that can be edited */
        private readonly array $criterionfields = ['sortorder', 'description', 'descriptionformat'],
        /** @var string[] level fields that can be edited */
        private readonly array $levelfields = ['score', 'definition', 'definitionformat'],
    ) {
    }

    /**
     * Whether an editor key stands for a record that does not exist yet.
     *
     * @param int|string $id
     * @return bool
     */
    public static function is_new_id($id): bool {
        return (bool)preg_match('/^NEWID\d+$/', (string)$id);
    }

    /**
     * Compares the definitions.
     *
     * @param \stdClass $current stored definition record
     * @param array $currentcriteria stored criteria with levels
     * @param bool $currentlockzeropoints stored 'lockzeropoints' option
     * @param \stdClass $new submitted definition record (options already JSON-encoded)
     * @param array $newcriteria submitted criteria with levels
     * @param bool $newlockzeropoints submitted 'lockzeropoints' option
     * @param int|null $usermodified user saving the definition
     * @return change_summary
     */
    public function compare(
        \stdClass $current,
        array $currentcriteria,
        bool $currentlockzeropoints,
        \stdClass $new,
        array $newcriteria,
        bool $newlockzeropoints,
        ?int $usermodified = null
    ): change_summary {
        $summary = new change_summary();

        if ($newlockzeropoints != $currentlockzeropoints) {
            $summary->add(change_level::SCORES_CHANGED);
        }

        foreach ($newcriteria as $id => $criterion) {
            $levels = $criterion['levels'] ?? [];
            $maxscore = null;
            if (self::is_new_id($id)) {
                $summary->add(change_level::CRITERIA_ADDED);
            } else {
                $stored = $currentcriteria[$id] ?? ['levels' => []];
                if ($this->changed_fields($this->criterionfields, $criterion, $stored)) {
                    $summary->add(change_level::TEXT);
                }
                foreach ($stored['levels'] as $levelid => $storedlevel) {
                    if ($maxscore === null || $maxscore < $storedlevel['score']) {
                        $maxscore = $storedlevel['score'];
                    }
                    if (!array_key_exists($levelid, $levels)) {
                        $summary->add(change_level::LEVELS_REMOVED);
                    }
                }
            }
            foreach ($levels as $levelid => $level) {
                if (self::is_new_id($levelid)) {
                    if ($maxscore !== null && $maxscore >= ($level['score'] ?? null)) {
                        $summary->add(change_level::LEVELS_ADDED_SAME_MAX);
                    } else {
                        $summary->add(change_level::SCORES_CHANGED);
                    }
                } else {
                    $changed = $this->changed_fields(
                        $this->levelfields,
                        $level,
                        $currentcriteria[$id]['levels'][$levelid] ?? []
                    );
                    if ($changed) {
                        if (in_array('score', $changed)) {
                            $summary->add(change_level::SCORES_CHANGED);
                        }
                        $summary->add(change_level::TEXT);
                    }
                }
            }
        }

        foreach (array_keys($currentcriteria) as $id) {
            if (!array_key_exists($id, $newcriteria)) {
                $summary->add(change_level::SCORES_CHANGED);
            }
        }

        foreach (self::DEFINITION_FIELDS as $key) {
            if (isset($new->$key) && $new->$key != ($current->$key ?? null)) {
                $summary->add(change_level::TEXT);
            }
        }
        if ($usermodified && $usermodified != ($current->usermodified ?? null)) {
            $summary->add(change_level::TEXT);
        }

        return $summary;
    }

    /**
     * Returns the submitted fields whose value differs from the stored one.
     *
     * @param string[] $fields fields to compare
     * @param array $submitted
     * @param array $stored
     * @return string[]
     */
    public function changed_fields(array $fields, array $submitted, array $stored): array {
        $changed = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $submitted) && $submitted[$field] != ($stored[$field] ?? null)) {
                $changed[] = $field;
            }
        }
        return $changed;
    }
}
