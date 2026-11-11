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
use customcertelement_conditionaltext\local\rule_evaluator;
use grade_item;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Unit tests for the conditional text element.
 *
 * @package    customcertelement_conditionaltext
 * @category   test
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(element::class)]
#[CoversClass(rule_evaluator::class)]
final class element_test extends advanced_testcase {
    /**
     * Numeric comparison operators.
     */
    public function test_compare_numbers(): void {
        $this->assertTrue(rule_evaluator::compare_numbers(90.0, 'gte', 90.0));
        $this->assertFalse(rule_evaluator::compare_numbers(89.99, 'gte', 90.0));
        $this->assertTrue(rule_evaluator::compare_numbers(90.01, 'gt', 90.0));
        $this->assertFalse(rule_evaluator::compare_numbers(90.0, 'gt', 90.0));
        $this->assertTrue(rule_evaluator::compare_numbers(50.0, 'lte', 50.0));
        $this->assertTrue(rule_evaluator::compare_numbers(49.0, 'lt', 50.0));
        $this->assertTrue(rule_evaluator::compare_numbers(75.000001, 'eq', 75.0));
        $this->assertTrue(rule_evaluator::compare_numbers(74.0, 'neq', 75.0));
        $this->assertFalse(rule_evaluator::compare_numbers(74.0, 'bogus', 75.0));
    }

    /**
     * Text comparison operators are trimmed and case-insensitive.
     */
    public function test_compare_text(): void {
        $this->assertTrue(rule_evaluator::compare_text(' Manager ', 'equals', 'manager'));
        $this->assertTrue(rule_evaluator::compare_text('Sales', 'notequals', 'manager'));
        $this->assertTrue(rule_evaluator::compare_text('Senior Manager', 'contains', 'MANAGER'));
        $this->assertFalse(rule_evaluator::compare_text('Senior Manager', 'contains', ''));
        $this->assertTrue(rule_evaluator::compare_text('Sales', 'notcontains', 'manager'));
        $this->assertTrue(rule_evaluator::compare_text('  ', 'empty', ''));
        $this->assertTrue(rule_evaluator::compare_text('x', 'notempty', ''));
    }

    /**
     * Malformed rules are dropped or repaired.
     */
    public function test_normalise_rules(): void {
        $rules = element::normalise_rules([
            ['type' => 'grade', 'gradeop' => 'evil', 'gradevalue' => 'abc', 'text' => 'A'],
            ['type' => 'unknown', 'text' => 'B'],
            'not an array',
            ['type' => 'profile', 'profilefield' => 'department<script>', 'profileop' => 'contains', 'text' => 'C'],
        ]);
        $this->assertCount(2, $rules);
        $this->assertSame('gte', $rules[0]['gradeop']);
        $this->assertSame('', $rules[0]['gradevalue']);
        $this->assertSame('departmentscript', $rules[1]['profilefield']);
        $this->assertSame($rules, element::normalise_rules(json_encode($rules)));
        $this->assertSame([], element::normalise_rules('not json'));
    }

    /**
     * Form data with deleted repeats (gaps in the indexes) is stored in order.
     */
    public function test_normalise_data_with_deleted_rules(): void {
        $el = new element($this->make_record([]));
        $data = $el->normalise_data((object)[
            'matchmode' => 'all',
            'ruletype' => [0 => 'grade', 2 => 'always'],
            'rulegradeitem' => [0 => '0', 2 => '0'],
            'rulegradeop' => [0 => 'gte', 2 => 'gte'],
            'rulegradevalue' => [0 => '90', 2 => ''],
            'rulegradeunit' => [0 => 'percent', 2 => 'percent'],
            'ruletext' => [0 => 'Distinction', 2 => 'Thanks'],
            'defaulttext' => 'Default',
            'font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0,
        ]);
        $this->assertSame('all', $data['matchmode']);
        $this->assertCount(2, $data['rules']);
        $this->assertSame('Distinction', $data['rules'][0]['text']);
        $this->assertSame('always', $data['rules'][1]['type']);
        $this->assertSame('Default', $data['defaulttext']);
    }

    /**
     * The grade value must be numeric for grade rules.
     */
    public function test_validate(): void {
        $el = new element($this->make_record([]));
        $errors = $el->validate([
            'ruletype' => [0 => 'grade', 1 => 'completion', 2 => 'grade'],
            'rulegradevalue' => [0 => 'abc', 1 => '', 2 => '75'],
        ]);
        $this->assertArrayHasKey('rulegradegroup[0]', $errors);
        $this->assertArrayNotHasKey('rulegradegroup[1]', $errors);
        $this->assertArrayNotHasKey('rulegradegroup[2]', $errors);
    }

