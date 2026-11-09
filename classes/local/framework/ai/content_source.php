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

namespace gradingform_matrix\local\framework\ai;

use cm_info;
use course_modinfo;

/**
 * Lists the course content an assistant may read, and extracts it as plain text.
 *
 * Adapted from qbank_questiongen's question_generator (ByCS, GPL v3 or later): the same module types, without
 * the image-to-text step for PDFs and images, which core AI does not offer.
 *
 * Only modules visible to the user of the modinfo are listed or read. The description of the activity being
 * graded is always offered, whatever its type.
 *
 * @package    gradingform_matrix
 * @copyright  2026 Marcus Green, based on work by ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_source {
    /** @var string[] module types whose content can be read */
    public const MODULES = ['page', 'label', 'book', 'lesson', 'folder', 'resource'];

    /** @var string[] file types read as text */
    public const MIMETYPES = ['text/plain', 'text/html', 'text/csv'];

    /**
     * Constructor.
     *
     * @param course_modinfo $modinfo the course, as seen by the user the assistant works for
     * @param int $gradedcmid the activity being graded, or 0
     */
    public function __construct(
        /** @var course_modinfo the course, as seen by the user the assistant works for */
        protected readonly course_modinfo $modinfo,
        /** @var int the activity being graded, or 0 */
        protected readonly int $gradedcmid = 0,
    ) {
    }

    /**
     * The modules that can be read, in course order.
     *
     * @return array list of ['cmid' => int, 'name' => string, 'modname' => string, 'type' => string, 'section' => string,
     *     'graded' => bool], where type is the module type's display name
     */
    public function list(): array {
        $list = [];
        foreach ($this->modinfo->get_cms() as $cm) {
            if (!$this->is_readable($cm)) {
                continue;
            }
            $list[] = [
                'cmid' => (int)$cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'type' => (string)$cm->modfullname,
                'section' => get_section_name($this->modinfo->get_course(), $cm->sectionnum),
                'graded' => (int)$cm->id === $this->gradedcmid,
            ];
        }
        // The graded activity comes first, because it is the most relevant source.
        usort($list, fn($a, $b) => (int)$b['graded'] <=> (int)$a['graded']);
        return $list;
    }

    /**
     * Whether a module can be read.
     *
     * @param cm_info $cm
     * @return bool
     */
    public function is_readable(cm_info $cm): bool {
        if (!$cm->uservisible || $cm->deletioninprogress) {
            return false;
        }
        // Visible is not the same as allowed to view: also check the module's own view capability, where it has one.
        $viewcap = "mod/{$cm->modname}:view";
        if (get_capability_info($viewcap) && !has_capability($viewcap, $cm->context, $this->modinfo->get_user_id())) {
            return false;
        }
        if ((int)$cm->id === $this->gradedcmid) {
            return true;
        }
        if ($cm->modname === 'resource') {
            return (bool)$this->text_files($cm, 'mod_resource');
        }
        return in_array($cm->modname, self::MODULES, true);
    }

    /**
     * The text of the given modules, keyed by module name.
     *
     * Modules that cannot be read, or that belong to another course, are skipped.
     *
     * @param int[] $cmids
     * @return array title => plain text
     */
    public function extract_many(array $cmids): array {
        $texts = [];
        foreach ($cmids as $cmid) {
            $cm = $this->modinfo->get_cms()[(int)$cmid] ?? null;
            if (!$cm || !$this->is_readable($cm)) {
                continue;
            }
            $text = $this->extract($cm);
            if ($text !== '') {
                $texts[$cm->get_formatted_name()] = $text;
            }
        }
        return $texts;
    }

    /**
     * The content of a module as plain text.
     *
     * @param cm_info $cm a module for which {@see is_readable()} is true
     * @return string
     */
    public function extract(cm_info $cm): string {
        global $DB;
        $instance = $cm->get_instance_record();
        if ((int)$cm->id === $this->gradedcmid) {
            $html = $instance->intro ?? '';
        } else {
            $html = match ($cm->modname) {
                'page' => $instance->content,
                'label' => $instance->intro,
                'book' => implode('<br><br>', array_map(
                    fn($chapter) => '<h3>' . s($chapter->title) . '</h3>' . $chapter->content,
                    $DB->get_records('book_chapters', ['bookid' => $instance->id, 'hidden' => 0], 'pagenum', 'id, title, content')
                )),
                'lesson' => implode('<br><br>', $DB->get_fieldset('lesson_pages', 'contents', ['lessonid' => $instance->id])),
                'folder' => $this->files_text($this->text_files($cm, 'mod_folder')),
                'resource' => $this->files_text($this->text_files($cm, 'mod_resource')),
                default => '',
            };
        }
        return trim(html_to_text((string)$html, 0, false));
    }

    /**
     * The text files in a module's content area.
     *
     * @param cm_info $cm
     * @param string $component
     * @return \stored_file[]
     */
    protected function text_files(cm_info $cm, string $component): array {
        $files = get_file_storage()->get_area_files(
            $cm->context->id,
            $component,
            'content',
            false,
            'sortorder DESC, id ASC',
            false
        );
        return array_filter($files, fn($file) => in_array($file->get_mimetype(), self::MIMETYPES, true));
    }

    /**
     * The contents of files, joined.
     *
     * @param \stored_file[] $files
     * @return string HTML
     */
    protected function files_text(array $files): string {
        return implode('<br><br>', array_map(
            fn($file) => $file->get_mimetype() === 'text/html' ? $file->get_content() : nl2br(s($file->get_content())),
            $files
        ));
    }
}
