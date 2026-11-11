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

use customcertelement_conditionaltext\local\form_options;
use customcertelement_conditionaltext\local\presets;
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
 * Conditional text element: prints different text depending on the certificate recipient.
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

    /** @var string Combine value: the next rule must also be met. */
    public const string COMBINE_AND = 'and';

    /** @var int Maximum number of rules stored per element. */
    public const int MAX_RULES = 50;

    /** @var string[] Placeholders supported inside rule texts. */
    public const array PLACEHOLDERS = [
        '{firstname}', '{lastname}', '{fullname}', '{coursename}', '{courseshortname}', '{coursegrade}',
    ];

    /** @var string[] Stored rule keys that have a "rule<key>" field in the form. */
    private const array RULE_KEYS = [
        'type', 'negate', 'gradeitem', 'gradeop', 'gradevalue', 'gradeunit', 'cmid', 'profilefield', 'profileop',
        'profilevalue', 'groupid', 'cohortid', 'roleid', 'badgeid', 'competencyid', 'enrol', 'datesource', 'dateop',
        'date', 'progressop', 'progressvalue', 'hoursop', 'hoursvalue', 'daysop', 'daysvalue', 'combine', 'text',
    ];

    /** @var array<string,string> Rule types that need a choice in a select: type => stored key. */
    private const array REQUIRED_CHOICES = [
        'activity' => 'cmid', 'group' => 'groupid', 'cohort' => 'cohortid', 'role' => 'roleid',
        'badge' => 'badgeid', 'competency' => 'competencyid', 'enrolment' => 'enrol',
    ];

    /** @var string Plugin component, for strings. */
    private const string COMPONENT = 'customcertelement_conditionaltext';

    /** @var array|null Preset loaded through the examples menu, if any. */
    private ?array $preset = null;

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        $mform->addElement('static', 'conditionalintro', '', get_string('intro', self::COMPONENT));

        $this->preset = $this->get_requested_preset();
        $mform->addElement('static', 'examples', get_string('examples', self::COMPONENT), $this->render_examples_menu());
        $mform->addHelpButton('examples', 'examples', self::COMPONENT);

        $mform->addElement('select', 'matchmode', get_string('matchmode', self::COMPONENT), [
            self::MATCH_FIRST => get_string('matchmode_first', self::COMPONENT),
            self::MATCH_ALL => get_string('matchmode_all', self::COMPONENT),
        ]);
        $mform->addHelpButton('matchmode', 'matchmode', self::COMPONENT);

        $this->build_rules($mform);

        $mform->addElement('header', 'otherwiseheader', get_string('otherwise', self::COMPONENT));
        $mform->setExpanded('otherwiseheader');
        $mform->addElement('textarea', 'defaulttext', get_string('defaulttext', self::COMPONENT), ['rows' => 3, 'cols' => 60]);
        $mform->setType('defaulttext', PARAM_RAW);
        $mform->addHelpButton('defaulttext', 'defaulttext', self::COMPONENT);

        $mform->addElement('header', 'appearanceheader', get_string('appearance', self::COMPONENT));
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
        global $COURSE;

        $c = self::COMPONENT;
        $options = new form_options($COURSE);
        $str = fn(string $id): string => get_string($id, $c);
        $list = fn(array $keys, string $prefix): array => array_combine(
            $keys,
            array_map(fn(string $key): string => get_string($prefix . $key, $c), $keys)
        );
        $types = $list(rule_evaluator::TYPES, 'type_');
        $numops = $list(rule_evaluator::GRADE_OPERATORS, 'op_');

        $repeat = [];
        $repeat[] = $mform->createElement('header', 'ruleheader', get_string('rulenumber', $c, '{no}'));
        $repeat[] = $mform->createElement('select', 'ruletype', $str('ruletype'), $types);
        $repeat[] = $mform->createElement('select', 'rulegradeitem', $str('gradeitem'), $options->grade_sources());
        $repeat[] = $mform->createElement('group', 'rulegradegroup', $str('gradecondition'), [
            $mform->createElement('select', 'rulegradeop', '', $numops),
            $mform->createElement('text', 'rulegradevalue', '', ['size' => 6]),
            $mform->createElement('select', 'rulegradeunit', '', [
                rule_evaluator::UNIT_PERCENT => $str('unit_percent'),
                rule_evaluator::UNIT_POINTS => $str('unit_points'),
            ]),
        ], ' ', false);
        $repeat[] = $mform->createElement('group', 'ruleprogressgroup', $str('progresscondition'), [
            $mform->createElement('select', 'ruleprogressop', '', $numops),
            $mform->createElement('text', 'ruleprogressvalue', '', ['size' => 6]),
            $mform->createElement('static', 'ruleprogressunit', '', '%'),
        ], ' ', false);
        $repeat[] = $mform->createElement('select', 'rulecmid', $str('activity'), $options->activities());
        $repeat[] = $mform->createElement('select', 'ruleprofilefield', $str('profilefield'), $options->profile_fields());
        $repeat[] = $mform->createElement('group', 'ruleprofilegroup', $str('profilecondition'), [
            $mform->createElement('select', 'ruleprofileop', '', $list(rule_evaluator::PROFILE_OPERATORS, 'op_')),
            $mform->createElement('text', 'ruleprofilevalue', '', ['size' => 20]),
        ], ' ', false);
        $repeat[] = $mform->createElement('select', 'rulegroupid', get_string('group'), $options->groups());
        $repeat[] = $mform->createElement('select', 'rulecohortid', get_string('cohort', 'cohort'), $options->cohorts());
        $repeat[] = $mform->createElement('select', 'ruleroleid', get_string('role'), $options->roles());
        $repeat[] = $mform->createElement('select', 'rulebadgeid', $str('badge'), $options->badges());
        $repeat[] = $mform->createElement(
            'select',
            'rulecompetencyid',
            get_string('competency', 'core_competency'),
            $options->competencies()
        );
        $repeat[] = $mform->createElement('select', 'ruleenrol', $str('enrolmentmethod'), $options->enrolment_methods());
        $repeat[] = $mform->createElement('group', 'ruledategroup', $str('datecondition'), [
            $mform->createElement('select', 'ruledatesource', '', $list(rule_evaluator::DATE_SOURCES, 'datesource_')),
            $mform->createElement('select', 'ruledateop', '', $list(rule_evaluator::DATE_OPERATORS, 'dateop_')),
        ], ' ', false);
        $repeat[] = $mform->createElement('date_selector', 'ruledate', $str('date'));
        $repeat[] = $mform->createElement('group', 'rulehoursgroup', $str('hourscondition'), [
            $mform->createElement('select', 'rulehoursop', '', $numops),
            $mform->createElement('text', 'rulehoursvalue', '', ['size' => 6]),
            $mform->createElement('static', 'rulehoursunit', '', $str('unit_hours')),
        ], ' ', false);
        $repeat[] = $mform->createElement('group', 'ruledaysgroup', $str('dayscondition'), [
            $mform->createElement('select', 'ruledaysop', '', $numops),
            $mform->createElement('text', 'ruledaysvalue', '', ['size' => 6]),
            $mform->createElement('static', 'ruledaysunit', '', $str('unit_days')),
        ], ' ', false);
        $repeat[] = $mform->createElement('advcheckbox', 'rulenegate', $str('negate'), $str('negate_label'));
        $repeat[] = $mform->createElement('select', 'rulecombine', $str('combine'), [
            '' => $str('combine_none'),
            self::COMBINE_AND => $str('combine_and'),
        ]);
        $repeat[] = $mform->createElement('textarea', 'ruletext', $str('ruletext'), ['rows' => 3, 'cols' => 60]);
        $repeat[] = $mform->createElement('submit', 'ruledelete', $str('deleterule'), [], false);

        // Show each field only for the rule types that use it.
        $onlyfor = fn(string ...$only): array => ['hideif' => ['ruletype', 'in', array_values(array_diff(
            rule_evaluator::TYPES,
            $only
        ))]];
        $repeatoptions = [
            'ruletype' => ['default' => rule_evaluator::TYPE_GRADE],
            'rulegradeitem' => $onlyfor('grade'),
            'rulegradegroup' => $onlyfor('grade'),
            'rulegradeop' => ['default' => 'gte'],
            'rulegradevalue' => ['type' => PARAM_RAW_TRIMMED],
            'ruleprogressgroup' => $onlyfor('progress'),
            'ruleprogressop' => ['default' => 'gte'],
            'ruleprogressvalue' => ['type' => PARAM_RAW_TRIMMED],
            'rulecmid' => $onlyfor('activity') + ['type' => PARAM_INT],
            'ruleprofilefield' => $onlyfor('profile'),
            'ruleprofilegroup' => $onlyfor('profile'),
            'ruleprofilevalue' => ['type' => PARAM_TEXT],
            'rulegroupid' => $onlyfor('group') + ['type' => PARAM_INT],
            'rulecohortid' => $onlyfor('cohort') + ['type' => PARAM_INT],
            'ruleroleid' => $onlyfor('role') + ['type' => PARAM_INT],
            'rulebadgeid' => $onlyfor('badge') + ['type' => PARAM_INT],
            'rulecompetencyid' => $onlyfor('competency') + ['type' => PARAM_INT],
            'ruleenrol' => $onlyfor('enrolment') + ['type' => PARAM_PLUGIN],
            'ruledategroup' => $onlyfor('date'),
            'ruledate' => $onlyfor('date'),
            'rulehoursgroup' => $onlyfor('timespent'),
            'rulehoursop' => ['default' => 'gte'],
            'rulehoursvalue' => ['type' => PARAM_RAW_TRIMMED],
            'ruledaysgroup' => $onlyfor('completiondays'),
            'ruledaysop' => ['default' => 'lte'],
            'ruledaysvalue' => ['type' => PARAM_RAW_TRIMMED],
            'rulenegate' => ['hideif' => ['ruletype', 'eq', rule_evaluator::TYPE_ALWAYS]],
            'ruletext' => ['type' => PARAM_RAW, 'hideif' => ['rulecombine', 'eq', self::COMBINE_AND]],
        ];

        $count = max(1, count($this->get_form_rules()));
        $repeats = $this->get_edit_element_form()->repeat_elements(
            $repeat,
            $count,
            $repeatoptions,
            'rule_repeats',
            'rule_add',
            1,
            $str('addrule'),
            false,
            'ruledelete'
        );
        // Expanded sections, help icons and the operand visibility are set here rather than through
        // the repeat options, because those are also applied to deleted rules, which no longer exist.
        for ($i = 0; $i < $repeats; $i++) {
            if (!$mform->elementExists("ruletype[$i]")) {
                continue;
            }
            $mform->setExpanded("ruleheader[$i]");
            $mform->addHelpButton("ruletype[$i]", 'ruletype', $c);
            $mform->addHelpButton("rulenegate[$i]", 'negate', $c);
            $mform->addHelpButton("rulecombine[$i]", 'combine', $c);
            $mform->addHelpButton("ruletext[$i]", 'ruletext', $c);
            $mform->hideIf("ruleprofilevalue[$i]", "ruleprofileop[$i]", 'in', ['empty', 'notempty']);
        }
    }

    /**
     * Pre-populate the form from the stored payload (or from the requested example).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->preset ?? $this->get_payload();
        $mform->setDefault('matchmode', $payload['matchmode'] ?? self::MATCH_FIRST);
        $mform->setDefault('defaulttext', (string)($payload['defaulttext'] ?? ''));

        foreach ($this->get_form_rules() as $i => $rule) {
            foreach (self::RULE_KEYS as $key) {
                if ($key === 'date' && empty($rule['date'])) {
                    continue;
                }
                $mform->setDefault("rule{$key}[{$i}]", $rule[$key]);
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
        $c = self::COMPONENT;
        $errors = [];
        $types = (array)($data['ruletype'] ?? []);
        $numeric = fn(string $field, int|string $i): bool =>
            is_numeric(unformat_float(trim((string)($data[$field][$i] ?? '')), true));

        foreach ($types as $i => $type) {
            if ($type === rule_evaluator::TYPE_GRADE && !$numeric('rulegradevalue', $i)) {
                $errors["rulegradegroup[$i]"] = get_string('errornumber', $c);
            } else if ($type === rule_evaluator::TYPE_PROGRESS && !$numeric('ruleprogressvalue', $i)) {
                $errors["ruleprogressgroup[$i]"] = get_string('errornumber', $c);
            } else if ($type === rule_evaluator::TYPE_TIMESPENT && !$numeric('rulehoursvalue', $i)) {
                $errors["rulehoursgroup[$i]"] = get_string('errornumber', $c);
            } else if ($type === rule_evaluator::TYPE_COMPLETIONDAYS && !$numeric('ruledaysvalue', $i)) {
                $errors["ruledaysgroup[$i]"] = get_string('errornumber', $c);
            } else if (isset(self::REQUIRED_CHOICES[$type]) && empty($data['rule' . self::REQUIRED_CHOICES[$type]][$i])) {
                $errors['rule' . self::REQUIRED_CHOICES[$type] . "[$i]"] = get_string('errorchoose', $c);
            }
        }
        if ($types) {
            $last = array_key_last($types);
            if (($data['rulecombine'][$last] ?? '') === self::COMBINE_AND) {
                $errors["rulecombine[$last]"] = get_string('errorcombinelast', $c);
            }
        }
        if (count($types) > self::MAX_RULES) {
            $errors['rule_add'] = get_string('errortoomanyrules', $c, self::MAX_RULES);
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
        foreach (array_keys((array)($formdata->ruletype ?? [])) as $i) {
            $raw = [];
            foreach (self::RULE_KEYS as $key) {
                $values = (array)($formdata->{'rule' . $key} ?? []);
                if (array_key_exists($i, $values)) {
                    $raw[$key] = $values[$i];
                }
            }
            foreach (['gradevalue', 'progressvalue', 'hoursvalue', 'daysvalue'] as $key) {
                if (isset($raw[$key])) {
                    $raw[$key] = (string)unformat_float((string)$raw[$key], true);
                }
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

        $pick = fn(mixed $value, array $allowed, string $default): string =>
            in_array((string)$value, $allowed, true) ? (string)$value : $default;
        $number = fn(mixed $value): string => is_numeric($value) ? (string)$value : '';

        $clean = [];
        foreach (array_values($rules) as $rule) {
            if (!is_array($rule) || !in_array($rule['type'] ?? null, rule_evaluator::TYPES, true)) {
                continue;
            }
            // Earlier rule formats expressed the inversion per type.
            $negate = !empty($rule['negate'])
                || ($rule['completion'] ?? '') === 'incomplete'
                || ($rule['membership'] ?? '') === 'notmember';

            $clean[] = [
                'type' => $rule['type'],
                'negate' => $negate ? 1 : 0,
                'gradeitem' => clean_param((string)($rule['gradeitem'] ?? rule_evaluator::GRADE_COURSE), PARAM_TEXT),
                'gradeop' => $pick($rule['gradeop'] ?? '', rule_evaluator::GRADE_OPERATORS, 'gte'),
                'gradevalue' => $number($rule['gradevalue'] ?? ''),
                'gradeunit' => $pick($rule['gradeunit'] ?? '', [rule_evaluator::UNIT_POINTS], rule_evaluator::UNIT_PERCENT),
                'cmid' => max(0, (int)($rule['cmid'] ?? 0)),
                'profilefield' => clean_param((string)($rule['profilefield'] ?? ''), PARAM_ALPHANUMEXT),
                'profileop' => $pick($rule['profileop'] ?? '', rule_evaluator::PROFILE_OPERATORS, 'equals'),
                'profilevalue' => clean_param((string)($rule['profilevalue'] ?? ''), PARAM_TEXT),
                'groupid' => max(0, (int)($rule['groupid'] ?? 0)),
                'cohortid' => max(0, (int)($rule['cohortid'] ?? 0)),
                'roleid' => max(0, (int)($rule['roleid'] ?? 0)),
                'badgeid' => max(0, (int)($rule['badgeid'] ?? 0)),
                'competencyid' => max(0, (int)($rule['competencyid'] ?? 0)),
                'enrol' => clean_param((string)($rule['enrol'] ?? ''), PARAM_PLUGIN),
                'datesource' => $pick($rule['datesource'] ?? '', rule_evaluator::DATE_SOURCES, 'completion'),
                'dateop' => $pick($rule['dateop'] ?? '', rule_evaluator::DATE_OPERATORS, 'before'),
                'date' => max(0, (int)($rule['date'] ?? 0)),
                'progressop' => $pick($rule['progressop'] ?? '', rule_evaluator::GRADE_OPERATORS, 'gte'),
                'progressvalue' => $number($rule['progressvalue'] ?? ''),
                'hoursop' => $pick($rule['hoursop'] ?? '', rule_evaluator::GRADE_OPERATORS, 'gte'),
                'hoursvalue' => $number($rule['hoursvalue'] ?? ''),
                'daysop' => $pick($rule['daysop'] ?? '', rule_evaluator::GRADE_OPERATORS, 'lte'),
                'daysvalue' => $number($rule['daysvalue'] ?? ''),
                'combine' => ($rule['combine'] ?? '') === self::COMBINE_AND ? self::COMBINE_AND : '',
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
     * Groups the rules into blocks: rules joined with "AND the next rule" form one block,
     * whose text is the text of its last rule.
     *
     * @param array[] $rules Normalised rules.
     * @return array[] List of ['rules' => array[], 'text' => string].
     */
    public static function group_rules(array $rules): array {
        $blocks = [];
        $current = [];
        foreach ($rules as $rule) {
            $current[] = $rule;
            if ($rule['combine'] !== self::COMBINE_AND) {
                $blocks[] = ['rules' => $current, 'text' => $rule['text']];
                $current = [];
            }
        }
        if ($current) {
            $blocks[] = ['rules' => $current, 'text' => end($current)['text']];
        }
        return $blocks;
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
            $content = s(get_string('pluginname', self::COMPONENT));
        }
        if ($renderer) {
            return (string)$renderer->render_content($this, $content);
        }
        return element_helper::render_html_content($this, $content);
    }

    /**
     * Resolves the formatted text to print for the user.
     *
     * In preview mode the text of the first block of rules (or the default text) is shown,
     * so the designer sees representative content regardless of their own data.
     *
     * @param stdClass $user The certificate recipient.
     * @param bool $preview
     * @return string Formatted HTML, or '' when nothing should be printed.
     */
    public function get_content(stdClass $user, bool $preview): string {
        $payload = $this->get_payload();
        $blocks = self::group_rules($this->get_rules());
        $courseid = element_helper::get_courseid($this->get_id());
        $texts = [];

        if ($preview) {
            $texts[] = $blocks[0]['text'] ?? (string)($payload['defaulttext'] ?? '');
        } else {
            $evaluator = new rule_evaluator($courseid, $this->get_customcertid());
            $all = ($payload['matchmode'] ?? '') === self::MATCH_ALL;
            foreach ($blocks as $block) {
                foreach ($block['rules'] as $rule) {
                    if (!$evaluator->matches($rule, $user)) {
                        continue 2;
                    }
                }
                $texts[] = $block['text'];
                if (!$all) {
                    break;
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
     * The certificate activity this element belongs to, or 0 for site templates.
     *
     * @return int
     */
    private function get_customcertid(): int {
        global $DB;

        return (int)$DB->get_field_sql(
            'SELECT c.id
               FROM {customcert} c
               JOIN {customcert_pages} p ON p.templateid = c.templateid
              WHERE p.id = ?',
            [$this->get_pageid()]
        );
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
     * Remap course-specific references (activities, grade items, groups, course badges) after a restore.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $payload = $this->get_payload();
        $rules = self::normalise_rules($payload['rules'] ?? []);
        $changed = false;
        $remap = function (string $itemname, int $oldid) use ($restore, &$changed): int {
            $newid = $oldid ? (int)$restore->get_mappingid($itemname, $oldid) : 0;
            if ($newid) {
                $changed = true;
                return $newid;
            }
            return $oldid;
        };

        foreach ($rules as &$rule) {
            switch ($rule['type']) {
                case rule_evaluator::TYPE_GROUP:
                    $rule['groupid'] = $remap('group', $rule['groupid']);
                    break;
                case rule_evaluator::TYPE_ACTIVITY:
                    $rule['cmid'] = $remap('course_module', $rule['cmid']);
                    break;
                case rule_evaluator::TYPE_BADGE:
                    $rule['badgeid'] = $remap('badge', $rule['badgeid']);
                    break;
                case rule_evaluator::TYPE_GRADE:
                    $source = $rule['gradeitem'];
                    if ($source === rule_evaluator::GRADE_COURSE || $source === '') {
                        break;
                    }
                    $isgradeitem = str_starts_with($source, 'gradeitem:');
                    $oldid = (int)($isgradeitem ? substr($source, 10) : $source);
                    $newid = $remap($isgradeitem ? 'grade_item' : 'course_module', $oldid);
                    $rule['gradeitem'] = ($isgradeitem ? 'gradeitem:' : '') . $newid;
                    break;
            }
        }
        unset($rule);

        if ($changed) {
            $payload['rules'] = $rules;
            $DB->set_field('customcert_elements', 'data', json_encode($payload), ['id' => $this->get_id()]);
        }
    }

    /**
     * Rules shown in the form: the requested example, or the stored rules.
     *
     * @return array[]
     */
    private function get_form_rules(): array {
        return $this->preset['rules'] ?? $this->get_rules();
    }

    /**
     * Returns the example requested through the examples menu.
     *
     * Only honoured when the page is first displayed, never on a form submission,
     * so loading an example cannot overwrite what the designer is typing.
     *
     * @return array|null
     */
    private function get_requested_preset(): ?array {
        global $COURSE;

        $key = optional_param('preset', '', PARAM_ALPHANUMEXT);
        if ($key === '' || optional_param('rule_repeats', null, PARAM_INT) !== null) {
            return null;
        }
        return presets::get($key, (int)$COURSE->id);
    }

    /**
     * Dropdown with the ready-made examples. Each item reloads the form with that example.
     *
     * @return string HTML
     */
    private function render_examples_menu(): string {
        global $PAGE;

        $items = '';
        foreach (presets::KEYS as $key) {
            $url = new \moodle_url($PAGE->url, ['preset' => $key]);
            $label = \html_writer::tag('strong', get_string("preset_{$key}", self::COMPONENT))
                . \html_writer::tag('div', get_string("preset_{$key}_desc", self::COMPONENT), ['class' => 'small text-muted']);
            $items .= \html_writer::tag('li', \html_writer::link($url, $label, [
                'class' => 'dropdown-item text-wrap',
                'data-preset' => $key,
            ]));
        }
        $button = \html_writer::tag('button', get_string('loadexample', self::COMPONENT), [
            'type' => 'button',
            'class' => 'btn btn-outline-primary dropdown-toggle',
            'data-bs-toggle' => 'dropdown',
            'aria-expanded' => 'false',
        ]);
        $menu = \html_writer::tag('ul', $items, [
            'class' => 'dropdown-menu',
            'style' => 'max-width: 28rem; max-height: 24rem; overflow-y: auto;',
        ]);
        $note = $this->preset
            ? \html_writer::div(get_string('exampleloaded', self::COMPONENT), 'alert alert-info mt-2 mb-0')
            : '';
        return \html_writer::div($button . $menu, 'dropdown') . $note;
    }
}
