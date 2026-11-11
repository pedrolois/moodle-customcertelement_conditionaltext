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

namespace customcertelement_conditionaltext;

use advanced_testcase;
use completion_completion;
use customcertelement_conditionaltext\local\rule_evaluator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Tests for every rule type, inversion and AND combinations.
 *
 * @package    customcertelement_conditionaltext
 * @category   test
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rule_evaluator::class)]
#[CoversClass(element::class)]
final class types_test extends advanced_testcase {
    /** @var stdClass Course with completion enabled. */
    private stdClass $course;

    /** @var stdClass A learner who meets the conditions. */
    private stdClass $yes;

    /** @var stdClass A learner who does not. */
    private stdClass $no;

    /** @var rule_evaluator Evaluator for the course. */
    private rule_evaluator $evaluator;

    /**
     * Shared fixture.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['enablecompletion' => 1]);
        $this->yes = $gen->create_and_enrol($this->course, 'student');
        $this->no = $gen->create_and_enrol($this->course, 'student');
        $this->evaluator = new rule_evaluator((int)$this->course->id);
    }

    /**
     * Asserts the rule matches $this->yes and not $this->no, and the opposite when inverted.
     *
     * @param array $rule
     * @param string $message
     */
    private function assert_yes_no(array $rule, string $message = ''): void {
        $this->assertTrue($this->evaluator->matches($rule, $this->yes), $message . ' (yes)');
        $this->assertFalse($this->evaluator->matches($rule, $this->no), $message . ' (no)');
        $rule['negate'] = 1;
        $this->assertFalse($this->evaluator->matches($rule, $this->yes), $message . ' (yes, inverted)');
        $this->assertTrue($this->evaluator->matches($rule, $this->no), $message . ' (no, inverted)');
    }

    /**
     * Asserts that an invalid rule never matches, inverted or not.
     *
     * @param array $rule
     * @param string $message
     */
    private function assert_never(array $rule, string $message = ''): void {
        foreach ([0, 1] as $negate) {
            $rule['negate'] = $negate;
            $this->assertFalse($this->evaluator->matches($rule, $this->yes), $message);
            $this->assertFalse($this->evaluator->matches($rule, $this->no), $message);
        }
    }