    /**
     * End to end: the text depends on the recipient's course grade and profile.
     */
    public function test_content_depends_on_recipient(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Safety 101']);
        $high = $gen->create_and_enrol($course, 'student', ['firstname' => 'Ana', 'department' => 'Operations']);
        $mid = $gen->create_and_enrol($course, 'student', ['firstname' => 'Ben', 'department' => 'Sales']);
        $low = $gen->create_and_enrol($course, 'student', ['firstname' => 'Cai', 'department' => 'Sales']);
        $ungraded = $gen->create_and_enrol($course, 'student', ['firstname' => 'Dee', 'department' => 'Sales']);

        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $item = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'courseid' => $course->id]);
        $item->update_final_grade($high->id, 95);
        $item->update_final_grade($mid->id, 80);
        $item->update_final_grade($low->id, 30);

        $rules = [
            ['type' => 'grade', 'gradeitem' => '0', 'gradeop' => 'gte', 'gradevalue' => '90',
                'gradeunit' => 'percent', 'text' => 'With distinction, {firstname}'],
            ['type' => 'grade', 'gradeitem' => (string)$assign->cmid, 'gradeop' => 'gte', 'gradevalue' => '75',
                'gradeunit' => 'points', 'text' => 'With merit'],
            ['type' => 'profile', 'profilefield' => 'department', 'profileop' => 'equals',
                'profilevalue' => 'operations', 'text' => 'Operations track'],
        ];
        $el = $this->create_element($course, ['matchmode' => 'first', 'rules' => $rules, 'defaulttext' => 'Completed']);

        $this->assertStringContainsString('With distinction, Ana', $el->get_content($high, false));
        $this->assertStringContainsString('With merit', $el->get_content($mid, false));
        $this->assertStringContainsString('Completed', $el->get_content($low, false));
        $this->assertStringContainsString('Completed', $el->get_content($ungraded, false));
        // Preview always shows the first rule.
        $this->assertStringContainsString('With distinction', $el->get_content($low, true));

        $all = $this->create_element($course, ['matchmode' => 'all', 'rules' => $rules, 'defaulttext' => '']);
        $content = $all->get_content($high, false);
        $this->assertStringContainsString('With distinction', $content);
        $this->assertStringContainsString('With merit', $content);
        $this->assertStringContainsString('Operations track', $content);
        $this->assertSame('', $all->get_content($low, false));
    }

    /**
     * Completion rules and escaped placeholders.
     */
    public function test_completion_and_placeholders(): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'R&D basics', 'enablecompletion' => 1]);
        $done = $gen->create_and_enrol($course, 'student', ['firstname' => 'Tom & Eve']);
        $notdone = $gen->create_and_enrol($course, 'student');
        $completion = new \completion_completion(['userid' => $done->id, 'course' => $course->id]);
        $completion->mark_complete();

        $el = $this->create_element($course, ['rules' => [
            ['type' => 'completion', 'completion' => 'complete', 'text' => '{firstname} finished {coursename}'],
            ['type' => 'completion', 'completion' => 'incomplete', 'text' => 'In progress'],
        ]]);

        $content = $el->get_content($done, false);
        $this->assertStringContainsString('Tom &amp; Eve finished R&amp;D basics', $content);
        $this->assertStringContainsString('In progress', $el->get_content($notdone, false));
    }

    /**
     * Builds an element record.
     *
     * @param array $payload
     * @param int $pageid
     * @return stdClass
     */
    private function make_record(array $payload, int $pageid = 1): stdClass {
        return (object)[
            'id' => 0,
            'pageid' => $pageid,
            'name' => 'Conditional text',
            'element' => 'conditionaltext',
            'data' => json_encode($payload + ['font' => 'times', 'fontsize' => 12, 'colour' => '#000000', 'width' => 0]),
            'posx' => 10,
            'posy' => 10,
            'refpoint' => 0,
            'alignment' => 'L',
            'sequence' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
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
        $page = (object)[
            'templateid' => $customcert->templateid, 'width' => 297, 'height' => 210,
            'leftmargin' => 0, 'rightmargin' => 0, 'sequence' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $page->id = $DB->insert_record('customcert_pages', $page);
        $record = $this->make_record($payload, (int)$page->id);
        unset($record->id);
        $record->id = $DB->insert_record('customcert_elements', $record);
        return new element($record);
    }
}
