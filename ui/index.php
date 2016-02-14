<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"/>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script src="https://cdn.datatables.net/1.10.10/js/jquery.dataTables.min.js"></script>
<script src="jquery-bonsai/jquery.bonsai.js"></script>
<script src="pdf.js/pdf.js"></script>
<script src="https://cdn.datatables.net/select/1.1.0/js/dataTables.select.min.js"></script>
<script src="mypdfdb.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css"/>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.10/css/jquery.dataTables.min.css"/>
<link rel="stylesheet" href="jquery-bonsai/jquery.bonsai.css"/>
<link rel="stylesheet" href="https://cdn.datatables.net/select/1.1.0/css/select.dataTables.min.css"/>
<link rel="stylesheet" href="mypdfdb.css"/>
<title>MyPDF DB</title>
</head>

<body>

<dialog id="addTagDialog" title="Add new tag">
  <label>Tag Name:</label>
  <input name="tag" type="text">
  <label>Parent Tag Name:</label>
  <select name="parent">
  <select>
  <label>Description:</label>
  <input name="description" type="text">
  <a class="addTagDialogCancel button">cancel</a>
  <a class="addTagDialogSubmit button">add tag</a>
</dialog>

<dialog id="updatePdfDialog" title="Update PDF">
  <label>Title:</label>
  <input name="title" type="text">
  <label>Date:</label>
  <input name="date" type="date">
  <label>Origin:</label>
  <input name="origin" type="text">
  <label>Recipient:</label>
  <input name="recipient" type="text">
  
  <div>
    <a class="updatePdfDialogCancel button">cancel</a>
    <a class="updatePdfDialogSubmit button">update</a>
  </div>
  
  <div>
    <a class="viewPDFPrev button">Previous</a>
    <a class="viewPDFNext button">Next</a>
    &nbsp; &nbsp;
    <span>Page: <span class="viewPDFPageNum"></span> / <span class="viewPDFPageCount"></span></span>
  </div>

  <div>
    <canvas id="viewPDFCanvas" style="border:1px solid black"></canvas>
  </div>
</dialog>

<header>
	<h1>myPDF DB</h1>
</header>

<div class="searchbar">
</div>

<div class="optionsbar">
	<ul class="tagTree">
	</ul>	
	<a class="searchWithTags button">search</a>
	<a class="associateTags button">tag</a>
	<a class="disassociateTags button">untag</a>
	<a class="addTag button">add</a>
	<a class="editTag button">edit</a>
	<a class="deleteTags button">delete</a>
</div>
<div class="queryview">
	<table id="pdfInfoTableContainer" class="display" width="100%">
		<thead>
			<th>id</th>
			<th>tags</th>
			<th>title</th>
			<th>date</th>
			<th>md5</th>
			<th>pages</th>
			<th>origin</th>
			<th>recipient</th>
		</thead>
	</table>
</div>
<div class="controlbar">
	<a class="showAllPDFs button">all</a>
	<a class="showDupes button">dupes</a>
	<a class="editPDF button">edit</a>
	<a class="mergePDFs button">merge</a>
	<a class="deletePDFs button">delete</a>
	<a class="downloadPDF button">download</a>
</div>
</body>

</html>
