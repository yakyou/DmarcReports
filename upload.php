<?php
//zipやgzのままアップロード
require_once('ini.php');
require_once('sqlite.php');

//zipやgzの中にあるxmlファイルの内容を取り出す
$xmlstr = '';
if (!empty($_FILES['userfile']['type']) && $_FILES['userfile']['tmp_name']) {
	if ($_FILES['userfile']['type'] == 'application/x-zip-compressed') {
		$z = new ZipArchive();
		if ($z->open($_FILES['userfile']['tmp_name'])) {
			for ($i = 0; $i < $z->numFiles; $i++) {
				$filename = $z->getNameIndex($i);
				$fp = $z->getStream($filename);
				while (($buffer = fgets($fp)) !== false) {
					$xmlstr .= $buffer;
				}
			}
			fclose($fp);
		}
	} else if ($_FILES['userfile']['type'] == 'application/x-gzip') {
		$fp = gzopen($_FILES['userfile']['tmp_name'], 'rb');
		while (($buffer = fgets($fp)) !== false) {
			$xmlstr .= $buffer;
		}
		gzclose($fp);
	}
} else {
	header('Location: ' . './');
	exit;
}

//xmlの内容を処理する
$report = array();
$reportRecords = array();
if (!empty($xmlstr)) {
	$dmarcReport = simplexml_load_string($xmlstr);
	foreach ($dmarcReport as $key1 => $value1) {
		if ($key1 != 'record') {
			if (isString($value1)) {
				$report[$key1] = (string)$value1;
			} else {
				foreach ($value1 as $key2 => $value2) {
					if (isString($value2)) {
						$report[$key1 . '_' . $key2] = (string)$value2;
					} else {
						foreach ($value2 as $key3 => $value3) {
							if (isString($value3)) {
								$report[$key1 . '_' . $key2 . '_' . $key3] = (string)$value3;
							} else {
								foreach ($value3 as $key4 => $value4) {
									if (isString($value4)) {
										//本来ここまで来ないはず（未知の項目検出用）
										$report[$key1 . '_' . $key2 . '_' . $key3 . '_' . $key4] = (string)$value4;
									}
								}
							}
						}
					}
				}	
			}	
		}
	}
	$num = 0;
	foreach ($dmarcReport->record as $key1 => $value1) {
		if (isString($value1)) {
		} else {
			foreach ($value1 as $key2 => $value2) {
				if (isString($value2)) {
					$reportRecords[$num][$key2] = (string)$value2;
				} else {
					foreach ($value2 as $key3 => $value3) {
						if (isString($value3)) {
							$reportRecords[$num][$key2 . '_' . $key3] = (string)$value3;
						} else {
							foreach ($value3 as $key4 => $value4) {
								if (isString($value4)) {
									$reportRecords[$num][$key2 . '_' . $key3 . '_' . $key4] = (string)$value4;
								} else {
									foreach ($value4 as $key5 => $value5) {
										if (isString($value5)) {
											//本来ここまで来ないはず（未知の項目検出用）
											$reportRecords[$num][$key2 . '_' . $key3 . '_' . $key4 . '_' . $key5] = (string)$value5;
										}
									}
								}
							}
						}
					}
				}
			}	
		}
		$num++;
	}
} else {
	header('Location: ' . './');
	exit;
}

//処理済みか確認する
$reportsCount = $db->query("SELECT COUNT(*) FROM reports WHERE report_metadata_org_name = '" . $db->escapeString($report['report_metadata_org_name']) . "' AND report_metadata_report_id = '" . $db->escapeString($report['report_metadata_report_id']) . "'");
while ($count = $reportsCount->fetchArray()) {
	if ($count[0] > 0) {
		//処理済み
		header('Location: ' . './');
		exit;
	}
}

