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

/**
 *
 * @package     block_navigationbs
 * @copyright   Synergy Learning
 * @author      Gerry G Hall 2016
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_navigationbs_renderer
 */
class block_navigationbs_renderer extends plugin_renderer_base {

    /**
     * @var core_course_renderer
     */
    protected $courserenderer;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courserenderer = $this->page->get_renderer('core_course');
    }

    /**
     * Output all the course sections (topics) as a list
     * @param object $course
     * @param int $opensection (optional)
     * @param int $moduleid (optional)
     * @return string
     */
    public function render_sections($course, $opensection = null, $moduleid = null) {

        $completioninfo = new completion_info($course);
        $modinfo = get_fast_modinfo($course);
        $mods = $modinfo->get_cms();
        $format = course_get_format($course);
        $sections  = $format->get_sections();
        $course = $format->get_course();
        $out = '';
        $active = false;

        // Get a list of modules that have completion criteria.
        $completionmods = array_flip(array_keys($completioninfo->get_activities()));

        $firstsection = true;
        foreach ($sections as $id => $section) {

            $class = '';
            $attrb = [];

            $toggleimage = html_writer::img(
                $this->output->image_url(
                    'collapsed',
                    'block_navigationbs'
                ),
                'collapsed', array('height' => '15px')
            );
            $tick = '';

            // We skip section zero and stealth/orphaned sections.
            if ($section->section == 0 || $section->section > $format->get_last_section_number()) {
                continue;
            }

            $showsection = $section->uservisible || ($section->visible && !$section->available && !empty($section->availableinfo));

            // If the section is not available and is not visible to the user.
            if (!$showsection) {

                // If the section is visible though is visible.
                if ($section->available && !$course->hiddensections) {
                    $out .= $this->dimmed_section($course->id, $section);
                }
                // Ok the section is hidden exclude it.
                continue;

            } else {  // The section is available.

                // Get the section modules and the completion status.
                $sectionmodules = $this->sectionmodules(
                    $modinfo->sections,
                    $section,
                    $completioninfo,
                    $completionmods,
                    $mods,
                    $moduleid
                );

                if ($sectionmodules->completion == 1) {
                    $class .= ' completed clearfix';
                    $tick = html_writer::img($this->output->image_url('i/valid'), 'completed', ['class' => 'completed pull-right'] );
                }
                // Find out if this is the currently view section either by it's view status or it's.
                if (($opensection === null && $firstsection) || ($opensection == $id && !$active && $section->uservisible)) {

                    $active = true;
                    $class .= ' expanded';
                    $toggleimage = html_writer::img(
                        $this->output->image_url(
                            'expanded',
                            'block_navigationbs'
                        ),
                        'expanded', array('height' => '15px')
                    );

                } else {
                    // Hide the containing div completely.
                    $attrb['style'] = ' display: none;';
                }

                // Helper class to ascertain if the section has activities.
                if ($sectionmodules->modules == '') {

                    $class .= ' empty';
                }

                $formattedinfo = '';
                $ci = new \core_availability\info_section($section);
                $fullinfo = $ci->get_full_information();
                if ($fullinfo) {
                    $formatted = \core_availability\info::format_info(
                        $fullinfo, $section->course);
                    $formattedinfo .= html_writer::div($formatted, 'availabilityinfo');
                }

                // Get the sections title.
                $title = $format->get_section_name($section);

                $sectiontitle = html_writer::link (
                    $format->get_view_url ($section),
                    $title,
                    ['title' => $title]
                );

                $out .= html_writer::start_div ("section $class");
                $out .= html_writer::start_div ("sectionheader");
                $out .= html_writer::span($toggleimage, 'chevron');
                $out .= $sectiontitle;
                $out .= $tick;
                $out .= html_writer::end_div ();
                $out .= html_writer::start_div ("sectionbody", $attrb);
                $out .= $formattedinfo;
                $out .= $sectionmodules->modules;
                $out .= html_writer::end_div ();
                $out .= html_writer::end_div ();
            }
            $firstsection = false;
        }

        return $out;
    }

    /**
     * @param object $course
     * @param object $section
     * @return string
     */
    public function dimmed_section($course, $section) {

        $sectionname = get_section_name($course, $section);
        $strnotavailable = get_string('notavailablecourse', '', $sectionname);

        $out = html_writer::start_div ("section dimmed_text");
        $out .= html_writer::start_div ("sectionheader");

        $out .= html_writer::span(
            html_writer::img($this->output->image_url('collapsed', 'block_navigationbs'), 'collapsed', array('height' => '15px'))
            , 'chevron');
        $out .= $sectionname;
        $out .= html_writer::end_div ();
        $out .= html_writer::start_div ("sectionbody", ['style' => ' display: none;']);
        $out .= $strnotavailable;
        $out .= html_writer::end_div ();
        $out .= html_writer::end_div ();
        return $out;
    }

    /**
     * @param array $sections
     * @param \section_info $section
     * @param \completion_info $completioninfo
     * @param array $completionmods
     * @param array $mods
     * @param int $moduleid (optional)
     * @return object
     */
    protected function sectionmodules($sections, \section_info $section, \completion_info $completioninfo, $completionmods,
        $mods, $moduleid = null) {
        global $USER;

        // Count the activities that have completion enabled in this section.
        $secmodscomp = 0;

        // Count the completed activities in this section.
        $secmodscomped = 0;

        // The output of this function.
        $out = '';

        if (isset($sections[$section->section])) {

            $out = html_writer::start_tag ('ul');

            foreach ($sections[$section->section] as $modid) {

                // The li element attribute.
                $secattrb = [];

                if (isset($completionmods[$modid])) {

                    // If the Module is not visible to the user then don't include it.
                    $showmod = $mods[$modid]->uservisible ||
                               ($mods[$modid]->visible && !$mods[$modid]->available && !empty($mods[$modid]->availableinfo));

                    if (!$showmod && $mods[$modid]->available) {
                        unset($completionmods[$modid]);
                        continue;
                    }

                    $secmodscomp ++;
                    $modcomp = $completioninfo->get_data ($mods[$modid], false, $USER->id);

                    if ($modcomp->completionstate == COMPLETION_COMPLETE || $modcomp->completionstate == COMPLETION_COMPLETE_PASS) {

                        $secmodscomped ++;
                        $secattrb['class'] = ' completion_complete';

                        if ($confcolour = get_config('block_navigationbs', 'completion_colour')) {
                            $secattrb['style'] = " background-color: $confcolour;";
                        }

                    } else if ($modcomp->completionstate == COMPLETION_COMPLETE_FAIL) {

                        $secattrb['class'] = ' completion_fail';
                    } else {
                        $secattrb['class'] = ' completion_imcomplete';
                    }
                }

                if ($activity = $this->render_coursemodule($mods[$modid])) {

                    if ($moduleid == $modid) {
                        $secattrb['class'] = ' bold';
                    }

                    $out .= html_writer::start_tag ('li', $secattrb);
                    $out .= $activity;
                    $out .= html_writer::end_tag ('li');

                }

            }

            $out .= html_writer::end_tag ('ul');
        }
        $return = ['complete' => false, 'modules' => $out ];

        // If section module completion count is larger then 0 then this section has completable activities.
        if ($secmodscomp > 0) {

            // If section module completion count is equal to section module completed count the the sum of both is larger then 0.
            // ... then the section is completed.
            if (($secmodscomp == $secmodscomped) &&  ($secmodscomp + $secmodscomped) != 0) {
                $return['completion'] = 1;
            } else {
                $return['completion'] = 0;
            }

        } else {
            $return['completion'] = -1;
        }

        // Return if the section completion state and the section rendered activities.
        return (object)$return;
    }

    /**
     * Render element of navigation menu
     * @param \cm_info $mod
     * @return bool|string
     */
    public function render_coursemodule(cm_info $mod) {
        global $CFG;

        $output = false;

        if (plugin_supports('mod', $mod->modname, FEATURE_NO_VIEW_LINK, false)) {
            return false;
        }

        if ($mod->url) {

            // Set the old and new icon variables to the current value.
            $icon = $oldicon = $mod->get_icon_url();
            // Find out if we have a incon to override with.
            if (file_exists($CFG->dirroot."/blocks/navigationbs/pix/mod/{$mod->modname}")) {
                $icon = $this->output->image_url("mod/{$mod->modname}/icon", 'block_navigationbs');
            } else if ($mod->name == 'file') {// If not is the mod name file.
                $icon = new moodle_url(str_replace('core', 'block_navigationbs', $oldicon->get_path()));
            }
            // Set the new icon.
            $mod->set_icon_url($icon);
            $output = $this->courserenderer->course_section_cm_name($mod);
            // Reset the Icon.
            $mod->set_icon_url($oldicon);

        }

        return $output;
    }
}