    /**
     * Activity completion.
     */
    public function test_activity(): void {
        $gen = $this->getDataGenerator();
        $page = $gen->create_module('page', ['course' => $this->course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $cm = get_coursemodule_from_id('page', $page->cmid);
        (new \completion_info($this->course))->update_state($cm, COMPLETION_COMPLETE, $this->yes->id);

        $this->assert_yes_no(['type' => 'activity', 'cmid' => $page->cmid], 'activity');
        $this->assert_never(['type' => 'activity', 'cmid' => 999999], 'missing activity');
    }

    /**
     * Course progress: share of tracked activities completed.
     */
    public function test_progress(): void {
        // Core counts the activities visible on the course page to the session user.
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $pages = [];
        for ($i = 0; $i < 4; $i++) {
            $page = $gen->create_module('page', ['course' => $this->course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
            $pages[] = get_coursemodule_from_id('page', $page->cmid);
        }
        $completion = new \completion_info($this->course);
        foreach (array_slice($pages, 0, 3) as $cm) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $this->yes->id);
        }
        $completion->update_state($pages[0], COMPLETION_COMPLETE, $this->no->id);

        // 75 % vs 25 %.
        $this->assert_yes_no(['type' => 'progress', 'progressop' => 'gte', 'progressvalue' => '50'], 'progress');
        $this->assertTrue($this->evaluator->matches(['type' => 'progress', 'progressop' => 'eq',
            'progressvalue' => '75'], $this->yes));

        // A course without tracked activities has no progress: the rule never applies.
        $empty = $gen->create_course(['enablecompletion' => 1]);
        $gen->enrol_user($this->yes->id, $empty->id, 'student');
        $evaluator = new rule_evaluator((int)$empty->id);
        foreach ([0, 1] as $negate) {
            $this->assertFalse($evaluator->matches(['type' => 'progress', 'progressop' => 'gte', 'progressvalue' => '0',
                'negate' => $negate], $this->yes));
        }
    }

    /**
     * Role in the course.
     */
    public function test_role(): void {
        global $DB;
        $teacherrole = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $this->getDataGenerator()->enrol_user($this->yes->id, $this->course->id, 'teacher');

        $this->assert_yes_no(['type' => 'role', 'roleid' => $teacherrole], 'role');
        $this->assert_never(['type' => 'role', 'roleid' => 999999], 'missing role');
    }

    /**
     * Badges: course badge, site badge and another course's badge.
     */
    public function test_badge(): void {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
        $gen = $this->getDataGenerator();
        /** @var \core_badges_generator $badgegen */
        $badgegen = $gen->get_plugin_generator('core_badges');
        $coursebadge = $badgegen->create_badge(['courseid' => $this->course->id, 'type' => BADGE_TYPE_COURSE]);
        $sitebadge = $badgegen->create_badge(['type' => BADGE_TYPE_SITE]);
        $othercourse = $gen->create_course();
        $foreignbadge = $badgegen->create_badge(['courseid' => $othercourse->id, 'type' => BADGE_TYPE_COURSE]);
        foreach ([$coursebadge, $sitebadge, $foreignbadge] as $badge) {
            $badge->issue($this->yes->id, true);
        }

        $this->assert_yes_no(['type' => 'badge', 'badgeid' => $coursebadge->id], 'course badge');
        $this->assert_yes_no(['type' => 'badge', 'badgeid' => $sitebadge->id], 'site badge');
        $this->assert_never(['type' => 'badge', 'badgeid' => $foreignbadge->id], 'other course badge');
    }

    /**
     * Competency rated proficient in the course.
     */
    public function test_competency(): void {
        global $DB;
        $competencyid = $DB->insert_record('competency', (object)[
            'shortname' => 'Safety', 'idnumber' => 'S1', 'competencyframeworkid' => 1, 'sortorder' => 0,
            'path' => '/0/', 'parentid' => 0, 'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 2,
        ]);
        $DB->insert_record('competency_usercompcourse', (object)[
            'userid' => $this->yes->id, 'courseid' => $this->course->id, 'competencyid' => $competencyid,
            'proficiency' => 1, 'grade' => 2, 'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 2,
        ]);
        $DB->insert_record('competency_usercompcourse', (object)[
            'userid' => $this->no->id, 'courseid' => $this->course->id, 'competencyid' => $competencyid,
            'proficiency' => 0, 'grade' => 1, 'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 2,
        ]);

        $this->assert_yes_no(['type' => 'competency', 'competencyid' => $competencyid], 'competency');
        $this->assert_never(['type' => 'competency', 'competencyid' => 999999], 'missing competency');
    }

    /**
     * Enrolment method.
     */
    public function test_enrolment(): void {
        global $DB;
        $self = enrol_get_plugin('self');
        $instanceid = $self->add_instance($this->course);
        $instance = $DB->get_record('enrol', ['id' => $instanceid]);
        $self->enrol_user($instance, $this->yes->id);

        $this->assert_yes_no(['type' => 'enrolment', 'enrol' => 'self'], 'self enrolment');
        $this->assert_never(['type' => 'enrolment', 'enrol' => ''], 'no method');
    }

    /**
     * Dates: completion, enrolment and issue date.
     */
    public function test_dates(): void {
        global $DB;
        $jan = make_timestamp(2026, 1, 15);
        $jun = make_timestamp(2026, 6, 15);
        $cut = make_timestamp(2026, 3, 1);

        (new completion_completion(['userid' => $this->yes->id, 'course' => $this->course->id]))->mark_complete($jan);
        (new completion_completion(['userid' => $this->no->id, 'course' => $this->course->id]))->mark_complete($jun);
        $this->assert_yes_no(['type' => 'date', 'datesource' => 'completion', 'dateop' => 'before', 'date' => $cut]);

        $DB->execute('UPDATE {user_enrolments} SET timestart = ? WHERE userid = ?', [$jun, $this->yes->id]);
        $DB->execute('UPDATE {user_enrolments} SET timestart = ? WHERE userid = ?', [$jan, $this->no->id]);
        $this->assert_yes_no(['type' => 'date', 'datesource' => 'enrolment', 'dateop' => 'onorafter', 'date' => $cut]);

        // Issue date: issued in January vs not issued yet (now).
        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->course->id]);
        $DB->insert_record('customcert_issues', (object)['customcertid' => $customcert->id, 'userid' => $this->yes->id,
            'code' => 'ABC', 'emailed' => 0, 'timecreated' => $jan]);
        $evaluator = new rule_evaluator((int)$this->course->id, (int)$customcert->id);
        $rule = ['type' => 'date', 'datesource' => 'issue', 'dateop' => 'before', 'date' => $cut];
        $this->assertTrue($evaluator->matches($rule, $this->yes));
        $this->assertFalse($evaluator->matches($rule, $this->no));

        $this->assert_never(['type' => 'date', 'datesource' => 'completion', 'dateop' => 'before', 'date' => 0]);
    }

    /**
     * Days from enrolment to completion; learners who did not complete never match.
     */
    public function test_completion_days(): void {
        global $DB;
        $start = make_timestamp(2026, 1, 1);
        $DB->execute('UPDATE {user_enrolments} SET timestart = ?', [$start]);
        (new completion_completion(['userid' => $this->yes->id, 'course' => $this->course->id]))
            ->mark_complete($start + 10 * DAYSECS);
        (new completion_completion(['userid' => $this->no->id, 'course' => $this->course->id]))
            ->mark_complete($start + 45 * DAYSECS);

        $this->assert_yes_no(['type' => 'completiondays', 'daysop' => 'lte', 'daysvalue' => '30']);

        $late = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->assertFalse($this->evaluator->matches(['type' => 'completiondays', 'daysop' => 'lte',
            'daysvalue' => '30'], $late));
    }

