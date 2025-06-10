<?php
//SQLiteの接続とテーブルの存在確認までやるよ
class MyDB extends SQLite3 {
	function __construct() {
		$this->open(dirname(__FILE__) . '/reports.sqlite3');
	}
}
$db = new MyDB();

$results = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE TYPE='table' AND name='reports'");
while ($row = $results->fetchArray()) {
	if ($row[0] == 0) {
		$ret = $db->exec('CREATE TABLE reports (
			report_metadata_org_name, 
			report_metadata_email, 
			report_metadata_extra_contact_info, 
			report_metadata_report_id, 
			report_metadata_date_range_begin, 
			report_metadata_date_range_end, 
			policy_published_domain, 
			policy_published_adkim, 
			policy_published_aspf, 
			policy_published_p, 
			policy_published_sp, 
			policy_published_pct, 
			policy_published_np, 
			policy_published_fo, 
			version, 
			PRIMARY KEY(report_metadata_email, report_metadata_report_id)
		)');
		if (!$ret) {
			echo 'reportsテーブルの作成に失敗しました。';
			exit;
		}
	}
}
$results = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE TYPE='table' AND name='report_records'");
while ($row = $results->fetchArray()) {
	if ($row[0] == 0) {
		$ret = $db->exec('CREATE TABLE report_records (
			report_metadata_email, 
			report_metadata_report_id, 
			num,
			row_source_ip, 
			row_souece_hostname, /* 独自追加 */
			row_count, 
			row_policy_evaluated_disposition, 
			row_policy_evaluated_dkim, 
			row_policy_evaluated_spf, 
			row_policy_evaluated_reason_type, 
			row_policy_evaluated_reason_comment, 
			identifiers_envelope_to, 
			identifiers_envelope_from, 
			identifiers_header_from, 
			auth_results_dkim_domain, 
			auth_results_dkim_selector, 
			auth_results_dkim_result, 
			auth_results_dkim_human_result, 
			auth_results_spf_domain, 
			auth_results_spf_scope, 
			auth_results_spf_result, 
			PRIMARY KEY(report_metadata_email, report_metadata_report_id, num)
		)');
		if (!$ret) {
			echo 'report_recordsテーブルの作成に失敗しました。';
			exit;
		}
	}
}
$results = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE TYPE='table' AND name='ipinfos'");
while ($row = $results->fetchArray()) {
	if ($row[0] == 0) {
		$ret = $db->exec('CREATE TABLE ipinfos (
			ip, 
			hostname, 
			city, 
			region, 
			country, 
			loc, 
			org, 
			postal, 
			timezone, 
			readme, 
			created, 
			modified, 
			PRIMARY KEY(ip)
		)');
		if (!$ret) {
			echo 'ipinfosテーブルの作成に失敗しました。';
			exit;
		}
	}
}

