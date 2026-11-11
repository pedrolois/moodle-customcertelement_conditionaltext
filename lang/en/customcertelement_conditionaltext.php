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

$string['activity'] = 'Activity';
$string['addrule'] = 'Add another rule';
$string['appearance'] = 'Appearance';
$string['badge'] = 'Badge';
$string['chooseactivity'] = 'Choose an activity...';
$string['choosebadge'] = 'Choose a badge...';
$string['choosecohort'] = 'Choose a cohort...';
$string['choosecompetency'] = 'Choose a competency...';
$string['chooseenrolment'] = 'Choose an enrolment method...';
$string['choosegroup'] = 'Choose a group...';
$string['chooserole'] = 'Choose a role...';
$string['combine'] = 'Combine with the next rule';
$string['combine_and'] = 'Yes: the next rule must ALSO be met (AND)';
$string['combine_help'] = 'Use this to require several conditions at once. When a rule is combined with the next one, both must be met, and the text of the last rule of the chain is printed. For example: Rule 1 "Grade ≥ 90 %" combined with Rule 2 "Course completion", text "With honours". To express OR, create separate rules with the same text.';
$string['combine_none'] = 'No';
$string['coursegrade'] = 'Course total';
$string['date'] = 'Date';
$string['datecondition'] = 'Date';
$string['dateop_before'] = 'is before';
$string['dateop_onorafter'] = 'is on or after';
$string['datesource_completion'] = 'Course completion date';
$string['datesource_enrolment'] = 'Enrolment date';
$string['datesource_issue'] = 'Certificate issue date';
$string['dayscondition'] = 'Days from enrolment to completion';
$string['defaulttext'] = 'Text when no rule matches';
$string['defaulttext_help'] = 'Printed when none of the rules match. Leave empty to print nothing. The same placeholders as in the rule texts can be used.';
$string['deleterule'] = 'Delete rule {no}';
$string['enrolmentmethod'] = 'Enrolment method';
$string['errorchoose'] = 'Choose an option.';
$string['errorcombinelast'] = 'The last rule cannot be combined with a next rule.';
$string['errornumber'] = 'Enter a number.';
$string['errortoomanyrules'] = 'An element can have at most {$a} rules.';
$string['exampleloaded'] = 'Example loaded. Review the rules and adapt the texts, then save. Nothing is saved until you press "Save changes".';
$string['examples'] = 'Examples';
$string['examples_help'] = 'Fill the form with a ready-made set of rules. Loading an example replaces the rules shown in the form; nothing changes until you save.';
$string['gradecondition'] = 'Grade is';
$string['gradeitem'] = 'Grade item';
$string['hourscondition'] = 'Time spent in the course';
$string['intro'] = 'Rules are checked from top to bottom against the learner who receives the certificate. Their text is printed when the condition is met.';
$string['loadexample'] = 'Load an example';
$string['matchmode'] = 'When several rules match';
$string['matchmode_all'] = 'Show the texts of all matching rules';
$string['matchmode_first'] = 'Show only the first matching rule';
$string['matchmode_help'] = 'With "first matching rule", order your rules from the most to the least specific (for example 90%, then 75%, then 50%). With "all matching rules", every matching text is printed on its own line.';
$string['negate'] = 'Invert';
$string['negate_help'] = 'Print the text when the condition is NOT met. For example "Group: Advanced" inverted applies to everybody who is not in the Advanced group. Rules that point to something that no longer exists (a deleted group, activity or badge) never apply, inverted or not.';
$string['negate_label'] = 'Apply when this condition is NOT met';
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
$string['preset_achievements'] = 'Achievement lines';
$string['preset_achievements_desc'] = 'Several lines at once: completion, top grade and a line for everybody.';
$string['preset_achievements_rule1'] = 'Course completed';
$string['preset_achievements_rule2'] = 'Outstanding result: {coursegrade}';
$string['preset_achievements_rule3'] = 'Awarded to {fullname}';
$string['preset_classification'] = 'Distinction / Merit / Pass';
$string['preset_classification_desc'] = 'Classification by course total: 90 %, 75 % and 50 %.';
$string['preset_classification_rule1'] = 'WITH DISTINCTION';
$string['preset_classification_rule2'] = 'WITH MERIT';
$string['preset_classification_rule3'] = 'PASS';
$string['preset_cohort'] = 'By cohort';
$string['preset_cohort_desc'] = 'An extra line for the members of a cohort.';
$string['preset_cohort_rule1'] = 'Staff development programme';
$string['preset_completion'] = 'Completed or participated';
$string['preset_completion_default'] = 'has participated in {coursename}';
$string['preset_completion_desc'] = 'Different sentence depending on course completion.';
$string['preset_completion_rule1'] = 'has successfully completed {coursename}';
$string['preset_department'] = 'By department';
$string['preset_department_desc'] = 'Programme name taken from the Department profile field.';
$string['preset_department_rule1'] = 'Sales Academy';
$string['preset_department_rule2'] = 'Operations Programme';
$string['preset_fasttrack'] = 'Fast track';
$string['preset_fasttrack_desc'] = 'A line for learners who completed the course within 30 days of enrolling.';
$string['preset_fasttrack_rule1'] = 'Completed on the fast track';
$string['preset_group'] = 'By group';
$string['preset_group_default'] = 'Standard level';
$string['preset_group_desc'] = 'A different level for the members of a course group.';
$string['preset_group_rule1'] = 'Advanced level';
$string['preset_honours'] = 'With honours (two conditions)';
$string['preset_honours_desc'] = 'Grade of 90 % or more AND course completed, using "Combine with the next rule".';
$string['preset_honours_rule1'] = 'WITH HONOURS';
$string['preset_hours'] = 'Hours of training';
$string['preset_hours_desc'] = 'A line for learners who spent 20 hours or more in the course.';
$string['preset_hours_rule1'] = '20-hour certified programme';
$string['preset_language'] = 'By preferred language';
$string['preset_language_default'] = 'Certificate of completion';
$string['preset_language_desc'] = 'Title in the learner\'s language: Spanish, French, otherwise English.';
$string['preset_language_rule1'] = 'Certificado de finalización';
$string['preset_language_rule2'] = 'Certificat de réussite';
$string['preset_passfail'] = 'Passed or attended';
$string['preset_passfail_default'] = 'has attended {coursename}';
$string['preset_passfail_desc'] = 'Course total of 50 % or more says "passed", otherwise "attended".';
$string['preset_passfail_rule1'] = 'has successfully passed {coursename}';
$string['preset_performance'] = 'Excellent / Very good / Good';
$string['preset_performance_desc'] = 'Performance level by course total: 85 %, 70 % and 50 %.';
$string['preset_performance_rule1'] = 'Excellent';
$string['preset_performance_rule2'] = 'Very good';
$string['preset_performance_rule3'] = 'Good';
$string['preset_progress'] = 'By course progress';
$string['preset_progress_default'] = 'has started the course';
$string['preset_progress_desc'] = 'Wording by the percentage of tracked activities completed: 100 %, 50 % or less.';
$string['preset_progress_rule1'] = 'has completed all the activities of the course';
$string['preset_progress_rule2'] = 'has completed most of the activities of the course';
$string['preset_role'] = 'Facilitator or participant';
$string['preset_role_default'] = 'as Participant';
$string['preset_role_desc'] = 'Teachers get "Facilitator", everybody else "Participant".';
$string['preset_role_rule1'] = 'as Facilitator';
$string['privacy:metadata'] = 'The Conditional text element does not store any personal data.';
$string['profilecondition'] = 'Field value';
$string['profilefield'] = 'User field';
$string['progresscondition'] = 'Course progress';
$string['rulenumber'] = 'Rule {$a}';
$string['ruletext'] = 'Text to show';
$string['ruletext_help'] = 'The text printed when this rule matches. HTML and filters (for example multi-language content) are supported, and so are these placeholders: {firstname}, {lastname}, {fullname}, {coursename}, {courseshortname} and {coursegrade}. Leave empty to print nothing when the rule matches.';
$string['ruletype'] = 'Condition';
$string['ruletype_help'] = 'The condition the learner must meet for this rule\'s text to be printed:

