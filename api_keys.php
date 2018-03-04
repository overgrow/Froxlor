<?php
if (! defined('AREA')) {
	header("Location: index.php");
	exit();
}

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2018 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright (c) the authors
 * @author Froxlor team <team@froxlor.org> (2018-)
 * @license GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package Panel
 * @since 0.10.0
 *
 */

// This file is being included in admin_index and customer_index
// and therefore does not need to require lib/init.php

$log->logAction(USR_ACTION, LOG_NOTICE, "viewed api::api_keys");

// select all my (accessable) certificates
$keys_stmt_query = "SELECT ak.*, c.loginname, a.loginname as adminname
	FROM `" . TABLE_API_KEYS . "` ak
	LEFT JOIN `" . TABLE_PANEL_CUSTOMERS . "` c ON `c`.`customerid` = `ak`.`customerid`
	LEFT JOIN `" . TABLE_PANEL_ADMINS . "` a ON `a`.`adminid` = `ak`.`adminid`
	WHERE ";

$qry_params = array();
if (AREA == 'admin' && $userinfo['customers_see_all'] == '0') {
	// admin with only customer-specific permissions
	$keys_stmt_query .= "ak.adminid = :adminid ";
	$qry_params['adminid'] = $userinfo['adminid'];
	$fields = array(
		'a.loginname' => $lng['login']['username']
	);
} elseif (AREA == 'customer') {
	// customer-area
	$keys_stmt_query .= "ak.customerid = :cid ";
	$qry_params['cid'] = $userinfo['customerid'];
	$fields = array(
		'c.loginname' => $lng['login']['username']
	);
} else {
	// admin who can see all customers / reseller / admins
	$keys_stmt_query .= "1 ";
	$fields = array(
		'a.loginname' => $lng['login']['username']
	);
}

$paging = new paging($userinfo, TABLE_API_KEYS, $fields);
$keys_stmt_query .= $paging->getSqlWhere(true) . " " . $paging->getSqlOrderBy() . " " . $paging->getSqlLimit();

$keys_stmt = Database::prepare($keys_stmt_query);
Database::pexecute($keys_stmt, $qry_params);
$all_keys = $keys_stmt->fetchAll(PDO::FETCH_ASSOC);
$apikeys = "";

if (count($all_keys) == 0) {
	$count = 0;
	$message = $lng['apikeys']['no_api_keys'];
	$sortcode = "";
	$searchcode = "";
	$pagingcode = "";
	eval("\$apikeys.=\"" . getTemplate("api_keys/keys_error", true) . "\";");
} else {
	$count = count($all_keys);
	$paging->setEntries($count);
	$sortcode = $paging->getHtmlSortCode($lng);
	$arrowcode = $paging->getHtmlArrowCode($filename . '?page=' . $page . '&s=' . $s);
	$searchcode = $paging->getHtmlSearchCode($lng);
	$pagingcode = $paging->getHtmlPagingCode($filename . '?page=' . $page . '&s=' . $s);
	
	foreach ($all_keys as $idx => $key) {
		if ($paging->checkDisplay($idx)) {

			// my own key
			$isMyKey = false;
			if ($key['adminid'] == $userinfo['adminid'] && (AREA == 'admin' || (AREA == 'customer' && $key['customerid'] == $userinfo['customerid']))) {
				// this is mine
				$isMyKey = true;
			}

			$adminCustomerLink = "";
			if (AREA == 'admin') {
				if ($isMyKey) {
					$adminCustomerLink = $key['adminname'];
				} else {
					$adminCustomerLink = '&nbsp;(<a href="' . $linker->getLink(array(
						'section' => (empty($key['customerid']) ? 'admins' : 'customers'),
						'page' => (empty($key['customerid']) ? 'admins' : 'customers'),
						'action' => 'su',
						'id' => (empty($key['customerid']) ? $key['adminid'] : $key['customerid'])
					)) . '" rel="external">' . (empty($key['customerid']) ? $key['adminname'] : $key['loginname']) . '</a>)';
				}
			} else {
				// customer do not need links
				$adminCustomerLink = $key['loginname'];
			}
			
			// escape stuff
			$row = htmlentities_array($key);

			// check whether the api key is not valid anymore
			$isValid = true;
			if ($row['valid_until'] >= 0) {
				if ($row['valid_until'] < time()) {
					$isValid = false;
				}
				// format
				$row['valid_until'] = date('d.m.Y H:i', $row['valid_until']);
			} else {
				$row['valid_until'] = "&infin;";
			}
			eval("\$apikeys.=\"" . getTemplate("api_keys/keys_key", true) . "\";");
		} else {
			continue;
		}
	}
}
eval("echo \"" . getTemplate("api_keys/keys_list", true) . "\";");
