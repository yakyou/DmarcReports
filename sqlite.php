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
			PRIMARY KEY(report_metadata_org_name, report_metadata_report_id)
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
		$ret2 = $db->exec('CREATE TABLE report_records (
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