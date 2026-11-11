<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for customcertelement_conditionaltext.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addrule'] = 'Add another rule';
$string['appearance'] = 'Appearance';
$string['completion_complete'] = 'Has completed the course';
$string['completion_incomplete'] = 'Has not completed the course';
$string['completionstate'] = 'Course completion';
$string['coursegrade'] = 'Course total';
$string['defaulttext'] = 'Text when no rule matches';
$string['defaulttext_help'] = 'Printed when none of the rules match. Leave empty to print nothing. The same placeholders as in the rule texts can be used.';
$string['deleterule'] = 'Delete rule {no}';
$string['errorgradevalue'] = 'Enter a number.';
$string['errortoomanyrules'] = 'An element can have at most {$a} rules.';
$string['gradecondition'] = 'Grade is';
$string['gradeitem'] = 'Grade item';
$string['intro'] = 'Rules are checked from top to bottom against the learner who receives the certificate. Their text is printed when the condition is met.';
$string['matchmode'] = 'When several rules match';
$string['matchmode_all'] = 'Show the texts of all matching rules';
$string['matchmode_first'] = 'Show only the first matching rule';
$string['matchmode_help'] = 'With "first matching rule", order your rules from the most to the least specific (for example 90%, then 75%, then 50%). With "all matching rules", every matching text is printed on its own line.';
$string['op_contains'] = 'contains';
$string['op_empty'] = 'is empty';
$string['op_eq'] = '=';
$string['op_equals'] = 'is equal to';
$string['op_gt'] = '>';
$string['op_gte'] = '≥';
$string['op_lt'] = '<';
$string['op_lte'] = '≤';
$string['op_neq'] = '≠';
$string['op_notcontains'] = 'does not contain';
$string['op_notempty'] = 'is not empty';
$string['op_notequals'] = 'is not equal to';
$string['otherwise'] = 'Otherwise';
$string['pluginname'] = 'Conditional text';
$string['privacy:metadata'] = 'The Conditional text element does not store any personal data.';
$string['profilecondition'] = 'Field value';
$string['profilefield'] = 'User field';
$string['rulenumber'] = 'Rule {$a}';
$string['ruletext'] = 'Text to show';
$string['ruletext_help'] = 'The text printed when this rule matches. HTML and filters (for example multi-language content) are supported, and so are these placeholders: {firstname}, {lastname}, {fullname}, {coursename}, {courseshortname} and {coursegrade}. Leave empty to print nothing when the rule matches.';
$string['ruletype'] = 'Condition';
$string['type_always'] = 'Always';
$string['type_completion'] = 'Course completion';
$string['type_grade'] = 'Grade';
$string['type_profile'] = 'User profile field';
$string['unit_percent'] = '%';
$string['unit_points'] = 'points';