* **Grade**: compares the learner\'s grade (course total, a grade item or an activity) with a value, as a percentage or in points. Learners without a grade never meet it.
* **Course completion**: the learner has completed the course.
* **Course progress (%)**: percentage of the activities with completion tracking that the learner has completed, as shown in the course progress bar.
* **Activity completion**: the learner has completed (or passed) an activity with completion tracking.
* **User profile field**: compares a standard or custom profile field with a value. Not case-sensitive.
* **Group**: the learner is a member of a group of this course.
* **Cohort**: the learner is a member of a cohort.
* **Role in the course**: the learner has a role in this course, for example Non-editing teacher.
* **Badge earned**: the learner has been awarded a site badge or a badge of this course.
* **Competency achieved**: the learner has been rated proficient in a competency of this course.
* **Enrolment method**: the learner is enrolled through a method, for example Self enrolment.
* **Date**: compares the completion, certificate issue or enrolment date with a date.
* **Time spent in the course**: hours the learner has spent in the course, as recorded in the logs.
* **Days to complete the course**: days between enrolment and course completion.
* **Always**: matches every learner. Use it for a line everybody gets (with "Show the texts of all matching rules"), or as a last rule that catches everyone not matched before.

Use **Invert** to apply the rule when the condition is not met, and **Combine with the next rule** to require several conditions at once.';
$string['type_activity'] = 'Activity completion';
$string['type_always'] = 'Always';
$string['type_badge'] = 'Badge earned';
$string['type_cohort'] = 'Cohort';
$string['type_competency'] = 'Competency achieved';
$string['type_completion'] = 'Course completion';
$string['type_completiondays'] = 'Days to complete the course';
$string['type_date'] = 'Date';
$string['type_enrolment'] = 'Enrolment method';
$string['type_grade'] = 'Grade';
$string['type_group'] = 'Group';
$string['type_profile'] = 'User profile field';
$string['type_progress'] = 'Course progress (%)';
$string['type_role'] = 'Role in the course';
$string['type_timespent'] = 'Time spent in the course';
$string['unit_days'] = 'days';
$string['unit_hours'] = 'hours';
$string['unit_percent'] = '%';
$string['unit_points'] = 'points';