//カラムが存在するか確認する
//DBのカラム一覧作成
$reportsDbCols = array();
$reportsTableInfo = $db->query("PRAGMA table_info('reports')");
while ($row = $reportsTableInfo->fetchArray()) {
	$reportsDbCols[] = $row['name'];
}
$reportRecordsDbCols = array();
$reportRecordsTableInfo = $db->query("PRAGMA table_info('report_records')");
while ($row = $reportRecordsTableInfo->fetchArray()) {
	$reportRecordsDbCols[] = $row['name'];
}
//XMLから変換したカラム一覧作成
$reportsXmlCols = array();
foreach ($report as $col => $val) {
	$reportsXmlCols[] = $col;
}
$reportRecordsXmlCols = array();
foreach ($reportRecords as $reportRecord) {
	foreach ($reportRecord as $col => $val) {
		$reportRecordsXmlCols[] = $col;
	}
}
//比較
$reportsDiff = array_diff($reportsXmlCols, $reportsDbCols);
$reportRecordsDiff = array_diff($reportRecordsXmlCols, $reportRecordsDbCols);
if (!empty($reportsDiff) || !empty($reportRecordsDiff)) {
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
	if (!empty($reportsDiff)) {
		echo 'テーブル reports にカラム ';
		foreach ($reportsDiff as $reportsDiffCol) {
			echo $reportsDiffCol . ' ';
		}
		echo ' が存在しません。<br />';
	}
	if (!empty($reportRecordsDiff)) {
		echo 'テーブル report_records にカラム ';
		foreach ($reportRecordsDiff as $reportRecordsDiffCol) {
			echo $reportRecordsDiffCol . ' ';
		}
		echo ' が存在しません。<br />';
	}
	echo 'DBに登録できません。';
	echo '</body></html>';
	exit;
}

//DBへの書込
$db->exec('begin');
$reportsCols = '';
$reportsVals = '';
foreach ($report as $col => $val) {
	if (!empty($reportsCols) && !empty($reportsVals)) {
		$reportsCols .= ', ';
		$reportsVals .= ', ';
	}
	$reportsCols .= $col;
	$reportsVals .= "'" . $db->escapeString($val) . "'";
}
$sql = 'INSERT INTO reports (' . $reportsCols . ') VALUES (' . $reportsVals . ')';
if (!$db->exec($sql)) {
	$db->exec('rollback');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(reports)の書込に失敗しました。</body></html>';
	exit;
}
foreach ($reportRecords as $num => $reportRecord) {
	$reportRecordsCols = '';
	$reportRecordsVals = '';
	$reportRecordsCols .= 'report_metadata_org_name';
	$reportRecordsVals .= "'" . $db->escapeString($report['report_metadata_org_name']) . "'";
	$reportRecordsCols .= ', ';
	$reportRecordsVals .= ', ';
	$reportRecordsCols .= 'report_metadata_report_id';
	$reportRecordsVals .= "'" . $db->escapeString($report['report_metadata_report_id']) . "'";
	$reportRecordsCols .= ', ';
	$reportRecordsVals .= ', ';
	$reportRecordsCols .= 'num';
	$reportRecordsVals .= $db->escapeString($num);
	foreach ($reportRecord as $col => $val) {
		if (!empty($reportRecordsCols) && !empty($reportRecordsVals)) {
			$reportRecordsCols .= ', ';
			$reportRecordsVals .= ', ';
		}
		$reportRecordsCols .= $col;
		$reportRecordsVals .= "'" . $db->escapeString($val) . "'";
		if ($col == 'row_source_ip') {
			$hostname = gethostbyaddr2($val);
			$reportRecordsCols .= ', ';
			$reportRecordsVals .= ', ';
			$reportRecordsCols .= 'row_souece_hostname';
			$reportRecordsVals .= "'" . $db->escapeString($hostname) . "'";
		}
	}
	$sql = 'INSERT INTO report_records (' . $reportRecordsCols . ') VALUES (' . $reportRecordsVals . ')';
	if (!$db->exec($sql)) {
		$db->exec('rollback');
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(report_records)の書込に失敗しました。</body></html>';
		exit;
	}
}
$db->exec('COMMIT');
header('Location: ' . './');
exit;

