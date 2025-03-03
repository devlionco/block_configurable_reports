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

/** Configurable Reports
  * A Moodle block for creating Configurable Reports
  * @package blocks
  * @author: Juan leyva <http://www.twitter.com/jleyvadelgado>
  * @date: 2009
  */

    require_once("../../config.php");

	require_once($CFG->dirroot."/blocks/configurable_reports/locallib.php");


	$id = optional_param('id', 0,PARAM_INT);
	$courseid = optional_param('courseid',SITEID,PARAM_INT);
	$delete = optional_param('delete', 0,PARAM_BOOL);
	$confirm = optional_param('confirm', 0,PARAM_BOOL);
	$show = optional_param('show', 0,PARAM_BOOL);
	$hide = optional_param('hide', 0,PARAM_BOOL);
	$duplicate = optional_param('duplicate', 0,PARAM_BOOL);


	$report = null;

	if (! $course = $DB->get_record("course",array( "id" =>  $courseid)) ) {
		print_error("nosuchcourseid",'block_configurable_reports');
	}

	// Force user login in course (SITE or Course)
    if ($course->id == SITEID){
		require_login();
		$context = context_system::instance();
	} else {
		require_login($course->id);
        $context = context_course::instance($course->id);
	}

	if(! has_capability('block/configurable_reports:managereports', $context) && ! has_capability('block/configurable_reports:manageownreports', $context))
		print_error('badpermissions','block_configurable_reports');


	$PAGE->set_context($context);
	$PAGE->set_pagelayout('incourse');


	if($id){
		if(! $report = $DB->get_record('block_configurable_reports',array('id' => $id)))
			print_error('reportdoesnotexists','block_configurable_reports');

		if(! has_capability('block/configurable_reports:managereports', $context) && $report->ownerid != $USER->id)
			print_error('badpermissions','block_configurable_reports');

		$title = format_string($report->name);

		$courseid = $report->courseid;
		if (! $course = $DB->get_record("course",array( "id" =>  $courseid)) ) {
			print_error("nosuchcourseid",'block_configurable_reports');
		}

		require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
		require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$report->type.'/report.class.php');
		$reportclassname = 'report_'.$report->type;
		$reportclass = new $reportclassname($report->id);
		$PAGE->set_url('/blocks/configurable_reports/editreport.php', array('id'=>$id));
	} else {
		$title = get_string('newreport','block_configurable_reports');
		$PAGE->set_url('/blocks/configurable_reports/editreport.php', null);
	}

	if($report) {
		$title = format_string($report->name);
		$report_courseid = $report->courseid;
	} else {
		$title = get_string('report','block_configurable_reports');
		$report_courseid = $courseid;
	}

    $courseurl =  new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$courseid));
    $PAGE->navbar->add($course->shortname, $courseurl);

    $managereporturl =  new moodle_url($CFG->wwwroot.'/blocks/configurable_reports/managereport.php', array('courseid'=>$report_courseid));
    $PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'), $managereporturl);

    $PAGE->navbar->add($title);

	// Common actions
	if(($show || $hide) && confirm_sesskey()){
		$visible = ($show)? 1 : 0;
		if(!$DB->set_field('block_configurable_reports','visible',$visible,array('id' => $report->id)))
			print_error('cannotupdatereport','block_configurable_reports');
		$action = ($visible)? 'showed' : 'hidden';
		//add_to_log($report->courseid, 'configurable_reports', 'report '.$action, '/block/configurable_reports/editreport.php?id='.$report->id, $report->id);
        if ($action == 'showed')
            \block_configurable_reports\event\report_showed::create_from_report($report, context_course::instance($course->id))->trigger();
        if ($action == 'hidden')
            \block_configurable_reports\event\report_hidden::create_from_report($report, context_course::instance($course->id))->trigger();

		header("Location: $CFG->wwwroot/blocks/configurable_reports/managereport.php?courseid=$courseid");
		die;
	}

	if($duplicate && confirm_sesskey()){
		//$newreport = new stdClass();
		$newreport = $report;
		unset($newreport->id);
		$newreport->name = get_string('copyasnoun').' '.$newreport->name;
        if (!$newreportid = $DB->insert_record('block_configurable_reports', $newreport)) {
            print_error('cannotduplicate','block_configurable_reports');
        }
        $newreport->id = $newreportid;
        \block_configurable_reports\event\report_duplicated::create_from_report($newreport,
            context_course::instance($course->id))->trigger();
		header("Location: $CFG->wwwroot/blocks/configurable_reports/managereport.php?courseid=$courseid");
		die;
	}

	if($delete && confirm_sesskey()){
		if(!$confirm){
			$PAGE->set_title($title);
			$PAGE->set_heading( $title);
			$PAGE->set_cacheable( true);
			echo $OUTPUT->header();
			$message = get_string('confirmdeletereport','block_configurable_reports');
			$optionsyes = array('id'=>$report->id, 'delete'=>$delete, 'sesskey'=>sesskey(), 'confirm'=>1);
			$optionsno = array();
			$buttoncontinue = new single_button(new moodle_url('editreport.php', $optionsyes), get_string('yes'), 'get');
			$buttoncancel   = new single_button(new moodle_url('managereport.php', $optionsno), get_string('no'), 'get');
			echo $OUTPUT->confirm($message, $buttoncontinue, $buttoncancel);
			echo $OUTPUT->footer();
			exit;
		} else {
			if($DB->delete_records('block_configurable_reports',array('id'=>$report->id)))
                \block_configurable_reports\event\report_deleted::create_from_report($report, context_course::instance($course->id))->trigger();
            header("Location: $CFG->wwwroot/blocks/configurable_reports/managereport.php?courseid=$courseid");
            die;
		}
	}


	require_once('editreport_form.php');
	if(!empty($report))
		$editform = new report_edit_form('editreport.php',compact('report','courseid','context'));
	else
		$editform = new report_edit_form('editreport.php',compact('courseid','context'));

	if(!empty($report)){
		$export = explode(',',$report->export);
		if(!empty($export)){
			foreach($export as $e)
				$report->{'export_'.$e} = 1;
		}
		//$report->summary['text'] = $report->summary;
		//$report->summary['format'] = FORMAT_HTML;
		$editform->set_data($report);
	}

	if($editform->is_cancelled()){
		if(!empty($report))
			redirect($CFG->wwwroot.'/blocks/configurable_reports/editreport.php?id='.$report->id);
		else
			redirect($CFG->wwwroot.'/blocks/configurable_reports/editreport.php');
	}
	else if ($data = $editform->get_data()) {

		require_once($CFG->dirroot.'/blocks/configurable_reports/report.class.php');
		require_once($CFG->dirroot.'/blocks/configurable_reports/reports/'.$data->type.'/report.class.php');
		if(empty($report)) {
            $reportclassname = 'report_'.$data->type;
        } else {
            $reportclassname = 'report_'.$report->type;
        }


        // Handle checkbox values
        $data->cron = ($data->cron == 1) ? 1 : 0;
        $data->jsordering = ($data->jsordering == 1) ? 1 : 0;
        $data->subreport = ($data->subreport == 1) ? 1 : 0;
        $data->summary = $data->summary['text'];

		$arraydata = (array) $data;
		$data->export = '';
		foreach($arraydata as $key=>$d){
			if(strpos($key,'export_') !== false){
				$data->export .= str_replace('export_','',$key).',';
			}
		}

		if(empty($report)){
			$data->ownerid = $USER->id;
			$data->courseid = $courseid;
			$data->visible = 1;
			$data->components = '';
			if(!isset($data->jsordering))
				$data->jsordering = 0;

			// extra check
			if($data->type == 'sql' && !has_capability('block/configurable_reports:managesqlreports',$context))
				print_error('nosqlpermissions');

			if(! $lastid = $DB->insert_record('block_configurable_reports',$data)){
				print_error('errorsavingreport','block_configurable_reports');
			}else{
                $report = $DB->get_record('block_configurable_reports', array('id'=>$lastid));
				//add_to_log($courseid, 'configurable_reports', 'report created', '/block/configurable_reports/editreport.php?id='.$lastid, $data->name);
                \block_configurable_reports\event\report_created::create_from_report($report, context_course::instance($course->id))->trigger();
				$reportclass = new $reportclassname($lastid);
				redirect($CFG->wwwroot.'/blocks/configurable_reports/editcomp.php?id='.$lastid.'&comp='.$reportclass->components[0]);
			}
		}
		else{
			//add_to_log($report->courseid, 'configurable_reports', 'edit', '/block/configurable_reports/editreport.php?id='.$id, $report->name);
            \block_configurable_reports\event\report_edited::create_from_report($report, context_course::instance($course->id))->trigger();
			$reportclass = new $reportclassname($data->id);
			$data->type = $report->type;
            $permissionscache = \cache::make('block_configurable_reports', 'permissions');
            $permissionscache->set($data->id, []);
			if(! $DB->update_record('block_configurable_reports', $data)){
				print_error('errorsavingreport','block_configurable_reports');
			}else{
				redirect($CFG->wwwroot.'/blocks/configurable_reports/editcomp.php?id='.$data->id.'&comp='.$reportclass->components[0]);
			}
		}
	}

	$PAGE->set_context($context);
	$PAGE->set_pagelayout('incourse');
	$PAGE->set_title($title);
	$PAGE->set_heading( $title);
	$PAGE->set_cacheable( true);

	echo $OUTPUT->header();

	if($id){
		$currenttab = 'report';
		include('tabs.php');
	}

	$editform->display();

	echo $OUTPUT->footer();

