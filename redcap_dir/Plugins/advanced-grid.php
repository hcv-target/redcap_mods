<?php
/*****************************************************************************************
 **  REDCap is only available through a license agreement with Vanderbilt University
 ******************************************************************************************/
/**
 * This grid plugin is meant to replace / supplant built-in DataEntry/grid.php.
 * All calls to DataEntry/grid.php are redirected to this plugin via Apache RedirectMatch
 * Its purpose is to provide additional information to abstractors trying to find a given
 * record in a series of longitudinal events.
 */

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';


//Required files
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
require_once APP_PATH_DOCROOT . 'Surveys/survey_functions.php';
/**
 * TARGET includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/plugins/includes/functions.php';

// Auto-number logic (pre-submission of new record)
if ($auto_inc_set) {
	// If the auto-number record selected has already been created by another user, fetch the next one to prevent overlapping data
	if (isset($_GET['id']) && isset($_GET['auto'])) {
		$q = db_query("select 1 from redcap_data where project_id = $project_id and record = '" . prep($_GET['id']) . "' limit 1");
		if (db_num_rows($q) > 0) {
			// Record already exists, so redirect to new page with this new record value
			redirect(PAGE_FULL . "?pid=$project_id&page={$_GET['page']}&id=" . getAutoId());
		}
	}
}

//Get arm number from URL var 'arm'
$arm = getArm();

// Reload page if id is a blank value
if (isset($_GET['id']) && trim($_GET['id']) == "") {
	redirect(PAGE_FULL . "?pid=" . PROJECT_ID . "&page=" . $_GET['page'] . "&arm=" . $arm);
	exit;
}

// Clean id
if (isset($_GET['id'])) {
	$_GET['id'] = strip_tags(label_decode($_GET['id']));
}

//include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once $base_path . '/plugins/Overrides/ProjectGeneral/header_advanced_grid.php';

// Header
if (isset($_GET['id'])) {
	renderPageTitle("<img src='" . APP_PATH_IMAGES . "application_view_tile.png' class='imgfix2'> {$lang['grid_02']}");
} else {
	renderPageTitle("<img src='" . APP_PATH_IMAGES . "blog_pencil.gif' class='imgfix2'> " . ($user_rights['record_create'] ? $lang['bottom_62'] : $lang['bottom_72']));
}

//Custom page header note
if (trim($custom_data_entry_note) != '') {
	print "<br><div class='green' style='font-size:11px;'>" . str_replace("\n", "<br>", $custom_data_entry_note) . "</div>";
}


//Alter how records are saved if project is Double Data Entry (i.e. add --# to end of Study ID)
if ($double_data_entry && $user_rights['double_data'] != 0) {
	$entry_num = "--" . $user_rights['double_data'];
} else {
	$entry_num = "";
}


## GRID
if (isset($_GET['id']))
{
	## If study id has been entered or selected, display grid.

	//Adapt for Double Data Entry module
	if ($entry_num == "") {
		//Not Double Data Entry or this is Reviewer of Double Data Entry project
		$id = $_GET['id'];
	} else {
		//This is #1 or #2 Double Data Entry person
		$id = $_GET['id'] . $entry_num;
	}

	$sql = "select d.record from redcap_events_metadata m, redcap_events_arms a, redcap_data d where a.project_id = $project_id 
			and a.project_id = d.project_id and m.event_id = d.event_id and a.arm_num = $arm and a.arm_id = m.arm_id 
			and d.record = '" . prep($id) . "' limit 1";
	$q = db_query($sql);
	$row_num = db_num_rows($q);
	$existing_record = ($row_num > 0);

	## LOCK RECORDS & E-SIGNATURES
	// For lock/unlock records feature, show locks by any forms that are locked (if a record is pulled up on data entry page)
	$locked_forms = array();
	$qsql = "select event_id, form_name, timestamp from redcap_locking_data where project_id = $project_id and record = '" . prep($id) . "'";
	$q = db_query($qsql);
	while ($row = db_fetch_array($q)) {
		$locked_forms[$row['event_id'] . "," . $row['form_name']] = " <img src='" . APP_PATH_IMAGES . "lock_small.png' title='Locked on " . DateTimeRC::format_ts_from_ymd($row['timestamp']) . "'>";
	}
	// E-signatures
	$qsql = "select event_id, form_name, timestamp from redcap_esignatures where project_id = $project_id and record = '" . prep($id) . "'";
	$q = db_query($qsql);
	while ($row = db_fetch_array($q)) {
		$this_esign_ts = " <img src='" . APP_PATH_IMAGES . "tick_shield_small.png' title='E-signed on " . DateTimeRC::format_ts_from_ymd($row['timestamp']) . "'>";
		if (isset($locked_forms[$row['event_id'] . "," . $row['form_name']])) {
			$locked_forms[$row['event_id'] . "," . $row['form_name']] .= $this_esign_ts;
		} else {
			$locked_forms[$row['event_id'] . "," . $row['form_name']] = $this_esign_ts;
		}
	}

	//Check if record exists in another group, if user is in a DAG
	if ($user_rights['group_id'] != "" && $existing_record) {
		$q = db_query("select 1 from redcap_data where project_id = $project_id and record = '" . prep($id) . "' and
						  field_name = '__GROUPID__' and value = '{$user_rights['group_id']}' limit 1");
		if (db_num_rows($q) < 1) {
			//Record is not in user's DAG
			print  "<div class='red'>
						<img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'>
						<b>{$lang['global_49']} " . $_GET['id'] . " {$lang['grid_13']}</b><br><br>
						{$lang['grid_14']}<br><br>
						<a href='" . APP_PATH_WEBROOT . "DataEntry/grid.php?pid=$project_id' style='text-decoration:underline'><< {$lang['grid_15']}</a>
						<br><br>
					</div>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}
	}

	## If new study id, give some brief instructions above normal instructions.			
	if (!$existing_record) {
		print  "<p style='margin-top:15px;color:#800000;'>
					<b>\"{$_GET['id']}\" {$lang['grid_16']} " . RCView::escape($table_pk_label) . "{$lang['period']}</b>
					{$lang['grid_40']} " . RCView::escape($table_pk_label) . " {$lang['grid_18']}
				</p>
				<hr size=1>";
	}

	## General instructions for grid.
	print    RCView::table(array('style' => 'width:700px;table-layout:fixed;', 'cellspacing' => '0'),
		RCView::tr('',
			RCView::td(array('style' => 'padding:10px 30px 0 0;', 'valign' => 'top'),
				// Instructions
				"{$lang['grid_19']} " . RCView::escape($table_pk_label) . " {$lang['grid_20']} {$lang['grid_21']}
					<a href='" . APP_PATH_WEBROOT . "Design/define_events.php?pid=$project_id&edit'
						style='text-decoration:underline;'>{$lang['global_16']}</a> 
					{$lang['global_14']}{$lang['period']}"
			) .
			RCView::td(array('valign' => 'top', 'style' => 'width:300px;'),
				// Legend
				RCView::div(array('class' => 'chklist', 'style' => 'background-color:#eee;border:1px solid #ccc;'),
					RCView::table(array('style' => '', 'cellspacing' => '2'),
						RCView::tr('',
							RCView::td(array('colspan' => '2', 'style' => 'font-weight:bold;'),
								$lang['data_entry_178']
							)
						) .
						RCView::tr('',
							RCView::td(array('class' => 'nowrap', 'style' => 'padding-right:5px;'),
								RCView::img(array('src' => 'circle_red.gif', 'class' => 'imgfix')) . $lang['global_92']
							) .
							RCView::td(array('class' => 'nowrap', 'style' => ''),
								RCView::img(array('src' => 'circle_gray.png', 'class' => 'imgfix')) . $lang['global_92'] . " " . $lang['data_entry_205'] .
								RCView::a(array('href' => 'javascript:;', 'class' => 'help', 'title' => $lang['global_58'], 'onclick' => "simpleDialog('" . cleanHtml($lang['data_entry_232']) . "','" . cleanHtml($lang['global_92'] . " " . $lang['data_entry_205']) . "');"), '?')
							)
						) .
						RCView::tr('',
							RCView::td(array('class' => 'nowrap', 'style' => 'padding-right:5px;'),
								RCView::img(array('src' => 'circle_yellow.png', 'class' => 'imgfix')) . $lang['global_93']
							) .
							RCView::td(array('class' => 'nowrap', 'style' => ''),
								(!$surveys_enabled ? "" :
									RCView::img(array('src' => 'circle_orange_tick.png', 'class' => 'imgfix')) . $lang['global_95']
								)
							)
						) .
						RCView::tr('',
							RCView::td(array('class' => 'nowrap', 'style' => 'padding-right:5px;'),
								RCView::img(array('src' => 'circle_green.png', 'class' => 'imgfix')) . $lang['survey_28']
							) .
							RCView::td(array('class' => 'nowrap', 'style' => ''),
								(!$surveys_enabled ? "" :
									RCView::img(array('src' => 'tick_circle_frame.png', 'class' => 'imgfix')) . $lang['global_94']
								)
							)
						)
					)
				)
			)
		)
	);

	// Check if record exists for other arms, and if so, notify the user (only for informational purposes)		
	if (recordExistOtherArms($id, $arm)) {
		// Record exists in other arms, so give message
		print  "<p class='red' style='font-family:arial;'>
					<b>{$lang['global_03']}</b>{$lang['colon']} {$lang['grid_36']} " . RCView::escape($table_pk_label) . "
					\"<b>" . removeDDEending($id) . "</b>\" {$lang['grid_37']}
				</p>";
	}

	// Set up context messages to users for actions performed in longitudinal projects (Save button redirects back here for longitudinals)
	if (isset($_GET['msg'])) {
		if ($_GET['msg'] == 'edit') {
			print "<div class='darkgreen' style='margin:10px 0;width:640px;'><img src='" . APP_PATH_IMAGES . "tick.png' class='imgfix'> " . RCView::escape($table_pk_label) . " <b>{$_GET['id']}</b> {$lang['data_entry_08']}</div>";
		} elseif ($_GET['msg'] == 'add') {
			print "<div class='darkgreen' style='margin:10px 0;width:640px;'><img src='" . APP_PATH_IMAGES . "tick.png' class='imgfix'> " . RCView::escape($table_pk_label) . " <b>{$_GET['id']}</b> {$lang['data_entry_09']}</div>";
		} elseif ($_GET['msg'] == 'cancel') {
			print "<div class='red' style='margin:10px 0;width:640px;'><img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'> " . RCView::escape($table_pk_label) . " <b>{$_GET['id']}</b> {$lang['data_entry_11']}</div>";
		} elseif ($_GET['msg'] == '__rename_failed__') {
			print "<div class='red' style='margin:10px 0;width:640px;'><img src='" . APP_PATH_IMAGES . "exclamation.png' class='imgfix'> " . RCView::escape($table_pk_label) . " <b>{$_GET['id']}</b> {$lang['data_entry_08']}<br/><b>{$lang['data_entry_13']} " . RCView::escape($table_pk_label) . " {$lang['data_entry_15']}</b></div>";
		}
	}


	/***************************************************************
	 ** EVENT-FORM GRID
	 ***************************************************************/

	## Query to get all Form Status values for all forms across all time-points. Put all into array for later retrieval.
	// Prefill $grid_form_status array with blank defaults
	$grid_form_status = array();
	foreach ($Proj->eventsForms as $this_event_id => $these_forms) {
		foreach ($these_forms as $this_form) {
			$grid_form_status[$this_event_id][$this_form][1] = '';
		}
	}
	// Get form statuses
	$qsql = "select distinct d.event_id, m.form_name, if(d2.value is null, '0', d2.value) as value, d2.instance 
			from (redcap_data d, redcap_metadata m) left join redcap_data d2 
			on d2.project_id = m.project_id and d2.record = d.record and d2.event_id = d.event_id 
			and d2.field_name = concat(m.form_name, '_complete') 
			where d.project_id = $project_id and d.project_id = m.project_id and d.record = '" . prep($id) . "' and m.element_type != 'calc'
			and d.field_name = m.field_name and m.form_name in (" . prep_implode(array_keys($Proj->forms)) . ") and m.field_name != '{$Proj->table_pk}'";
	$q = db_query($qsql);
	$has_repeated_events = false;
	while ($row = db_fetch_array($q)) {
		if ($row['instance'] == '') {
			$row['instance'] = '1';
		} else {
			$has_repeated_events = true;
		}
		//Put time-point and form name as array keys with form status as value
		$grid_form_status[$row['event_id']][$row['form_name']][$row['instance']] = $row['value'];
	}

	// Create an array to count the max instances per event
	$instance_count = array();
	// If has repeated events, then loop through all events/forms and sort them by instance
	if ($has_repeated_events) {
		// Loop through events
		foreach ($grid_form_status as $this_event_id => $these_forms) {
			foreach ($these_forms as $this_form => $these_instances) {
				$count_instances = count($these_instances);
				if ($count_instances > 1) {
					ksort($these_instances);
					$grid_form_status[$this_event_id][$this_form] = $these_instances;
				}
				// Add form instance
				foreach ($these_instances as $this_instance => $this_form_status) {
					if (!isset($instance_count[$this_event_id][$this_instance])) {
						$instance_count[$this_event_id][$this_instance] = '';
					}
				}
			}
			// Loop through other remaining forms and seed with blank value
			foreach (array_diff(array_keys($Proj->forms), array_keys($these_forms)) as $this_form) {
				$grid_form_status[$this_event_id][$this_form] = array();
			}
		}
		// Now loop back through and seed all forms so that each event_id has same number of form instances per event
		foreach ($grid_form_status as $this_event_id => $these_forms) {
			ksort($instance_count[$this_event_id]);
			foreach ($these_forms as $this_form => $these_instances) {
				// Seed all defaults for this form
				$grid_form_status[$this_event_id][$this_form] = $instance_count[$this_event_id];
				// Add form instance
				foreach ($these_instances as $this_instance => $this_form_status) {
					$grid_form_status[$this_event_id][$this_form][$this_instance] = $this_form_status;
				}
			}
		}
	}

	// Determine if this record also exists as a survey response for some instruments
	$surveyResponses = array();
	if ($surveys_enabled) {
		$surveyResponses = Survey::getResponseStatus($project_id, $id);
	}

	// Get Custom Record Label and Secondary Unique Field values (if applicable)
	if ($existing_record) {
		$this_custom_record_label_secondary_pk = "<span style='color:#800000;margin-left:3px;'>" .
			Records::getCustomRecordLabelsSecondaryFieldAllRecords(addDDEending($_GET['id']), false, $arm, true, '') . "</span>";
	} else {
		$this_custom_record_label_secondary_pk = "";
	}

	// JavaScript for setting floating table headers
	?>
	<script type="text/javascript">
		$(function () {
			// Center the record ID name with the table
			var eg = $('#event_grid_table');
			if (eg.width() < 700) {
				$('#record_display_name').width(eg.width());
			}
			// Enable fixed table headers for event grid
			enableFixedTableHdrs('event_grid_table');
			// Also set it to run again if the page is resized
			$(window).resize(function () {
				enableFixedTableHdrs('event_grid_table');
			});
		});
	</script>
	<?php

	// DISPLAY RECORD ID above grid
	print  "<div id='record_display_name' style='max-width:700px;padding:0 5px 6px;color:#000066;text-align:center;font-size:16px;'>
				" . (!$existing_record ? "<span style='font-weight:bold;'>{$lang['grid_30']}</span> " : "") . "
				" . RCView::escape($table_pk_label) . " <b>{$_GET['id']}</b>
				$this_custom_record_label_secondary_pk" .
		// If has multiple arms, then display this arm's name
		(!$multiple_arms ? "" : "<div style='color:#800000;font-size:13px;'>({$lang['global_08']} {$arm}{$lang['colon']} " . RCView::escape(strip_tags($Proj->events[$arm]['name'])) . ")</div>") . "
			</div>";

	// GRID
	/**
	 * added next line to expose filtered columns if needed
	 */
	// print "<span>Show Events <a id='show100' href='javascript:;'>51 - 100</a> | <a id='show200' href='javascript:;'>101 - 200</a></span>";
	/**
	 * end added line
	 */
	$grid_disp_change = "";
	print  "<table id='event_grid_table' class='form_border'>";

	// Display "events" and/or arm name
	print  "<thead>
				<tr>
					<th class='header' style='text-align:center;padding:5px;'>{$lang['global_35']}</th>";

	//Render table headers
	$i = 1;
	foreach ($Proj->events[$arm]['events'] as $this_event_id => $this_event) {
		if (!isset($instance_count[$this_event_id])) {
			$instance_count[$this_event_id][1] = '';
		}
		$has_multiple_instances = (count($instance_count[$this_event_id]) > 1);
		foreach (array_keys($instance_count[$this_event_id]) as $this_instance) {
			print  "	<th class='header' style='text-align:center;width:25px;color:#800000;padding:5px;white-space:normal;vertical-align:bottom;'>
							<div style='font-family:Arial;'>" . RCView::escape(strip_tags($this_event['descrip'])) . "</div>
							<div class='nowrap' style='font-weight:normal;font-size:10px;'>($i)" . ($has_multiple_instances ? "&nbsp;&nbsp;#" . $this_instance : "") . "</div>
						</th>";
		}
		$i++;
	}
	print "		</tr>
			</thead>";
	// Create array of all events and forms for this arm
	$form_events = array();
	foreach (array_keys($Proj->events[$arm]['events']) as $this_event_id) {
		$form_events[$this_event_id] = (isset($Proj->eventsForms[$this_event_id])) ? $Proj->eventsForms[$this_event_id] : array();
	}
	// Create array of all forms used in this arm (because some may not be used, so we should not display them)
	$forms_this_arm = array();
	foreach ($form_events as $these_forms) {
		$forms_this_arm = array_merge($forms_this_arm, $these_forms);
	}
	$forms_this_arm = array_unique($forms_this_arm);
	//Render table rows
	$prev_form = "";
	foreach ($Proj->forms as $form_name => $attr) {
		// If form is not used in this arm, then skip it
		if (!in_array($form_name, $forms_this_arm)) continue;
		// Set vars
		$row['form_name'] = $form_name;
		$row['form_menu_description'] = $attr['menu'];
		// Make sure user has access to this form. If not, then do not display this form's row.
		if ($user_rights['forms'][$row['form_name']] == '0') continue;
		//Deterine if we are starting new row	
		if ($prev_form != $row['form_name']) {
			if ($prev_form != "") print "</tr>";
			print "<tr><td class='data'>" . RCView::escape($row['form_menu_description']);
			// If instrument is enabled as a survey, then display "(survey)" next to it
			if (isset($Proj->forms[$row['form_name']]['survey_id'])) {
				print RCView::span(array('style' => 'margin:0 4px;color:#888;font-size:10px;font-family:tahoma;'), $lang['grid_39']);
			}
			print "</td>";
		}
		// Render cells
		foreach ($form_events as $this_event_id => $eattr) {
			$row['event_id'] = $this_event_id;
			// Add first event instance, if missing
			if (!isset($grid_form_status[$row['event_id']][$row['form_name']])) {
				$grid_form_status[$row['event_id']][$row['form_name']][1] = '';
			}
			// Loop through all instances			
			foreach ($grid_form_status[$row['event_id']][$row['form_name']] as $this_instance => $this_form_status) {
				// Render table cell
				print "<td class='data' style='text-align:center;'>";
				if (in_array($row['form_name'], $eattr)) {
					// If it's a survey response, display different icons
					if (isset($surveyResponses[$id][$row['event_id']][$row['form_name']])) {
						//Determine color of button based on response status
						switch ($surveyResponses[$id][$row['event_id']][$row['form_name']]) {
							case '2':
								$this_color = 'tick_circle_frame.png';
								break;
							default:
								$this_color = 'circle_orange_tick.png';
						}
					} else {
						//Form status
						switch ($this_form_status) {
							case '2':
								$this_color = 'circle_green.png';
								break;
							case '1':
								$this_color = 'circle_yellow.png';
								break;
							case '0':
								$this_color = 'circle_red.gif';
								break;
							default:
								$this_color = 'circle_gray.png';
						}
					}
					//Determine record id (will be different for each time-point). Configure if Double Data Entry
					if ($entry_num == "") {
						$displayid = $id;
					} else {
						//User is Double Data Entry person
						$displayid = $_GET['id'];
					}
					/**
					 * BEGIN PLUGIN
					 * Where there is more than one event for this $row['form_name'], display additional detail to help identify the record in the grid
					 * The specific functionality of this code is dependent upon the use of the CDISC-compliant variable naming convention
					 * defined for HCV-TARGET 2.0 and other subsequent HCV-TARGET-related studies.
					 * FIRST, get the start date if there is one
					 */
					$count_form_events_query = "SELECT count(a.event_id) as value FROM redcap_events_forms a
			JOIN (SELECT event_id FROM redcap_events_metadata WHERE arm_id =
			(SELECT arm_id FROM redcap_events_arms WHERE project_id = '$project_id')) b
			ON a.event_id = b.event_id
			WHERE a.form_name = '{$row['form_name']}'";
					$count_form_events_result = db_query($count_form_events_query);
					if ($count_form_events_result) {
						$count_form_events = db_fetch_assoc($count_form_events_result);
						if ($count_form_events['value'] >= 1) {
							$detail_date_field_query = "SELECT DISTINCT m1.field_name as date_name FROM redcap_metadata m1
					WHERE m1.project_id = '$project_id'
					AND (m1.field_name LIKE '%dtc' OR m1.field_name LIKE '%date')
					AND m1.field_name NOT LIKE '%end%'
					AND m1.form_name = '{$row['form_name']}'
					ORDER BY m1.field_order ASC";
							$detail_date_field_result = db_query($detail_date_field_query);
							if ($detail_date_field_result) {
								while ($detail_date_field = db_fetch_assoc($detail_date_field_result)) {
									if (isset($detail_date_field['date_name'])) {
										$detail_date_query = "SELECT a.value as date from redcap_data a
								WHERE a.project_id = '$project_id'
								AND a.record = '$displayid'
								AND a.field_name = '{$detail_date_field['date_name']}'
								AND a.value != ''
								AND a.event_id = '{$row['event_id']}' LIMIT 1";
										$detail_date_result = db_query($detail_date_query);
										if ($detail_date_result) {
											$detail_date = db_fetch_assoc($detail_date_result);
											if (isset($detail_date['date'])) {
												print "<div><a style='font-weight:normal;font-size:10px;' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . urlencode($displayid) . "&event_id={$row['event_id']}&page={$row['form_name']}'>{$detail_date['date']}</a></div>";
											}
											db_free_result($detail_date_result);
										}
									}
								}
								db_free_result($detail_date_field_result);
							}
						}
						db_free_result($count_form_events_result);
					}
					/**
					 * PAUSE PLUGIN
					 */
					//Set button HTML, but don't make clickable if color is gray
					$this_button = "<img src='" . APP_PATH_IMAGES . "$this_color' style='height:16px;width:16px;' class='imgfix2'>";
					print "<a href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . urlencode($displayid) . "&event_id={$row['event_id']}&page={$row['form_name']}" . ($this_instance > 1 ? "&instance=$this_instance" : "") . ((isset($_GET['auto']) && $auto_inc_set) ? "&auto=1" : "") . "'>$this_button</a>";
					/**
					 * RESUME PLUGIN
					 */
					if (isset($count_form_events['value']) && $count_form_events['value'] >= 1) {
						$detail_hint_field_query = "SELECT DISTINCT m2.field_name FROM redcap_metadata m2
				WHERE m2.project_id = '$project_id'
				AND (m2.field_name LIKE '%term' OR m2.field_name LIKE '%dsncomplt' OR m2.field_name LIKE '%cmtrt' OR m2.field_name LIKE '%trtout' OR m2.field_name LIKE '%name' OR m2.field_name LIKE '%acn' OR m2.field_name LIKE '%nxfile' OR m2.field_name LIKE '%endtc')
				AND m2.form_name = '" . $row['form_name'] . "'";
						$detail_hint_field_result = db_query($detail_hint_field_query);
						if ($detail_hint_field_result) {
							while ($detail_hint_field = db_fetch_assoc($detail_hint_field_result)) {
								if (isset($detail_hint_field['field_name'])) {
									$detail_hint_query = "SELECT b.value as hint from redcap_data b
							WHERE b.project_id = '$project_id'
							AND b.record = '$displayid'
							AND b.field_name = '{$detail_hint_field['field_name']}'
							AND b.value != ''
							AND b.event_id = '{$row['event_id']}' LIMIT 1";
									$detail_hint_result = db_query($detail_hint_query);
									if ($detail_hint_result) {
										while ($detail_hint = db_fetch_assoc($detail_hint_result)) {
											if ($detail_hint['hint'] == 'OTHER') {
												unset ($detail_hint['hint']);
											}
											if (isset($detail_hint['hint'])) {
												if (strrchr($detail_hint_field['field_name'], '_') == '_nxfile') {
													$file_name_result = db_query("SELECT doc_name FROM redcap_edocs_metadata WHERE doc_id = {$detail_hint['hint']}");
													if ($file_name_result) {
														$file_name = db_fetch_assoc($file_name_result);
														$detail_hint['hint'] = $file_name['doc_name'];
													}
												}
												if (strlen($detail_hint['hint']) > 10) {
													$detail_hint['hint'] = substr($detail_hint['hint'], 0, 10);
												}
												print "<div><a style='font-weight:normal;font-size:10px;' href='" . APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=" . urlencode($displayid) . "&event_id={$row['event_id']}&page={$row['form_name']}'>{$detail_hint['hint']}</a></div>";
											}
										}
										db_free_result($detail_hint_result);
									}
								}
							}
							db_free_result($detail_hint_field_result);
						}
					}
					/**
					 * END PLUGIN
					 */
					//Display lock icon for any forms that are locked for this record
					if ($this_color != "gray" && isset($locked_forms[$row['event_id'] . "," . $row['form_name']])) {
						print $locked_forms[$row['event_id'] . "," . $row['form_name']];
					}
				}
				print "</td>";
			}
		}
		//Set for next loop
		$prev_form = $row['form_name'];
	}

	print  "</tr>";
	print  "</table>";

	/**
	 * get query string vars needed to control column loads
	 */
	$cols = $_GET['cols'] ? $_GET['cols'] + 1 : 76;
	$show_cols_url = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'] . "&cols=";
	?>
	<script type="text/javascript">
		$(document).ready(function () {
			$("#FixedTableHdrsEnable").hide();
			var range_array = function (a, b) {
				d = [];
				c = b - a + 1;
				while (c--) {
					d[c] = b--
				}
				return d
			};
			var table = $('table#event_grid_table').DataTable({
				"paging": false,
				"ordering": false,
				"info": false,
				"autoWidth": false
			});
			var nColumns = $('table#event_grid_table thead tr th').length - 1;
			var nLoadStart = <?php echo $cols ?>;
			$("#event_grid_table_filter").before("<div><button id='showcols' class='jqbuttonsm ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only' type='button'><span class='ui-button-text'>Show All Events</span></button></div>");
			if (nColumns >= nLoadStart) {
				table.columns(range_array(nLoadStart, nColumns)).visible(false, false);
			} else {
				$('#showcols').hide();
			}
			fixed_header = new $.fn.dataTable.FixedHeader(table, {
				left: true
			});
			$('#showcols').on('click', function () {
				if (!table.column(nColumns).visible()) {
					var showColsUrl = '<?php echo $show_cols_url ?>';
					window.location = showColsUrl + nColumns;
				}
			});
			/*table.on('search.dt', function () {
				fixed_header.fnDisable();
				fixed_header = new $.fn.dataTable.FixedHeader(table, {
					left: true
				});
			}).draw();*/
		});
	</script>
	<?php

	## LOCK / UNLOCK RECORDS
	//If user has ability to lock a record, give option to lock it for all forms and all time-points (but ONLY if the record exists)
	if ($existing_record && $user_rights['lock_record_multiform'] && $user_rights['lock_record'] > 0) {
		//Show link "Lock all forms"
		print  "	<div style='text-align:center;padding:20px 0 5px;max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "lock.png' class='imgfix'>
						<a style='color:#A86700;font-weight:bold;font-size:12px' href='javascript:;' onclick=\"
							lockUnlockForms('$id','" . RCView::escape($_GET['id']) . "','','" . $arm . "','1','lock');
						\">{$lang['grid_28']} &nbsp;&nbsp;&nbsp;</a>
					</div>";
		//Show link "Unlock all forms"
		print  "	<div style='text-align:center;padding:5px 0 0;max-width:700px;'>
						<img src='" . APP_PATH_IMAGES . "lock_open.png' class='imgfix'>
						<a style='color:#666;font-weight:bold;font-size:12px' href='javascript:;' onclick=\"
							lockUnlockForms('$id','" . RCView::escape($_GET['id']) . "','','" . $arm . "','1','unlock');
						\">{$lang['grid_29']}</a>
					</div>";
	}
	/* 		
	## FORM LOCKING POP-UP FOR E-SIGNATURE
	if ($user_rights['lock_record'] > 1) 
	{
		include APP_PATH_DOCROOT . "Locking/esignature_popup.php";
	}	
	*/
}