//バージョンチェック
$isVersion1 = true;
$results = $db->query("PRAGMA table_info('report_records')");
while ($row = $results->fetchArray()) {
	if ($row['name'] == 'report_metadata_email') {
		//バージョンアップ済み
		$isVersion1 = false;
	}
}
//バージョンアップ処理
if ($isVersion1) {
	//移行用テーブル作成
	$ret = $db->exec('DROP TABLE IF EXISTS temp_reports');
	$ret = $db->exec('CREATE TABLE temp_reports (
		report_metadata_org_name, 
		report_metadata_email, 
		report_metadata_extra_contact_info, 
		report_metadata_report_id, 
		report_metadata_date_range_begin, 
		report_metadata_date_range_end, 
		policy_published_domain, 
		policy_published_adkim, 
		policy_published_aspf, 
		policy_published_p, 
		policy_published_sp, 
		policy_published_pct, 
		policy_published_np, 
		policy_published_fo, 
		version, 
		PRIMARY KEY(report_metadata_org_name, report_metadata_report_id)
	)');
	if (!$ret) {
		echo 'temp_reportsテーブルの作成に失敗しました。';
		exit;
	}	
	$ret = $db->exec('DROP TABLE IF EXISTS temp_report_records');
	$ret = $db->exec('CREATE TABLE temp_report_records (
		report_metadata_org_name, 
		report_metadata_report_id, 
		num,
		row_source_ip, 
		row_souece_hostname, /* 独自追加 */
		row_count, 
		row_policy_evaluated_disposition, 
		row_policy_evaluated_dkim, 
		row_policy_evaluated_spf, 
		row_policy_evaluated_reason_type, 
		row_policy_evaluated_reason_comment, 
		identifiers_envelope_to, 
		identifiers_envelope_from, 
		identifiers_header_from, 
		auth_results_dkim_domain, 
		auth_results_dkim_selector, 
		auth_results_dkim_result, 
		auth_results_dkim_human_result, 
		auth_results_spf_domain, 
		auth_results_spf_scope, 
		auth_results_spf_result, 
		PRIMARY KEY(report_metadata_org_name, report_metadata_report_id, num)
	)');
	if (!$ret) {
		echo 'temp_report_recordsテーブルの作成に失敗しました。';
		exit;
	}
	//一時用データコピー
	$ret = $db->exec('INSERT INTO temp_reports SELECT * FROM reports');
	if (!$ret) {
		echo 'temp_reportsテーブルへのデータコピーに失敗しました。';
		exit;
	}
	$ret = $db->exec('INSERT INTO temp_report_records SELECT * FROM report_records');
	if (!$ret) {
		echo 'temp_report_recordsテーブルへのデータコピーに失敗しました。';
		exit;
	}
	//データ調整
	$ret = $db->exec('ALTER TABLE temp_report_records ADD COLUMN report_metadata_email');
	if (!$ret) {
		echo 'temp_report_recordsテーブルへのカラム追加に失敗しました。';
		exit;
	}
	$tempReports = $db->query('SELECT report_metadata_email, report_metadata_org_name, report_metadata_report_id FROM temp_reports');
	while ($tempReport = $tempReports->fetchArray()) {
		$ret = $db->exec('UPDATE temp_report_records SET 
			report_metadata_email = \'' . $db->escapeString($tempReport['report_metadata_email']) . '\' 
			WHERE report_metadata_org_name = \'' . $db->escapeString($tempReport['report_metadata_org_name']) . '\' 
			AND report_metadata_report_id = \'' . $db->escapeString($tempReport['report_metadata_report_id']) . '\' 
		');
		if (!$ret) {
			echo 'temp_report_recordsテーブルへtemp_reportsからの情報追加に失敗しました。';
			exit;
		}
	}
	//旧テーブル破棄
	$ret = $db->exec('DROP TABLE reports');
	if (!$ret) {
		echo '旧バージョンreportsテーブルの削除に失敗しました。';
		exit;
	}
	$ret = $db->exec('DROP TABLE report_records');
	if (!$ret) {
		echo '旧バージョンreport_recordsテーブルの削除に失敗しました。';
		exit;
	}
	//新テーブル作成
	$ret = $db->exec('CREATE TABLE reports (
		report_metadata_org_name, 
		report_metadata_email, 
		report_metadata_extra_contact_info, 
		report_metadata_report_id, 
		report_metadata_date_range_begin, 
		report_metadata_date_range_end, 
		policy_published_domain, 
		policy_published_adkim, 
		policy_published_aspf, 
		policy_published_p, 
		policy_published_sp, 
		policy_published_pct, 
		policy_published_np, 
		policy_published_fo, 
		version, 
		PRIMARY KEY(report_metadata_email, report_metadata_report_id)
	)');
	if (!$ret) {
		echo '新バージョンreportsテーブルの作成に失敗しました。';
		exit;
	}
	$ret = $db->exec('CREATE TABLE report_records (
		report_metadata_email, 
		report_metadata_report_id, 
		num,
		row_source_ip, 
		row_souece_hostname, 
		row_count, 
		row_policy_evaluated_disposition, 
		row_policy_evaluated_dkim, 
		row_policy_evaluated_spf, 
		row_policy_evaluated_reason_type, 
		row_policy_evaluated_reason_comment, 
		identifiers_envelope_to, 
		identifiers_envelope_from, 
		identifiers_header_from, 
		auth_results_dkim_domain, 
		auth_results_dkim_selector, 
		auth_results_dkim_result, 
		auth_results_dkim_human_result, 
		auth_results_spf_domain, 
		auth_results_spf_scope, 
		auth_results_spf_result, 
		PRIMARY KEY(report_metadata_email, report_metadata_report_id, num)
	)');
	if (!$ret) {
		echo '新バージョンreport_recordsテーブルの作成に失敗しました。';
		exit;
	}
	//最終データコピー
	$ret = $db->exec('INSERT INTO reports (
		report_metadata_org_name, 
		report_metadata_email, 
		report_metadata_extra_contact_info, 
		report_metadata_report_id, 
		report_metadata_date_range_begin, 
		report_metadata_date_range_end, 
		policy_published_domain, 
		policy_published_adkim, 
		policy_published_aspf, 
		policy_published_p, 
		policy_published_sp, 
		policy_published_pct, 
		policy_published_np, 
		policy_published_fo, 
		version 
		)
		SELECT 
		report_metadata_org_name, 
		report_metadata_email, 
		report_metadata_extra_contact_info, 
		report_metadata_report_id, 
		report_metadata_date_range_begin, 
		report_metadata_date_range_end, 
		policy_published_domain, 
		policy_published_adkim, 
		policy_published_aspf, 
		policy_published_p, 
		policy_published_sp, 
		policy_published_pct, 
		policy_published_np, 
		policy_published_fo, 
		version 
		FROM temp_reports
	');
	if (!$ret) {
		echo 'temp_reportsテーブルへのデータコピーに失敗しました。';
		exit;
	}
	$ret = $db->exec('INSERT INTO report_records (
		report_metadata_email, 
		report_metadata_report_id, 
		num,
		row_source_ip, 
		row_souece_hostname, 
		row_count, 
		row_policy_evaluated_disposition, 
		row_policy_evaluated_dkim, 
		row_policy_evaluated_spf, 
		row_policy_evaluated_reason_type, 
		row_policy_evaluated_reason_comment, 
		identifiers_envelope_to, 
		identifiers_envelope_from, 
		identifiers_header_from, 
		auth_results_dkim_domain, 
		auth_results_dkim_selector, 
		auth_results_dkim_result, 
		auth_results_dkim_human_result, 
		auth_results_spf_domain, 
		auth_results_spf_scope, 
		auth_results_spf_result 
		) 
		SELECT 
		report_metadata_email, 
		report_metadata_report_id, 
		num,
		row_source_ip, 
		row_souece_hostname, 
		row_count, 
		row_policy_evaluated_disposition, 
		row_policy_evaluated_dkim, 
		row_policy_evaluated_spf, 
		row_policy_evaluated_reason_type, 
		row_policy_evaluated_reason_comment, 
		identifiers_envelope_to, 
		identifiers_envelope_from, 
		identifiers_header_from, 
		auth_results_dkim_domain, 
		auth_results_dkim_selector, 
		auth_results_dkim_result, 
		auth_results_dkim_human_result, 
		auth_results_spf_domain, 
		auth_results_spf_scope, 
		auth_results_spf_result 
		FROM temp_report_records
	');
	if (!$ret) {
		echo 'temp_report_recordsテーブルへのデータコピーに失敗しました。';
		exit;
	}
	//移行用テーブル削除
	$ret = $db->exec('DROP TABLE IF EXISTS temp_reports');
	$ret = $db->exec('DROP TABLE IF EXISTS temp_report_records');
}