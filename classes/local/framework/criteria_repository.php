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
 * Reads and writes a definition's criteria and levels.
 *
 * Table names are passed in because each grading method owns its own tables.
 * They must be trusted constants, never user input.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class criteria_repository {
    /** Fields copied from {grading_definitions}. */
    private const DEFINITION_FIELDS = ['id', 'name', 'description', 'descriptionformat', 'status', 'copiedfromid',
        'timecreated', 'usercreated', 'timemodified', 'usermodified', 'timecopied', 'options'];

    /**
     * Constructor.
     *
     * @param string $criteriatable criteria table, without prefix
     * @param string $levelstable levels table, without prefix
     * @param string[] $criterionfields criterion fields besides id and definitionid
     * @param string[] $levelfields level fields besides id and criterionid
     */
    public function __construct(
        /** @var string criteria table, without prefix */
        protected readonly string $criteriatable,
        /** @var string levels table, without prefix */
        protected readonly string $levelstable,
        /** @var string[] criterion fields besides id and definitionid */
        protected readonly array $criterionfields = ['sortorder', 'description', 'descriptionformat'],
        /** @var string[] level fields besides id and criterionid */
        protected readonly array $levelfields = ['score', 'definition', 'definitionformat'],
    ) {
    }

    /**
     * Loads the active definition of an area with its criteria and levels in one query.
     *
     * @param int $areaid
     * @param string $method grading method name
     * @return array|null [definition record, criteria keyed by id each with 'levels' keyed by id], or null
     */
    public function load(int $areaid, string $method): ?array {
        global $DB;

        $criterioncols = implode(', ', array_map(fn($f) => "c.$f AS c_$f", array_merge(['id'], $this->criterionfields)));
        $levelcols = implode(', ', array_map(fn($f) => "l.$f AS l_$f", array_merge(['id'], $this->levelfields)));
        $sql = "SELECT gd.*, $criterioncols, $levelcols
                  FROM {grading_definitions} gd
             LEFT JOIN {{$this->criteriatable}} c ON c.definitionid = gd.id
             LEFT JOIN {{$this->levelstable}} l ON l.criterionid = c.id
                 WHERE gd.areaid = :areaid AND gd.method = :method
              ORDER BY c.sortorder, l.score";
        $rs = $DB->get_recordset_sql($sql, ['areaid' => $areaid, 'method' => $method]);

        $definition = null;
        $criteria = [];
        foreach ($rs as $record) {
            if ($definition === null) {
                $definition = new \stdClass();
                foreach (self::DEFINITION_FIELDS as $field) {
                    $definition->$field = $record->$field;
                }
            }
            $cid = $record->c_id;
            if (!empty($cid) && !isset($criteria[$cid])) {
                foreach (array_merge(['id'], $this->criterionfields) as $field) {
                    $criteria[$cid][$field] = $record->{'c_' . $field};
                }
                $criteria[$cid]['levels'] = [];
            }
            if (!empty($record->l_id)) {
                foreach (array_merge(['id'], $this->levelfields) as $field) {
                    $value = $record->{'l_' . $field};
                    if ($field === 'score') {
                        // Avoids displaying 1.00000.
                        $value = (float)$value;
                    }
                    $criteria[$cid]['levels'][$record->l_id][$field] = $value;
                }
            }
        }
        $rs->close();

        return $definition === null ? null : [$definition, $criteria];
    }

    /**
     * Writes submitted criteria and levels: inserts new ones, updates changed fields, deletes removed ones.
     *
     * Call inside a transaction. Level scores must already be normalised to numbers.
     *
     * @param int $definitionid
     * @param array $newcriteria submitted criteria keyed by id or NEWIDn
     * @param array $currentcriteria stored criteria
     */
    public function save(int $definitionid, array $newcriteria, array $currentcriteria): void {
        global $DB;
        $diff = new definition_diff($this->criterionfields, $this->levelfields);

        foreach ($newcriteria as $id => $criterion) {
            $levels = $criterion['levels'] ?? [];
            if (definition_diff::is_new_id($id)) {
                // TODO MDL-31235 format is not supported yet.
                $data = array_intersect_key($criterion, array_flip($this->criterionfields))
                    + ['definitionid' => $definitionid, 'descriptionformat' => FORMAT_MOODLE];
                $id = $DB->insert_record($this->criteriatable, $data);
            } else {
                $stored = $currentcriteria[$id] ?? ['levels' => []];
                $changed = $diff->changed_fields($this->criterionfields, $criterion, $stored);
                if ($changed) {
                    $DB->update_record($this->criteriatable, ['id' => $id] + array_intersect_key($criterion, array_flip($changed)));
                }
                $removed = array_diff(array_keys($stored['levels']), array_keys($levels));
                if ($removed) {
                    $DB->delete_records_list($this->levelstable, 'id', $removed);
                }
            }
            foreach ($levels as $levelid => $level) {
                if (definition_diff::is_new_id($levelid)) {
                    // TODO MDL-31235 format is not supported yet.
                    $data = array_intersect_key($level, array_flip($this->levelfields))
                        + ['criterionid' => $id, 'definitionformat' => FORMAT_MOODLE];
                    $DB->insert_record($this->levelstable, $data);
                } else {
                    $stored = $currentcriteria[$id]['levels'][$levelid] ?? [];
                    $changed = $diff->changed_fields($this->levelfields, $level, $stored);
                    if ($changed) {
                        $record = ['id' => $levelid] + array_intersect_key($level, array_flip($changed));
                        $DB->update_record($this->levelstable, $record);
                    }
                }
            }
        }

        $removed = array_diff(array_keys($currentcriteria), array_keys($newcriteria));
        if ($removed) {
            $DB->delete_records_list($this->levelstable, 'criterionid', $removed);
            $DB->delete_records_list($this->criteriatable, 'id', $removed);
        }
    }

    /**
     * Deletes all criteria and levels of a definition.
     *
     * @param int $definitionid
     */
    public function delete_for_definition(int $definitionid): void {
        global $DB;
        $DB->delete_records_select(
            $this->levelstable,
            "criterionid IN (SELECT id FROM {{$this->criteriatable}} WHERE definitionid = :definitionid)",
            ['definitionid' => $definitionid]
        );
        $DB->delete_records($this->criteriatable, ['definitionid' => $definitionid]);
    }
}
