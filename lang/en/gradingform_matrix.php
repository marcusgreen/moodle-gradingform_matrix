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

/**
 * Language file for plugin gradingform_matrix
 *
 * @package    gradingform_matrix
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcriterion'] = 'Add criterion';
$string['additionalfeedback'] = 'Additional feedback';
$string['alwaysshowdefinition'] = 'Allow users to preview the grading form (otherwise it will only be displayed after grading)';
$string['backtoediting'] = 'Back to editing';
$string['confirmdeletecriterion'] = 'Are you sure you want to delete this criterion?';
$string['confirmdeletelevel'] = 'Are you sure you want to delete this level?';
$string['criterion'] = 'Criterion {$a}';
$string['criterionaddlevel'] = 'Add level';
$string['criteriondelete'] = 'Delete criterion';
$string['criterionduplicate'] = 'Duplicate criterion';
$string['criterionempty'] = 'Click to edit criterion';
$string['criterionmovedown'] = 'Move down';
$string['criterionmoveup'] = 'Move up';
$string['criterionremark'] = 'Remark for criterion {$a->description}: {$a->remark}';
$string['defineform'] = 'Define grading form';
$string['description'] = 'Description';
$string['enableremarks'] = 'Allow grader to add text remarks for each criterion';
$string['err_mintwolevels'] = 'Each criterion must have at least two levels';
$string['err_nocriteria'] = 'The grading form must contain at least one criterion';
$string['err_nodefinition'] = 'Level definition can not be empty';
$string['err_nodescription'] = 'Criterion description can not be empty';
$string['err_novariations'] = 'Criterion levels cannot all be worth the same number of points';
$string['err_scoreformat'] = 'Number of points for each level must be a valid number';
$string['err_totalscore'] = 'Maximum number of points possible when graded by the grading form must be more than zero';
$string['export'] = 'Export';
$string['exportnothing'] = 'There is no saved grading form to export.';
$string['formnotcompleted'] = 'Please choose something for each criterion';
$string['formoptions'] = 'Grading form options';
$string['formstatus'] = 'Current grading form status';
$string['gradingform'] = 'Grading form';
$string['gradingform_help'] = 'Define the grading form used to grade this activity. Add one or more criteria, each with a set of levels and scores.

Criterion descriptions and level definitions support **Markdown**, so you can use formatting such as `**bold**`, `*italic*`, lists and links.

For example, a criterion description could be:

* **Structure** - is the report *well organised* with a clear introduction, body and conclusion?';
$string['gradingof'] = '{$a} grading';
$string['import'] = 'Import';
$string['importerrorfile'] = 'The file contains an embedded file that can\'t be read: {$a}.';
$string['importerrorjson'] = 'The file is not valid JSON.';
$string['importerrorscore'] = '"{$a->score}" is not a valid number of points ({$a->where}).';
$string['importerrorstructure'] = 'The file is not a grading form export: each criterion needs a description and a list of levels, and each level a definition and a score.';
$string['importerrorused'] = 'This grading form has already been used for grading, so it can\'t be replaced by an import.';
$string['importerrorversion'] = 'The file has an unsupported format version ({$a}).';
$string['importfile'] = 'File to import';
$string['importfile_help'] = 'A JSON file made by "Export" on this or another site. It holds the whole grading form: name, description with its images, options, criteria and levels.';
$string['importform'] = 'Import grading form';
$string['importreplaces'] = 'Importing replaces the current grading form: its name, description, options, criteria and levels.';
$string['importsuccess'] = 'Imported {$a} criteria. The grading form is saved as a draft: check it, then save it to make it ready.';
$string['importwherelevel'] = 'criterion {$a->criterion}, level {$a->level}';
$string['level'] = 'Level {$a->definition}, {$a->score} points.';
$string['leveldefinition'] = 'Level {$a} definition';
$string['leveldelete'] = 'Delete level {$a}';
$string['levelempty'] = 'Click to edit level';
$string['levelsgroup'] = 'Levels group';
$string['lockzeropoints'] = 'Calculate grade having a minimum score of the minimum achievable grade for the grading form';
$string['lockzeropoints_help'] = 'This setting only applies if the sum of the minimum number of points for each criterion is greater than 0. If ticked, the minimum score of the activity will be the minimum achievable grade for the grading form. If unticked, the minimum possible score for the grading form will be mapped to the minimum grade available for the activity (which is 0 unless a scale is used).';
$string['name'] = 'Name';
$string['needregrademessage'] = 'The grading form definition was changed after this student had been graded. The student can not see this grading form until you check it and update the grade.';
$string['notset'] = 'Not set';
$string['pluginname'] = 'Matrix';
$string['pointsvalue'] = '{$a} points';
$string['previewform'] = 'Preview grading form';
$string['privacy:metadata:criterionid'] = 'An identifier for a specific criterion being graded.';
$string['privacy:metadata:fillingssummary'] = 'Stores information about the user\'s grade created by the grading form.';
$string['privacy:metadata:instanceid'] = 'An identifier relating to a grade in an activity.';
$string['privacy:metadata:levelid'] = 'The level obtained in the grading form.';
$string['privacy:metadata:remark'] = 'Remarks related to the criterion being assessed.';
$string['regrademessage1'] = 'You are about to save changes to a grading form that has already been used for grading. Please indicate if existing grades need to be reviewed. If you set this then the grading form will be hidden from students until their item is regraded.';
$string['regrademessage5'] = 'You are about to save significant changes to a grading form that has already been used for grading. The gradebook value will be unchanged, but the grading form will be hidden from students until their item is regraded.';
$string['regradeoption0'] = 'Do not mark for regrade';
$string['regradeoption1'] = 'Mark for regrade';
$string['restoredfromdraft'] = 'NOTE: The last attempt to grade this person was not saved properly so draft grades have been restored. If you want to cancel these changes use the \'Cancel\' button below.';
$string['save'] = 'Save';
$string['saveform'] = 'Save grading form and make it ready';
$string['saveformdraft'] = 'Save as draft';
$string['scoreinputforlevel'] = 'Score input for level {$a}';
$string['scoremapping'] = 'Score to grade mapping rules';
$string['scoremappingexplained'] = 'The minimum possible score for this grading form is <b>{$a->minscore} points</b>. It will be converted to the minimum grade available for the activity (which is 0 unless a scale is used). The maximum score of <b>{$a->maxscore} points</b> will be converted to the maximum grade. Intermediate scores will be converted respectively.

If a scale is used for grading, the score will be rounded and converted to the scale elements as if they were consecutive integers.

This grade calculation may be changed by editing the form and ticking the box \'Calculate grade having a minimum score of the minimum achievable grade for the grading form\'.';
$string['scorepostfix'] = '{$a} points';
$string['showdescriptionstudent'] = 'Display grading form description to those being graded';
$string['showdescriptionteacher'] = 'Display grading form description during evaluation';
$string['showremarksstudent'] = 'Show remarks to those being graded';
$string['showscorestudent'] = 'Display points for each level to those being graded';
$string['showscoreteacher'] = 'Display points for each level during evaluation';
$string['sortlevelsasc'] = 'Sort order for levels:';
$string['sortlevelsasc0'] = 'Descending by number of points';
$string['sortlevelsasc1'] = 'Ascending by number of points';
$string['zerolevelsabsent'] = 'Warning: The minimum possible score for this grading form is not 0; this can result in unexpected grades for the activity. To avoid this, each criterion should have a level with 0 points.<br>
This warning may be ignored if a scale is used for grading, and the minimum levels in the grading form correspond to the minimum value of the scale.';
