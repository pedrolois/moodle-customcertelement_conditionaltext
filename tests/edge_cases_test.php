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
use backup;
use backup_controller;
use completion_completion;
use customcertelement_conditionaltext\local\rule_evaluator;
use customcertelement_conditionaltext\privacy\provider;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use restore_controller;
use stdClass;

/**
 * Edge cases: security, boundaries, match modes, restore, site templates and limits.
 *
 * @package    customcertelement_conditionaltext
 * @category   test
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(element::class)]
#[CoversClass(rule_evaluator::class)]
#[CoversClass(exporter::class)]
#[CoversClass(provider::class)]
final class edge_cases_test extends advanced_testcase {
    /**
     * Rule texts are cleaned: scripts never reach the certificate.
     */
    public function test_rule_text_is_cleaned(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user();
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'always', 'text' => '<b>Bold</b><script>alert(1)</script><img src=x onerror="alert(2)">'],
        ]]);
        $content = $el->get_content($user, false);
        $this->assertStringContainsString('<b>Bold</b>', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
    }

    /**
     * Links (typed or added by filters such as activity names auto-linking) are removed, keeping their text.
     */
    public function test_links_are_removed(): void {
        $this->resetAfterTest();
        filter_set_global_state('activitynames', TEXTFILTER_ON);
        [$course, $user] = $this->course_and_user();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Final exam']);
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'always', 'text' => 'Final exam passed, see <a href="https://example.com">details</a>'],
        ]]);
        $content = $el->get_content($user, false);
        $this->assertStringNotContainsString('<a', $content);
        $this->assertStringContainsString('Final exam passed, see details', $content);
    }

    /**
     * User data inserted through placeholders is escaped and never expanded a second time.
     */
    public function test_placeholder_values_are_escaped_and_not_reexpanded(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Course {lastname}']);
        $user = $gen->create_and_enrol($course, 'student', ['lastname' => 'Smith']);
        // Bypass the generator, which cleans names, to simulate hostile data already stored.
        $DB->set_field('user', 'firstname', '{lastname} <i>x</i>', ['id' => $user->id]);
        $user = $DB->get_record('user', ['id' => $user->id]);
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'always', 'text' => '{firstname}|{coursename}|{unknown}'],
        ]]);
        $content = $el->get_content($user, false);
        $this->assertStringContainsString('{lastname} &lt;i&gt;x&lt;/i&gt;|Course {lastname}|{unknown}', $content);
        $this->assertStringNotContainsString('<i>', $content);
        $this->assertStringNotContainsString('Smith', $content);
    }

    /**
     * {coursegrade} is empty for users without a grade; the default text supports placeholders.
     */
    public function test_coursegrade_placeholder_and_default_text(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user();
        $el = $this->create_element($course, [
            'rules' => [['type' => 'completion', 'text' => 'Completed']],
            'defaulttext' => 'Hello {firstname}, grade: [{coursegrade}]',
        ]);
        $this->assertStringContainsString('Hello ' . $user->firstname . ', grade: []', $el->get_content($user, false));
    }

    /**
     * Date rules at the exact boundary: "before" excludes the date, "on or after" includes it.
     */
    public function test_date_boundaries(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user(['enablecompletion' => 1]);
        $when = make_timestamp(2026, 5, 1, 0, 0, 0);
        (new completion_completion(['userid' => $user->id, 'course' => $course->id]))->mark_complete($when);
        $evaluator = new rule_evaluator((int)$course->id);
        $rule = ['type' => 'date', 'datesource' => 'completion', 'date' => $when];

        $this->assertFalse($evaluator->matches($rule + ['dateop' => 'before'], $user));
        $this->assertTrue($evaluator->matches($rule + ['dateop' => 'onorafter'], $user));
        $this->assertTrue($evaluator->matches(['dateop' => 'before', 'date' => $when + 1] + $rule, $user));
        // Not completed yet: neither before nor after.
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertFalse($evaluator->matches($rule + ['dateop' => 'before'], $other));
        $this->assertFalse($evaluator->matches($rule + ['dateop' => 'onorafter'], $other));
    }

    /**
     * Grade comparison operators at the boundary, as a data provider.
     *
     * @return array
     */
    public static function grade_boundaries_provider(): array {
        return [
            'gte equal' => ['gte', '80', true],
            'gt equal' => ['gt', '80', false],
            'lte equal' => ['lte', '80', true],
            'lt equal' => ['lt', '80', false],
            'eq' => ['eq', '80', true],
            'neq' => ['neq', '80', false],
            'gte decimal above' => ['gte', '80.00001', false],
            'gte decimal below' => ['gte', '79.99999', true],
            'negative threshold' => ['gt', '-1', true],
        ];
    }

    /**
     * Grade boundaries.
     *
     * @param string $op
     * @param string $value
     * @param bool $expected
     */
    #[DataProvider('grade_boundaries_provider')]
    public function test_grade_boundaries(string $op, string $value, bool $expected): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = \grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id,
            'courseid' => $course->id]);
        $item->update_final_grade($user->id, 80);

        $evaluator = new rule_evaluator((int)$course->id);
        $rule = ['type' => 'grade', 'gradeitem' => '0', 'gradeop' => $op, 'gradevalue' => $value, 'gradeunit' => 'percent'];
        $this->assertSame($expected, $evaluator->matches($rule, $user));
    }

    /**
     * "All matching rules" with AND blocks prints every matching block once, in order.
     */
    public function test_all_mode_with_and_blocks(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user();
        $el = $this->create_element($course, [
            'matchmode' => 'all',
            'rules' => [
                ['type' => 'always', 'text' => 'One'],
                ['type' => 'always', 'combine' => 'and', 'text' => ''],
                ['type' => 'always', 'negate' => 1, 'text' => 'Never'],
                ['type' => 'always', 'combine' => 'and', 'text' => ''],
                ['type' => 'always', 'text' => 'Two'],
            ],
            'defaulttext' => 'Default',
        ]);
        $content = $el->get_content($user, false);
        $this->assertMatchesRegularExpression('/One.*<br>.*Two/s', $content);
        $this->assertStringNotContainsString('Never', $content);
        $this->assertStringNotContainsString('Default', $content);
    }

    /**
     * A rule whose text is empty prints nothing but still stops "first match" evaluation.
     */
    public function test_empty_text_stops_first_match(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->course_and_user();
        $el = $this->create_element($course, [
            'rules' => [
                ['type' => 'always', 'text' => '   '],
                ['type' => 'always', 'text' => 'Second'],
            ],
            'defaulttext' => 'Default',
        ]);
        $this->assertSame('', $el->get_content($user, false));
    }

    /**
     * Elements on site templates (no course, no activity) still render.
     */
    public function test_site_template(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['department' => 'Sales']);
        $templateid = $DB->insert_record('customcert_templates', (object)[
            'name' => 'Site template', 'contextid' => \context_system::instance()->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $pageid = $DB->insert_record('customcert_pages', (object)[
            'templateid' => $templateid, 'width' => 297, 'height' => 210, 'leftmargin' => 0, 'rightmargin' => 0,
            'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $el = $this->insert_element((int)$pageid, ['rules' => [
            ['type' => 'profile', 'profilefield' => 'department', 'profileop' => 'equals', 'profilevalue' => 'sales',
                'text' => 'Sales on {coursename}'],
            ['type' => 'date', 'datesource' => 'issue', 'dateop' => 'onorafter', 'date' => 1, 'text' => 'Issued'],
        ]]);
        $this->assertStringContainsString('Sales on', $el->get_content($user, false));
        $this->assertNotSame('', $el->render_html());
    }

    /**
     * Normalisation caps the number of rules and is idempotent.
     */
    public function test_rule_limit_and_idempotence(): void {
        $rules = array_fill(0, element::MAX_RULES + 10, ['type' => 'always', 'text' => 'x']);
        $clean = element::normalise_rules($rules);
        $this->assertCount(element::MAX_RULES, $clean);
        $this->assertSame($clean, element::normalise_rules($clean));
        $this->assertSame($clean, element::normalise_rules(json_encode($clean)));
        $this->assertSame([], element::normalise_rules(null));
        $this->assertSame([], element::normalise_rules(42));
    }

    /**
     * Form submissions with every rule field, including dates, inversion and AND.
     */
    public function test_normalise_data_all_fields(): void {
        $this->resetAfterTest();
        [$course] = $this->course_and_user();
        $el = $this->create_element($course, ['rules' => []]);
        $date = make_timestamp(2026, 12, 31);
        $data = $el->normalise_data((object)[
            'matchmode' => 'first',
            'ruletype' => [0 => 'date', 1 => 'timespent', 2 => 'role'],
            'rulenegate' => [0 => 1, 1 => 0, 2 => 0],
            'rulecombine' => [0 => 'and', 1 => '', 2 => ''],
            'ruledatesource' => [0 => 'issue'],
            'ruledateop' => [0 => 'onorafter'],
            'ruledate' => [0 => $date],
            'rulehoursop' => [1 => 'gt'],
            'rulehoursvalue' => [1 => '12.5'],
            'ruleroleid' => [2 => 5],
            'ruletext' => [0 => '', 1 => 'Long', 2 => 'Student'],
            'defaulttext' => '',
            'font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0,
        ]);
        [$first, $second, $third] = $data['rules'];
        $this->assertSame(
            ['date', 1, 'and', 'issue', 'onorafter', $date],
            [$first['type'], $first['negate'], $first['combine'], $first['datesource'], $first['dateop'], $first['date']]
        );
        $this->assertSame(['gt', 0], [$second['hoursop'], $second['negate']]);
        $this->assertEqualsWithDelta(12.5, (float)$second['hoursvalue'], 0.0001);
        $this->assertSame(5, $third['roleid']);
    }

    /**
     * Template export/import keeps the new rule keys.
     */
    public function test_export_import_new_keys(): void {
        $rules = element::normalise_rules([
            ['type' => 'date', 'datesource' => 'enrolment', 'dateop' => 'onorafter', 'date' => 1767225600,
                'negate' => 1, 'combine' => 'and', 'text' => ''],
            ['type' => 'progress', 'progressop' => 'lt', 'progressvalue' => '50', 'text' => 'Halfway'],
            ['type' => 'enrolment', 'enrol' => 'self', 'text' => 'Open'],
        ]);
        $exporter = new exporter(
            'conditionaltext',
            $this->createMock(template_import_logger_interface::class),
            $this->createMock(template_appendix_manager_interface::class),
        );
        $exported = json_decode(json_encode($exporter->export(json_encode([
            'matchmode' => 'first', 'rules' => $rules, 'defaulttext' => '',
            'font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0,
        ]))), true);
        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertSame($rules, $imported['rules']);
    }

    /**
     * Restore remaps activity completion rules and course badges.
     */
    public function test_restore_activity_and_badge(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/badgeslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $page = $gen->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $badge = $gen->get_plugin_generator('core_badges')->create_badge(['courseid' => $course->id,
            'type' => BADGE_TYPE_COURSE, 'name' => 'Course badge']);
        $this->create_element($course, ['rules' => [
            ['type' => 'activity', 'cmid' => $page->cmid, 'text' => 'Activity'],
            ['type' => 'badge', 'badgeid' => $badge->id, 'text' => 'Badge'],
        ]]);

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(0);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);

        $newcourse = $gen->create_course(['enablecompletion' => 1]);
        $rc = new restore_controller(
            $backupid,
            $newcourse->id,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $newcmid = (int)$DB->get_field_sql("SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND m.name = 'page'", [$newcourse->id]);
        $newbadgeid = (int)$DB->get_field('badge', 'id', ['courseid' => $newcourse->id, 'name' => 'Course badge']);
        $record = $DB->get_record_sql("SELECT e.* FROM {customcert_elements} e
            JOIN {customcert_pages} p ON p.id = e.pageid JOIN {customcert} c ON c.templateid = p.templateid
            WHERE c.course = ? AND e.element = 'conditionaltext'", [$newcourse->id], MUST_EXIST);
        $rules = (new element($record))->get_rules();
        $this->assertNotEmpty($newcmid);
        $this->assertSame($newcmid, $rules[0]['cmid']);
        if ($newbadgeid) {
            $this->assertSame($newbadgeid, $rules[1]['badgeid']);
        }
    }

    /**
     * The privacy provider reason string exists.
     */
    public function test_privacy_reason(): void {
        $this->assertNotEmpty(get_string(provider::get_reason(), 'customcertelement_conditionaltext'));
    }

    /**
     * Every rule type has a name string, and the help mentions every type.
     */
    public function test_strings_for_every_type(): void {
        $help = get_string('ruletype_help', 'customcertelement_conditionaltext');
        foreach (rule_evaluator::TYPES as $type) {
            $name = get_string('type_' . $type, 'customcertelement_conditionaltext');
            $this->assertNotEmpty($name);
            $this->assertStringContainsString('**' . $name . '**', $help, $type);
        }
    }

    /**
     * Creates a course with an enrolled student.
     *
     * @param array $courseoptions
     * @return array [course, user]
     */
    private function course_and_user(array $courseoptions = []): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course($courseoptions);
        return [$course, $gen->create_and_enrol($course, 'student')];
    }

    /**
     * Persists an element in a customcert activity of the course.
     *
     * @param stdClass $course
     * @param array $payload
     * @return element
     */
    private function create_element(stdClass $course, array $payload): element {
        global $DB;

        $customcert = $this->getDataGenerator()->create_module('customcert', ['course' => $course->id]);
        $pageid = $DB->get_field('customcert_pages', 'id', ['templateid' => $customcert->templateid], IGNORE_MULTIPLE);
        if (!$pageid) {
            $pageid = $DB->insert_record('customcert_pages', (object)[
                'templateid' => $customcert->templateid, 'width' => 297, 'height' => 210, 'leftmargin' => 0,
                'rightmargin' => 0, 'sequence' => 1, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        return $this->insert_element((int)$pageid, $payload);
    }

    /**
     * Inserts an element record on a page.
     *
     * @param int $pageid
     * @param array $payload
     * @return element
     */
    private function insert_element(int $pageid, array $payload): element {
        global $DB;

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