    /**
     * Time spent, computed from the standard log.
     */
    public function test_time_spent(): void {
        global $DB;
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $context = \context_course::instance($this->course->id);
        $start = time() - 5 * HOURSECS;
        // One hit every 10 minutes during 3 hours for "yes", during 30 minutes for "no".
        foreach ([[$this->yes->id, 18], [$this->no->id, 3]] as [$userid, $hits]) {
            for ($i = 0; $i <= $hits; $i++) {
                $DB->insert_record('logstore_standard_log', (object)[
                    'eventname' => '\\core\\event\\course_viewed', 'component' => 'core', 'action' => 'viewed',
                    'target' => 'course', 'crud' => 'r', 'edulevel' => 2, 'contextid' => $context->id,
                    'contextlevel' => CONTEXT_COURSE, 'contextinstanceid' => $this->course->id, 'userid' => $userid,
                    'courseid' => $this->course->id, 'anonymous' => 0, 'timecreated' => $start + $i * 600,
                ]);
            }
        }

        $this->assert_yes_no(['type' => 'timespent', 'hoursop' => 'gte', 'hoursvalue' => '2']);
        $this->assert_never(['type' => 'timespent', 'hoursop' => 'gte', 'hoursvalue' => 'x']);
    }

    /**
     * AND combinations: every rule of a block must match and the last text is printed.
     */
    public function test_and_blocks(): void {
        $gen = $this->getDataGenerator();
        $group = $gen->create_group(['courseid' => $this->course->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $this->yes->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $this->no->id]);
        (new completion_completion(['userid' => $this->yes->id, 'course' => $this->course->id]))->mark_complete();

        $el = $this->create_element([
            'matchmode' => 'first',
            'rules' => [
                ['type' => 'group', 'groupid' => $group->id, 'combine' => 'and', 'text' => 'ignored'],
                ['type' => 'completion', 'text' => 'Group member who completed'],
                ['type' => 'group', 'groupid' => $group->id, 'text' => 'Group member'],
            ],
            'defaulttext' => 'Nobody',
        ]);
        $this->assertStringContainsString('Group member who completed', $el->get_content($this->yes, false));
        $this->assertStringNotContainsString('ignored', $el->get_content($this->yes, false));
        $content = $el->get_content($this->no, false);
        $this->assertStringContainsString('Group member', $content);
        $this->assertStringNotContainsString('completed', $content);
        // Preview shows the text of the first block.
        $this->assertStringContainsString('Group member who completed', $el->get_content($this->no, true));

        $blocks = element::group_rules(element::normalise_rules([
            ['type' => 'always', 'combine' => 'and', 'text' => 'a'],
            ['type' => 'always', 'combine' => 'and', 'text' => 'b'],
        ]));
        $this->assertCount(1, $blocks);
        $this->assertSame('b', $blocks[0]['text']);
    }

    /**
     * Rules saved by earlier versions (per-type inversion) keep their meaning.
     */
    public function test_legacy_inversion(): void {
        $rules = element::normalise_rules([
            ['type' => 'completion', 'completion' => 'incomplete'],
            ['type' => 'group', 'groupid' => 3, 'membership' => 'notmember'],
            ['type' => 'group', 'groupid' => 3, 'membership' => 'member'],
        ]);
        $this->assertSame([1, 1, 0], array_column($rules, 'negate'));
    }

    /**
     * Validation of required choices, numbers and a dangling AND.
     */
    public function test_validation(): void {
        $el = $this->create_element(['rules' => []]);
        $errors = $el->validate([
            'ruletype' => [0 => 'badge', 1 => 'timespent', 2 => 'completiondays', 3 => 'enrolment', 4 => 'activity'],
            'rulebadgeid' => [0 => 0],
            'rulehoursvalue' => [1 => 'abc'],
            'ruledaysvalue' => [2 => ''],
            'ruleenrol' => [3 => 'manual'],
            'rulecmid' => [4 => 5],
            'rulecombine' => [4 => 'and'],
        ]);
        $this->assertArrayHasKey('rulebadgeid[0]', $errors);
        $this->assertArrayHasKey('rulehoursgroup[1]', $errors);
        $this->assertArrayHasKey('ruledaysgroup[2]', $errors);
        $this->assertArrayNotHasKey('ruleenrol[3]', $errors);
        $this->assertArrayNotHasKey('rulecmid[4]', $errors);
        $this->assertArrayHasKey('rulecombine[4]', $errors);
    }

    /**
     * Persists an element in a customcert activity of the course.
     *
     * @param array $payload
     * @return element
     */
    private function create_element(array $payload): element {
        global $DB;

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $this->course->id]);
        $pageid = $DB->get_field('customcert_pages', 'id', ['templateid' => $customcert->templateid], IGNORE_MULTIPLE);
        if (!$pageid) {
            $pageid = $DB->insert_record('customcert_pages', (object)[
                'templateid' => $customcert->templateid, 'width' => 297, 'height' => 210, 'leftmargin' => 0,
                'rightmargin' => 0, 'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $record = (object)[
            'pageid' => $pageid, 'name' => 'Conditional text', 'element' => 'conditionaltext',
            'data' => json_encode($payload + ['font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0]),
            'posx' => 10, 'posy' => 10, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('customcert_elements', $record);
        return new element($record);
    }
}
