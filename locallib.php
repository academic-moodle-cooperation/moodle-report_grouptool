<?php
// This file is part of report_grouptool for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/report/grouptool/lib.php');
require_once($CFG->dirroot.'/mod/grouptool/definitions.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/report/grouptool/lib.php');
require_once($CFG->dirroot.'/group/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_grade.php');
require_once($CFG->libdir.'/pdflib.php');

/**
 * This class helps to show reports for individual grouptools
 * @author Anne Kreppenhofer
 * @package report_grouptool
 * @copyright 2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_grouptool {

    /** @var object */
    protected $cm;
    /** @var object */
    protected $course;
    /** @var object */
    protected $grouptool;
    /** @var object instance's context record */
    protected $context;

    /**
     * filter all groups
     */
    const FILTER_ALL = 0;
    /**
     * filter active groups
     */
    const FILTER_ACTIVE = 1;
    /**
     * filter inactive groups
     */
    const FILTER_INACTIVE = 2;

    /**
     * NAME_TAGS - the tags available for grouptool's group naming schemes
     */
    const NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

    /**
     * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
     */
    const HIDE_GROUPMEMBERS = GROUPTOOL_HIDE_GROUPMEMBERS;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
     */
    const SHOW_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
     */
    const SHOW_OWN_GROUPMEMBERS_AFTER_DUE = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
    /**
     * SHOW_OWN_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
     */
    const SHOW_OWN_GROUPMEMBERS_AFTER_REG = GROUPTOOL_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
    /**
     * SHOW_GROUPMEMBERS - show groupmembers no matter what...
     */
    const SHOW_GROUPMEMBERS = GROUPTOOL_SHOW_GROUPMEMBERS;

    /**
     * Constructor for the grouptool class
     *
     * If cmid is set create the cm, course, checkmark objects.
     *
     * @param int $cmid the current course module id - not set for new grouptools
     * @param stdClass $grouptool usually null, but if we have it we pass it to save db access
     * @param stdClass $cm usually null, but if we have it we pass it to save db access
     * @param stdClass $course usually null, but if we have it we pass it to save db access
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($cmid, $grouptool=null, $cm=null, $course=null) {
        global $DB;

        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }
        if (!empty($cm)) {
            $this->cm = $cm;
        } else if (!$this->cm = get_coursemodule_from_id('grouptool', $cmid)) {
            print_error('invalidcoursemodule');
        }
        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if (!$this->course = $DB->get_record('course', ['id' => $this->cm->course])) {
            print_error('invalidid', 'grouptool');
        }

        if ($grouptool) {
            $this->grouptool = $grouptool;
        } else if (! $this->grouptool = $DB->get_record('grouptool',
            ['id' => $this->cm->instance])) {
            print_error('invalidid', 'grouptool');
        }

        $this->grouptool->cmidnumber = $this->cm->idnumber;
        $this->grouptool->course   = $this->course->id;
    }
    /**
     * view userlist tab
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function view_userlist() {
        global $PAGE;

        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);

        $url = new moodle_url($PAGE->url, [
            'sesskey'     => sesskey(),
            'groupid'     => $groupid,
            'groupingid'  => $groupingid,
            'orientation' => $orientation,
        ]);

        $groupings = groups_get_all_groupings($this->course->id);
        $options = [0 => get_string('all')];
        if (count($groupings)) {
            foreach ($groupings as $grouping) {
                $options[$grouping->id] = $grouping->name;
            }
        }
        // $this->get_paramter_dropdowns($url,$groupingid,$groupid,$orientation);
        flush();

        $this->userlist_table($groupingid, $groupid);
    }

    /**
     * Returns the dropdown for each download option
     * @param string $url Url of the download options
     * @param moodle_url $downloadurl Url of the Download link
     * @return void
     * @throws coding_exception
     */
    protected function get_download_dropdown($url, $downloadurl) {
        global $OUTPUT;
        // <button type="button" class="btn btn-primary">Primary</button>
        $downloadselect = $this->get_download_select($url);
        echo html_writer::tag('div', get_string('download_options', 'report_grouptool').'&nbsp;'.
            $OUTPUT->render($downloadselect).'&nbsp;'.
            html_writer::tag('a', get_string('download', 'report_grouptool'),
                ['class' => 'btn btn-primary align-baseline', 'type' => 'button', 'href' => $downloadurl]),
            ['class' => 'centered grouptool_userlist_download']);
        echo $OUTPUT->box(" ");

    }
    /**
     * Returns a single select to change currently selected page-orientation.
     *
     * @param moodle_url $url Base URL to use
     * @return single_select
     * @throws coding_exception
     */
    protected function get_download_select($url) {
        static $options = null;
        if (!$options) {
            $options = [
                GROUPTOOL_TXT => get_string('download_txt', 'report_grouptool'),
                GROUPTOOL_XLSX => get_string('download_xlsx', 'report_grouptool'),
                GROUPTOOL_PDF => get_string('download_pdf', 'report_grouptool'),
                GROUPTOOL_ODS => get_string('download_ods', 'report_grouptool'),
            ];
        }
        $param = optional_param('format', GROUPTOOL_TXT, PARAM_INT);
        return new single_select($url, 'format', $options, $param, false);
    }
    /**
     * gets data about active groups for this instance or all instances if ignoregtinstance is set
     *
     * @param bool $includeregs optional include registered users in returned object
     * @param bool $includequeues optional include queued users in returned object
     * @param int $agrpid optional filter by a single active-groupid from {grouptool_agrps}.id
     * @param int $groupid optional filter by a single group-id from {groups}.id
     * @param int $groupingid optional filter by a single grouping-id
     * @param bool $indexbygroup optional index returned array by {groups}.id
     *                                    instead of {grouptool_agrps}.id
     * @param bool $includeinactive optional include also inactive groups - despite the method being called get_active_groups()!
     * @param bool $ignoregtinstance If true gets active groups from all grouptool instances and not only from this instance
     * @return array of objects containing all necessary information about chosen active groups
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_active_groups($includeregs=false, $includequeues=false, $agrpid=0, $groupid=0, $groupingid=0,
                                      $indexbygroup=true, $includeinactive = false, $ignoregtinstance = false) {
        global $DB;
        require_capability('report/grouptool:view_groups', $this->context);

        if (!$ignoregtinstance) {
            $params = ['grouptoolid' => $this->cm->instance];
        }
        if (!empty($agrpid)) {
            $agrpidwhere = " AND agrp.id = :agroup";
            $params['agroup'] = $agrpid;
        } else {
            $agrpidwhere = "";
        }
        if (!empty($groupid)) {
            $groupidwhere = " AND grp.id = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupidwhere = "";
        }
        if (!empty($groupingid)) {
            $groupingidwhere = " AND grpgs.id = :groupingid";
            $params['groupingid'] = $groupingid;
        } else {
            $groupingidwhere = "";
        }

        if (!empty($this->grouptool->use_size)) {
            if (false) {
                $sizesql = " ".$this->grouptool->grpsize." grpsize,";
            } else {
                $grouptoolgrpsize = get_config('report_grouptool', 'grpsize');
                $grpsize = (!empty($this->grouptool->grpsize) ? $this->grouptool->grpsize : $grouptoolgrpsize);
                if (empty($grpsize)) {
                    $grpsize = 3;
                }
                $sizesql = " COALESCE(agrp.grpsize, ".$grpsize.") AS grpsize,";
            }
        } else {
            $sizesql = "";
        }
        if ($indexbygroup) {
            $idstring = "grp.id AS id, agrp.id AS agrpid";
        } else {
            $idstring = "agrp.id AS agrpid, grp.id AS id";
        }

        if (!$includeinactive) {
            $active = " AND agrp.active = 1 ";
        } else {
            $active = "";
        }

        $groupdata = null;
        if ($ignoregtinstance) {
            $groupdata = $DB->get_records_sql("
                   SELECT ".$idstring.", MAX(grp.name) AS name, MAX(grp.description) AS description,".$sizesql." MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE 1=1".$active.
                $agrpidwhere.$groupidwhere.$groupingidwhere."
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        } else {
            $params['grouptoolid1'] = $params['grouptoolid'];
            $groupdata = $DB->get_records_sql("
                   SELECT ".$idstring.", MAX(grp.name) AS name, MAX(grp.description) AS description,".$sizesql." MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {grouptool_agrps} agrp ON agrp.groupid = grp.id AND agrp.grouptoolid = :grouptoolid
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE agrp.grouptoolid = :grouptoolid1 ".$active.
                $agrpidwhere.$groupidwhere.$groupingidwhere."
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        }
        if (!empty($groupdata)) {
            foreach ($groupdata as $key => $group) {
                $groupingids = $DB->get_fieldset_select('groupings_groups',
                    'groupingid',
                    'groupid = ?',
                    [$group->id]);
                if (!empty($groupingids)) {
                    $groupdata[$key]->classes = implode(',', $groupingids);
                } else {
                    $groupdata[$key]->classes = '';
                }
            }
            if ((!empty($this->grouptool->use_size))
                || ($includequeues)
                || ($includeregs)) {
                $keys = array_keys($groupdata);
                foreach ($keys as $key) {
                    $groupdata[$key]->queued = null;
                    if ($includequeues) {
                        $attr = ['agrpid' => $groupdata[$key]->agrpid];
                        $groupdata[$key]->queued = (array)$DB->get_records('grouptool_queued', $attr);
                    }

                    $groupdata[$key]->registered = null;
                    if ($includeregs) {
                        $params = ['agrpid' => $groupdata[$key]->agrpid];
                        $where = "agrpid = :agrpid AND modified_by >= 0";
                        $groupdata[$key]->registered = $DB->get_records_select('grouptool_registered',
                            $where, $params);
                        $params['modifierid'] = -1;
                        $where = "agrpid = :agrpid AND modified_by = :modifierid";
                        $groupdata[$key]->marked = $DB->get_records_select('grouptool_registered',
                            $where, $params);
                        $groupdata[$key]->moodle_members = groups_get_members($groupdata[$key]->id);
                    }
                }
                unset($key);
            }
        } else {
            $groupdata = [];
        }

        return $groupdata;
    }
    /**
     * get all data necessary for displaying/exporting userlist table
     *
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group (groupid not agroupid!)
     * @param bool $onlydata optional return object with raw data not html-fragment-string
     * @return stdClass[]|bool true if table is output or raw data as array of objects
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function userlist_table($groupingid = 0, $groupid = 0, $onlydata = false) {
        global $OUTPUT, $CFG, $DB, $PAGE, $SESSION;
        $useridentityfields = self::get_useridentity_fields();

        if (!isset($SESSION->report_grouptool->userlist)) {
            $SESSION->report_grouptool->userlist = new stdClass();
        }
        // Handles order direction!
        if (!isset($SESSION->report_grouptool->userlist->orderby)) {
            $SESSION->report_grouptool->userlist->orderby = [];
        }
        $orderby = $SESSION->report_grouptool->userlist->orderby;
        if ($tsort = optional_param('tsort', 0, PARAM_ALPHANUM)) {
            // SHOW RESET button
            if ($tsort != 'reset') {
                $resetbutton = html_writer::link(new moodle_url($PAGE->url, ['tsort' => 'reset']), get_string('resettable'));
                echo $OUTPUT->container($resetbutton, $classes = "resettable mdl-right");
            }
            $olddir = 'DESC';
            if (key_exists($tsort, $orderby)) {
                $olddir = $orderby[$tsort];
                unset($orderby[$tsort]);
            }
            if($tsort == 'reset'){
                $tsort = 'lastname';
                $olddir = 'DESC';
            }
            // Insert as first element and rebuild!
            $oldorderby = array_keys($orderby);
            $oldorderdir = array_values($orderby);
            array_unshift($oldorderby, $tsort);
            array_unshift($oldorderdir, (($olddir == 'DESC') ? 'ASC' : 'DESC'));
            $orderby = array_combine($oldorderby, $oldorderdir);
            $SESSION->report_grouptool->userlist->orderby = $orderby;
        }

        // Handles collapsed columns!
        if (!isset($SESSION->report_grouptool->userlist->collapsed)) {
            $SESSION->report_grouptool->userlist->collapsed = [];
        }
        $collapsed = $SESSION->report_grouptool->userlist->collapsed;
        if ($thide = optional_param('thide', 0, PARAM_ALPHANUM)) {
            if (!in_array($thide, $collapsed)) {
                array_push($collapsed, $thide);
            }
            $SESSION->report_grouptool->userlist->collapsed = $collapsed;
        }
        if ($tshow = optional_param('tshow', 0, PARAM_ALPHANUM)) {
            foreach ($collapsed as $key => $value) {
                if ($value == $tshow) {
                    unset($collapsed[$key]);
                }
            }
            $SESSION->report_grouptool->userlist->collapsed = $collapsed;
        }

        $downloadurl = '';
        if (!$onlydata) {
            flush();
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/report/grouptool/download.php?tab=userlist',
                [
                    'id'          => $this->cm->id,
                    'groupingid'  => $groupingid,
                    'groupid'     => $groupid,
                    'orientation' => $orientation,
                    'sesskey'     => sesskey(),
                ]);
        }

        // Get all ppl that are allowed to register!
        list($esql, $params) = get_enrolled_sql($this->context, 'report/grouptool:register');
        $sql = "SELECT u.id
                  FROM {user} u
             LEFT JOIN ($esql) eu ON eu.id=u.id
                 WHERE u.deleted = 0 AND eu.id=u.id ";
        $groups = groups_get_all_groups($this->course->id, 0, $groupingid);
        if (!empty($groupingid) && !empty($groups)) {
            // Get all groupings groups!
            $ufields = $mainuserfields = \core_user\fields::for_userpic()->including(
                'idnumber')->get_sql('u', false, '', '', false)->selects;
            $groupingusers = groups_get_grouping_members($groupingid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupingusers)) {
                $groupingusers = [];
            } else {
                $groupingusers = array_keys($groupingusers);
            }
            list($groupssql, $groupsparams) = $DB->get_in_or_equal(array_keys($groups));
            $groupingusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid ".$groupssql, $groupsparams);
            $groupingusers = array_merge($groupingusers, $groupingusers2);
            if (empty($groupingusers)) {
                $userssql = " = :groupingparam";
                $groupingparams = ['groupingparam' => -1];
            } else {
                list($userssql, $groupingparams) = $DB->get_in_or_equal($groupingusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupingparams);
        }
        if (!empty($groupid)) {
            // Same as with groupingid but just with 1 group!
            // Get all group members!
            $ufields = $mainuserfields = \core_user\fields::for_userpic()->including(
                'idnumber')->get_sql('u', false, '', '', false)->selects;
            $groupusers = groups_get_members($groupid, 'DISTINCT u.id, '.$ufields);
            if (empty($groupusers)) {
                $groupusers = [];
            } else {
                $groupusers = array_keys($groupusers);
            }
            $groupusers2 = $DB->get_fieldset_sql("
            SELECT DISTINCT u.id
              FROM {user} u
         LEFT JOIN {grouptool_registered} reg ON u.id = reg.userid AND reg.modified_by >= 0
         LEFT JOIN {grouptool_queued} queue ON u.id = queue.userid
         LEFT JOIN {grouptool_agrps} agrp ON reg.agrpid = agrp.id OR queue.agrpid = agrp.id
             WHERE agrp.groupid = ?", [$groupid]);
            $groupusers = array_merge($groupusers, $groupusers2);
            if (empty($groupusers)) {
                $userssql = " = :groupparam";
                $groupparams = ['groupparam' => -1];
            } else {
                list($userssql, $groupparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            }
            // Extend sql to only include people registered in moodle-group/grouptool-group or queued in grouptool group!
            $sql .= " AND u.id ".$userssql;
            $params = array_merge($params, $groupparams);
        }
        $users = $DB->get_records_sql($sql, $params);

        if (!$onlydata) {
            $format = optional_param('format', GROUPTOOL_TXT, PARAM_INT);
            $url = new moodle_url($PAGE->url, [
                'sesskey'     => sesskey(),
            ]);
            echo $this->get_download_dropdown($url, new moodle_url($downloadurl, ['format' => $format]));
            flush();
        }

        if (!empty($users)) {
            $users = array_keys($users);
            $userdata = $this->get_user_data($groupingid, $groupid, $users, $orderby, $onlydata);
        } else {
            if (!$onlydata) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('no_users_to_display', 'report_grouptool'),
                    \core\output\notification::NOTIFY_ERROR), 'centered generalbox');
            } else {
                return get_string('no_users_to_display', 'report_grouptool');
            }
        }
        $groupinfo = $this->get_active_groups(false, false, 0, $groupid, $groupingid,
            false);

        // We create a dummy user-object to get the fullname-format!
        $dummy = new stdClass();
        $namefields = \core_user\fields::for_name()->get_required_fields();
        foreach ($namefields as $namefield) {
            $dummy->$namefield = $namefield;
        }
        $fullnameformat = fullname($dummy);
        // Now get the ones used in fullname in the correct order!
        $namefields = order_in_string($namefields, $fullnameformat);

        $head = [];
        $rows = [];

        if (!$onlydata) {
            echo html_writer::start_tag('table',
                ['class' => 'centeredblock userlist table table-striped table-hover table-condensed']);

            echo html_writer::start_tag('thead');
            echo html_writer::start_tag('tr');
            echo html_writer::tag('th', $this->collapselink('picture', $collapsed), ['class' => '']);
            flush();
            if (!in_array('fullname', $collapsed)) {
                $firstnamelink = html_writer::link(new moodle_url($PAGE->url,
                    ['tsort' => 'firstname']),
                    get_string('firstname').
                    $this->pic_if_sorted($orderby, 'firstname'));
                $surnamelink = html_writer::link(new moodle_url($PAGE->url,
                    ['tsort' => 'lastname']),
                    get_string('lastname').
                    $this->pic_if_sorted($orderby, 'lastname'));
                $fullname = html_writer::tag('div', get_string('fullname').
                    html_writer::empty_tag('br').
                    $firstnamelink.'&nbsp;/&nbsp;'.$surnamelink);
                echo html_writer::tag('th', $fullname.$this->collapselink('fullname', $collapsed),
                    ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('fullname', $collapsed), ['class' => '']);
            }

            foreach ($useridentityfields as $identifier => $text) {
                if (!in_array($identifier, $collapsed)) {
                    $idnumberlink = html_writer::link(new moodle_url($PAGE->url,
                        ['tsort' => $identifier]),
                        $text.
                        $this->pic_if_sorted($orderby, $identifier));
                    echo html_writer::tag('th', $idnumberlink.$this->collapselink($identifier, $collapsed),
                        ['class' => '']);
                } else {
                    echo html_writer::tag('th', $this->collapselink($identifier, $collapsed), ['class' => '']);
                }
            }
            if (!in_array('registrations', $collapsed)) {
                $registrationslink = get_string('registrations', 'report_grouptool');
                echo html_writer::tag('th', $registrationslink.
                    $this->collapselink('registrations', $collapsed), ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('registrations', $collapsed), ['class' => '']);
            }
            if (!in_array('queues', $collapsed)) {
                $queueslink = get_string('queues', 'report_grouptool').' ('.get_string('rank',
                        'report_grouptool').')';
                echo html_writer::tag('th', $queueslink.
                    $this->collapselink('queues', $collapsed), ['class' => '']);
            } else {
                echo html_writer::tag('th', $this->collapselink('queues', $collapsed), ['class' => '']);
            }
            echo html_writer::end_tag('tr');
            echo html_writer::end_tag('thead');
        } else {
            $head = ['name'          => get_string('fullname')];
            foreach ($namefields as $namefield) {
                $head[$namefield] = \core_user\fields::get_display_name($namefield);
            }
            if (empty($CFG->showuseridentity)) {
                $head['idnumber'] = \core_user\fields::get_display_name('idnumber');
            } else {
                $fields = explode(',', $CFG->showuseridentity);
                foreach ($fields as $field) {
                    $head[$field] = \core_user\fields::get_display_name($field);
                }
            }
            $head['idnumber'] = \core_user\fields::get_display_name('idnumber');
            $head['email']         = \core_user\fields::get_display_name('email');
            $head['registrations'] = get_string('registrations', 'report_grouptool');
            $head['queues']        = get_string('queues', 'report_grouptool').' ('.get_string('rank',
                    'report_grouptool').')';
        }

        if (!$onlydata) {
            echo html_writer::start_tag('tbody');
        }
        if (!empty($userdata)) {
            core_php_time_limit::raise(5 * count($userdata));
            foreach ($userdata as $key => $user) {
                if (!$onlydata) {
                    echo html_writer::start_tag('tr', ['class' => '']);

                    $userlink = new moodle_url($CFG->wwwroot.'/user/view.php', [
                        'id'     => $user->id,
                        'course' => $this->course->id,
                    ]);
                    if (!in_array('picture', $collapsed)) {
                        $picture = html_writer::link($userlink, $OUTPUT->user_picture($user));
                        echo html_writer::tag('td', $picture, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    if (!in_array('fullname', $collapsed)) {
                        $fullname = html_writer::link($userlink, fullname($user));
                        echo html_writer::tag('td', $fullname, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    // Print all activated useridentityvalue infos.
                    foreach ($useridentityfields as $identifier => $value) {
                        if (!in_array($identifier, $collapsed)) {
                            $identifier = strtolower($identifier);
                            $identityvalue = $user->$identifier;
                            echo html_writer::tag('td', $identityvalue, ['class' => '']);
                        } else {
                            $this->print_empty_cell();
                        }
                    }
                    if (!in_array('registrations', $collapsed)) {
                        if (!empty($user->regs)) {
                            $registrations = [];
                            foreach ($user->regs as $reg) {
                                $grouplink = new moodle_url($PAGE->url, [
                                    'tab'     => 'overview',
                                    'groupid' => $groupinfo[$reg]->id,
                                ]);
                                $registrations[] = html_writer::link($grouplink, $groupinfo[$reg]->name);
                            }
                        } else {
                            $registrations = ['-'];
                        }
                        $registrations = implode(html_writer::empty_tag('br'), $registrations);
                        echo html_writer::tag('td', $registrations, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    if (!in_array('queues', $collapsed)) {
                        if (!empty($user->queued)) {
                            $queueentries = [];
                            foreach ($user->queued as $queue) {
                                $grouplink = new moodle_url($PAGE->url, [
                                    'tab'     => 'overview',
                                    'groupid' => $groupinfo[$queue]->id,
                                ]);
                                $groupdata = $this->get_active_groups(false, true, $queue);
                                $groupdata = current($groupdata);
                                $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                                $groupdata = null;
                                unset($groupdata);
                                if (empty($rank)) {
                                    $rank = '*';
                                }
                                $queueentries[] = html_writer::link($grouplink, $groupinfo[$queue]->name." (#".$rank.")");
                            }
                        } else {
                            $queueentries = ['-'];
                        }
                        $queueentries = implode(html_writer::empty_tag('br'), $queueentries);
                        echo html_writer::tag('td', $queueentries, ['class' => '']);
                    } else {
                        $this->print_empty_cell();
                    }
                    echo html_writer::end_tag('tr');
                    flush();
                    $picture = null;
                    unset($picture);
                    $fullname = null;
                    unset($fullname);
                    $idnumber = null;
                    unset($idnumber);
                    $email = null;
                    unset($email);
                    $registrations = null;
                    unset($registrations);
                    $queueentries = null;
                    unset($queueentries);
                } else {
                    $row = [];
                    $row['name'] = fullname($user);

                    foreach ($namefields as $namefield) {
                        $row[$namefield] = $user->$namefield;
                        $user->namefield = null;
                        unset($user->namefield);
                    }
                    $row['idnumber'] = $user->idnumber;
                    $row['email'] = $user->email;
                    if (empty($CFG->showuseridentity)) {
                        $row['idnumber'] = $user->idnumber;
                        $user->idnumber = null;
                        unset($user->idnumber);
                        $row['email'] = $user->email;
                        $user->email = null;
                        unset($user->email);
                    } else {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $field = strtolower($field);
                            $row[$field] = $user->$field;
                            $user->$field = null;
                            unset($user->$field);
                        }
                    }
                    if (!empty($user->regs)) {
                        $registrations = [];
                        foreach ($user->regs as $reg) {
                            $registrations[] = $groupinfo[$reg]->name;
                        }
                        $row['registrations'] = $registrations;
                    } else {
                        $row['registrations'] = [];
                    }
                    $user->regs = null;
                    unset($user->regs);
                    if (!empty($user->queued)) {
                        $queueentries = [];
                        foreach ($user->queued as $queue) {
                            $groupdata = $this->get_active_groups(false, true, $queue);
                            $groupdata = current($groupdata);
                            $rank = $this->get_rank_in_queue($groupdata->queued, $user->id);
                            if (empty($rank)) {
                                $rank = '*';
                            }
                            $queueentries[] = [
                                'rank' => $rank,
                                'name' => $groupinfo[$queue]->name,
                            ];
                        }
                        $row['queues'] = $queueentries;
                    } else {
                        $row['queues'] = [];
                    }
                    $user->queues = null;
                    unset($user->queues);
                    $rows[] = $row;
                    $row = null;
                    unset($row);
                }
            }
        }
        if (!$onlydata) {
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        } else {
            return array_merge([$head], $rows);
        }

        return true;
    }
    /**
     * Get showuseridentity itentifiers and their display text on the current instance
     *
     * @return array Identifiers in showuseridentity and their display names
     * @throws coding_exception
     */
    public static function get_useridentity_fields() {
        global $CFG;
        $useridentityfields = explode(',', $CFG->showuseridentity);

        // Set default values to idnumber and email in no showuseridentity setting is given.
        if (empty($useridentityfields)) {
            $useridentityfields = ['idnumber', 'email'];
        }

        $useridentity = [];
        foreach ($useridentityfields as $identifier) {
            $useridentity[$identifier] = \core_user\fields::get_display_name($identifier);
        }
        return $useridentity;
    }
    /**
     * get information about particular users with their registrations/queues
     *
     * @param int $groupingid optional get only this grouping
     * @param int $groupid optional get only this group
     * @param int|array $userids optional get only this user(s)
     * @param stdClass[] $orderby array how data should be sorted (column as key and ASC/DESC as value)
     * @param bool $isdownloading Indicates if the function is called from a download, muting all output
     * @return stdClass[] array of objects records from DB with all necessary data
     * @throws coding_exception
     * @throws dml_exception
     * @throws required_capability_exception
     */
    public function get_user_data($groupingid = 0, $groupid = 0, $userids = 0, $orderby = [], $isdownloading = false) {
        global $DB, $OUTPUT;

        // After which table-fields can we sort?
        $sortable = ['firstname', 'lastname'];
        // Add instance specific useridentity fields.
        $sortable = array_merge($sortable, array_keys(self::get_useridentity_fields()));

        // Indexed by agrpid!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid, false);
        $agrpids = array_keys($agrps);
        if (!empty($agrpids)) {
            list($agrpsql, $agrpparams) = $DB->get_in_or_equal($agrpids);
        } else {
            $agrpsql = '';
            $agrpparams = [];
            if (!$isdownloading) {
                echo $OUTPUT->box($OUTPUT->notification(get_string('no_groups_to_display', 'report_grouptool'),
                    \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
            }
        }

        if (!empty($userids)) {
            if (!is_array($userids)) {
                $userids = [$userids];
            }
            list($usersql, $userparams) = $DB->get_in_or_equal($userids);
        } else {
            $usersql = ' LIKE *';
            $userparams = [];
        }

        $extrauserfields = \core_user\fields::for_identity($this->context)->get_sql('u');
        $mainuserfields = \core_user\fields::for_userpic()->including('idnumber', 'email')->get_sql('u',
            false, '', '', false)->selects;
        $orderbystring = "";
        if (!empty($orderby)) {
            foreach ($orderby as $field => $direction) {
                if (in_array($field, $sortable)) {
                    if ($orderbystring != "") {
                        $orderbystring .= ", ";
                    } else {
                        $orderbystring .= " ORDER BY";
                    }
                    $orderbystring .= " ".$field." ".
                        ((!empty($direction) && $direction == 'ASC') ? 'ASC' : 'DESC');
                } else {
                    unset($orderby[$field]);
                }
            }
        }
        $extrauserfieldsselects = $extrauserfields->selects;
        $extrauserfieldsfrom = $extrauserfields->joins;
        $sql = "SELECT $mainuserfields $extrauserfieldsselects ".
            "FROM {user} u $extrauserfieldsfrom".
            "WHERE u.id ".$usersql.
            $orderbystring;
        $params = array_merge($extrauserfields->params, $userparams);
        // $params = array_merge($params, $extrauserfields->params);

        $data = $DB->get_records_sql($sql, $params);

        // Add reg and queue data...
        if (!empty($agrpsql)) {
            foreach ($data as &$cur) {
                $sql = "SELECT agrps.id
                          FROM {grouptool_registered} regs
                     LEFT JOIN {grouptool_agrps}      agrps ON regs.agrpid = agrps.id
                     LEFT JOIN {groups}               grps  ON agrps.groupid = grps.id
                         WHERE regs.modified_by >= 0
                               AND regs.userid = ?
                               AND regs.agrpid ".$agrpsql;
                $params = array_merge([$cur->id], $agrpparams);
                $cur->regs = $DB->get_fieldset_sql($sql, $params);
                $sql = "SELECT agrps.id
                          FROM {grouptool_queued} queued
                     LEFT JOIN {grouptool_agrps}  agrps ON queued.agrpid = agrps.id
                     LEFT JOIN {groups}           grps  ON agrps.groupid = grps.id
                         WHERE queued.userid = ?
                               AND queued.agrpid ".$agrpsql;
                $params = array_merge([$cur->id], $agrpparams);
                $cur->queued = $DB->get_fieldset_sql($sql, $params);
            }
        }

        return $data;
    }
    /**
     * returns collapselink (= symbol to show column or column-name and symbol to hide column)
     *
     * @param string $search column-name to print link for
     * @param string[] $collapsed array with collapsed columns
     * @return string html-fragment with icon to show column or column header text with icon to hide
     *                              column
     * @throws moodle_exception
     */
    private function collapselink($search, $collapsed = []) {
        global $PAGE, $OUTPUT;
        if (in_array($search, $collapsed)) {
            $url = new moodle_url($PAGE->url, ['tshow' => $search]);
            $pic = $OUTPUT->pix_icon('t/switch_plus', 'show');
        } else {
            $url = new moodle_url($PAGE->url, ['thide' => $search]);
            $pic = $OUTPUT->pix_icon('t/switch_minus', 'hide');
        }
        return html_writer::tag('div', html_writer::link($url, $pic),
            ['class' => 'collapselink']);
    }
    /**
     * Return picture indicating sort-direction if data is primarily sorted by this column
     * or empty string if not
     *
     * @param stdClass[] $orderby array containing current state of sorting
     * @param string $search columnname to print sortpic for
     * @return string html fragment with sort-pic or empty string
     */
    private function pic_if_sorted($orderby = [], $search = '') {
        global $OUTPUT;
        $keys = array_keys($orderby);
        if (reset($keys) == $search) {
            if ($orderby[$search] == 'ASC') {
                return $OUTPUT->pix_icon('t/up', 'sorted ASC');
            } else {
                return $OUTPUT->pix_icon('t/down', 'sorted DESC');
            }
        }

        return "";
    }
    /**
     * returns rank in queue for a particular user
     * if $data is an array uses array (like queue/reg-info returned by {@see get_active_groups()})
     * to determin rank otherwise if $data is an integer uses DB-query to get queue rank in
     * active group with id == $data
     *
     * @param int[]|int $data array with regs/queues for a group like returned by get_active_groups() or agrpid
     * @param int $userid user for whom data should be returned
     * @return int rank in queue/registration (registration only via $data-array)
     * @throws dml_exception
     */
    private function get_rank_in_queue($data=0, $userid=0) {
        global $DB, $USER;

        if (is_array($data)) { // It's the queue itself!
            // uasort($data, [$this, "cmptimestamp"]);
            $i = 1;
            foreach ($data as $entry) {
                if ($entry->userid == $userid) {
                    return $i;
                } else {
                    $i++;
                }
            }
            return false;
        } else if (!empty($data)) { // It's an active-group-id, so we gotta get the queue data!
            $params = [
                'agrpid' => $data,
                'userid' => !empty($userid) ? $userid : $USER->id,
            ];
            $sql = "SELECT count(b.id) AS rank
                      FROM {grouptool_queued} a
                INNER JOIN {grouptool_queued} b ON b.timestamp <= a.timestamp
                     WHERE a.agrpid = :agrpid AND a.userid = :userid";
        } else {
            return null;
        }

        return $DB->count_records_sql($sql, $params);
    }
    /**
     * outputs generated txt-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_txt($groupid=0, $groupingid=0) {
        ob_start();

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $grouptoolname = $this->grouptool->name;
        $useridentityfields = self::get_useridentity_fields();

        $lines = [];
        $users = $this->userlist_table($groupingid, $groupid, true);

        if (count($users) > 0) {
            foreach ($users as $key => $user) {
                if ($key == 0) { // Headline!
                    $lines[] = get_string('fullname')."\t".
                        self::get_useridentity_values_for_txt
                        (self::convert_associative_array_into_nested_index_array(self::get_useridentity_fields())) . "\t" .
                        get_string('registrations', 'report_grouptool')."\t".
                        get_string('queues', 'grouptool')." (".get_string('rank',
                            'report_grouptool').")";
                } else {
                    $rows = max([1, count($user['registrations']), count($user['queues'])]);

                    for ($i = 0; $i < $rows; $i++) {
                        $line = "";
                        if ($i == 0) {
                            $line = $user['name'];
                            // Print all activated useridentityvalue infos.
                            foreach ($useridentityfields as $identifier => $value) {
                                if (!empty($user[$identifier])) {
                                    $line .= "\t" . $user[$identifier];
                                }
                            }
                        } else {
                            $line = "\t\t";
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('no_registrations', 'report_grouptool');
                        } else if (key_exists($i, $user['registrations'])) {
                            $line .= "\t".$user['registrations'][$i];
                        } else {
                            $line .= "\t";
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $line .= "\t".get_string('nowhere_queued', 'report_grouptool');
                        } else if (key_exists($i, $user['queues'])) {
                            $line .= "\t".$user['queues'][$i]['name']."(".$user['queues'][$i]['rank'].")";
                        } else {
                            $line .= "\t";
                        }
                        $lines[] = $line;
                    }
                }
            }
        } else {
            $lines[] = get_string('no_data_to_display', 'report_grouptool');
        }
        $filecontent = implode(GROUPTOOL_NL, $lines);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid).'_'.get_string('userlist', 'report_grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'report_grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('userlist', 'report_grouptool');
        }
        $filename = clean_filename("$filename.txt");
        ob_clean();
        header('Content-Type: text/plain');
        header('Content-Length: ' . strlen($filecontent));
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1!
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in past!
        header('Content-Disposition: attachment; filename="'.str_replace([' ', '"'], ['_', ''], $filename).'";'.
            ' filename*="'.rawurlencode($filename).'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Encoding: utf-8');
        echo $filecontent;
    }
    /**
     * Returns a ready to print string containing all given useridentity values separated by tabstops
     *
     * @param array $values array Values that should be separated
     * @return string
     */
    private static function get_useridentity_values_for_txt($values) {
        $outstring = '';
        foreach ($values as $value) {
            $outstring .= "\t".$value['value'];
        }
        return $outstring;
    }
    /**
     * Helper function to convert a given associative array into a nested index array so it can be iterated thorough by mustache.
     *
     * @param array $inarray Associative array that should be converted ($key => $value)
     * @return array Nested array in the format [['key' => $key, 'value' => $value]]
     */
    public static function convert_associative_array_into_nested_index_array($inarray) {
        $outarray = [];
        foreach ($inarray as $key => $value) {
            $outarray[] = ['key' => $key, 'value' => $value];
        }
        return $outarray;
    }
    /**
     * outputs generated xlsx-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_xlsx($groupid = 0, $groupingid = 0) {
        global $CFG;

        require_once($CFG->libdir . "/excellib.class.php");

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleExcelWorkbook("-", 'Excel2007');

        $data = $this->userlist_table($groupingid, $groupid, true);

        $this->userlist_fill_workbook($workbook, $data);

        if (!empty($groupid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid).'_'.
                get_string('userlist', 'report_grouptool'));
        } else if (!empty($groupingid)) {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid).'_'.
                get_string('userlist', 'report_grouptool'));
        } else {
            $filename = clean_filename($coursename . '_' . $grouptoolname . '_' .
                get_string('userlist', 'report_grouptool'));
        }
        $filename = clean_filename("$filename.xlsx");

        $workbook->send($filename);
        $workbook->close();
    }
    /**
     * fills workbook (either XLSX or ODS) with data
     *
     * @param MoodleExcelWorkbook|MoodleODSWorkbook $workbook workbook to put data into
     * @param stdClass[] $data userdata with headline at index 0
     * @param string[] $collapsed (optional) currently collapsed columns
     * @throws coding_exception
     */
    protected function userlist_fill_workbook(&$workbook, $data, $collapsed=[]) {
        global $CFG;
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        if (count($data) > 0) {

            $worksheet = false;

            // Prepare formats!
            $headlineprop = [
                'size' => 12,
                'bold' => 1,
                'HAlign' => 'center',
                'bottom' => 2,
                'VAlign' => 'vcenter',
            ];
            $headlineformat = $workbook->add_format($headlineprop);
            $headlineformat->set_right(1);
            $headlineformat->set_align('center');
            $headlineformat->set_align('vcenter');
            $headlinelast = $workbook->add_format($headlineprop);
            $headlinelast->set_align('center');
            $headlinelast->set_align('vcenter');
            $headlinelast->set_left(1);
            $headlinenb = $workbook->add_format($headlineprop);
            $headlinenb->set_align('center');
            $headlinenb->set_align('vcenter');
            unset($headlineprop['bottom']);
            $headlinenbb = $workbook->add_format($headlineprop);
            $headlinenbb->set_align('center');
            $headlinenbb->set_align('vcenter');

            $regentryprop = [
                'size' => 10,
                'align' => 'left',
            ];
            $queueentryprop = $regentryprop;
            $queueentryprop['italic'] = true;
            $queueentryprop['color'] = 'grey';

            $regentryformat = $workbook->add_format($regentryprop);
            $regentryformat->set_right(1);
            $regentryformat->set_align('vcenter');
            $regentrylast = $workbook->add_format($regentryprop);
            $regentrylast->set_align('vcenter');
            $noregentriesformat = $workbook->add_format($regentryprop);
            $noregentriesformat->set_align('left');
            $noregentriesformat->set_align('vcenter');
            $noregentriesformat->set_right(1);
            $queueentryformat = $workbook->add_format($queueentryprop);
            $queueentryformat->set_right(1);
            $queueentryformat->set_align('vcenter');
            $queueentrylast = $workbook->add_format($queueentryprop);
            $queueentrylast->set_align('vcenter');
            $noqueueentriesformat = $workbook->add_format($queueentryprop);
            $noqueueentriesformat->set_align('left');
            $noqueueentriesformat->set_align('vcenter');

            // Start row for groups general sheet!
            $j = 0;

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = \core_user\fields::for_name()->get_required_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            $columnwidth = [
                0               => 26,
                'fullname'      => 26,
                'firstname'     => 20,
                'surname'       => 20,
                'email'         => 35,
                'registrations' => 47,
                'queues_grp'    => 47,
                'queues_rank'   => 7.5,
            ]; // Unit: mm!

            foreach ($data as $key => $user) {
                if ($worksheet === false && count($data) > 1) {
                    // General information? unused at the moment!
                    $worksheet = $workbook->add_worksheet(get_string('all'));
                }
                if ($key == 0) {
                    // Headline!
                    $k = 0;
                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name($namefield), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $hidden = in_array($namefield, $collapsed) ? true : false;
                        $columnwidth[$namefield] = empty($columnwidth[$namefield]) ? $columnwidth[0] : $columnwidth[$namefield];
                        $worksheet->set_column($k, $k, $columnwidth[$namefield], null, $hidden);
                        $k++;
                    }
                    // ...k = n!
                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            $worksheet->write_string($j, $k, \core_user\fields::get_display_name($field), $headlineformat);
                            $worksheet->write_blank($j + 1, $k, $headlineformat);
                            $hidden = in_array($field, $collapsed) ? true : false;
                            $columnwidth[$field] = empty($columnwidth[$field]) ? $columnwidth[0] : $columnwidth[$field];
                            $worksheet->set_column($k, $k, $columnwidth[$field], null, $hidden);
                            $worksheet->merge_cells($j, $k, $j + 1, $k);
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name('idnumber'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('idnumber', $collapsed) ? true : false;
                        $columnwidth['idnumber'] = empty($columnwidth['idnumber']) ? $columnwidth[0] : $columnwidth['idnumber'];
                        $worksheet->set_column($k, $k, $columnwidth['idnumber'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, \core_user\fields::get_display_name('email'), $headlineformat);
                        $worksheet->write_blank($j + 1, $k, $headlineformat);
                        $hidden = in_array('email', $collapsed) ? true : false;
                        $columnwidth['email'] = empty($columnwidth['email']) ? $columnwidth[0] : $columnwidth['email'];
                        $worksheet->set_column($k, $k, $columnwidth['email'], null, $hidden);
                        $worksheet->merge_cells($j, $k, $j + 1, $k);
                        $k++; // ...k = n+2!
                    }
                    $worksheet->write_string($j, $k, $user['registrations'], $headlineformat);
                    $worksheet->write_blank($j + 1, $k, $headlineformat);
                    $hidden = in_array('registrations', $collapsed) ? true : false;
                    $tmp = $columnwidth['registrations'];
                    $columnwidth['registrations'] = empty($tmp) ? $columnwidth[0] : $tmp;
                    unset($tmp);
                    $worksheet->set_column($k, $k, $columnwidth['registrations'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j + 1, $k);
                    $k++; // ...k = n+3!
                    $worksheet->write_string($j, $k, $user['queues'], $headlinenbb);
                    $worksheet->write_blank($j, $k + 1, $headlinenbb);
                    $hidden = in_array('queues', $collapsed) ? true : false;
                    $columnwidth['queues_grp'] = empty($columnwidth['queues_grp']) ? $columnwidth[0] : $columnwidth['queues_grp'];
                    $worksheet->set_column($k, $k, $columnwidth['queues_grp'], null, $hidden);
                    $tmp = $columnwidth['queues_rank'];
                    $columnwidth['queues_rank'] = empty($tmp) ? $columnwidth[0] : $tmp;
                    unset($tmp);
                    $worksheet->set_column($k + 1, $k + 1, $columnwidth['queues_rank'], null, $hidden);
                    $worksheet->merge_cells($j, $k, $j, $k + 1);
                    $worksheet->write_string($j + 1, $k, get_string('group', 'group'), $headlinenb);
                    $worksheet->write_string($j + 1, $k + 1, get_string('rank', 'report_grouptool'),
                        $headlinelast);
                    $k += 2; // ...k = n+5!
                    $rows = 2;
                } else {
                    $k = 0;
                    $rows = max([1, count($user['registrations']), count($user['queues'])]);

                    // First we output every namefield from used by fullname in exact the defined order!
                    foreach ($namefields as $namefield) {
                        if (empty($user[$namefield])) {
                            $user[$namefield] = '';
                        }
                        $worksheet->write_string($j, $k, $user[$namefield], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++;
                    }
                    // ...k = n!

                    if (!empty($CFG->showuseridentity)) {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            if (empty($user[$field])) {
                                $worksheet->write_blank($j, $k, $regentryformat);
                            } else {
                                $worksheet->write_string($j, $k, $user[$field], $regentryformat);
                            }
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                            $k++; // ...k = n+x!
                        }
                    } else {
                        $worksheet->write_string($j, $k, $user['idnumber'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+1!

                        $worksheet->write_string($j, $k, $user['email'], $regentryformat);
                        if ($rows > 1) {
                            $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                        }
                        $k++; // ...k = n+2!
                    }

                    for ($i = 0; $i < $rows; $i++) {
                        if ($i != 0) {
                            for ($m = 0; $m < $k; $m++) {
                                // Write all the empty cells!
                                $worksheet->write_blank($j + $i, $m, $regentryformat);
                            }
                        }
                        if ((count($user['registrations']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k, get_string('no_registrations',
                                'report_grouptool'),
                                $noregentriesformat);
                            if ($rows > 1) {
                                $worksheet->merge_cells($j, $k, $j + $rows - 1, $k);
                            }
                        } else if (key_exists($i, $user['registrations'])) {
                            $worksheet->write_string($j + $i, $k, $user['registrations'][$i],
                                $regentryformat);
                        } else {
                            $worksheet->write_blank($j + $i, $k, $regentryformat);
                        }
                        if ((count($user['queues']) == 0) && ($i == 0)) {
                            $worksheet->write_string($j, $k + 1, get_string('nowhere_queued',
                                'report_grouptool'),
                                $noqueueentriesformat);
                            $worksheet->merge_cells($j, $k + 1, $j + $rows - 1, $k + 2);
                        } else if (key_exists($i, $user['queues'])) {
                            $worksheet->write_string($j + $i, $k + 1, $user['queues'][$i]['name'],
                                $queueentrylast);
                            $worksheet->write_number($j + $i, $k + 2, $user['queues'][$i]['rank'],
                                $queueentrylast);
                        } else {
                            $worksheet->write_blank($j + $i, $k + 1, $queueentrylast);
                            $worksheet->write_blank($j + $i, $k + 2, $queueentrylast);
                        }
                    }
                    $k += 3;
                }
                $j += $rows;    // We use 1 row space between groups!
            }
        }
    }
    /**
     * outputs generated pdf-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_pdf($groupid=0, $groupingid=0) {
        $data = $this->userlist_table($groupingid, $groupid, true);

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $timeavailable = $this->grouptool->timeavailable;
        $grouptoolname = $this->grouptool->name;
        $timedue = $this->grouptool->timedue;

        if (!empty($groupingid) || !empty($groupid)) {
            $viewname = "";
            if (!empty($groupingid)) {
                $viewname .= groups_get_grouping_name($groupingid);
            } else {
                $viewname .= get_string('all');
            }
            if ($viewname != "") {
                $viewname .= " / ";
            }
            if (!empty($groupid)) {
                $viewname .= groups_get_group_name($groupid);
            } else {
                $viewname .= get_string('all');
            }
        } else {
            $viewname = get_string('all').' '.get_string('groups');
        }

        $pdf = new \report_grouptool\pdf('userlist', $coursename, $grouptoolname, $timeavailable, $timedue,
            $viewname);

        if (count($data) > 1) {
            $user = reset($data);
            $name = $user['name'];
            $idnumber = $user['idnumber'];
            $email = $user['email'];
            $regdata = $user['registrations'];
            $queuedata = $user['queues'];
            $pdf->add_userdata($user, true);
            while (next($data)) {
                $user = current($data);
                $name = $user['name'];
                $idnumber = $user['idnumber'];
                $email = $user['email'];
                $regdata = $user['registrations'];
                $queuedata = $user['queues'];
                $pdf->add_userdata($user);
            }
        } else {
            $pdf->MultiCell(0, $pdf->getLastH(), get_string('no_data_to_display', 'report_grouptool'),
                'B', 'LRTB', false, 1, null, null, true, 1, true,
                false, $pdf->getLastH(), 'M', true);
        }

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid).'_'.get_string('userlist', 'report_grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'report_grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('userlist', 'report_grouptool');
        }
        $filename = clean_filename("$filename.pdf");

        $pdf->Output($filename, 'D');
        exit();
    }
    /**
     * outputs generated ods-file for userlist (forces download)
     *
     * @param int $groupid optional get only this group
     * @param int $groupingid optional get only this grouping
     * @param string[] $collapsed optional current array with collapsed columns
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function download_userlist_ods($groupid=0, $groupingid=0, $collapsed=[]) {
        global $CFG;

        require_once($CFG->libdir . "/odslib.class.php");

        $coursename = format_string($this->course->fullname, true, ['context' => context_module::instance($this->cm->id)]);
        $grouptoolname = $this->grouptool->name;

        $workbook = new MoodleODSWorkbook("-");

        $data = $this->userlist_table($groupingid, $groupid, true);

        $this->userlist_fill_workbook($workbook, $data, $collapsed);

        if (!empty($groupid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_group_name($groupid).'_'.get_string('userlist', 'report_grouptool');
        } else if (!empty($groupingid)) {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                groups_get_grouping_name($groupingid).'_'.get_string('userlist', 'report_grouptool');
        } else {
            $filename = $coursename . '_' . $grouptoolname . '_' .
                get_string('userlist', 'report_grouptool');
        }
        $filename = clean_filename("$filename.ods");

        $workbook->send($filename);
        $workbook->close();
    }
    /**
     * Helper function used to print empty cells for hidden columns
     */
    private function print_empty_cell() {
        echo html_writer::tag('td', '', ['class' => '']);
    }





}
