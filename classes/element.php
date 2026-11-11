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

use availability_profile\condition as profile_condition;
use core_collator;
use core_user\fields;
use customcertelement_conditionaltext\local\rule_evaluator;
use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\element\stylable_payload;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;

/**
 * Conditional text element: shows different text depending on the recipient's
 * grade, course completion or profile data.
 *
 * @package    customcertelement_conditionaltext
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    restorable_element_interface,
    validatable_element_interface
{
    /** @var string Show only the text of the first matching rule. */
    public const string MATCH_FIRST = 'first';

    /** @var string Show the texts of every matching rule, one per line. */
    public const string MATCH_ALL = 'all';

    /** @var int Maximum number of rules stored per element. */
    public const int MAX_RULES = 50;

    /** @var string[] Placeholders supported inside rule texts. */
    public const array PLACEHOLDERS = [
        '{firstname}', '{lastname}', '{fullname}', '{coursename}', '{courseshortname}', '{coursegrade}',
    ];

    /** @var string[] Names of the repeated per-rule form fields. */
    private const array RULE_FIELDS = [
        'ruletype', 'rulegradeitem', 'rulegradeop', 'rulegradevalue', 'rulegradeunit',
        'rulecompletion', 'ruleprofilefield', 'ruleprofileop', 'ruleprofilevalue', 'ruletext',
    ];

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        $component = 'customcertelement_conditionaltext';

        $mform->addElement('static', 'conditionalintro', '', get_string('intro', $component));

        $mform->addElement('select', 'matchmode', get_string('matchmode', $component), [
            self::MATCH_FIRST => get_string('matchmode_first', $component),
            self::MATCH_ALL => get_string('matchmode_all', $component),
        ]);
        $mform->addHelpButton('matchmode', 'matchmode', $component);

        $this->build_rules($mform);

        $mform->addElement('header', 'otherwiseheader', get_string('otherwise', $component));
        $mform->setExpanded('otherwiseheader');
        $mform->addElement('textarea', 'defaulttext', get_string('defaulttext', $component), ['rows' => 3, 'cols' => 60]);
        $mform->setType('defaulttext', PARAM_RAW);
        $mform->addHelpButton('defaulttext', 'defaulttext', $component);

        $mform->addElement('header', 'appearanceheader', get_string('appearance', $component));
        $mform->setExpanded('appearanceheader');

        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Adds the repeatable rule fields to the form.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    private function build_rules(MoodleQuickForm $mform): void {
        $component = 'customcertelement_conditionaltext';
        $types = [
            rule_evaluator::TYPE_GRADE => get_string('type_grade', $component),
            rule_evaluator::TYPE_COMPLETION => get_string('type_completion', $component),
            rule_evaluator::TYPE_PROFILE => get_string('type_profile', $component),
            rule_evaluator::TYPE_ALWAYS => get_string('type_always', $component),
        ];
        $gradeops = [];
        foreach (rule_evaluator::GRADE_OPERATORS as $op) {
            $gradeops[$op] = get_string('op_' . $op, $component);
        }
        $profileops = [];
        foreach (rule_evaluator::PROFILE_OPERATORS as $op) {
            $profileops[$op] = get_string('op_' . $op, $component);
        }

        $repeat = [];
        $repeat[] = $mform->createElement('header', 'ruleheader', get_string('rulenumber', $component, '{no}'));
        $repeat[] = $mform->createElement('select', 'ruletype', get_string('ruletype', $component), $types);
        $repeat[] = $mform->createElement(
            'select',
            'rulegradeitem',
            get_string('gradeitem', $component),
            self::get_grade_source_options()
        );
        $repeat[] = $mform->createElement('group', 'rulegradegroup', get_string('gradecondition', $component), [
            $mform->createElement('select', 'rulegradeop', '', $gradeops),
            $mform->createElement('text', 'rulegradevalue', '', ['size' => 6]),
            $mform->createElement('select', 'rulegradeunit', '', [
                rule_evaluator::UNIT_PERCENT => get_string('unit_percent', $component),
                rule_evaluator::UNIT_POINTS => get_string('unit_points', $component),
            ]),
        ], ' ', false);
        $repeat[] = $mform->createElement('select', 'rulecompletion', get_string('completionstate', $component), [
            rule_evaluator::COMPLETION_COMPLETE => get_string('completion_complete', $component),
            rule_evaluator::COMPLETION_INCOMPLETE => get_string('completion_incomplete', $component),
        ]);
        $repeat[] = $mform->createElement(
            'select',
            'ruleprofilefield',
            get_string('profilefield', $component),
            self::get_profile_field_options()
        );
        $repeat[] = $mform->createElement('group', 'ruleprofilegroup', get_string('profilecondition', $component), [
            $mform->createElement('select', 'ruleprofileop', '', $profileops),
            $mform->createElement('text', 'ruleprofilevalue', '', ['size' => 20]),
        ], ' ', false);
        $repeat[] = $mform->createElement(
            'textarea',
            'ruletext',
            get_string('ruletext', $component),
            ['rows' => 3, 'cols' => 60]
        );
        $repeat[] = $mform->createElement('submit', 'ruledelete', get_string('deleterule', $component), [], false);

        $options = [
            'ruleheader' => ['expanded' => true],
            'ruletype' => ['default' => rule_evaluator::TYPE_GRADE],
            'rulegradeitem' => ['hideif' => ['ruletype', 'neq', rule_evaluator::TYPE_GRADE]],
            'rulegradegroup' => ['hideif' => ['ruletype', 'neq', rule_evaluator::TYPE_GRADE]],
            'rulegradeop' => ['default' => 'gte'],
            'rulegradevalue' => ['type' => PARAM_RAW_TRIMMED],
            'rulecompletion' => ['hideif' => ['ruletype', 'neq', rule_evaluator::TYPE_COMPLETION]],
            'ruleprofilefield' => ['hideif' => ['ruletype', 'neq', rule_evaluator::TYPE_PROFILE]],
            'ruleprofilegroup' => ['hideif' => ['ruletype', 'neq', rule_evaluator::TYPE_PROFILE]],
            'ruleprofilevalue' => ['type' => PARAM_TEXT],
            'ruletext' => ['type' => PARAM_RAW, 'helpbutton' => ['ruletext', $component]],
        ];

        $count = max(1, count($this->get_rules()));
        $repeats = $this->get_edit_element_form()->repeat_elements(
            $repeat,
            $count,
            $options,
            'rule_repeats',
            'rule_add',
            1,
            get_string('addrule', $component),
            false,
            'ruledelete'
        );
        // Hide the operand when the operator does not need one.
        for ($i = 0; $i < $repeats; $i++) {
            if ($mform->elementExists("ruleprofilevalue[$i]")) {
                $mform->hideIf("ruleprofilevalue[$i]", "ruleprofileop[$i]", 'in', ['empty', 'notempty']);
            }
        }
    }

    /**
     * Pre-populate the form from the stored payload.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->get_payload();
        $mform->setDefault('matchmode', $payload['matchmode'] ?? self::MATCH_FIRST);
        $mform->setDefault('defaulttext', (string)($payload['defaulttext'] ?? ''));

        foreach ($this->get_rules() as $i => $rule) {
            foreach (self::RULE_FIELDS as $field) {
                $key = substr($field, 4); // Strip the "rule" prefix.
                if (array_key_exists($key, $rule)) {
                    $mform->setDefault("{$field}[{$i}]", $rule[$key]);
                }
            }
        }
    }

    /**
     * Validate the submitted rules.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        $errors = [];
        $types = (array)($data['ruletype'] ?? []);
        foreach ($types as $i => $type) {
            if ($type === rule_evaluator::TYPE_GRADE) {
                $value = trim((string)($data['rulegradevalue'][$i] ?? ''));
                if ($value === '' || !is_numeric(unformat_float($value, true))) {
                    $errors["rulegradegroup[$i]"] = get_string('errorgradevalue', 'customcertelement_conditionaltext');
                }
            }
        }
        if (count($types) > self::MAX_RULES) {
            $errors['rule_add'] = get_string('errortoomanyrules', 'customcertelement_conditionaltext', self::MAX_RULES);
        }
        return $errors;
    }

    /**
     * Convert the form submission to the JSON payload.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        $rules = [];
        foreach ((array)($formdata->ruletype ?? []) as $i => $type) {
            $raw = ['type' => $type];
            foreach (self::RULE_FIELDS as $field) {
                $values = (array)($formdata->$field ?? []);
                if (array_key_exists($i, $values)) {
                    $raw[substr($field, 4)] = $values[$i];
                }
            }
            if (isset($raw['gradevalue'])) {
                $raw['gradevalue'] = (string)unformat_float((string)$raw['gradevalue'], true);
            }
            $rules[] = $raw;
        }

        return array_merge(
            [
                'matchmode' => ($formdata->matchmode ?? '') === self::MATCH_ALL ? self::MATCH_ALL : self::MATCH_FIRST,
                'rules' => self::normalise_rules($rules),
                'defaulttext' => (string)($formdata->defaulttext ?? ''),
            ],
            stylable_payload::from_form($formdata)->to_array(),
        );
    }

    /**
     * Sanitises a list of rules into the canonical stored shape.
     *
     * @param mixed $rules Rules array (or JSON string, as produced by template import).
     * @return array[]
     */
    public static function normalise_rules(mixed $rules): array {
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        if (!is_array($rules)) {
            return [];
        }

        $types = [rule_evaluator::TYPE_GRADE, rule_evaluator::TYPE_COMPLETION,
            rule_evaluator::TYPE_PROFILE, rule_evaluator::TYPE_ALWAYS];
        $clean = [];
        foreach (array_values($rules) as $rule) {
            if (!is_array($rule) || !in_array($rule['type'] ?? null, $types, true)) {
                continue;
            }
            $gradeop = (string)($rule['gradeop'] ?? 'gte');
            $profileop = (string)($rule['profileop'] ?? 'equals');
            $gradevalue = (string)($rule['gradevalue'] ?? '');
            $clean[] = [
                'type' => $rule['type'],
                'gradeitem' => clean_param((string)($rule['gradeitem'] ?? rule_evaluator::GRADE_COURSE), PARAM_TEXT),
                'gradeop' => in_array($gradeop, rule_evaluator::GRADE_OPERATORS, true) ? $gradeop : 'gte',
                'gradevalue' => is_numeric($gradevalue) ? $gradevalue : '',
                'gradeunit' => ($rule['gradeunit'] ?? '') === rule_evaluator::UNIT_POINTS
                    ? rule_evaluator::UNIT_POINTS : rule_evaluator::UNIT_PERCENT,
                'completion' => ($rule['completion'] ?? '') === rule_evaluator::COMPLETION_INCOMPLETE
                    ? rule_evaluator::COMPLETION_INCOMPLETE : rule_evaluator::COMPLETION_COMPLETE,
                'profilefield' => clean_param((string)($rule['profilefield'] ?? ''), PARAM_ALPHANUMEXT),
                'profileop' => in_array($profileop, rule_evaluator::PROFILE_OPERATORS, true) ? $profileop : 'equals',
                'profilevalue' => clean_param((string)($rule['profilevalue'] ?? ''), PARAM_TEXT),
                'text' => (string)($rule['text'] ?? ''),
            ];
            if (count($clean) >= self::MAX_RULES) {
                break;
            }
        }
        return $clean;
    }

    /**
     * Returns the stored rules.
     *
     * @return array[]
     */
    public function get_rules(): array {
        return self::normalise_rules($this->get_payload()['rules'] ?? []);
    }

    /**
     * Render the element on the PDF.
     *
     * @param pdf $pdf
     * @param bool $preview
     * @param stdClass $user The certificate recipient.
     * @param element_renderer|null $renderer
     * @return void
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $content = $this->get_content($user, $preview);
        if ($content === '') {
            return;
        }
        if ($renderer) {
            $renderer->render_content($this, $content);
        } else {
            element_helper::render_content($pdf, $this, $content);
        }
    }

    /**
     * Render the element on the drag-and-drop editor.
     *
     * @param element_renderer|null $renderer
     * @return string
     */
    public function render_html(?element_renderer $renderer = null): string {
        global $USER;

        $content = $this->get_content($USER, true);
        if ($content === '') {
            // Always show something so the element can be positioned.
            $content = s(get_string('pluginname', 'customcertelement_conditionaltext'));
        }
        if ($renderer) {
            return (string)$renderer->render_content($this, $content);
        }
        return element_helper::render_html_content($this, $content);
    }

    /**
     * Resolves the formatted text to print for the user.
     *
     * In preview mode the first rule's text (or the default text) is shown, so the
     * designer sees representative content regardless of their own data.
     *
     * @param stdClass $user The certificate recipient.
     * @param bool $preview
     * @return string Formatted HTML, or '' when nothing should be printed.
     */
    public function get_content(stdClass $user, bool $preview): string {
        $payload = $this->get_payload();
        $rules = $this->get_rules();
        $courseid = element_helper::get_courseid($this->get_id());
        $texts = [];

        if ($preview) {
            $texts[] = $rules[0]['text'] ?? (string)($payload['defaulttext'] ?? '');
        } else {
            $evaluator = new rule_evaluator($courseid);
            $all = ($payload['matchmode'] ?? '') === self::MATCH_ALL;
            foreach ($rules as $rule) {
                if ($evaluator->matches($rule, $user)) {
                    $texts[] = $rule['text'];
                    if (!$all) {
                        break;
                    }
                }
            }
            if (!$texts) {
                $texts[] = (string)($payload['defaulttext'] ?? '');
            }
        }

        $texts = array_filter($texts, static fn(string $text): bool => trim($text) !== '');
        if (!$texts) {
            return '';
        }

        $context = element_helper::get_context($this->get_id());
        $html = [];
        foreach ($texts as $text) {
            $text = $this->replace_placeholders($text, $user, $courseid, $preview);
            $html[] = format_text($text, FORMAT_HTML, ['context' => $context]);
        }
        return implode('<br>', $html);
    }

    /**
     * Replaces the supported placeholders with escaped values.
     *
     * @param string $text
     * @param stdClass $user
     * @param int $courseid
     * @param bool $preview
     * @return string
     */
    private function replace_placeholders(string $text, stdClass $user, int $courseid, bool $preview): string {
        global $CFG;

        if (!str_contains($text, '{')) {
            return $text;
        }
        $course = get_course($courseid);
        $context = \context_course::instance($courseid);

        $coursegrade = '';
        if (str_contains($text, '{coursegrade}')) {
            require_once($CFG->libdir . '/gradelib.php');
            $item = \grade_item::fetch_course_item($courseid);
            if ($item) {
                if ($preview) {
                    $coursegrade = grade_format_gradevalue((float)$item->grademax, $item, true);
                } else {
                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $user->id]);
                    if ($grade && $grade->finalgrade !== null) {
                        $coursegrade = grade_format_gradevalue((float)$grade->finalgrade, $item, true);
                    }
                }
            }
        }

        $values = [
            '{firstname}' => s((string)($user->firstname ?? '')),
            '{lastname}' => s((string)($user->lastname ?? '')),
            '{fullname}' => s(fullname($user)),
            '{coursename}' => format_string($course->fullname, true, ['context' => $context]),
            '{courseshortname}' => format_string($course->shortname, true, ['context' => $context]),
            '{coursegrade}' => s($coursegrade),
        ];
        return strtr($text, $values);
    }

    /**
     * Remap grade sources after a course restore.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $payload = $this->get_payload();
        $rules = self::normalise_rules($payload['rules'] ?? []);
        $changed = false;
        foreach ($rules as &$rule) {
            $source = $rule['gradeitem'];
            if ($rule['type'] !== rule_evaluator::TYPE_GRADE || $source === rule_evaluator::GRADE_COURSE || $source === '') {
                continue;
            }
            $isgradeitem = str_starts_with($source, 'gradeitem:');
            $oldid = (int)($isgradeitem ? substr($source, 10) : $source);
            $newid = $restore->get_mappingid($isgradeitem ? 'grade_item' : 'course_module', $oldid);
            if ($newid) {
                $rule['gradeitem'] = ($isgradeitem ? 'gradeitem:' : '') . $newid;
                $changed = true;
            }
        }
        unset($rule);

        if ($changed) {
            $payload['rules'] = $rules;
            $DB->set_field('customcert_elements', 'data', json_encode($payload), ['id' => $this->get_id()]);
        }
    }

    /**
     * Grade sources selectable in the form: course total plus the course's grade items.
     *
     * @return array<string,string>
     */
    private static function get_grade_source_options(): array {
        global $CFG, $COURSE;
        require_once($CFG->libdir . '/gradelib.php');

        $options = [rule_evaluator::GRADE_COURSE => get_string('coursegrade', 'customcertelement_conditionaltext')];
        return $options + element_helper::get_grade_items($COURSE);
    }

    /**
     * Profile fields selectable in the form.
     *
     * Custom fields hidden from everyone are only offered to users who may view all details,
     * so a certificate cannot be used to probe values the designer is not allowed to see.
     *
     * @return array<string,string>
     */
    private static function get_profile_field_options(): array {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $fields = [];
        foreach (rule_evaluator::USER_FIELDS as $field) {
            $fields[$field] = $field === 'lang' ? get_string('preferredlanguage') : fields::get_display_name($field);
        }
        $canviewall = has_capability('moodle/user:viewalldetails', $PAGE->context);
        foreach (profile_condition::get_custom_profile_fields() as $custom) {
            if ((int)$custom->visible === (int)PROFILE_VISIBLE_NONE && !$canviewall) {
                continue;
            }
            $fields[(string)$custom->id] = format_string($custom->name);
        }
        core_collator::asort($fields);
        return $fields;
    }
}
