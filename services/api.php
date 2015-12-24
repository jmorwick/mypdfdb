<?php

////////////////////////////////////////////////////////////////////////////////
// error messages
////////////////////////////////////////////////////////////////////////////////

function err_no_such_service($service) {
	echo "404: no such service: $service";	
	http_response_code(404);
	die();
}

function err_bad_input_format($msg) {
	echo "404: malformed input to service: $msg";	
	http_response_code(404);
	die();
}

function err_bad_input_data($field, $value, $msg) {
	echo "404: bad input value for service: '$field' can't have value '$value': $msg";	
	http_response_code(403);
	die();
}


////////////////////////////////////////////////////////////////////////////////
// begin service handling script
////////////////////////////////////////////////////////////////////////////////

header("Access-Control-Allow-Orgin: *");
header("Access-Control-Allow-Methods: *");

$data_dir = getenv('DATA_DIR');
$db = new SQLite3($data_dir.'/.mypdfdb');

// extract arguments from request
$args = explode('/', rtrim($_GET['request'], '/'));
$endpoint = array_shift($args);
unset($_GET['request']);
$extra_args = $_GET;
$method = $_SERVER['REQUEST_METHOD'];

// TODO: clean input arguments

switch($method) {
	case 'GET':
		switch($endpoint) {
			case 'search':
				search_db($args, $_GET);
				die();
			case 'pdf':
				retrieve_pdf($args);
			default: err_no_such_service($endpoint);
		}
	case 'POST':
		switch($endpoint) {
			default: err_no_such_service($endpoint);
		}
	case 'PUT':
		switch($endpoint) {
			default: err_no_such_service($endpoint);
		}
	case 'DELETE':
		switch($endpoint) {
			default: err_no_such_service($endpoint);
		}
	default: err_no_such_service($endpoint);
}


////////////////////////////////////////////////////////////////////////////////
// helper functions
////////////////////////////////////////////////////////////////////////////////

function is_tag($str) {
	return false;  // TODO: check for existence of tag in DB
}

function retrieve_pdf() {
	global $data_dir, $args;
	
	if(count($args) != 1)
		err_bad_input_format("expected one argument in URL");
	
	$details = get_pdf_details($args[0]);
	if(!$details)
		err_bad_input_data('file_id', $args[0], 'not a valid pdf id');
	
	$filename = $details['path'];
	if(!$filename) err_bad_input_data('file_id', $args[0], "pdf file missing from database");
	
	header('Content-Type: application/pdf');
	header("Content-Disposition:attachment;filename='$filename'");
	readfile($data_dir.'/'.$filename);
	exit();
}

function get_pdf_details($id) {
	global $db;
	if(is_numeric($id)) {
		$res = $db->query("SELECT * FROM files WHERE id = '$id'");
		return $res->fetchArray(SQLITE3_ASSOC);
	}
	return false;
}

////////////////////////////////////////////////////////////////////////////////
// servicing functions
////////////////////////////////////////////////////////////////////////////////

function search_db() {
	global $args, $db;
	
	if(count($args) > 1) 
		err_bad_input_format("exactly one url argument expected");
	
	// validate tag argument
	if(count($args) == 1 || isset($extra_args['tag'])) { // validate tag
		$tag = isset($args['tag']) ? $extra_args['tag'] : $args[0];
		if(!is_tag($tag)) 
			err_bad_input_data('tag', $tag, "not a valid tag");
	}
	
	
	header("Content-Type: application/json");
	$res = $db->query("SELECT * FROM files");
	$pdfs = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdfs[] = $row;
	echo json_encode($pdfs);
}

?>
