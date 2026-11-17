<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace gradingform_matrix;

use gradingform_matrix\local\framework\display_mode;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/form/matrix/lib.php');
require_once($CFG->dirroot . '/grade/grading/form/matrix/renderer.php');

/**
 * Tests that the matrix renderer runs text filters, such as multi-language content, where text is read.
 *
 * @package    gradingform_matrix
 * @category   test
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\gradingform_matrix_renderer::class)]
final class renderer_test extends \advanced_testcase {
    /** @var string text in English and German, in the multi-language filter's syntax */
    const MULTILANG = '<span lang="en" class="multilang">Good</span><span lang="de" class="multilang">Gut</span>';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Turns a filter on or off for the whole site.
     *
     * @param string $filter
     * @param int $state TEXTFILTER_ON or TEXTFILTER_OFF
     * @return \gradingform_matrix_renderer
     */
    protected function renderer_with_filter(string $filter, int $state): \gradingform_matrix_renderer {
        global $PAGE;
        filter_set_global_state($filter, $state);
        \filter_manager::reset_caches();
        $PAGE->set_context(\context_system::instance());
        return $PAGE->get_renderer('gradingform_matrix');
    }

    public function test_plain_text_shows_the_current_language(): void {
        $renderer = $this->renderer_with_filter('multilang', TEXTFILTER_ON);
        $this->assertSame('Good', $renderer->plain_text(self::MULTILANG));
    }

    public function test_plain_text_without_the_filter_shows_every_language(): void {
        $renderer = $this->renderer_with_filter('multilang', TEXTFILTER_OFF);
        $this->assertSame('GoodGut', $renderer->plain_text(self::MULTILANG));
    }

    public function test_criterion_and_level_show_the_current_language(): void {
        $renderer = $this->renderer_with_filter('multilang', TEXTFILTER_ON);
        $options = \gradingform_matrix_controller::get_default_options();
        $level = ['id' => 11, 'definition' => self::MULTILANG, 'score' => 2, 'checked' => true, 'index' => 1];
        $levelhtml = $renderer->level_template(display_mode::VIEW, $options, 'advancedgrading', 1, $level);
        $criterion = ['id' => 1, 'description' => self::MULTILANG, 'sortorder' => 1, 'class' => ''];
        $value = ['remark' => ''];
        $html = $renderer->criterion_template(display_mode::VIEW, $options, 'advancedgrading', $criterion, $levelhtml, $value);

        $this->assertStringContainsString('Good', $html);
        $this->assertStringNotContainsString('Gut', $html);
    }

    public function test_remark_is_escaped_and_filtered(): void {
        $renderer = $this->renderer_with_filter('emoticon', TEXTFILTER_ON);
        set_config('formats', implode(',', [FORMAT_HTML, FORMAT_MARKDOWN, FORMAT_MOODLE]), 'filter_emoticon');

        $html = $renderer->remark_html("<b>Well</b> done :-)\nSee me");

        $this->assertStringContainsString('&lt;b&gt;Well&lt;/b&gt;', $html);
        $this->assertMatchesRegularExpression('~<br\s*/?>~', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function test_remark_cell_is_filtered(): void {
        $renderer = $this->renderer_with_filter('emoticon', TEXTFILTER_ON);
        set_config('formats', implode(',', [FORMAT_HTML, FORMAT_MARKDOWN, FORMAT_MOODLE]), 'filter_emoticon');
        $options = \gradingform_matrix_controller::get_default_options();
        $criterion = ['id' => 1, 'description' => 'Spelling', 'sortorder' => 1, 'class' => ''];

        $value = ['remark' => 'Nice :-)'];
        $html = $renderer->criterion_template(display_mode::REVIEW, $options, 'advancedgrading', $criterion, '', $value);

        $this->assertMatchesRegularExpression('~<td class="remark"[^>]*>Nice <img~', $html);
    }
}
