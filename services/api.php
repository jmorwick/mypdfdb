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
				search_db($args);
				exit();
			case 'pdf':
				retrieve_pdf($args);
				exit();
			case 'tags':
				retrieve_tags();
				exit();
			default: err_no_such_service($endpoint);
		}
	case 'POST':
		switch($endpoint) {
			default: err_no_such_service($endpoint);
		}
	case 'PUT':
		switch($endpoint) {
			case 'tag':
				associate_tags($args);
				exit();
			case 'untag':
				disassociate_tags($args);
				exit();
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

function prepare_pdf_record($record) {
	global $db;
	$record['tags'] = array();
	$res = $db->query("SELECT tag FROM tags WHERE file_id = '".$record['id']."'");
	while($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$record['tags'][] = $row['tag'];
	}
	$record['DT_RowId'] = 'row_'.$record['id'];
	return $record;
}

function prepare_tag_record($record) {
	return $record;
}

function is_tag($str) {
	global $db;
	return $db->querySingle("SELECT tag FROM tag_info WHERE tag='$str'");
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

function search_db($args) {
	global $db;
	
	$tag_queue = array();
	$tags = array();
	
	// validate tag arguments
	foreach($args as $tag) {
		if(!is_tag($tag)) 
			err_bad_input_data('tag', $tag, "not a valid tag");
		$tag_queue[] = $tag;
		
	}
	
	// find all child tags
	while(count($tag_queue)) {
		$tag = array_shift($tag_queue);
		$tags[] = $tag;
		$res = $db->query("SELECT tag FROM tag_info WHERE parent = '$tag'");
		while($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$tag_queue[] = $row['tag'];
		}
	}
	
	header("Content-Type: application/json");
	if($tags) {
		$queries = array();
		foreach($tags as $tag) {
			$queries[] = "SELECT files.* FROM files, tags WHERE tags.file_id = files.id AND tags.tag = '$tag'";
		}
		$res = $db->query(implode(" UNION " , $queries));
	} else {
		$res = $db->query("SELECT files.* FROM files");
	}
	$pdfs = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdfs[] = prepare_pdf_record($row);
	echo json_encode(array( 'data' => $pdfs));
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

function retrieve_tags() {
	global $db;
	header("Content-Type: application/json");
	$tags = array();
	$res = $db->query("SELECT * FROM tag_info");
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$tags[] = prepare_pdf_record($row);
	echo json_encode($tags);
}

function associate_tags($args) {
	global $db;
	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdfs = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!is_tag($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdfs as $pdf) 
	  if(!get_pdf_details($pdf)) 
	    err_bad_input_data('pdfid', $pdf, 'not a valid pdf id');
    
    	foreach($pdfs as $pdf) {
    	  foreach($tags as $tag) {
    	    if(!$db->querySingle("SELECT * FROM tags WHERE file_id = '$pdf' AND tag = '$tag'")) {
    	      $db->exec("INSERT INTO tags VALUES ('$pdf', '$tag')");
    	    }
    	  }
    	}
}

function disassociate_tags($args) {
	global $db;
	
	if(count($args) != 2)
		err_bad_input_format("expected two argument in URL (dash-separated list of tag ids followed by dash-separated list of pdf ids)");
	$tags = explode('-',$args[0]);
	$pdfs = explode('-',$args[1]);
	foreach($tags as $tag) 
	  if(!is_tag($tag)) 
	    err_bad_input_data('tagid', $tag, 'not a valid tag id');
	foreach($pdfs as $pdf) 
	  if(!get_pdf_details($pdf)) 
	    err_bad_input_data('pdfid', $pdf, 'not a valid pdf id');
    
    	foreach($pdfs as $pdf) {
    	  foreach($tags as $tag) {
    	    $db->exec("DELETE FROM tags WHERE file_id = '$pdf' AND tag = '$tag'");
    	  }
    	}
}

?>
