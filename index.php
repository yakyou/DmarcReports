<?php
require_once('ini.php');
require_once('sqlite.php');
function h($string) {
	return htmlspecialchars($string);
}
function isMySource($ip) {
	global $mysourceips;
	$ret = '';
	if (!empty($mysourceips)) {
		if (in_array($ip, $mysourceips)) {
			$ret = '&#128522;';
		}
	}
	return $ret;
}
//国コード(ISO 3166-1)を国旗のemojiにします
function code2flag($countryCode) {
	$ret = '';
	if (!empty($countryCode)) {
		$countryCode = str_replace('A', '&#x1f1e6;', $countryCode);
		$countryCode = str_replace('B', '&#x1f1e7;', $countryCode);
		$countryCode = str_replace('C', '&#x1f1e8;', $countryCode);
		$countryCode = str_replace('D', '&#x1f1e9;', $countryCode);
		$countryCode = str_replace('E', '&#x1f1ea;', $countryCode);
		$countryCode = str_replace('F', '&#x1f1eb;', $countryCode);
		$countryCode = str_replace('G', '&#x1f1ec;', $countryCode);
		$countryCode = str_replace('H', '&#x1f1ed;', $countryCode);
		$countryCode = str_replace('I', '&#x1f1ee;', $countryCode);
		$countryCode = str_replace('J', '&#x1f1ef;', $countryCode);
		$countryCode = str_replace('K', '&#x1f1f0;', $countryCode);
		$countryCode = str_replace('L', '&#x1f1f1;', $countryCode);
		$countryCode = str_replace('M', '&#x1f1f2;', $countryCode);
		$countryCode = str_replace('N', '&#x1f1f3;', $countryCode);
		$countryCode = str_replace('O', '&#x1f1f4;', $countryCode);
		$countryCode = str_replace('P', '&#x1f1f5;', $countryCode);
		$countryCode = str_replace('Q', '&#x1f1f6;', $countryCode);
		$countryCode = str_replace('R', '&#x1f1f7;', $countryCode);
		$countryCode = str_replace('S', '&#x1f1f8;', $countryCode);
		$countryCode = str_replace('T', '&#x1f1f9;', $countryCode);
		$countryCode = str_replace('U', '&#x1f1fa;', $countryCode);
		$countryCode = str_replace('V', '&#x1f1fb;', $countryCode);
		$countryCode = str_replace('W', '&#x1f1fc;', $countryCode);
		$countryCode = str_replace('X', '&#x1f1fd;', $countryCode);
		$countryCode = str_replace('Y', '&#x1f1fe;', $countryCode);
		$countryCode = str_replace('Z', '&#x1f1ff;', $countryCode);
	}
	return $countryCode;
}
$reports = $db->query('
	SELECT * 
	FROM reports 
	INNER JOIN report_records 
	ON reports.report_metadata_org_name = report_records.report_metadata_org_name 
	AND reports.report_metadata_report_id = report_records.report_metadata_report_id
	LEFT OUTER JOIN ipinfos 
	ON report_records.row_source_ip = ipinfos.ip 
	ORDER BY reports.report_metadata_date_range_begin DESC
	, reports.report_metadata_org_name
	, reports.report_metadata_report_id
	, ipinfos.country
	, report_records.row_source_ip
	, report_records.num
');
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<script src="https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js" crossorigin="anonymous"></script>
<title>DMARC Reports</title>
<style>
	body {
		font-size: small;
	}
	th, td {
		border: solid 1px;
		padding: 1px 3px;
	}
	td{
		vertical-align: top;
	}
	table {
		border-collapse:  collapse;
		white-space: nowrap;
	}
	img.emoji {
		height: 1em;
		width: 1em;
		margin: 0 .05em 0 .1em;
		vertical-align: -0.1em;
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
		<th colspan="2" rowspan="2">report_metadata</th>
		<th colspan="8" rowspan="2">policy_published</th>
		<th colspan="7" rowspan="1">row</th>
		<th colspan="3" rowspan="2">identifiers</th>
		<th colspan="7" rowspan="1">auth_results</th>
	</tr>
	<tr>
		<th colspan="1" rowspan="2">source_ip</th>
		<th colspan="1" rowspan="2">count</th>
		<th colspan="5" rowspan="1">policy_evaluated</th>
		<th colspan="4" rowspan="1">dkim</th>
		<th colspan="3" rowspan="1">spf</th>
	</tr>
	<tr>
		<th>org_name</th>
		<th>date_range</th>
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
$recordsCount = array();
while ($report = $reports->fetchArray()) {
	if (empty($recordsCount[$report['report_metadata_org_name']][$report['report_metadata_report_id']])) {
		$recordsCount[$report['report_metadata_org_name']][$report['report_metadata_report_id']] = 1;
	} else {
		$recordsCount[$report['report_metadata_org_name']][$report['report_metadata_report_id']]++;
	}
}
$beforeRow = array();
$beforeRow['report_metadata_org_name'] = '';
$beforeRow['report_metadata_report_id'] = '';
while ($report = $reports->fetchArray()) {
	echo '<tr>';
	if (!($beforeRow['report_metadata_org_name'] == $report['report_metadata_org_name'] && $beforeRow['report_metadata_report_id'] == $report['report_metadata_report_id'])) {
		$rowspan = $recordsCount[$report['report_metadata_org_name']][$report['report_metadata_report_id']];
		$orgNameTitle = 'email: ' . h($report['report_metadata_email']);
		if (!empty($report['report_metadata_extra_contact_info'])) {
			$orgNameTitle .= '&#13;&#10;extra_contact_info: ' . h($report['report_metadata_extra_contact_info']);
		}
		echo '<td rowspan="' . $rowspan . '" title="' . $orgNameTitle . '">' . h($report['report_metadata_org_name']) . '</td>';
		echo '<td rowspan="' . $rowspan . '" title="report_id: ' . h($report['report_metadata_report_id']) . '">' . date("m-d H:i", $report['report_metadata_date_range_begin']) . ' to ' . date("m-d H:i", $report['report_metadata_date_range_end']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_domain']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_adkim']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_aspf']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_p']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_sp']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_pct']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_np']) . '</td>';
		echo '<td rowspan="' . $rowspan . '">' . h($report['policy_published_fo']) . '</td>';	
	}
	echo '<td title="' . h($report['row_source_ip'] . ' ' . $report['country']) . '">' . code2flag($report['country']) . isMySource($report['row_source_ip']) . h($report['row_souece_hostname']) . '</td>';
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
	$beforeRow = $report;
}
?>
</table>

<script>
twemoji.parse(document.body);
</script>

</body>
</html>