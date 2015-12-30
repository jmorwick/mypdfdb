var selectedRows = [];
var mainTable = null;

function mypdfdbRowWriter(rowIndex, record, columns, cellWriter) {
  var tr = '';

  // grab the record's attribute for each column
  for (var i = 0, len = columns.length; i < len; i++) {
    tr += cellWriter(columns[i], record);
  }
  var selected = '';
  if(selectedRows.indexOf(record.id) > -1) {
    selected = ' class="selectedRow" ';
  }
  return '<tr onClick="selectRow('+record.id+')" data-id="'+record.id+'"'+selected+'>' + tr + '</tr>';
};

function selectRow(id) {
  var tr = $("[data-id="+id+"]");alert(selectedRows);
  if(selectedRows.indexOf(id) == -1) {
    selectedRows.push(id); 
    tr.addClass('selectedRow');
  }
}

function unselectRow(event) {
  var tr = $(event.target.closest('tr'));
  if(selectedRows.indexOf(tr.attr('data-id')) == -1) {
    selectedRows.push(tr.attr('data-id'));
    tr.addClass('selectedRow');
  }
}

function loadTags() {
  $.ajax({
    url: 'api/tags',
    success: function(tags) {
      var used_tags = [];
      $(".tagTree").empty();
      while(used_tags.length < tags.length) {
        tags.forEach(function (tag_record) {
          if(used_tags.indexOf(tag_record.tag) > -1) {
          } else if(tag_record.parent != null) {
            if(used_tags.indexOf(tag_record.parent) > -1) {
              $("[data-tag="+tag_record.parent+"]")
                .append("<li>"+
                	tag_record.tag+"<ul data-tag='"+tag_record.tag+
                	"'></ul></li>");
              used_tags.push(tag_record.tag);
            }
          } else {
            $(".tagTree").append("<li>"+tag_record.tag+"<ul data-tag='"+tag_record.tag+"'></ul></li>");
            used_tags.push(tag_record.tag);
          }
        });
      }
      $(".tagTree").bonsai({
        createInputs: 'checkbox',
        addSelectAll: 'true'
      });
    }
  });
}

function defaultSort(a, b, attr, direction) {
  return (direction > 0 ? 1 : -1)(a.id-b.id);
};



$(function() { 
    mainTable = $('#pdfInfoTableContainer').DataTable( {
        "ajax": 'api/search/',
        "columns": [
            { "data": "id" },
            { "data": "tags" },
            { "data": "title" },
            { "data": "date" },
            { "data": "pages" },
            { "data": "origin" },
            { "data": "recipient" }
        ],
        "columnDefs": [
            {
                "render": function ( data, type, row ) {
                    return ' ('+ row['id']+')';
                },
                "targets": 6
            },
            {
                "render": function ( data, type, row ) {
                    return row['title'] == null ? row['path'] : row['title'];
                },
                "targets": 2
            },
            { "visible": false,  "targets": [ 0 ] }
        ],
        "select": true
    } );
    
    
    mainTable.on( 'select', function ( e, dt, type, indexes ) {
      var tags = [];
      // find tags in selected rows
      mainTable.rows( { selected: true } ).data()
        .each(function(row){ 
      	  row.tags.forEach(function(tag) {
            if(tags.indexOf(tag) == -1) tags.push(tag);
          });
        });
        
      // unselect all tags
      $("input[type=checkbox]:checked").attr('checked', false);
      
      // select only the tags in the selected rows
      tags.forEach(function(tag) {
        $("[data-tag="+tag+']').siblings('input').click();
      });
    });
    
    $('.searchWithTags').click( function () { 
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      mainTable.ajax.url('api/search/'+selectedTags.join('/'));
      mainTable.ajax.reload();
    });
 
    $('.associateTags').click( function () { 
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      
      $.ajax({
        url: 'api/tag/'+selectedTags.join('-')+'/'+selectedIds.join('-'),
        type: 'PUT',
        success: function(data) {
          mainTable.ajax.reload();
        }
      }); 
    });

    $('.disassociateTags').click( function () { 
      selectedIds = mainTable.rows( { selected: true } ).ids()
        .map(function(row){return row.substring(4);});
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      
      $.ajax({
        url: 'api/untag/'+selectedTags.join('-')+'/'+selectedIds.join('-'),
        type: 'PUT',
        success: function(data) {
          mainTable.ajax.reload();
        }
      }); 
    });
    
    $('.addTag').click( function () { 
      $("#addTagDialog").css("display", "block");
    });
    
    $('.deleteTag').click( function () { 
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      alert("TODO: confirm and then delete selected tags: "+ selectedTags);
    });
    
    $('.addTagDialogSubmit').click( function () {  
      alert("TODO: validate and add tag");
    });
    
    $('.addTagDialogCancel').click( function () { 
      $('#addTagDialog').hide();
    });
    
    loadTags();
});