################################################################################
## PAGE WITH RECORD ID DROP-DOWN
else
{
	// Get total record count
	$num_records = Records::getRecordCount();

	// Get extra record count in user's data access group, if they are in one
	if ($user_rights['group_id'] != "") {
		$sql = "select count(distinct(record)) from redcap_data where project_id = " . PROJECT_ID . " and field_name = '$table_pk'"
			. " and record != '' and record in (" . pre_query("select record from redcap_data where project_id = " . PROJECT_ID
				. " and field_name = '__GROUPID__' and value = '{$user_rights['group_id']}'") . ")";
		$num_records_group = db_result(db_query($sql), 0);
	}

	// If more records than a set number exist, do not render the drop-downs due to slow rendering.
	$search_text_label = $lang['grid_35'] . " " . RCView::escape($table_pk_label);
	if ($num_records > $maxNumRecordsHideDropdowns) {
		// If using auto-numbering, then bring back text box so users can auto-suggest to find existing records	.
		// The negative effect of this is that it also allows users to [accidentally] bypass the auto-numbering feature.
		if ($auto_inc_set) {
			$search_text_label = $lang['data_entry_121'] . " " . RCView::escape($table_pk_label);
		}
		// Give extra note about why drop-down is not being displayed
		$search_text_label .= RCView::div(array('style' => 'padding:10px 0 0;font-size:10px;font-weight:normal;color:#555;'),
			$lang['global_03'] . $lang['colon'] . " " . $lang['data_entry_172'] . " " .
			User::number_format_user($maxNumRecordsHideDropdowns, 0) . " " .
			$lang['data_entry_173'] . $lang['period']
		);
	}

	/**
	 * ARM SELECTION DROP-DOWN (if more than one arm exists)
	 */
	//Loop through each ARM and display as a drop-down choice
	$arm_dropdown_choices = "";
	if ($multiple_arms) {
		foreach ($Proj->events as $this_arm_num => $arm_attr) {
			//Render option
			$arm_dropdown_choices .= "<option";
			//If this tab is the current arm, make it selected
			if ($this_arm_num == $arm) {
				$arm_dropdown_choices .= " selected ";
			}
			$arm_dropdown_choices .= " value='$this_arm_num'>{$lang['global_08']} {$this_arm_num}{$lang['colon']} {$arm_attr['name']}</option>";
		}
	}

	// Page instructions and record selection table with drop-downs
	?>
	<p style="margin-bottom:20px;">
		<?php echo $lang['grid_38'] ?>
		<?php echo ($auto_inc_set) ? $lang['data_entry_96'] : $lang['data_entry_97']; ?>
	</p>

	<style type="text/css">
		.data {
			padding: 7px;
			width: 400px;
		}
	</style>

	<table class="form_border" style="width:700px;">
		<!-- Header displaying record count -->
		<tr>
			<td class="header" colspan="2" style="font-weight:normal;padding:10px 5px;color:#800000;font-size:12px;">
				<?php echo $lang['graphical_view_22'] ?> <b><?php echo User::number_format_user($num_records) ?></b>
				<?php if (isset($num_records_group)) { ?>
					&nbsp;/&nbsp; <?php echo $lang['data_entry_104'] ?>
					<b><?php echo User::number_format_user($num_records_group) ?></b>
				<?php } ?>
			</td>
		</tr>
		<?php

		/***************************************************************
		 ** DROP-DOWNS
		 ***************************************************************/
		if ($num_records <= $maxNumRecordsHideDropdowns) {
			print  "<tr>
					<td class='label'>{$lang['grid_31']} " . RCView::escape($table_pk_label) . "</td>
					<td class='data'>";

			// Obtain custom record label & secondary unique field labels for ALL records.
			$extra_record_labels = Records::getCustomRecordLabelsSecondaryFieldAllRecords(array(), true, $arm);
			foreach ($extra_record_labels as $this_record => $this_label) {
				$dropdownid_disptext[removeDDEending($this_record)] .= " $this_label";
			}
			unset($extra_record_labels);

			/**
			 * ARM SELECTION DROP-DOWN (if more than one arm exists)
			 */
			//Loop through each ARM and display as a drop-down choice
			if ($multiple_arms && $arm_dropdown_choices != "") {
				print  "<select id='arm_name' class='x-form-text x-form-field' style='padding-right:0;height:22px;margin-right:20px;' onchange=\"
						if ($('#record').val().length > 0) {
							window.location.href = app_path_webroot+'DataEntry/grid.php?pid=$project_id&id='+$('#record').val()+'&arm='+$('#arm_name').val()+addGoogTrans();
						} else {
							showProgress(1);
							setTimeout(function(){
								window.location.href = app_path_webroot+'DataEntry/grid.php?pid=$project_id&arm='+$('#arm_name').val()+addGoogTrans();
							},500);
						}
					\">
					$arm_dropdown_choices
					</select>";
			}

			/**
			 * RECORD SELECTION DROP-DOWN
			 */
			print  "<select id='record' class='x-form-text x-form-field' style='padding-right:0;height:22px;max-width:350px;' onchange=\"
					window.location.href = app_path_webroot+page+'?pid='+pid+'&arm=$arm&id=' + this.value + addGoogTrans();
				\">";
			print  "	<option value=''>{$lang['data_entry_91']}</option>";
			// Limit records pulled only to those in user's Data Access Group
			if ($user_rights['group_id'] == "") {
				$group_sql = "";
			} else {
				$group_sql = "and record in (" . pre_query("select record from redcap_data where field_name = '__GROUPID__' and
				value = '{$user_rights['group_id']}' and project_id = $project_id") . ")";
			}
			//If a Double Data Entry project, only look for entry-person-specific records by using SQL LIKE
			if ($double_data_entry && $user_rights['double_data'] != 0) {
				//If a designated entry person
				$qsql = "select distinct substring(record,1,locate('--',record)-1) as record FROM redcap_data
					 where project_id = $project_id and record in (" . pre_query("select distinct record from redcap_data where 
					 project_id = $project_id and record like '%--{$user_rights['double_data']}'") . ") $group_sql";
			} else {
				//If NOT a designated entry person OR not double data entry project
				$qsql = "select distinct record FROM redcap_data where project_id = $project_id and field_name = '$table_pk'
					and event_id in (" . prep_implode($Proj->getEventsByArmNum($arm)) . ") $group_sql";
			}
			$QQuery = db_query($qsql);
			while ($row = db_fetch_array($QQuery)) {
				$study_id_array[] = $row['record'];
			}
			natcasesort($study_id_array);
			foreach ($study_id_array as $this_record) {
				// Check for custom labels
				$secondary_pk_text = isset($secondary_pk_disptext[$this_record]) ? $secondary_pk_disptext[$this_record] : "";
				$custom_record_text = isset($dropdownid_disptext[$this_record]) ? $dropdownid_disptext[$this_record] : "";
				//Render drop-down options
				print "<option value='{$this_record}'>{$this_record}{$secondary_pk_text}{$dropdownid_disptext[$this_record]}</option>";
			}
			print  "</select>";

			print  "</td></tr>";
		}

		//User defines the Record ID
		if ((!$auto_inc_set && $user_rights['record_create']) || ($auto_inc_set && $num_records > $maxNumRecordsHideDropdowns)) {
			// Check if record ID field should have validation
			$text_val_string = "";
			if ($Proj->metadata[$table_pk]['element_type'] == 'text' && $Proj->metadata[$table_pk]['element_validation_type'] != '') {
				// Apply validation function to field
				$text_val_string = "if(redcap_validate(this,'{$Proj->metadata[$table_pk]['element_validation_min']}','{$Proj->metadata[$table_pk]['element_validation_max']}','hard','" . convertLegacyValidationType($Proj->metadata[$table_pk]['element_validation_type']) . "',1)) ";
			}
			//Text box for next records
			?>
			<tr>
				<td class="label">
					<?php echo $search_text_label ?>
				</td>
				<td class="data" style="width:400px;">
					<input id="inputString" type="text" class="x-form-text x-form-field" style="position:relative;">
				</td>
			</tr>
			<?php
		}

		// Auto-number button(s) - if option is enabled
		if ($auto_inc_set)// && $num_records <= $maxNumRecordsHideDropdowns)
		{
			$autoIdBtnText = $lang['data_entry_46'];
			if ($multiple_arms) {
				$autoIdBtnText .= $lang['data_entry_99'];
			}
			?>
			<tr>
				<td class="label">&nbsp;</td>
				<td class="data">
					<!-- New record button -->
					<button
						onclick="window.location.href=app_path_webroot+page+'?pid='+pid+'&id=<?php echo getAutoId() ?>&auto=1&arm='+($('#arm_name_newid').length ? $('#arm_name_newid').val() : '<?php echo $arm ?>');return false;"><?php echo $autoIdBtnText ?></button>
				</td>
			</tr>
			<?php
		}

		if ($Proj->metadata[$table_pk]['element_type'] != 'text') {
			// Error if first field is NOT a text field
			?>
			<tr>
				<td colspan="2"
				    class="red"><?php echo RCView::b($lang['global_48'] . $lang['colon']) . " " . $lang['data_entry_180'] . " <b>$table_pk</b> (\"" . RCView::escape($table_pk_label) . "\")" . $lang['period'] ?></td>
			</tr>
			<?php
		}

		print "</table>";

		// Display search utility
		renderSearchUtility();

		?>
		<br><br>

		<script type="text/javascript">
			// Enable validation and redirecting if hit Tab or Enter
			$(function () {
				$('#inputString').keypress(function (e) {
					if (e.which == 13) {
						$('#inputString').trigger('blur');
						return false;
					}
				});
				$('#inputString').blur(function () {
					var refocus = false;
					var idval = trim($('#inputString').val());
					if (idval.length < 1) {
						return;
					}
					if (idval.length > 100) {
						refocus = true;
						alert('<?php echo remBr($lang['data_entry_186']) ?>');
					}
					if (refocus) {
						setTimeout(function () {
							document.getElementById('inputString').focus();
						}, 10);
					} else {
						$('#inputString').val(idval);
						<?php echo $text_val_string ?>
						setTimeout(function () {
							idval = $('#inputString').val();
							idval = idval.replace(/&quot;/g, ''); // HTML char code of double quote
							// Don't allow pound signs in record names
							if (/#/g.test(idval)) {
								$('#inputString').val('');
								alert("Pound signs (#) are not allowed in record names! Please enter another record name.");
								$('#inputString').focus();
								return false;
							}
							// Don't allow apostrophes in record names
							if (/'/g.test(idval)) {
								$('#inputString').val('');
								alert("Apostrophes (') are not allowed in record names! Please enter another record name.");
								$('#inputString').focus();
								return false;
							}
							// Don't allow ampersands in record names
							if (/&/g.test(idval)) {
								$('#inputString').val('');
								alert("Ampersands (&) are not allowed in record names! Please enter another record name.");
								$('#inputString').focus();
								return false;
							}
							// Don't allow plus signs in record names
							if (/\+/g.test(idval)) {
								$('#inputString').val('');
								alert("Plus signs (+) are not allowed in record names! Please enter another record name.");
								$('#inputString').focus();
								return false;
							}
							// Redirect, but NOT if the validation pop-up is being displayed (for range check errors)
							if (!$('.simpleDialog.ui-dialog-content:visible').length)
								window.location.href = app_path_webroot + page + '?pid=' + pid + '&arm=<?php echo (($arm_dropdown_choices != "") ? "'+ $('#arm_name_newid').val() +'" : $arm) ?>&id=' + idval + addGoogTrans();
						}, 200);
					}
				});
			});
		</script>
		<?php


		//Using double data entry and auto-numbering for records at the same time can mess up how REDCap saves each record.
		//Give warning to turn one of these features off if they are both turned on.
		if ($double_data_entry && $auto_inc_set) {
			print "<div class='red' style='margin-top:20px;'><b>{$lang['global_48']}</b><br>{$lang['data_entry_56']}</div>";
		}

		// If multiple Arms exist, use javascript to pop in the drop-down listing the Arm names to choose from for new records
		if ($arm_dropdown_choices != "" && ((!$auto_inc_set && $user_rights['record_create'])
				|| ($auto_inc_set && $num_records > $maxNumRecordsHideDropdowns))
		) {
			print  "<script type='text/javascript'>
				$(function(){
					$('#inputString').before('" . cleanHtml("<select id='arm_name_newid' onchange=\"if (!$('select#arm_name').length){ window.location.href=window.location.href+'&arm='+this.value; return; } editAutoComp(autoCompObj,this.value);\" class='x-form-text x-form-field' style='padding-right:0;height:22px;margin-right:20px;'>$arm_dropdown_choices</select>") . "');
				});
				</script>";
		}

		//If project is a prototype, display notice for users telling them that no real data should be entered yet.
		if ($status < 1) {
			print  "<br>
				<div class='yellow' style='font-family:arial;width:550px;'>
					<img src='" . APP_PATH_IMAGES . "exclamation_orange.png' class='imgfix'>
					<b style='font-size:14px;'>{$lang['global_03']}:</b><br>
					{$lang['data_entry_28']}
				</div>";
		}

		}


		// Render JavaScript for record selecting auto-complete/auto-suggest
		?>
		<script type="text/javascript">
			var autoCompObj;
			$(function () {
				if ($('#inputString').length) {
					autoCompObj = $('#inputString').autocomplete({
						serviceUrl: app_path_webroot + 'DataEntry/auto_complete.php?pid=' + pid + '&arm=' + ($('#arm_name_newid').length ? $('#arm_name_newid').val() : '<?php echo $arm ?>'),
						deferRequestBy: 0
					});
				}
			});
			function editAutoComp(autoCompObj, val) {
				autoCompObj.disable();
				var autoCompObj = $('#inputString').autocomplete({
					serviceUrl: app_path_webroot + 'DataEntry/auto_complete.php?pid=' + pid + '&arm=' + val,
					deferRequestBy: 0
				});
			}
		</script>
<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
