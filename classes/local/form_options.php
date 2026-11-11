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

use availability_profile\condition as profile_condition;
use context_course;
use core_collator;
use core_user\fields;
use mod_customcert\element_helper;
use stdClass;

/**
 * Option lists for the selects of the rule form.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_options {
    /** @var string Plugin component, for strings. */
    private const string COMPONENT = 'customcertelement_conditionaltext';

    /**
     * Constructor.
     *
     * @param stdClass $course The course the certificate belongs to.
     */
    public function __construct(
        /** @var stdClass The course the certificate belongs to. */
        private readonly stdClass $course,
    ) {
    }

    /**
     * The "choose..." first option of a select.
     *
     * @param string $identifier Language string identifier.
     * @return array<int|string,string>
     */
    private static function choose(string $identifier): array {
        return [0 => get_string($identifier, self::COMPONENT)];
    }

    /**
     * Course total plus the grade items of the course.
     *
     * @return array<string,string>
     */
    public function grade_sources(): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        return [rule_evaluator::GRADE_COURSE => get_string('coursegrade', self::COMPONENT)]
            + element_helper::get_grade_items($this->course);
    }

    /**
     * Activities of the course with completion tracking enabled.
     *
     * @return array<int,string>
     */
    public function activities(): array {
        $options = [];
        foreach (get_fast_modinfo($this->course)->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
                $options[(int)$cm->id] = $cm->get_formatted_name();
            }
        }
        return self::choose('chooseactivity') + $options;
    }

    /**
     * Standard and custom profile fields.
     *
     * Custom fields hidden from everyone are only offered to users who may view all details,
     * so a certificate cannot be used to probe values the designer is not allowed to see.
     *
     * @return array<string,string>
     */
    public function profile_fields(): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $fields = [];
        foreach (rule_evaluator::USER_FIELDS as $field) {
            $fields[$field] = $field === 'lang' ? get_string('preferredlanguage') : fields::get_display_name($field);
        }
        $context = context_course::instance($this->course->id);
        $canviewall = has_capability('moodle/user:viewalldetails', $context);
        foreach (profile_condition::get_custom_profile_fields() as $custom) {
            if ((int)$custom->visible === (int)PROFILE_VISIBLE_NONE && !$canviewall) {
                continue;
            }
            $fields[(string)$custom->id] = format_string($custom->name);
        }
        core_collator::asort($fields);
        return $fields;
    }

    /**
     * Groups of the course.
     *
     * @return array<int,string>
     */
    public function groups(): array {
        $options = self::choose('choosegroup');
        foreach (groups_get_all_groups($this->course->id) as $group) {
            $options[(int)$group->id] = format_string($group->name);
        }
        return $options;
    }

    /**
     * Cohorts available in the course context.
     *
     * @return array<int,string>
     */
    public function cohorts(): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $options = self::choose('choosecohort');
        $context = context_course::instance($this->course->id);
        foreach (cohort_get_available_cohorts($context, COHORT_ALL, 0, 0) as $cohort) {
            $options[(int)$cohort->id] = format_string($cohort->name);
        }
        return $options;
    }

    /**
     * Roles that can be assigned in a course.
     *
     * @return array<int,string>
     */
    public function roles(): array {
        $context = context_course::instance($this->course->id);
        $roleids = get_roles_for_contextlevels(CONTEXT_COURSE);
        $names = role_get_names($context, ROLENAME_ALIAS, true);
        $options = self::choose('chooserole');
        foreach ($roleids as $roleid) {
            if (isset($names[$roleid])) {
                $options[(int)$roleid] = $names[$roleid];
            }
        }
        return $options;
    }

    /**
     * Site badges and badges of the course.
     *
     * @return array<int,string>
     */
    public function badges(): array {
        global $DB;

        $options = self::choose('choosebadge');
        if (empty(get_config('core', 'enablebadges'))) {
            return $options;
        }
        $badges = $DB->get_records_select(
            'badge',
            'courseid = :courseid OR courseid IS NULL',
            ['courseid' => $this->course->id],
            'name',
            'id, name, courseid'
        );
        foreach ($badges as $badge) {
            $scope = $badge->courseid ? '' : ' (' . get_string('site') . ')';
            $options[(int)$badge->id] = format_string($badge->name) . $scope;
        }
        return $options;
    }

    /**
     * Competencies linked to the course.
     *
     * @return array<int,string>
     */
    public function competencies(): array {
        $options = self::choose('choosecompetency');
        if (!get_config('core_competency', 'enabled')) {
            return $options;
        }
        try {
            foreach (\core_competency\api::list_course_competencies($this->course->id) as $item) {
                $competency = $item['competency'];
                $options[(int)$competency->get('id')] = format_string($competency->get('shortname'));
            }
        } catch (\moodle_exception $e) {
            // The user cannot read the course competencies: offer none.
            return self::choose('choosecompetency');
        }
        return $options;
    }

    /**
     * Enrolment methods used in the course.
     *
     * @return array<string,string>
     */
    public function enrolment_methods(): array {
        $options = ['' => get_string('chooseenrolment', self::COMPONENT)];
        foreach (enrol_get_instances($this->course->id, false) as $instance) {
            if (!isset($options[$instance->enrol])) {
                $options[$instance->enrol] = get_string('pluginname', 'enrol_' . $instance->enrol);
            }
        }
        return $options;
    }
}
