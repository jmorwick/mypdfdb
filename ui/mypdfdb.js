var selectedRows = [];
var mainTable = null;

// general helpers

function styleTags(tags) {
  return (''+tags).split(",").map(
    function(tag){ 
      return '<div class="tag">'+tag.split('_').join(' ')+'</div>'; 
  }).join("");
}


// bonsai tree stuff for tag tree

var treeLoaded = false;
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
                	(tag_record.tag).split('_').join(' ')+"<ul data-tag='"+tag_record.tag+
                	"'></ul></li>");
              used_tags.push(tag_record.tag);
            }
          } else {
            $(".tagTree").append("<li>"+tag_record.tag+"<ul data-tag='"+tag_record.tag+"'></ul></li>");
            used_tags.push(tag_record.tag);
          }
        });
      }
      if(!treeLoaded) {
        $(".tagTree").bonsai({
          createInputs: 'checkbox',
          addSelectAll: 'true'
        });
      } else {
        $(".tagTree").bonsai('update');
      }
      treeLoaded = true;
    }
  });
}




// initialization
$(function() { 
		
    // init datatable
    
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
            { "visible": false,  "targets": [ 0 ] },
            {
                "render": function ( data, type, row ) { // tags
                    // render spaces, add spaces after each tag in display
                    if(''+row['tags'] == '') return '';
                    return styleTags(row['tags']); 
                },
                "targets": 1
            },
            {
                "render": function ( data, type, row ) {
                    return row['title'] == null ? row['path'] : row['title'];
                },
                "targets": 2
            },
            {
                "render": function ( data, type, row ) {
                    return ' ('+ row['id']+')';
                },
                "targets": 6
            }
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
    
    
    // init tag tree

    loadTags();
    
    
    // button listeners

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
    
    $('.deleteTags').click( function () { 
      selectedTags = $("input[type=checkbox]:checked").siblings('ul')
        .map(function(){return $(this).attr("data-tag");}).get();
      if(selectedTags.length > 0) {
          $('<div></div>').appendTo('body')
            .html('Are you sure you want to delete the tags: '+styleTags(selectedTags)+'? ' + 
              'This will also alter any records using this tag and cannot be (easily) undone.')
            .dialog({
              modal: true,
              title: 'remove tag?',
              zIndex: 1000000,
              autoOpen: true,
              width: 'auto',
              resizable: false,
              buttons: {
                Yes: function () {
                  $.ajax({
                    url: 'api/deletetags/'+selectedTags.join('/'),
                    type: 'DELETE',
                    success: function(data) {
                      loadTags();
                    }
                  });
                  $(this).dialog("close");
                },
                Cancel: function () {
                  $(this).dialog("close");
                }
              },
              close: function (event, ui) {
                $(this).remove();
              }
            });
      }   
    });
    
    $('.addTagDialogSubmit').click( function () {  
      alert("TODO: validate and add tag");
    });
    
    $('.addTagDialogCancel').click( function () { 
      $('#addTagDialog').hide();
    });
});
