<?php

////////////////////////////////////////////////////////////////////////////////
// initialize global variables
////////////////////////////////////////////////////////////////////////////////

// TODO: check for existance of error functions

$data_dir = getenv('MYPDFDB_DATA_DIR');
if(!$data_dir) {
  die("FATAL ERROR: data directory environment variable not set");
} 

if(!file_exists($data_dir) || !is_dir($data_dir)) {
  die("FATAL ERROR: data directory '$data_dir' doesn't exist");
}

if(!file_exists($data_dir.'/.mypdfdb')) {
  die("FATAL ERROR: data directory '$data_dir' doesn't contain a .mypdfdb sqlite3 database file");
}

$db = new SQLite3($data_dir.'/.mypdfdb');

if(!$db) {
  die("FATAL ERROR: data directory '$data_dir/.mypdfdb' is not a valid sqlite3 database file");
}

// TODO: check file permissions


////////////////////////////////////////////////////////////////////////////////
// tag validation, manipulation, and retrieval functions
////////////////////////////////////////////////////////////////////////////////

function get_tag_info($str) {
	global $db;
	return $db->querySingle("SELECT tag FROM tag_info WHERE tag='$str'", true);
}

function create_tag($tag, $parent=null) {
	global $db;
	
	if(get_tag_info($tag))
		err_bad_input_data('tag', $tag, 'already exists');
	
	if($parent != null && !get_tag_info($parent))
		err_bad_input_data('parent', $parent, "doesn't exist");
	
	// TODO: validate tag format (a-z or _ only)
	
	$db->exec("INSERT INTO tag_info VALUES ('".$tag."',".
		($parent ? "'$parent'" : "NULL").",NULL)");
}

function find_child_tags($tag) {
	$tag_queue = array($tag);
	$tags = array();
	while(count($tag_queue)) {
		$tag = array_shift($tag_queue);
		$tags[] = $tag;
		$res = $db->query("SELECT tag FROM tag_info WHERE parent = '$tag'");
		while($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$tag_queue[] = $row['tag'];
		}
	}
	return $tags;
}

function find_parent_tags($tag) {
	$tags = array();
	$tag_info = get_tag_info($tag);
	while($tag_info) {
		$tags[] = $tag_info['tag'];
		$tag_info = get_tag_info($tag_info['parent']);
	}
	return $tags;
}


function find_tags_for_pdf($pdf_id) {
	global $db;
	$tags = array();
	$res = $db->query("SELECT tag FROM tags WHERE file_id = '$pdf_id'");
	while($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$tags = array_merge($tags, find_parent_tags($row['tag']));
	}
	return array_unique($tags);
}

function delete_tag($tag) {
	global $db;
	if(!get_tag_info($tag)) 
		err_bad_input_data('tag', $tag, "not a valid tag");		
	
	$parent = $db->querySingle("SELECT parent FROM tag_info WHERE tag='$tag'");
	$db->exec("BEGIN TRANSACTION");	
	if($parent) {
		$db->exec("UPDATE tags SET tag = '$parent' WHERE tag = '$tag'");
		$db->exec("UPDATE tag_info SET parent = '$parent' WHERE parent = '$tag'");
	} else {
		$db->exec("DELETE FROM tags WHERE tag = '$tag'");
		$db->exec("UPDATE tag_info SET parent = NULL WHERE parent = '$tag'");
	}
		
	$db->exec("DELETE FROM tag_info WHERE tag='$tag'");
	$db->exec("COMMIT TRANSACTION");	
}


////////////////////////////////////////////////////////////////////////////////
// pdf validation, manipulation, and retrieval functions
////////////////////////////////////////////////////////////////////////////////


function get_pdf_info($id) {
	global $db;
	if(is_numeric($id)) {
		$res = $db->query("SELECT * FROM files WHERE id = '$id'");
		return $res->fetchArray(SQLITE3_ASSOC);
	}
	return false;
}


function find_pdfs_with_no_tag() {
	global $db;
	$res = $db->query("SELECT id FROM files WHERE NOT EXISTS (SELECT * FROM tags WHERE file_id = files.id)");
	$pdf_ids = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function find_pdfs_with_tag($tag) {
	global $db;
	
	if(!get_tag_info($tag)) 
		err_bad_input_data('tag', $tag, "not a valid tag");	
	
	// find all child tags
	$tag_queue = array($tag);
	$tags = array();
	while(count($tag_queue)) {
		$tag = array_shift($tag_queue);
		$tags[] = $tag;
		$res = $db->query("SELECT tag FROM tag_info WHERE parent = '$tag'");
		while($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$tag_queue[] = $row['tag'];
		}
	}
	
	if($tags) {
		$queries = array();
		foreach($tags as $tag) {
			$queries[] = "SELECT files.id FROM files, tags WHERE tags.file_id = files.id AND tags.tag = '$tag'";
		}
		$res = $db->query(implode(" UNION " , $queries));
	} else {
		$res = $db->query("SELECT files.id FROM files");
	}
	$pdf_ids = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function tag_pdf($pdf_id, $tag) {
	global $db;
	$tag_info = get_tag_info($tag);
	if(!$tag_info) err_bad_input_data('tag', $tag, "tag doesn't exist");
	
	$db->exec("BEGIN TRANSACTION");	
	while($tag_info = get_tag_info($tag['parent'])) // dissassociate with any parent of this tag
		$db->exec("DELETE FROM tags WHERE file_id = '$pdf' AND tag = '$tag'");
	
	if(!$db->querySingle("SELECT * FROM tags WHERE file_id = '$pdf' AND tag = '$tag'")) {
		$db->exec("INSERT INTO tags VALUES ('$pdf', '$tag')");
	}
	$db->exec("COMMIT TRANSACTION");	
}

function untag_pdf($pdf_id, $tag) {
	global $db;
	$tags = find_child_tags($tag);
	$tags[] = $tag;
	foreach($tags as $tag) {
		$db->exec("DELETE FROM tags WHERE file_id = '$pdf' AND tag = '$tag'");
	}
}

function update_pdf_info($pdf_id, $fields) {
	global $db;
	$pdf = get_pdf_info($pdf_id);
	if(!$pdf) err_bad_input_data('pdf_id', $pdf_id, 'not a valid pdf id');
	
	$fields_sql = array();	
	print_r($fields);
	foreach($fields as $field => $value) {
		if(!array_key_exists($field, $pdf) || in_array($field , array('path', 'md5', 'pages', 'id')))
			err_bad_input_data($field, $value, 'not a valid field');
		$fields_sql[] = "`".addslashes($field)."` = ".($value ? "'".addslashes($value)."'" : "NULL");
	} 
	
	$db->exec("UPDATE files SET " . implode($fields_sql, ',') . " WHERE id = $pdf_id");
}

function delete_pdf_file($filename) {
	global $data_dir;
	unlink($data_dir.'/'.$pdf['path']);
	// TODO: check if dir pdf was in is empty and remove if it is
}

function delete_pdf($pdf_id) {
	global $db;
	
	$pdf = get_pdf_info($pdf_id);
	if(!get_pdf_info($pdf)) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');		
	
	$db->exec("BEGIN TRANSACTION");	
	$db->exec("DELETE FROM tags WHERE file_id='$pdf_id'");
	$db->exec("DELETE FROM files WHERE id='$pdf_id'");
	$db->exec("COMMIT TRANSACTION");	
	delete_pdf_file($pdf['path']);
}

