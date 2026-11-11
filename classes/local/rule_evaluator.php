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

    /** @var string Rule type: course completion state. */
    public const string TYPE_COMPLETION = 'completion';

    /** @var string Rule type: user profile field comparison. */
    public const string TYPE_PROFILE = 'profile';

    /** @var string Rule type: always matches (useful as an explicit catch-all). */
    public const string TYPE_ALWAYS = 'always';

    /** @var string Grade source identifier for the course total. */
    public const string GRADE_COURSE = '0';

    /** @var string Grade unit: percentage of the grade range. */
    public const string UNIT_PERCENT = 'percent';

    /** @var string Grade unit: raw points. */
    public const string UNIT_POINTS = 'points';

    /** @var string Completion state: course completed. */
    public const string COMPLETION_COMPLETE = 'complete';

    /** @var string Completion state: course not completed. */
    public const string COMPLETION_INCOMPLETE = 'incomplete';

    /** @var string[] Numeric comparison operators. */
    public const array GRADE_OPERATORS = ['gte', 'gt', 'lte', 'lt', 'eq', 'neq'];

    /** @var string[] Text comparison operators. */
    public const array PROFILE_OPERATORS = ['equals', 'notequals', 'contains', 'notcontains', 'empty', 'notempty'];

    /** @var string[] Standard user table fields that can be used in profile rules. */
    public const array USER_FIELDS = [
        'firstname', 'lastname', 'username', 'email', 'city', 'country', 'idnumber',
        'institution', 'department', 'lang',
    ];

    /** @var array<string,float|null> Per-request cache of grade percentages/points. */
    private array $gradecache = [];

    /** @var array<int,bool> Courses already regraded during this request. */
    private array $regraded = [];

    /** @var array<int,stdClass> Per-request cache of custom profile data by user id. */
    private array $profilecache = [];

    /**
     * Constructor.
     *
     * @param int $courseid The course the certificate belongs to.
     */
    public function __construct(
        /** @var int The course the certificate belongs to. */
        private readonly int $courseid,
    ) {
    }

    /**
     * Returns true when the given rule matches the user.
     *
     * Rules that are malformed or reference data that no longer exists never match.
     *
     * @param array $rule Normalised rule.
     * @param stdClass $user The certificate recipient.
     * @return bool
     */
    public function matches(array $rule, stdClass $user): bool {
        return match ($rule['type'] ?? '') {
            self::TYPE_GRADE => $this->matches_grade($rule, (int)$user->id),
            self::TYPE_COMPLETION => $this->matches_completion($rule, (int)$user->id),
            self::TYPE_PROFILE => $this->matches_profile($rule, $user),
            self::TYPE_ALWAYS => true,
            default => false,
        };
    }

    /**
     * Evaluates a grade rule.
     *
     * @param array $rule
     * @param int $userid
     * @return bool
     */
    private function matches_grade(array $rule, int $userid): bool {
        $value = $rule['gradevalue'] ?? '';
        if (!is_numeric($value)) {
            return false;
        }
        $unit = ($rule['gradeunit'] ?? '') === self::UNIT_POINTS ? self::UNIT_POINTS : self::UNIT_PERCENT;
        $grade = $this->get_grade((string)($rule['gradeitem'] ?? self::GRADE_COURSE), $userid, $unit);
        if ($grade === null) {
            // Ungraded users never satisfy a grade condition.
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
     * Evaluates a course completion rule.
     *
     * @param array $rule
     * @param int $userid
     * @return bool
     */
    private function matches_completion(array $rule, int $userid): bool {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($this->courseid);
        $complete = (new completion_info($course))->is_course_complete($userid);
        return ($rule['completion'] ?? self::COMPLETION_COMPLETE) === self::COMPLETION_INCOMPLETE ? !$complete : $complete;
    }

    /**
     * Evaluates a profile field rule.
     *
     * @param array $rule
     * @param stdClass $user
     * @return bool
     */
    private function matches_profile(array $rule, stdClass $user): bool {
        $field = (string)($rule['profilefield'] ?? '');
        if ($field === '') {
            return false;
        }
        $actual = $this->get_profile_value($field, $user);
        if ($actual === null) {
            return false;
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
}
