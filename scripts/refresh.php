<?php

function fatal_error($msg) {
	echo "$msg\n";
	die();
}

function err_bad_input_format($msg) {
	fatal_error("malformed input: $msg");
}

function err_bad_input_data($field, $value, $msg) {
	fatal_error("bad input value: '$field' can't have value '$value': $msg");
}

function err_internal($msg) {
	fatal_error($msg);
}

$data_dir = $argv[1];
require_once("../services/lib.php");
echo "using datadir: $data_dir\n";
$data_dir = realpath($data_dir);
// look at each file in db directory
foreach (new RecursiveIteratorIterator 
	  (new RecursiveDirectoryIterator ($data_dir)) as $file) {
  $path = canonicalize_path(substr($file->getPathname(), strlen($data_dir)));
  if(preg_match("/\.(pdf|PDF)$/", $path)) { // make sure it ends in .pdf
    $id = get_pdf_id($path);
    $md5 = get_md5_hash($path);
    if($id) {   // it already has a db record
      $attributes = get_pdf_info($id);
      if($attributes['md5'] != $md5) { // ...with an incorrect md5 value, correct it.
        unset($attributes['id']);
        echo "fixing md5 info: $id: $path \n";
        $attributes['md5'] = $md5;
        print_r($attributes);
        update_pdf_info($id, $attributes);
      }
    } else {  // it has no db record... insert it
      echo "inserting: $path \n";
      $attributes = array('md5' => $md5);
      print_r($attributes);
      add_pdf_to_db($path, $attributes);
    }
  }
}

$orphans = array();
// check every existing db record
foreach(find_pdfs_all() as $pdf_id) {
    $pdf = get_pdf_info($pdf_id);
    if($pdf) {
      $path = $pdf['path'];
      $cpath = canonicalize_path($path);
      if(!file_exists(get_full_path($path))) { // the file was deleted. keep the data, but make it an orphan (no path)
        echo "*** file $cpath doesn't exist! adding to orphans list\n";
        $orphans[] = $pdf_id;
      } else if($path != $cpath) {  // the path is not a cannonical path... fix it
        echo "path not canoncial: $path -> $cpath\n";
        $other_pdf_id = get_pdf_id($cpath);
        if($other_pdf_id) { // another record is using this file with an alternative path name... merge the records only
          // TODO: merge metadata only -- they're using the same file
          echo "redundant path with $other_pdf_id: merge with $pdf_id \n";
          merge_pdf_metadata(array($pdf_id, $other_pdf_id), $cpath);
        } else {
          echo "update path to be canonical...\n";
          $pdf['path'] = $cpath;
          unset($pdf['id']);
          update_pdf_info($pdf_id, $pdf);
        }
      }

      // check for duplicates of this file and merge if necessary
      $dupes = get_pdf_ids_with_hash($pdf['md5']);
      if(count($dupes) > 1) { // merge with duplicates
        $path = null;
        $dupe_paths = array();
        foreach($dupes as $dupe_id) {
          $dupe_info = get_pdf_info($dupe_id);
          if($path == null)
            $path = $dupe_info['path'];
          else $dupe_paths[] = $dupe_info['path'];
        }
        echo "merging metadata: ";
        print_r($dupes);
        $new_id = merge_pdf_metadata($dupes, $path);
        if($new_id) {
          echo "new pdf id: $new_id\n";
          foreach($dupe_paths as $dupe_path) {
            delete_pdf_file($dupe_path);
          }
        } else {
          echo "merge failed\n";
        }
      }
    }
}

echo "Total records: ".count(find_pdfs_all()). "\n";
echo "orphans: \n";
foreach($orphans as $pdf_id) {
  print_r(get_pdf_info($pdf_id));
  echo "deleting path info (making this pdf an orphan)...";
  orphan_pdf($pdf_id);
}
