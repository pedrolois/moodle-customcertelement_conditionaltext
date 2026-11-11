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
use customcertelement_conditionaltext\local\presets;
use customcertelement_conditionaltext\local\rule_evaluator;
use customcertelement_conditionaltext\local\rules_field;
use grade_item;
use mod_customcert\export\template_appendix_manager_interface;
use mod_customcert\export\template_import_logger_interface;
use mod_customcert\service\element_factory;
use mod_customcert\service\pdf_generation_service;
use mod_customcert\template;
use PHPUnit\Framework\Attributes\CoversClass;
use restore_controller;
use stdClass;

/**
 * Integration tests: grade sources, profile fields, backup/restore, template export and PDF output.
 *
 * @package    customcertelement_conditionaltext
 * @category   test
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(element::class)]
#[CoversClass(rule_evaluator::class)]
#[CoversClass(exporter::class)]
#[CoversClass(rules_field::class)]
#[CoversClass(presets::class)]
final class integration_test extends advanced_testcase {
    /**
     * Manual grade items, points vs percentage and non-zero minimum grades.
     */
    public function test_grade_item_sources_and_units(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'student');
        $other = $gen->create_and_enrol($course, 'student');

        // Manual item with range 10..60: 35 points = 50 %.
        $manual = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Oral', 'grademin' => 10,
            'grademax' => 60]);
        $manual = grade_item::fetch(['id' => $manual->id]);
        $manual->update_final_grade($user->id, 35);

        $evaluator = new rule_evaluator((int)$course->id);
        $source = 'gradeitem:' . $manual->id;
        $this->assertEqualsWithDelta(50.0, $evaluator->get_grade($source, (int)$user->id, 'percent'), 0.0001);
        $this->assertEqualsWithDelta(35.0, $evaluator->get_grade($source, (int)$user->id, 'points'), 0.0001);
        $this->assertNull($evaluator->get_grade($source, (int)$other->id, 'percent'));

        $rule = ['type' => 'grade', 'gradeitem' => $source, 'gradeop' => 'eq', 'gradevalue' => '50', 'gradeunit' => 'percent'];
        $this->assertTrue($evaluator->matches($rule, $user));
        // A user without a grade never matches, not even "not equal".
        $rule['gradeop'] = 'neq';
        $this->assertFalse($evaluator->matches($rule, $other));
    }

    /**
     * Grade items of another course, deleted activities and garbage sources never match.
     */
    public function test_foreign_and_missing_grade_sources(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $othercourse = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'student');
        $gen->enrol_user($user->id, $othercourse->id, 'student');

        $foreign = $gen->create_module('assign', ['course' => $othercourse->id, 'grade' => 100]);
        $item = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $foreign->id,
            'courseid' => $othercourse->id]);
        $item->update_final_grade($user->id, 100);

        $evaluator = new rule_evaluator((int)$course->id);
        foreach ([(string)$foreign->cmid, 'gradeitem:' . $item->id, '999999', 'gradeitem:999999', 'nonsense'] as $source) {
            $this->assertNull($evaluator->get_grade_item($source), $source);
            $this->assertFalse($evaluator->matches(['type' => 'grade', 'gradeitem' => $source, 'gradeop' => 'gte',
                'gradevalue' => '0', 'gradeunit' => 'points'], $user), $source);
        }

        // Deleted activity.
        $local = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        \core_courseformat\formatactions::cm($course->id)->delete((int)$local->cmid);
        $this->assertNull($evaluator->get_grade_item((string)$local->cmid));
    }

    /**
     * Custom profile fields of several types, and unknown fields.
     */
    public function test_custom_profile_fields(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $text = $gen->create_custom_profile_field(['shortname' => 'role', 'name' => 'Role', 'datatype' => 'text']);
        $menu = $gen->create_custom_profile_field(['shortname' => 'level', 'name' => 'Level', 'datatype' => 'menu',
            'param1' => "Junior\nSenior"]);
        $check = $gen->create_custom_profile_field(['shortname' => 'vip', 'name' => 'VIP', 'datatype' => 'checkbox']);
        $user = $gen->create_user(['profile_field_role' => 'Regional Manager', 'profile_field_level' => 'Senior',
            'profile_field_vip' => 1]);
        $blank = $gen->create_user();

        $evaluator = new rule_evaluator((int)SITEID);
        $rule = fn($field, $op, $value = '') => ['type' => 'profile', 'profilefield' => (string)$field,
            'profileop' => $op, 'profilevalue' => $value];

        $this->assertTrue($evaluator->matches($rule($text->id, 'contains', 'manager'), $user));
        $this->assertTrue($evaluator->matches($rule($menu->id, 'equals', 'senior'), $user));
        $this->assertTrue($evaluator->matches($rule($check->id, 'equals', '1'), $user));
        $this->assertTrue($evaluator->matches($rule($text->id, 'empty'), $blank));
        $this->assertFalse($evaluator->matches($rule($text->id, 'notempty'), $blank));
        $this->assertFalse($evaluator->matches($rule(999999, 'empty'), $user));
        $this->assertFalse($evaluator->matches($rule('password', 'notempty'), $user));
    }

    /**
     * Standard user fields are read from the database when the user record is partial.
     */
    public function test_standard_field_with_partial_user_record(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['institution' => 'ACME']);
        $partial = (object)['id' => $user->id, 'firstname' => $user->firstname, 'lastname' => $user->lastname];

        $evaluator = new rule_evaluator((int)SITEID);
        $this->assertTrue($evaluator->matches(['type' => 'profile', 'profilefield' => 'institution',
            'profileop' => 'equals', 'profilevalue' => 'acme'], $partial));
    }

    /**
     * Course backup and restore remaps activity and grade item references.
     */
    public function test_backup_restore_remaps_grade_sources(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $manual = $gen->create_grade_item(['courseid' => $course->id, 'itemname' => 'Oral']);
        $group = $gen->create_group(['courseid' => $course->id, 'name' => 'Advanced']);
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'group', 'groupid' => $group->id, 'membership' => 'member', 'text' => 'Group'],
            ['type' => 'grade', 'gradeitem' => (string)$assign->cmid, 'gradeop' => 'gte', 'gradevalue' => '50',
                'gradeunit' => 'percent', 'text' => 'Activity'],
            ['type' => 'grade', 'gradeitem' => 'gradeitem:' . $manual->id, 'gradeop' => 'gte', 'gradevalue' => '50',
                'gradeunit' => 'percent', 'text' => 'Manual'],
            ['type' => 'grade', 'gradeitem' => '0', 'gradeop' => 'gte', 'gradevalue' => '50',
                'gradeunit' => 'percent', 'text' => 'Course'],
        ]]);
        $this->assertCount(4, $el->get_rules());

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

        $newcourse = $gen->create_course();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
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

        $newcmid = (int)$DB->get_field_sql(
            "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = ? AND m.name = 'assign'",
            [$newcourse->id]
        );
        $newitemid = (int)$DB->get_field('grade_items', 'id', ['courseid' => $newcourse->id, 'itemname' => 'Oral']);
        $this->assertNotEmpty($newcmid);
        $this->assertNotEquals((int)$assign->cmid, $newcmid);

        $record = $DB->get_record_sql(
            "SELECT e.* FROM {customcert_elements} e
               JOIN {customcert_pages} p ON p.id = e.pageid
               JOIN {customcert} c ON c.templateid = p.templateid
              WHERE c.course = ? AND e.element = 'conditionaltext'",
            [$newcourse->id],
            MUST_EXIST
        );
        $rules = (new element($record))->get_rules();
        $newgroupid = (int)$DB->get_field('groups', 'id', ['courseid' => $newcourse->id, 'name' => 'Advanced']);
        $this->assertNotEmpty($newgroupid);
        $this->assertSame($newgroupid, $rules[0]['groupid']);
        $this->assertSame((string)$newcmid, $rules[1]['gradeitem']);
        $this->assertSame('gradeitem:' . $newitemid, $rules[2]['gradeitem']);
        $this->assertSame('0', $rules[3]['gradeitem']);
        $this->assertSame('Manual', $rules[2]['text']);
    }

    /**
     * Group membership rules, including groups of other courses.
     */
    public function test_group_rules(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $member = $gen->create_and_enrol($course, 'student');
        $nonmember = $gen->create_and_enrol($course, 'student');
        $group = $gen->create_group(['courseid' => $course->id]);
        $foreign = $gen->create_group(['courseid' => $other->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        $gen->enrol_user($member->id, $other->id, 'student');
        $gen->create_group_member(['groupid' => $foreign->id, 'userid' => $member->id]);

        $evaluator = new rule_evaluator((int)$course->id);
        $rule = ['type' => 'group', 'groupid' => $group->id];
        $this->assertTrue($evaluator->matches($rule, $member));
        $this->assertFalse($evaluator->matches($rule, $nonmember));
        $rule['negate'] = 1;
        $this->assertFalse($evaluator->matches($rule, $member));
        $this->assertTrue($evaluator->matches($rule, $nonmember));
        // Another course's group never matches, not even "not a member".
        $this->assertFalse($evaluator->matches(
            ['type' => 'group', 'groupid' => $foreign->id],
            $member
        ));
        $this->assertFalse($evaluator->matches(['type' => 'group', 'groupid' => $foreign->id,
            'negate' => 1], $nonmember));
        $this->assertFalse($evaluator->matches(['type' => 'group', 'groupid' => 0], $member));
    }

    /**
     * Cohort membership rules, including deleted cohorts.
     */
    public function test_cohort_rules(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $member = $gen->create_user();
        $nonmember = $gen->create_user();
        $cohort = $gen->create_cohort();
        cohort_add_member($cohort->id, $member->id);

        $evaluator = new rule_evaluator((int)SITEID);
        $rule = ['type' => 'cohort', 'cohortid' => $cohort->id];
        $this->assertTrue($evaluator->matches($rule, $member));
        $this->assertFalse($evaluator->matches($rule, $nonmember));
        $rule['negate'] = 1;
        $this->assertTrue($evaluator->matches($rule, $nonmember));

        cohort_delete_cohort($cohort);
        $this->assertFalse($evaluator->matches($rule, $nonmember));
    }

    /**
     * Every example loads, contains valid rules and uses existing strings.
     */
    public function test_presets(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        foreach (presets::KEYS as $key) {
            $preset = presets::get($key, (int)$course->id);
            $this->assertNotNull($preset, $key);
            $this->assertContains($preset['matchmode'], [element::MATCH_FIRST, element::MATCH_ALL], $key);
            $this->assertNotEmpty($preset['rules'], $key);
            $this->assertSame(element::normalise_rules($preset['rules']), $preset['rules'], $key);
            foreach (element::group_rules($preset['rules']) as $block) {
                $this->assertNotSame('', trim($block['text']), $key);
            }
            $this->assertNotEmpty(get_string("preset_{$key}", 'customcertelement_conditionaltext'));
            $this->assertNotEmpty(get_string("preset_{$key}_desc", 'customcertelement_conditionaltext'));
        }
        $this->assertSame((int)$group->id, presets::get('group', (int)$course->id)['rules'][0]['groupid']);
        $this->assertNull(presets::get('nonexistent', (int)$course->id));
    }

    /**
     * Template export and import keep the rules.
     */
    public function test_export_import_round_trip(): void {
        $rules = [
            ['type' => 'grade', 'gradeitem' => '0', 'gradeop' => 'gte', 'gradevalue' => '90', 'gradeunit' => 'percent',
                'text' => '<b>Distinction</b>'],
            ['type' => 'profile', 'profilefield' => 'department', 'profileop' => 'contains', 'profilevalue' => 'sales',
                'text' => 'Sales'],
        ];
        $data = json_encode([
            'matchmode' => 'all', 'rules' => $rules, 'defaulttext' => 'Done',
            'font' => 'times', 'fontsize' => 12, 'colour' => '#112233', 'width' => 50,
        ]);

        $exporter = new exporter(
            'conditionaltext',
            $this->createMock(template_import_logger_interface::class),
            $this->createMock(template_appendix_manager_interface::class),
        );
        $exported = $exporter->export($data);
        // The export must survive the JSON file the template is written to.
        $exported = json_decode(json_encode($exported), true);
        $imported = json_decode($exporter->convert_for_import($exported), true);

        $this->assertSame('all', $imported['matchmode']);
        $this->assertSame('Done', $imported['defaulttext']);
        $this->assertSame('#112233', $imported['colour']);
        $this->assertSame(element::normalise_rules($rules), $imported['rules']);

        // A tampered import is sanitised.
        $exported['rules']['value'][] = ['type' => 'evil', 'text' => 'x'];
        $exported['rules']['value'][0]['gradeop'] = 'drop table';
        $imported = json_decode($exporter->convert_for_import($exported), true);
        $this->assertCount(2, $imported['rules']);
        $this->assertSame('gte', $imported['rules'][0]['gradeop']);
    }

    /**
     * The element is discovered by customcert and renders in a real PDF and in the editor.
     */
    public function test_factory_pdf_and_html_rendering(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'student');
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'always', 'text' => 'Hello {fullname}'],
        ]]);

        $record = $DB->get_record('customcert_elements', ['id' => $el->get_id()]);
        $created = element_factory::build_with_defaults()->create_from_record($record);
        $this->assertInstanceOf(element::class, $created);

        $templateid = (int)$DB->get_field('customcert_pages', 'templateid', ['id' => $record->pageid]);
        $trec = $DB->get_record('customcert_templates', ['id' => $templateid]);
        $template = new template((int)$trec->id, $trec->name, (int)$trec->contextid);
        $pdf = pdf_generation_service::create()->generate_pdf($template, false, (int)$user->id, true);
        $this->assertStringStartsWith('%PDF', $pdf);

        $this->setUser($user);
        $this->assertStringContainsString('Hello ' . fullname($user), $el->render_html());

        // An element with nothing to show still renders a placeholder in the editor.
        $empty = $this->create_element($course, ['rules' => []]);
        $this->assertStringContainsString(
            get_string('pluginname', 'customcertelement_conditionaltext'),
            $empty->render_html()
        );
        $this->assertSame('', $empty->get_content($user, false));
    }

    /**
     * Rule texts are passed through the filters, so multi-language content works.
     */
    public function test_multilang_filter(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_and_enrol($course, 'student');
        $el = $this->create_element($course, ['rules' => [
            ['type' => 'always', 'text' => '<span lang="en" class="multilang">Passed</span>'
                . '<span lang="es" class="multilang">Aprobado</span>'],
        ]]);
        $content = $el->get_content($user, false);
        $this->assertStringContainsString('Passed', $content);
        $this->assertStringNotContainsString('Aprobado', $content);
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
                'templateid' => $customcert->templateid, 'width' => 297, 'height' => 210,
                'leftmargin' => 0, 'rightmargin' => 0, 'sequence' => 1,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $record = (object)[
            'pageid' => $pageid,
            'name' => 'Conditional text',
            'element' => 'conditionaltext',
            'data' => json_encode($payload + ['font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0]),
            'posx' => 10, 'posy' => 10, 'refpoint' => 0, 'alignment' => 'L', 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('customcert_elements', $record);
        return new element($record);
    }
}