function isString($value) {
	if (!empty($value)) {
		if (!settype($value, "string")) {
			return false;	
		}
		$value = trim($value);
		if (empty($value)) {
			return false;
		}
	}
	return true;
}

function gethostbyaddr2($ip) {
	global $ipinfotoken;
	global $db;
	if (empty($ipinfotoken)) {
		return gethostbyaddr($ip);
	} else {
		//保存済みの情報の呼び出し
		$mode = 'insert'; // or update or none
		$savedipinfo = array();
		$dbipinfos = $db->query("SELECT * FROM ipinfos WHERE ip = '" . $db->escapeString($ip) . "'");
		while ($dbipinfo = $dbipinfos->fetchArray()) {
			$savedipinfo = $dbipinfo;
			$mode = 'update';
		}
		//ip情報の取得
		$ipinfo = file_get_contents('https://ipinfo.io/' . $ip . '?token=' . $ipinfotoken);
		if ($ipinfo === false) {
			return $ip;
		}
		$json = json_decode($ipinfo);

		if ($mode == 'insert') {
			//新規IP情報保存
			$ipinfoCols = "ip";
			$ipinfoVals = "'" . $db->escapeString($json->ip) . "'";
			if (!empty($json->hostname)) {
				$ipinfoCols .= ", hostname";
				$ipinfoVals .= ", '" . $db->escapeString($json->hostname) . "'";
			}
			if (!empty($json->city)) {
				$ipinfoCols .= ", city";
				$ipinfoVals .= ", '" . $db->escapeString($json->city) . "'";
			}
			if (!empty($json->region)) {
				$ipinfoCols .= ", region";
				$ipinfoVals .= ", '" . $db->escapeString($json->region) . "'";
			}
			if (!empty($json->country)) {
				$ipinfoCols .= ", country";
				$ipinfoVals .= ", '" . $db->escapeString($json->country) . "'";
			}
			if (!empty($json->loc)) {
				$ipinfoCols .= ", loc";
				$ipinfoVals .= ", '" . $db->escapeString($json->loc) . "'";
			}
			if (!empty($json->org)) {
				$ipinfoCols .= ", org";
				$ipinfoVals .= ", '" . $db->escapeString($json->org) . "'";
			}
			if (!empty($json->postal)) {
				$ipinfoCols .= ", postal";
				$ipinfoVals .= ", '" . $db->escapeString($json->postal) . "'";
			}
			if (!empty($json->timezone)) {
				$ipinfoCols .= ", timezone";
				$ipinfoVals .= ", '" . $db->escapeString($json->timezone) . "'";
			}
			if (!empty($json->readme)) {
				$ipinfoCols .= ", readme";
				$ipinfoVals .= ", '" . $db->escapeString($json->readme) . "'";
			}
			$ipinfoCols .= ", created";
			$ipinfoVals .= ", '" . date('Y-m-d H:i:s') . "'";
			$ipinfoCols .= ", modified";
			$ipinfoVals .= ", '" . date('Y-m-d H:i:s') . "'";
			$sql = 'INSERT INTO ipinfos (' . $ipinfoCols . ') VALUES (' . $ipinfoVals . ')';
			if (!$db->exec($sql)) {
				$db->exec('rollback');
				echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(ipinfos)の書込に失敗しました。</body></html>';
				exit;
			}
		} else if ($mode == 'update') {
			//既存IP情報更新
			$sqlCols = '';
			if (empty($savedipinfo['hostname'])) {
				if (!empty($json->hostname)) {
					$sqlCols .= "hostname = '" . $db->escapeString($json->hostname) . "', ";
				}
			} else {
				if (empty($json->hostname)) {
					$sqlCols .= "hostname = NULL, ";
				} else {
					if ($savedipinfo['hostname'] != $json->hostname) {
						$sqlCols .= "hostname = '" . $db->escapeString($json->hostname) . "', ";
					}
				}
			}
			if (empty($savedipinfo['city'])) {
				if (!empty($json->city)) {
					$sqlCols .= "city = '" . $db->escapeString($json->city) . "', ";
				}
			} else {
				if (empty($json->city)) {
					$sqlCols .= "city = NULL, ";
				} else {
					if ($savedipinfo['city'] != $json->city) {
						$sqlCols .= "city = '" . $db->escapeString($json->city) . "', ";
					}
				}
			}
			if (empty($savedipinfo['region'])) {
				if (!empty($json->region)) {
					$sqlCols .= "region = '" . $db->escapeString($json->region) . "', ";
				}
			} else {
				if (empty($json->region)) {
					$sqlCols .= "region = NULL, ";
				} else {
					if ($savedipinfo['region'] != $json->region) {
						$sqlCols .= "region = '" . $db->escapeString($json->region) . "', ";
					}
				}
			}
			if (empty($savedipinfo['country'])) {
				if (!empty($json->country)) {
					$sqlCols .= "country = '" . $db->escapeString($json->country) . "', ";
				}
			} else {
				if (empty($json->country)) {
					$sqlCols .= "country = NULL, ";
				} else {
					if ($savedipinfo['country'] != $json->country) {
						$sqlCols .= "country = '" . $db->escapeString($json->country) . "', ";
					}
				}
			}
			if (empty($savedipinfo['loc'])) {
				if (!empty($json->loc)) {
					$sqlCols .= "loc = '" . $db->escapeString($json->loc) . "', ";
				}
			} else {
				if (empty($json->loc)) {
					$sqlCols .= "loc = NULL, ";
				} else {
					if ($savedipinfo['loc'] != $json->loc) {
						$sqlCols .= "loc = '" . $db->escapeString($json->loc) . "', ";
					}
				}
			}
			if (empty($savedipinfo['org'])) {
				if (!empty($json->org)) {
					$sqlCols .= "org = '" . $db->escapeString($json->org) . "', ";
				}
			} else {
				if (empty($json->org)) {
					$sqlCols .= "org = NULL, ";
				} else {
					if ($savedipinfo['org'] != $json->org) {
						$sqlCols .= "org = '" . $db->escapeString($json->org) . "', ";
					}
				}
			}
			if (empty($savedipinfo['postal'])) {
				if (!empty($json->postal)) {
					$sqlCols .= "postal = '" . $db->escapeString($json->postal) . "', ";
				}
			} else {
				if (empty($json->postal)) {
					$sqlCols .= "postal = NULL, ";
				} else {
					if ($savedipinfo['postal'] != $json->postal) {
						$sqlCols .= "postal = '" . $db->escapeString($json->postal) . "', ";
					}
				}
			}
			if (empty($savedipinfo['timezone'])) {
				if (!empty($json->timezone)) {
					$sqlCols .= "timezone = '" . $db->escapeString($json->timezone) . "', ";
				}
			} else {
				if (empty($json->timezone)) {
					$sqlCols .= "timezone = NULL, ";
				} else {
					if ($savedipinfo['timezone'] != $json->timezone) {
						$sqlCols .= "timezone = '" . $db->escapeString($json->timezone) . "', ";
					}
				}
			}
			if (empty($savedipinfo['readme'])) {
				if (!empty($json->readme)) {
					$sqlCols .= "readme = '" . $db->escapeString($json->readme) . "', ";
				}
			} else {
				if (empty($json->readme)) {
					$sqlCols .= "readme = NULL, ";
				} else {
					if ($savedipinfo['readme'] != $json->readme) {
						$sqlCols .= "readme = '" . $db->escapeString($json->readme) . "', ";
					}
				}
			}
			if (!empty($sqlCols)) {
				//更新すべき内容があれば更新
				$sqlCols .= "modified = '" . date('Y-m-d H:i:s') . "'";
				$sql = "UPDATE ipinfos SET " . $sqlCols . " WHERE ip = '" . $db->escapeString($json->ip) . "'";
				if (!$db->exec($sql)) {
					$db->exec('rollback');
					echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>DB(ipinfos)の書込に失敗しました。</body></html>';
					exit;
				}
			}
		}
		if (!empty($json->hostname)) {
			return $json->hostname;
		} else {
			return $ip;
		}
	}
}