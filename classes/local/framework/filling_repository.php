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
 * Reads and writes how a grading instance was filled in, one record per criterion.
 *
 * The table name is passed in because each grading method owns its own tables.
 * It must be a trusted constant, never user input.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filling_repository {
    /**
     * Constructor.
     *
     * @param string $table fillings table, without prefix
     * @param string[] $fields fields besides id, instanceid and criterionid
     */
    public function __construct(
        /** @var string fillings table, without prefix */
        protected readonly string $table,
        /** @var string[] fields besides id, instanceid and criterionid */
        protected readonly array $fields = ['levelid', 'remark', 'remarkformat'],
    ) {
    }

    /**
     * Returns an instance's fillings as arrays keyed by criterion id.
     *
     * @param int $instanceid
     * @return array
     */
    public function load(int $instanceid): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records($this->table, ['instanceid' => $instanceid]) as $record) {
            $result[$record->criterionid] = (array)$record;
        }
        return $result;
    }

    /**
     * Copies fillings to another instance.
     *
     * @param array $fillings as returned by {@see load()}
     * @param int $instanceid target instance
     */
    public function copy(array $fillings, int $instanceid): void {
        global $DB;
        $records = [];
        foreach ($fillings as $criterionid => $filling) {
            $records[] = ['instanceid' => $instanceid, 'criterionid' => $criterionid]
                + array_intersect_key($filling, array_flip($this->fields));
        }
        if ($records) {
            $DB->insert_records($this->table, $records);
        }
    }

    /**
     * Saves submitted fillings: inserts new ones, updates changed fields only, deletes criteria no longer submitted.
     *
     * @param int $instanceid
     * @param array $current fillings as returned by {@see load()}
     * @param array $submitted submitted values keyed by criterion id
     */
    public function save(int $instanceid, array $current, array $submitted): void {
        global $DB;
        foreach ($submitted as $criterionid => $values) {
            if (!array_key_exists($criterionid, $current)) {
                $record = ['instanceid' => $instanceid, 'criterionid' => $criterionid];
                foreach ($this->fields as $field) {
                    if (isset($values[$field])) {
                        $record[$field] = $values[$field];
                    }
                }
                $DB->insert_record($this->table, $record);
            } else {
                $record = ['id' => $current[$criterionid]['id']];
                foreach ($this->fields as $field) {
                    if (isset($values[$field]) && $current[$criterionid][$field] != $values[$field]) {
                        $record[$field] = $values[$field];
                    }
                }
                if (count($record) > 1) {
                    $DB->update_record($this->table, $record);
                }
            }
        }
        $removed = array_diff_key($current, $submitted);
        if ($removed) {
            $DB->delete_records_list($this->table, 'id', array_column($removed, 'id'));
        }
    }

    /**
     * Deletes all fillings of an instance, or only those of some criteria.
     *
     * @param int $instanceid
     * @param int[]|null $criterionids null for all criteria
     */
    public function delete(int $instanceid, ?array $criterionids = null): void {
        global $DB;
        if ($criterionids === null) {
            $DB->delete_records($this->table, ['instanceid' => $instanceid]);
            return;
        }
        if ($criterionids) {
            [$insql, $params] = $DB->get_in_or_equal($criterionids, SQL_PARAMS_NAMED);
            $params['instanceid'] = $instanceid;
            $DB->delete_records_select($this->table, "instanceid = :instanceid AND criterionid $insql", $params);
        }
    }

    /**
     * Deletes the fillings of every instance of a definition.
     *
     * @param int $definitionid
     */
    public function delete_for_definition(int $definitionid): void {
        global $DB;
        $DB->delete_records_select(
            $this->table,
            'instanceid IN (SELECT id FROM {grading_instances} WHERE definitionid = :definitionid)',
            ['definitionid' => $definitionid]
        );
    }
}
