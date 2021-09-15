<?php

// Configuration
require_once('../../admin/config.php');

require_once(DIR_SYSTEM . 'startup.php');
require_once(DIR_SYSTEM . 'library/cart/currency.php');
require_once(DIR_SYSTEM . 'library/cart/user.php');
require_once(DIR_SYSTEM . 'library/cart/weight.php');
require_once(DIR_SYSTEM . 'library/cart/length.php');

// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Store
if (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
	$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`ssl`, 'www.', '') = '" . $db->escape('https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
} else {
	$store_query = $db->query("SELECT * FROM " . DB_PREFIX . "store WHERE REPLACE(`url`, 'www.', '') = '" . $db->escape('http://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . rtrim(dirname($_SERVER['PHP_SELF']), '/.\\') . '/') . "'");
}

if ($store_query->num_rows) {
	$config->set('config_store_id', $store_query->row['store_id']);
} else {
	$config->set('config_store_id', 0);
}

// Settings
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' OR store_id = '" . (int)$config->get('config_store_id') . "' ORDER BY store_id ASC");

foreach ($query->rows as $result) {
	if (!$result['serialized']) {
		$config->set($result['key'], $result['value']);
	} else {
		$config->set($result['key'], json_decode($result['value'], true));
	}
}

if (!$store_query->num_rows) {
	$config->set('config_url', HTTP_SERVER);
	$config->set('config_ssl', HTTPS_SERVER);
}

// Request
$request = new Request();
$registry->set('request', $request);

// Log
$filename = "exchange1c";

if (isset($request->get['type'])) {
	$filename .= "_" . $request->get['type'];
} else {
	$filename .= "_" . $config->get('config_error_filename');
}
$filename .= "_" . date('Ymd', time());

$log = new Log($filename);
$registry->set('log', $log);

// Error Handler
function error_handler($errno, $errstr, $errfile, $errline) {
	global $config, $log;

	if (0 === error_reporting()) return TRUE;
	switch ($errno) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($config->get('config_error_display')) {
		echo '<b>' . $error . '</b>: ' . $errstr . ' in <b>' . $errfile . '</b> on line <b>' . $errline . '</b>';
	}

	if ($config->get('config_error_log')) {
		$log->write('PHP ' . $error . ':  ' . $errstr . ' in ' . $errfile . ' on line ' . $errline);
	}

	return TRUE;
}

// Error Handler
set_error_handler('error_handler');

// Response
$response = new Response();
$response->addHeader('Content-Type: text/html; charset=utf-8');
$registry->set('response', $response);

// Session
$registry->set('session', new Session());

// Cache
$registry->set('cache', new Cache('file'));

// Document
$registry->set('document', new Document());

// Document
$registry->set('encryption', new Encryption($config->get('config_encryption')));

// Language
$language = new Language($config->get('language_default'));
$language->load($config->get('language_default'));
$registry->set('language', $language);

// Currency
$registry->set('currency', new Cart\Currency($registry));

// Weight
$registry->set('weight', new Cart\Weight($registry));

// Length
$registry->set('length', new Cart\Length($registry));

// User
$registry->set('user', new Cart\User($registry));

//OpenBay Pro
$registry->set('openbay', new Openbay($registry));

// Event
$event = new Event($registry);
$registry->set('event', $event);

// Front Controller
$controller = new Front($registry);

// Router
$module = 'extension/module/exchange1c/';
if (isset($request->get['mode']) && $request->get['type'] == 'catalog') {

	switch ($request->get['mode']) {
		case 'checkauth':
			$action = new Action($module.'modeCheckauth');
		break;

		case 'init':
			$action = new Action($module.'modeInit');
		break;

		case 'file':
			$action = new Action($module.'modeFile');
		break;

		case 'import':
			$action = new Action($module.'modeCatalogImport');
		break;

		default:
			echo "success\n";
	}

} else if (isset($request->get['mode']) && $request->get['type'] == 'sale') {

	switch ($request->get['mode']) {
		case 'checkauth':
			$action = new Action($module.'modeCheckauth');
		break;

		case 'init':
			$action = new Action($module.'modeInit');
		break;

		case 'query':
			$action = new Action($module.'modeSaleQuery');
		break;

		case 'file':
			$action = new Action($module.'modeFile');
		break;

		case 'import':
			$action = new Action($module.'modeSaleImport');
		break;

		case 'info':
			$action = new Action($module.'modeSaleInfo');
		break;

		case 'success':
			$action = new Action($module.'modeSaleSuccess');
		break;

		default:
			echo "success\n";

	}

} else if (isset($request->get['mode']) && $request->get['type'] == 'get_catalog') {

	switch ($request->get['mode']) {
		case 'init':
			$action = new Action($module.'modeInitGetCatalog');
		break;

		case 'query':
			$action = new Action($module.'modeQueryGetCatalog');
		break;


		default:
			echo "success\n";

	}


} elseif (isset($request->get['module'])) {
	switch ($request->get['module']) {
		case 'export':
			$action = new Action($module.'modeExportModule');
		break;

		case 'cronImport':
			$action = new Action($module.'cronImport');
		break;

		default:
			echo "available: module=export, module=remove, module=cronImport";
	}

} else {
	$action = new Action($module.'status');
	//echo "Exchange 1c module - status...\n<br>";
//	exit;
}


// Dispatch
if (isset($action)) {
	$controller->dispatch($action, new Action('error/not_found'));
}

// Output
$response->output();
?>
