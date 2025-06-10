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

//特定のドメインに関する記録を消したい
$deleteDomain = '';
if (!empty($deleteDomain)) {
	$db->exec('begin');
	$deleteTargets = $db->query('
		SELECT reports.report_metadata_email, reports.report_metadata_report_id 
		FROM reports 
		WHERE reports.policy_published_domain = \'' . $db->escapeString($deleteDomain) . '\' 
		ORDER BY reports.report_metadata_report_id
	');
	while ($deleteTarget = $deleteTargets->fetchArray()) {
		$recordsDeleteSql = '
			DELETE FROM report_records 
			WHERE report_metadata_email = \'' . $db->escapeString($deleteTarget['report_metadata_email']) . '\' 
			AND report_metadata_report_id = \'' . $db->escapeString($deleteTarget['report_metadata_report_id']) . '\'
		';
		if (!$db->exec($recordsDeleteSql)) {
			$db->exec('rollback');
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(report_records)の削除に失敗しました。</body></html>';
			exit;
		}
		$reportsDeleteSql = '
			DELETE FROM reports 
			WHERE report_metadata_email = \'' . $db->escapeString($deleteTarget['report_metadata_email']) . '\' 
			AND report_metadata_report_id = \'' . $db->escapeString($deleteTarget['report_metadata_report_id']) . '\'
		';
		if (!$db->exec($reportsDeleteSql)) {
			$db->exec('rollback');
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(reports)の削除に失敗しました。</body></html>';
			exit;
		}
	}
	$db->exec('commit');
}

$domains = $db->query('
	SELECT DISTINCT reports.policy_published_domain
	FROM reports 
	ORDER BY reports.policy_published_domain
');

$orgnames = $db->query('
	SELECT DISTINCT reports.report_metadata_org_name
	FROM reports 
	ORDER BY reports.report_metadata_org_name
');

$query1 = '
	SELECT * 
	FROM reports 
	INNER JOIN report_records 
	ON reports.report_metadata_email = report_records.report_metadata_email 
	AND reports.report_metadata_report_id = report_records.report_metadata_report_id
	LEFT OUTER JOIN ipinfos 
	ON report_records.row_source_ip = ipinfos.ip 
';
$query2 = '';
$queryWhere = '';
if (!empty($_GET['report_metadata_org_name'])) {
	if (!empty($queryWhere)) {
		$queryWhere .= ' AND ';
	}
	$queryWhere .= ' reports.report_metadata_org_name = \'' . $db->escapeString($_GET['report_metadata_org_name']) . '\' ';
}
if (!empty($_GET['policy_published_domain'])) {
	if (!empty($queryWhere)) {
		$queryWhere .= ' AND ';
	}
	$queryWhere .= ' reports.policy_published_domain = \'' . $db->escapeString($_GET['policy_published_domain']) . '\' ';
}
if (!empty($_GET['policy_evaluated'])) {
	if (!empty($queryWhere)) {
		$queryWhere .= ' AND ';
	}
	if ($_GET['policy_evaluated'] == 'fail') {
		$queryWhere .= ' report_records.row_policy_evaluated_dkim = \'fail\' AND report_records.row_policy_evaluated_spf = \'fail\' ';
	} else if ($_GET['policy_evaluated'] == 'pass') {
		$queryWhere .= ' NOT (report_records.row_policy_evaluated_dkim = \'fail\' AND report_records.row_policy_evaluated_spf = \'fail\') ';
	}
}
if (!empty($_GET['source_ip']) && $_GET['source_ip'] == 'mine') {
	if (!empty($mysourceips)) {
		if (!empty($queryWhere)) {
			$queryWhere .= ' AND ';
		}
		$ipList = '';
		foreach ($mysourceips as $mysourceip) {
			if (!empty($ipList)) {
				$ipList .= ', ';
			}
			$ipList .= "'" . $db->escapeString($mysourceip) . "'";
		}
		$queryWhere .= ' report_records.row_source_ip IN (';
		$queryWhere .= $ipList;
		$queryWhere .= ') ';
	}
}
if (!empty($queryWhere)) {
	$query2 = ' WHERE ' . $queryWhere;
}
$query3 = '
	ORDER BY reports.report_metadata_date_range_begin DESC
	, reports.report_metadata_email
	, reports.report_metadata_report_id
	, ipinfos.country
	, report_records.row_source_ip
	, report_records.identifiers_envelope_to
	, report_records.num
';
$reports = $db->query($query1 . $query2 . $query3);

?><!DOCTYPE html>
<html lang="ja">
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

<?php
	$current = '';
	foreach ($_GET as $key => $val) {
		if (empty($current)) {
			$current .= '?';
		} else {
			$current .= '&';
		}
		$current .= h($key) . '=' . h($val);
	}
	if (empty($current)) {
		$current .= './?';
	} else {
		$current = './' . $current . '&';
	}

	echo 'メール受信サービスによる絞り込み : ';
	if (empty($_GET['report_metadata_org_name'])) {
		$orgnameList = '';
		while ($orgname = $orgnames->fetchArray()) {
			if (!empty($orgnameList)) {
				$orgnameList .= ' / ';
			}
			$orgnameList .= '<a href="' . $current . 'report_metadata_org_name=' . h($orgname['report_metadata_org_name']) . '">' . h($orgname['report_metadata_org_name']) . '</a>';
		}
		echo $orgnameList;
	} else {
		echo h($_GET['report_metadata_org_name']);
	}

	$domainCount = 0;
	$domainList = '';
	while ($domain = $domains->fetchArray()) {
		if (!empty($domainList)) {
			$domainList .= ' / ';
		}
		$domainList .= '<a href="' . $current . 'policy_published_domain=' . h($domain['policy_published_domain']) . '">' . h($domain['policy_published_domain']) . '</a>';
		$domainCount++;
	}
	if ($domainCount > 1) {
		echo '<br />メール送信ドメインによる絞り込み : ';
		if (empty($_GET['policy_published_domain'])) {
			echo $domainList;
		} else {
			echo h($_GET['policy_published_domain']);
		}
	}

	echo '<br />判定結果による絞り込み : ';
	if (empty($_GET['policy_evaluated'])) {
		echo '<a href="' . $current . 'policy_evaluated=pass">"pass" を含む</a>';
		echo ' / <a href="' . $current . 'policy_evaluated=fail">"fail" のみ</a>';
	} else {
		if ($_GET['policy_evaluated'] == 'pass') {
			echo '"pass" を含む';
		} else if ($_GET['policy_evaluated'] = 'fail') {
			echo '"fail" のみ';
		}
	}

	if (!empty($mysourceips)) {
		echo '<br />送信元IPアドレスによる絞り込み : ';
		if (empty($_GET['source_ip'])) {
			echo '<a href="' . $current . 'source_ip=mine">わたしのIPアドレス</a>';
		} else if ($_GET['source_ip'] == 'mine') {
			echo 'わたしのIPアドレス';
		}
	}

	if ($current != './?') {
		echo '<br /><a href="./">絞り込み解除</a>';
	}
?>
<br />
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
	if (empty($recordsCount[$report['report_metadata_email']][$report['report_metadata_report_id']])) {
		$recordsCount[$report['report_metadata_email']][$report['report_metadata_report_id']] = 1;
	} else {
		$recordsCount[$report['report_metadata_email']][$report['report_metadata_report_id']]++;
	}
}
$beforeRow = array();
$beforeRow['report_metadata_email'] = '';
$beforeRow['report_metadata_report_id'] = '';
while ($report = $reports->fetchArray()) {
	echo '<tr>';
	if (!($beforeRow['report_metadata_email'] == $report['report_metadata_email'] && $beforeRow['report_metadata_report_id'] == $report['report_metadata_report_id'])) {
		$rowspan = $recordsCount[$report['report_metadata_email']][$report['report_metadata_report_id']];
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