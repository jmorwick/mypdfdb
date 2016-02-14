<?php

////////////////////////////////////////////////////////////////////////////////
// initialize global variables
////////////////////////////////////////////////////////////////////////////////

// TODO: check for existance of error functions

if(!$data_dir) $data_dir = getenv('MYPDFDB_DATA_DIR');
if(!$data_dir) {
  die("FATAL ERROR: data directory environment variable not set\n");
} 

if(!file_exists($data_dir) || !is_dir($data_dir)) {
  die("FATAL ERROR: data directory '$data_dir' doesn't exist\n");
}

if(!file_exists($data_dir.'/.mypdfdb')) {
  die("FATAL ERROR: data directory '$data_dir' doesn't contain a .mypdfdb sqlite3 database file\n");
}

$db = new SQLite3($data_dir.'/.mypdfdb');

if(!$db) {
  die("FATAL ERROR: data directory '$data_dir/.mypdfdb' is not a valid sqlite3 database file\n");
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
	global $db;
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
// file manipulation
////////////////////////////////////////////////////////////////////////////////

function get_full_path($path) {
	global $data_dir;
	return realpath($data_dir)."/".$path;
}

function canonicalize_path($path) {
	global $data_dir;
	$data_dir = realpath($data_dir);
	$full_path = realpath(get_full_path($path));
	return substr($full_path, strlen($data_dir)+1);
}

function get_md5_hash($path) {
	return md5_file(get_full_path($path)); 
}

function delete_pdf_file($path) {
	unlink(get_full_path($path));
}

function merge_pdf_files($paths) { 
	global $data_dir;
	if(count($paths) < 2)
		err_bad_input_format("expected at least 2 file paths");
	
    	$i=0;
    	$new_path = $paths[0];
    	while(file_exists(get_full_path($new_path))) {
    	  $i++;
    	  $new_path = substr($new_path, 0, -(($i==1?3:4) + strlen("".($i-1))))."$i.pdf";
    	}
    	$merge_cmd = "gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=";
    	$merge_cmd .= escapeshellarg(get_full_path($new_path));
    	foreach($paths as $path) {
    	  $merge_cmd .= " ".escapeshellarg(get_full_path($path))." ";
    	}
    	$res = `$merge_cmd`;
    	if(!file_exists(get_full_path($new_path))) err_internal("could not create merged file: ".get_full_path($new_path));
        foreach($paths as $path) delete_pdf_file($path);
    	return canonicalize_path($new_path);
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

function get_pdf_id($path) {
	global $db;
	$path = addslashes($path);
	return $db->querySingle("SELECT id FROM files WHERE path = '$path'");
}

function get_pdf_ids_with_hash($md5) {
	global $db;
	$pdf_ids = array();
	$res = $db->query("SELECT id FROM files WHERE md5 = '$md5'");
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function find_pdfs_with_no_tag() {
	global $db;
	$res = $db->query("SELECT id FROM files WHERE path IS NOT NULL AND NOT EXISTS (SELECT * FROM tags WHERE file_id = files.id)");
	$pdf_ids = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function find_pdfs_with_dupes() {
	global $db;
	$res = $db->query("SELECT id FROM files AS f1 WHERE path IS NOT NULL AND EXISTS (SELECT * FROM files AS f2 WHERE f1.id != f2.id AND f1.md5 == f2.md5)");
	$pdf_ids = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function find_pdfs_all() {
	global $db;
	$res = $db->query("SELECT id FROM files WHERE path IS NOT NULL");
	$pdf_ids = array();
	while($row = $res->fetchArray(SQLITE3_ASSOC))
		$pdf_ids[] = $row['id'];
	return $pdf_ids;
}

function orphan_pdf($pdf_id) {
	global $db;
	$db->exec("UPDATE files SET path = NULL WHERE id = $pdf_id");
}

function find_orphaned_pdf_info() {
	global $db;
	$res = $db->query("SELECT id FROM files WHERE path IS NULL");
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

	$pdf_ids = array();
	$clauses = array();
	foreach($tags as $tag) 
		$clauses[] = "tag = '$tag'";
	$res = $db->query("SELECT file_id FROM tags WHERE " . implode(" OR ", $clauses));
	while($row = $res->fetchArray(SQLITE3_ASSOC)) 
		$pdf_ids[] = $row['file_id'];
	return $pdf_ids;
}

function tag_pdf($pdf_id, $tag) {
	global $db;
	$tag_info = get_tag_info($tag);
	if(!$tag_info) err_bad_input_data('tag', $tag, "tag doesn't exist");
	if(!get_pdf_info($pdf_id)) err_bad_input_data('pdf_id', $pdf_id, "pdf doesn't exist");
	
	$db->exec("BEGIN TRANSACTION");	
	while($tag_info = get_tag_info($tag_info['parent'])) // dissassociate with any parent of this tag
		$db->exec("DELETE FROM tags WHERE file_id = $pdf_id AND tag = '$tag'");
	
	if(!$db->querySingle("SELECT * FROM tags WHERE file_id = $pdf_id AND tag = '$tag'")) {
		$db->exec("INSERT INTO tags VALUES ($pdf_id, '$tag')");
	}
	$db->exec("COMMIT TRANSACTION");	
}

function untag_pdf($pdf_id, $tag) {
	global $db;
	$tags = find_child_tags($tag);
	$tags[] = $tag;
	foreach($tags as $tag) {
		$db->exec("DELETE FROM tags WHERE file_id = '$pdf_id' AND tag = '$tag'");
	}
}

function add_pdf_to_db($path, $attributes) {
	global $db;
	$path = canonicalize_path($path);
	if(!file_exists(get_full_path($path)))
		err_bad_input_data('file_path', $path, "file doesn't exist");
	
    	$attributes['md5'] = get_md5_hash($path);    	
    	if(!$attributes['md5']) err_internal("could not generate hash of file $path");
    	// TODO: find number of pages
    	$pages=0;
	
    	$sql="INSERT INTO files VALUES (NULL, '".addslashes($path)."'".
    		', '.($attributes['title'] ? "'".addslashes($attributes['title'])."'":'NULL').
    		', '.($attributes['md5'] ? "'".addslashes($attributes['md5'])."'":'NULL').
    		', '.($attributes['date'] ? "'".addslashes($attributes['date'])."'":'NULL').
    		", ".$pages.
    		', '.($attributes['origin'] ? "'".addslashes($attributes['origin'])."'":'NULL').
    		', '.($attributes['recipient'] ? "'".addslashes($attributes['recipient'])."'":'NULL').
    		")";
    	$db->exec($sql);	
        return $db->lastInsertRowID();
}

function update_pdf_info($pdf_id, $fields) {
	global $db;
	$pdf = get_pdf_info($pdf_id);
	if(!$pdf) err_bad_input_data('pdf_id', $pdf_id, 'not a valid pdf id');
	
	$fields_sql = array();	
	foreach($fields as $field => $value) {
		if(!array_key_exists($field, $pdf) || $field == 'id')
			err_bad_input_data($field, $value, 'not a valid field');
		$fields_sql[] = "`".addslashes($field)."` = ".($value ? "'".addslashes($value)."'" : "NULL");
	} 
	
	$db->exec("UPDATE files SET " . implode($fields_sql, ',') . " WHERE id = $pdf_id");
}

function delete_pdf_metadata($pdf_id) {
	global $db;
	
	$pdf = get_pdf_info($pdf_id);
	if(!get_pdf_info($pdf_id)) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');		
	
	$db->exec("DELETE FROM tags WHERE file_id='$pdf_id'");
	$db->exec("DELETE FROM files WHERE id='$pdf_id'");
}


function delete_pdf($pdf_id) {
	$pdf = get_pdf_info($pdf_id);
	if(!$pdf) err_bad_input_data('pdf_id', $pdf_id, "no such pdf id");
	delete_pdf_metadata($pdf_id);
	delete_pdf_file($pdf['path']);
}

function merge_pdf_metadata($pdf_ids, $path) { 
	if(count($pdf_ids) < 2)
		err_bad_input_format("expected at least 2 pdf ids");
	
	// validate pdf arguments and gather merged pdf attributes
	$attributes = array();
	$tags = array();
	$total_pages = 0;
	foreach($pdf_ids as $pdf_id) {
	  $pdf = get_pdf_info($pdf_id);
	  if(!$pdf) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');	
          
          if(!isset($attributes['title']) && $pdf['title']) 
            $attributes['title'] = $pdf['title'];
          if(!isset($attributes['date']) && $pdf['date']) 
            $attributes['date'] = $pdf['date'];
          if(!isset($attributes['origin']) && $pdf['origin']) 
            $attributes['origin'] = $pdf['origin'];
          if(!isset($attributes['recipient']) && $pdf['recipient']) 
            $attributes['recipient'] = $pdf['recipient'];
          $tags = array_unique(array_merge($tags, find_tags_for_pdf($pdf_id)));
    	}
    	
        foreach($pdf_ids as $pdf_id) delete_pdf_metadata($pdf_id);
    	$new_id = add_pdf_to_db($path, $attributes);
        foreach($tags as $tag) tag_pdf($new_id, $tag);

    	if(!$new_id) err_internal("could not insert merged file to db");
    	
        return $new_id;
}


function merge_pdfs($pdf_ids) { // TODO: this entire function should be gaurded by some sort of mutex	
	if(count($pdf_ids) < 2)
		err_bad_input_format("expected at least 2 pdf ids");
	
	// validate pdf arguments and gather merged pdf attributes
	$paths = array();
	foreach($pdf_ids as $pdf_id) {
	  $pdf = get_pdf_info($pdf_id);
	  if(!$pdf) 
	    err_bad_input_data('pdfid', $pdf_id, 'not a valid pdf id');	
          $paths[] = $pdf['path'];
    	}
    	
    	$new_path = merge_pdf_files($paths);
    	$new_id = merge_pdf_metadata($pdf_ids, $new_path);
    	if(!$new_id) err_internal("could not merge pdfs");
}
