<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

if (!auth_isadmin()) {
	auth_block();
}
;

switch (_OP_) {
	case "sandbox":
		$search_category = array(
			_('Time') => 'in_datetime',
			_('From') => 'in_sender',
			_('Content') => 'in_message' 
		);
		$base_url = 'index.php?app=main&inc=feature_report&route=sandbox&op=sandbox';
		$search = themes_search($search_category, $base_url);
		$conditions = array(
			'flag_deleted' => 0,
			'in_status' => 0 
		);
		$keywords = $search['dba_keywords'];
		$count = dba_count(_DB_PREF_ . '_tblSMSIncoming', $conditions, $keywords, '', $join);
		$nav = themes_nav($count, $search['url']);
		$extras = array(
			'ORDER BY' => 'in_id DESC',
			'LIMIT' => $nav['limit'],
			'OFFSET' => $nav['offset'] 
		);
		$list = dba_search(_DB_PREF_ . '_tblSMSIncoming', 'in_id, in_uid, in_sender, in_datetime, in_message', $conditions, $keywords, $extras, $join);
		
		$content = _dialog() . "
			<h2 class=page-header-title>" . _('Sandbox') . "</h2>
			<p>" . $search['form'] . "</p>
			<form id=fm_sandbox name=fm_sandbox action=\"index.php?app=main&inc=feature_report&route=sandbox&op=actions\" method=POST>
			" . _CSRF_FORM_ . "
			<input type=hidden name=go value=delete>
			<div class=playsms-actions-box>
				<div class=pull-left>
					<a href=\"" . _u('index.php?app=main&inc=feature_report&route=sandbox&op=actions&go=export') . "\">" . $icon_config['export'] . "</a>
				</div>
				<div class=pull-right>" . _submit(_('Are you sure you want to delete ?'), 'fm_sandbox', 'delete') . "</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=15%>" . _('Date/Time') . "</th>
				<th width=15%>" . _('From') . "</th>
				<th width=67%>" . _('Content') . "</th>
				<th width=3% class=\"sorttable_nosort\" nowrap><input type=checkbox onclick=CheckUncheckAll(document.fm_sandbox)></th>
			</tr>
			</thead>
			<tbody>";
		
		$i = $nav['top'];
		$j = 0;
		for ($j = 0; $j < count($list); $j++) {
			$list[$j] = core_display_data($list[$j]);
			$in_id = $list[$j]['in_id'];
			$in_uid = $list[$j]['in_uid'];
			$in_sender = $list[$j]['in_sender'];
			$p_desc = phonebook_number2name($in_uid, $in_sender);
			$current_sender = $in_sender;
			if ($p_desc) {
				$current_sender = "$in_sender<br />$p_desc";
			}
			$in_datetime = core_display_datetime($list[$j]['in_datetime']);
			$msg = $list[$j]['in_message'];
			$in_message = core_display_text($msg);
			$reply = '';
			$forward = '';
			if ($msg && $in_sender) {
				$reply = _sendsms($in_sender, $msg);
				$forward = _sendsms('', $msg, $icon_config['forward']);
			}
			$c_message = "<div id=\"sandbox_msg\">" . $in_message . "</div><div id=\"msg_option\">" . $reply . $forward . "</div>";
			$i--;
			$content .= "
				<tr>
					<td>$in_datetime</td>
					<td>$current_sender</td>
					<td>$c_message</td>
					<td nowrap>
						<input type=checkbox name=itemid[] value=\"$in_id\">
					</td>
				</tr>";
		}
		
		$content .= "
			</tbody>
			</table>
			</div>
			<div class=pull-right>" . $nav['form'] . "</div>
			</form>";
		
		_p($content);
		break;
	
	case "actions":
		$nav = themes_nav_session();
		$search = themes_search_session();
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'export':
				$conditions = array(
					'flag_deleted' => 0,
					'in_status' => 0 
				);
				$list = dba_search(_DB_PREF_ . '_tblSMSIncoming', 'in_datetime, in_sender, in_message', $conditions, $search['dba_keywords'], '', $join);
				$data[0] = array(
					_('Time'),
					_('From'),
					_('Content') 
				);
				for ($i = 0; $i < count($list); $i++) {
					$j = $i + 1;
					$data[$j] = array(
						core_display_datetime($list[$i]['in_datetime']),
						$list[$i]['in_sender'],
						$list[$i]['in_message'] 
					);
				}
				$content = core_csv_format($data);
				$fn = 'sandbox-' . $core_config['datetime']['now_stamp'] . '.csv';
				core_download($content, $fn, 'text/csv');
				break;
			
			case 'delete':
				if (isset($_POST['itemid'])) {
					foreach ($_POST['itemid'] as $itemid) {
						$up = array(
							'c_timestamp' => time(),
							'flag_deleted' => '1' 
						);
						dba_update(_DB_PREF_ . '_tblSMSIncoming', $up, array(
							'in_id' => $itemid 
						));
					}
				}
				$ref = $nav['url'] . '&search_keyword=' . $search['keyword'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
				$_SESSION['dialog']['info'][] = _('Selected incoming message has been deleted');
				header("Location: " . _u($ref));
				exit();
		}
		break;
}
