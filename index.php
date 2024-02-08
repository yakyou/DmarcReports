<?php
require_once('sqlite.php');
function h($string) {
	return htmlspecialchars($string);
}
$reports = $db->query('
	SELECT * 
	FROM reports 
	INNER JOIN report_records 
	ON reports.report_metadata_org_name = report_records.report_metadata_org_name 
	AND reports.report_metadata_report_id = report_records.report_metadata_report_id
	ORDER BY reports.report_metadata_date_range_begin DESC, report_records.num
');
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>DMARC Reports</title>
<style>
	body {
		font-size: small;
	}
	th, td {
		border: solid 1px;
		padding: 1px 3px;
	}
	table {
		border-collapse:  collapse;
		white-space: nowrap;
	}
</style>
</head>
<body>

<form enctype="multipart/form-data" action="./upload.php" method="post">
<input name="userfile" type="file" />
<input type="submit" value="ファイルを送信" />
</form>

<br />

<table>
	<tr>
		<th colspan="6" rowspan="1">report_metadata</th>
		<th colspan="8" rowspan="2">policy_published</th>
		<th colspan="8" rowspan="1">row</th>
		<th colspan="3" rowspan="2">identifiers</th>
		<th colspan="7" rowspan="1">auth_results</th>
	</tr>
	<tr>
		<th colspan="1" rowspan="2">org_name</th>
		<th colspan="1" rowspan="2">email</th>
		<th colspan="1" rowspan="2">extra_contact_info</th>
		<th colspan="1" rowspan="2">report_id</th>
		<th colspan="2" rowspan="1">date_range</th>
		<th colspan="1" rowspan="2">source_ip</th>
		<th colspan="1" rowspan="2">souece_hostname</th>
		<th colspan="1" rowspan="2">count</th>
		<th colspan="5" rowspan="1">policy_evaluated</th>
		<th colspan="4" rowspan="1">dkim</th>
		<th colspan="3" rowspan="1">spf</th>
	</tr>
	<tr>
		<th>begin</th>
		<th>end</th>
		<th>domain</th>
		<th>adkim</th>
		<th>aspf</th>
		<th>p</th>
		<th>sp</th>
		<th>pct</th>
		<th>np</th>
		<th>fo</th>
		<th>disposition</th>
		<th>dkim</th>
		<th>spf</th>
		<th>reason_type</th>
		<th>reason_comment</th>
		<th>envelope_to</th>
		<th>envelope_from</th>
		<th>header_from</th>
		<th>domain</th>
		<th>selector</th>
		<th>result</th>
		<th>human_result</th>
		<th>domain</th>
		<th>scope</th>
		<th>result</th>
	</tr>
<?php 
while ($report = $reports->fetchArray()) {
	echo '<tr>';
	echo '<td>' . h($report['report_metadata_org_name']) . '</td>';
	echo '<td>' . h($report['report_metadata_email']) . '</td>';
	echo '<td>' . h($report['report_metadata_extra_contact_info']) . '</td>';
	echo '<td>' . h($report['report_metadata_report_id']) . '</td>';
	echo '<td>' . date("m-d H:i", $report['report_metadata_date_range_begin']) . '</td>';
	echo '<td>' . date("m-d H:i", $report['report_metadata_date_range_end']) . '</td>';
	echo '<td>' . h($report['policy_published_domain']) . '</td>';
	echo '<td>' . h($report['policy_published_adkim']) . '</td>';
	echo '<td>' . h($report['policy_published_aspf']) . '</td>';
	echo '<td>' . h($report['policy_published_p']) . '</td>';
	echo '<td>' . h($report['policy_published_sp']) . '</td>';
	echo '<td>' . h($report['policy_published_pct']) . '</td>';
	echo '<td>' . h($report['policy_published_np']) . '</td>';
	echo '<td>' . h($report['policy_published_fo']) . '</td>';
	echo '<td>' . h($report['row_source_ip']) . '</td>';
	echo '<td>' . h($report['row_souece_hostname']) . '</td>';
	echo '<td>' . h($report['row_count']) . '</td>';
	echo '<td>' . h($report['row_policy_evaluated_disposition']) . '</td>';
	echo '<td>' . h($report['row_policy_evaluated_dkim']) . '</td>';
	echo '<td>' . h($report['row_policy_evaluated_spf']) . '</td>';
	echo '<td>' . h($report['row_policy_evaluated_reason_type']) . '</td>';
	echo '<td>' . h($report['row_policy_evaluated_reason_comment']) . '</td>';
	echo '<td>' . h($report['identifiers_envelope_to']) . '</td>';
	echo '<td>' . h($report['identifiers_envelope_from']) . '</td>';
	echo '<td>' . h($report['identifiers_header_from']) . '</td>';
	echo '<td>' . h($report['auth_results_dkim_domain']) . '</td>';
	echo '<td>' . h($report['auth_results_dkim_selector']) . '</td>';
	echo '<td>' . h($report['auth_results_dkim_result']) . '</td>';
	echo '<td>' . h($report['auth_results_dkim_human_result']) . '</td>';
	echo '<td>' . h($report['auth_results_spf_domain']) . '</td>';
	echo '<td>' . h($report['auth_results_spf_scope']) . '</td>';
	echo '<td>' . h($report['auth_results_spf_result']) . '</td>';
	echo '</tr>';
}
?>
</table>

</body>
</html>