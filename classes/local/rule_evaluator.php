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

declare(strict_types=1);

namespace customcertelement_conditionaltext\local;

use completion_info;
use context_course;
use core_text;
use grade_grade;
use grade_item;
use stdClass;

/**
 * Evaluates conditional text rules against the certificate recipient.
 *
 * All evaluation uses the data of the user the certificate is generated for,
 * never the user who is viewing or downloading it.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_evaluator {
    /** @var string Rule type: grade comparison. */
    public const string TYPE_GRADE = 'grade';

    /** @var string Rule type: course completion. */
    public const string TYPE_COMPLETION = 'completion';

    /** @var string Rule type: course progress percentage. */
    public const string TYPE_PROGRESS = 'progress';

    /** @var string Rule type: completion of one activity. */
    public const string TYPE_ACTIVITY = 'activity';

    /** @var string Rule type: user profile field comparison. */
    public const string TYPE_PROFILE = 'profile';

    /** @var string Rule type: course group membership. */
    public const string TYPE_GROUP = 'group';

    /** @var string Rule type: cohort membership. */
    public const string TYPE_COHORT = 'cohort';

    /** @var string Rule type: role in the course. */
    public const string TYPE_ROLE = 'role';

    /** @var string Rule type: badge earned. */
    public const string TYPE_BADGE = 'badge';

    /** @var string Rule type: competency proficient in the course. */
    public const string TYPE_COMPETENCY = 'competency';

    /** @var string Rule type: enrolment method. */
    public const string TYPE_ENROLMENT = 'enrolment';

    /** @var string Rule type: a date compared with a fixed date. */
    public const string TYPE_DATE = 'date';

    /** @var string Rule type: time spent in the course, in hours. */
    public const string TYPE_TIMESPENT = 'timespent';

    /** @var string Rule type: days from enrolment to course completion. */
    public const string TYPE_COMPLETIONDAYS = 'completiondays';

    /** @var string Rule type: always matches. */
    public const string TYPE_ALWAYS = 'always';

    /** @var string[] All rule types, in display order. */
    public const array TYPES = [
        'grade', 'completion', 'progress', 'activity', 'profile', 'group', 'cohort', 'role', 'badge', 'competency',
        'enrolment', 'date', 'timespent', 'completiondays', 'always',
    ];

    /** @var string Grade source identifier for the course total. */
    public const string GRADE_COURSE = '0';

    /** @var string Grade unit: percentage of the grade range. */
    public const string UNIT_PERCENT = 'percent';

    /** @var string Grade unit: raw points. */
    public const string UNIT_POINTS = 'points';

    /** @var string[] Numeric comparison operators. */
    public const array GRADE_OPERATORS = ['gte', 'gt', 'lte', 'lt', 'eq', 'neq'];

    /** @var string[] Text comparison operators. */
    public const array PROFILE_OPERATORS = ['equals', 'notequals', 'contains', 'notcontains', 'empty', 'notempty'];

    /** @var string[] Date sources. */
    public const array DATE_SOURCES = ['completion', 'issue', 'enrolment'];

    /** @var string[] Date operators. */
    public const array DATE_OPERATORS = ['before', 'onorafter'];

    /** @var string[] Standard user table fields that can be used in profile rules. */
    public const array USER_FIELDS = [
        'firstname', 'lastname', 'username', 'email', 'city', 'country', 'idnumber',
        'institution', 'department', 'lang',
    ];

    /** @var array<string,float|null> Per-request cache of grades. */
    private array $gradecache = [];

    /** @var array<int,bool> Courses already regraded during this request. */
    private array $regraded = [];

    /** @var array<int,stdClass> Per-request cache of custom profile data by user id. */
    private array $profilecache = [];

    /**
     * Constructor.
     *
     * @param int $courseid The course the certificate belongs to.
     * @param int $customcertid The certificate activity, used for the issue date (0 for site templates).
     */
    public function __construct(
        /** @var int The course the certificate belongs to. */
        private readonly int $courseid,
        /** @var int The certificate activity id, or 0. */
        private readonly int $customcertid = 0,
    ) {
    }

    /**
     * Returns true when the given rule matches the user.
     *
     * Rules that reference data that no longer exists (a deleted group, activity, badge...)
     * never match, not even when inverted.
     *
     * @param array $rule Normalised rule.
     * @param stdClass $user The certificate recipient.
     * @return bool
     */
    public function matches(array $rule, stdClass $user): bool {
        $result = $this->evaluate($rule, $user);
        if ($result === null) {
            return false;
        }
        return empty($rule['negate']) ? $result : !$result;
    }

    /**
     * Evaluates the condition without inversion.
     *
     * @param array $rule
     * @param stdClass $user
     * @return bool|null Null when the rule is invalid or references missing data.
     */
    private function evaluate(array $rule, stdClass $user): ?bool {
        $userid = (int)$user->id;
        return match ($rule['type'] ?? '') {
            self::TYPE_GRADE => $this->matches_grade($rule, $userid),
            self::TYPE_COMPLETION => $this->is_course_complete($userid),
            self::TYPE_PROGRESS => $this->matches_progress($rule, $userid),
            self::TYPE_ACTIVITY => $this->matches_activity((int)($rule['cmid'] ?? 0), $userid),
            self::TYPE_PROFILE => $this->matches_profile($rule, $user),
            self::TYPE_GROUP => $this->matches_group((int)($rule['groupid'] ?? 0), $userid),
            self::TYPE_COHORT => $this->matches_cohort((int)($rule['cohortid'] ?? 0), $userid),
            self::TYPE_ROLE => $this->matches_role((int)($rule['roleid'] ?? 0), $userid),
            self::TYPE_BADGE => $this->matches_badge((int)($rule['badgeid'] ?? 0), $userid),
            self::TYPE_COMPETENCY => $this->matches_competency((int)($rule['competencyid'] ?? 0), $userid),
            self::TYPE_ENROLMENT => $this->matches_enrolment((string)($rule['enrol'] ?? ''), $userid),
            self::TYPE_DATE => $this->matches_date($rule, $userid),
            self::TYPE_TIMESPENT => $this->matches_timespent($rule, $userid),
            self::TYPE_COMPLETIONDAYS => $this->matches_completiondays($rule, $userid),
            self::TYPE_ALWAYS => true,
            default => null,
        };
    }

    /**
     * Evaluates a grade rule. Users without a grade do not match.
     *
     * @param array $rule
     * @param int $userid
     * @return bool|null
     */
    private function matches_grade(array $rule, int $userid): ?bool {
        $value = $rule['gradevalue'] ?? '';
        if (!is_numeric($value)) {
            return null;
        }
        $source = (string)($rule['gradeitem'] ?? self::GRADE_COURSE);
        if (!$this->get_grade_item($source)) {
            return null;
        }
        $unit = ($rule['gradeunit'] ?? '') === self::UNIT_POINTS ? self::UNIT_POINTS : self::UNIT_PERCENT;
        $grade = $this->get_grade($source, $userid, $unit);
        if ($grade === null) {
            return false;
        }
        return self::compare_numbers($grade, (string)($rule['gradeop'] ?? ''), (float)$value);
    }

    /**
     * Numeric comparison helper.
     *
     * @param float $actual
     * @param string $operator One of GRADE_OPERATORS.
     * @param float $expected
     * @return bool
     */
    public static function compare_numbers(float $actual, string $operator, float $expected): bool {
        // Grades are stored with 5 decimals; compare with a matching tolerance.
        $epsilon = 0.000005;
        return match ($operator) {
            'gte' => $actual > $expected - $epsilon,
            'gt' => $actual > $expected + $epsilon,
            'lte' => $actual < $expected + $epsilon,
            'lt' => $actual < $expected - $epsilon,
            'eq' => abs($actual - $expected) < $epsilon,
            'neq' => abs($actual - $expected) >= $epsilon,
            default => false,
        };
    }

    /**
     * Returns the user's grade for the given source, as a percentage or as points.
     *
     * @param string $source '0' for the course total, 'gradeitem:<id>' or a course module id.
     * @param int $userid
     * @param string $unit UNIT_PERCENT or UNIT_POINTS.
     * @return float|null Null when the item does not exist or the user has no grade.
     */
    public function get_grade(string $source, int $userid, string $unit): ?float {
        $key = "{$source}|{$userid}|{$unit}";
        if (array_key_exists($key, $this->gradecache)) {
            return $this->gradecache[$key];
        }

        $result = null;
        $item = $this->get_grade_item($source);
        if ($item && $item->needsupdate && !isset($this->regraded[$this->courseid])) {
            // Calculated items (such as the course total) may be stale until regraded.
            $this->regraded[$this->courseid] = true;
            grade_regrade_final_grades($this->courseid);
            $item = $this->get_grade_item($source);
        }
        if ($item) {
            $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
            if ($grade && $grade->finalgrade !== null) {
                $final = (float)$grade->finalgrade;
                if ($unit === self::UNIT_POINTS) {
                    $result = $final;
                } else {
                    $range = (float)$item->grademax - (float)$item->grademin;
                    $result = $range > 0 ? ($final - (float)$item->grademin) / $range * 100 : 0.0;
                }
            }
        }

        return $this->gradecache[$key] = $result;
    }

    /**
     * Resolves a grade source identifier to a grade item in this course.
     *
     * @param string $source
     * @return grade_item|null
     */
    public function get_grade_item(string $source): ?grade_item {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');

        if ($source === self::GRADE_COURSE || $source === '') {
            return grade_item::fetch_course_item($this->courseid) ?: null;
        }
        if (str_starts_with($source, 'gradeitem:')) {
            $item = grade_item::fetch(['id' => (int)substr($source, 10), 'courseid' => $this->courseid]);
            return $item ?: null;
        }
        if (!ctype_digit($source)) {
            return null;
        }
        // Legacy customcert convention: a course module id.
        $cm = $DB->get_record('course_modules', ['id' => (int)$source, 'course' => $this->courseid]);
        if (!$cm) {
            return null;
        }
        $modname = $DB->get_field('modules', 'name', ['id' => $cm->module]);
        if (!$modname) {
            return null;
        }
        $item = grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $modname,
            'iteminstance' => $cm->instance,
            'courseid' => $this->courseid,
            'itemnumber' => 0,
        ]);
        return $item ?: null;
    }

    /**
     * Whether the user has completed the course.
     *
     * @param int $userid
     * @return bool
     */
    private function is_course_complete(int $userid): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        return (new completion_info(get_course($this->courseid)))->is_course_complete($userid);
    }

    /**
     * Compares the course progress percentage (activities completed out of those tracked).
     *
     * Courses without completion tracking, or without tracked activities, never match.
     *
     * @param array $rule
     * @param int $userid
     * @return bool|null
     */
    private function matches_progress(array $rule, int $userid): ?bool {
        $value = $rule['progressvalue'] ?? '';
        if (!is_numeric($value)) {
            return null;
        }
        $progress = \core_completion\progress::get_course_progress_percentage(get_course($this->courseid), $userid);
        if ($progress === null) {
            return null;
        }
        return self::compare_numbers((float)$progress, (string)($rule['progressop'] ?? ''), (float)$value);
    }

    /**
     * Whether the user has completed (or passed) an activity of the course.
     *
     * @param int $cmid
     * @param int $userid
     * @return bool|null
     */
    private function matches_activity(int $cmid, int $userid): ?bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $cms = get_fast_modinfo($this->courseid)->get_cms();
        if (!$cmid || !isset($cms[$cmid])) {
            return null;
        }
        $data = (new completion_info(get_course($this->courseid)))->get_data($cms[$cmid], false, $userid);
        return in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
    }

    /**
     * Evaluates a profile field rule.
     *
     * @param array $rule
     * @param stdClass $user
     * @return bool|null
     */
    private function matches_profile(array $rule, stdClass $user): ?bool {
        $field = (string)($rule['profilefield'] ?? '');
        $actual = $field === '' ? null : $this->get_profile_value($field, $user);
        if ($actual === null) {
            return null;
        }
        return self::compare_text($actual, (string)($rule['profileop'] ?? ''), (string)($rule['profilevalue'] ?? ''));
    }

    /**
     * Case-insensitive, trimmed text comparison helper.
     *
     * @param string $actual
     * @param string $operator One of PROFILE_OPERATORS.
     * @param string $expected
     * @return bool
     */
    public static function compare_text(string $actual, string $operator, string $expected): bool {
        $actual = core_text::strtolower(trim($actual));
        $expected = core_text::strtolower(trim($expected));
        return match ($operator) {
            'equals' => $actual === $expected,
            'notequals' => $actual !== $expected,
            'contains' => $expected !== '' && str_contains($actual, $expected),
            'notcontains' => $expected === '' || !str_contains($actual, $expected),
            'empty' => $actual === '',
            'notempty' => $actual !== '',
            default => false,
        };
    }

    /**
     * Returns the raw value of a standard or custom profile field for the user.
     *
     * @param string $field A USER_FIELDS name or a custom profile field id.
     * @param stdClass $user
     * @return string|null Null when the field does not exist.
     */
    public function get_profile_value(string $field, stdClass $user): ?string {
        global $CFG, $DB;

        if (in_array($field, self::USER_FIELDS, true)) {
            if (!property_exists($user, $field)) {
                $user = $DB->get_record('user', ['id' => $user->id]) ?: $user;
            }
            return (string)($user->$field ?? '');
        }

        if (!ctype_digit($field)) {
            return null;
        }
        $shortname = $DB->get_field('user_info_field', 'shortname', ['id' => (int)$field]);
        if ($shortname === false) {
            return null;
        }
        $userid = (int)$user->id;
        if (!isset($this->profilecache[$userid])) {
            require_once($CFG->dirroot . '/user/profile/lib.php');
            $this->profilecache[$userid] = profile_user_record($userid, false);
        }
        $value = $this->profilecache[$userid]->$shortname ?? '';
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * Group membership. Groups of other courses are invalid.
     *
     * @param int $groupid
     * @param int $userid
     * @return bool|null
     */
    private function matches_group(int $groupid, int $userid): ?bool {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $group = $groupid ? groups_get_group($groupid) : false;
        if (!$group || (int)$group->courseid !== $this->courseid) {
            return null;
        }
        return groups_is_member($groupid, $userid);
    }

    /**
     * Cohort membership. Deleted cohorts are invalid.
     *
     * @param int $cohortid
     * @param int $userid
     * @return bool|null
     */
    private function matches_cohort(int $cohortid, int $userid): ?bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if (!$cohortid || !$DB->record_exists('cohort', ['id' => $cohortid])) {
            return null;
        }
        return cohort_is_member($cohortid, $userid);
    }

    /**
     * Role in the course, assigned in the course or inherited from a parent context.
     *
     * @param int $roleid
     * @param int $userid
     * @return bool|null
     */
    private function matches_role(int $roleid, int $userid): ?bool {
        global $DB;

        if (!$roleid || !$DB->record_exists('role', ['id' => $roleid])) {
            return null;
        }
        $roles = get_user_roles(context_course::instance($this->courseid), $userid, true);
        return in_array($roleid, array_map('intval', array_column($roles, 'roleid')), true);
    }

    /**
     * Badge earned. Only site badges and badges of this course are valid.
     *
     * @param int $badgeid
     * @param int $userid
     * @return bool|null
     */
    private function matches_badge(int $badgeid, int $userid): ?bool {
        global $DB;

        $badge = $badgeid ? $DB->get_record('badge', ['id' => $badgeid], 'id, courseid') : false;
        if (!$badge || ($badge->courseid && (int)$badge->courseid !== $this->courseid)) {
            return null;
        }
        return $DB->record_exists('badge_issued', ['badgeid' => $badgeid, 'userid' => $userid]);
    }

    /**
     * Competency rated as proficient in this course.
     *
     * @param int $competencyid
     * @param int $userid
     * @return bool|null
     */
    private function matches_competency(int $competencyid, int $userid): ?bool {
        global $DB;

        if (!$competencyid || !$DB->record_exists('competency', ['id' => $competencyid])) {
            return null;
        }
        return $DB->record_exists('competency_usercompcourse', [
            'userid' => $userid, 'courseid' => $this->courseid, 'competencyid' => $competencyid, 'proficiency' => 1,
        ]);
    }

    /**
     * Enrolled in the course through a given enrolment method (plugin name such as "manual" or "self").
     *
     * @param string $enrol
     * @param int $userid
     * @return bool|null
     */
    private function matches_enrolment(string $enrol, int $userid): ?bool {
        global $DB;

        if ($enrol === '') {
            return null;
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND e.enrol = :enrol AND ue.userid = :userid",
            ['courseid' => $this->courseid, 'enrol' => $enrol, 'userid' => $userid]
        );
    }

    /**
     * Compares a date of the learner (completion, issue or enrolment) with a fixed date.
     *
     * @param array $rule
     * @param int $userid
     * @return bool|null
     */
    private function matches_date(array $rule, int $userid): ?bool {
        $date = (int)($rule['date'] ?? 0);
        if (!$date || !in_array($rule['dateop'] ?? '', self::DATE_OPERATORS, true)) {
            return null;
        }
        $actual = match ($rule['datesource'] ?? '') {
            'completion' => $this->get_completion_time($userid),
            'issue' => $this->get_issue_time($userid),
            'enrolment' => $this->get_enrolment_time($userid),
            default => null,
        };
        if ($actual === null) {
            return false;
        }
        return $rule['dateop'] === 'before' ? $actual < $date : $actual >= $date;
    }

    /**
     * Compares the time spent in the course, in hours.
     *
     * @param array $rule
     * @param int $userid
     * @return bool|null
     */
    private function matches_timespent(array $rule, int $userid): ?bool {
        $value = $rule['hoursvalue'] ?? '';
        if (!is_numeric($value)) {
            return null;
        }
        $seconds = \mod_customcert\service\certificate_time_service::create()->get_course_time($this->courseid, $userid);
        return self::compare_numbers($seconds / HOURSECS, (string)($rule['hoursop'] ?? ''), (float)$value);
    }

    /**
     * Compares the number of days between enrolment and course completion.
     *
     * @param array $rule
     * @param int $userid
     * @return bool|null
     */
    private function matches_completiondays(array $rule, int $userid): ?bool {
        $value = $rule['daysvalue'] ?? '';
        if (!is_numeric($value)) {
            return null;
        }
        $completed = $this->get_completion_time($userid);
        $enrolled = $this->get_enrolment_time($userid);
        if ($completed === null || $enrolled === null) {
            return false;
        }
        $days = max(0, $completed - $enrolled) / DAYSECS;
        return self::compare_numbers($days, (string)($rule['daysop'] ?? ''), (float)$value);
    }

    /**
     * Course completion time.
     *
     * @param int $userid
     * @return int|null Null when not completed.
     */
    public function get_completion_time(int $userid): ?int {
        global $DB;

        $time = $DB->get_field('course_completions', 'timecompleted', ['userid' => $userid, 'course' => $this->courseid]);
        return $time ? (int)$time : null;
    }

    /**
     * Certificate issue time; the current time when the certificate has not been issued yet.
     *
     * @param int $userid
     * @return int
     */
    public function get_issue_time(int $userid): int {
        global $DB;

        if ($this->customcertid) {
            $time = $DB->get_field_sql(
                'SELECT MIN(timecreated) FROM {customcert_issues} WHERE customcertid = ? AND userid = ?',
                [$this->customcertid, $userid]
            );
            if ($time) {
                return (int)$time;
            }
        }
        return time();
    }

    /**
     * Earliest enrolment start in the course.
     *
     * @param int $userid
     * @return int|null Null when not enrolled.
     */
    public function get_enrolment_time(int $userid): ?int {
        global $DB;

        $time = $DB->get_field_sql(
            "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $this->courseid, 'userid' => $userid]
        );
        return $time ? (int)$time : null;
    }
}
