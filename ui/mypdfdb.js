function defaultCellWriter(column, record) {
    var html = column.attributeWriter(record),
        td = '<td';

    if (column.hidden || column.textAlign) {
      td += ' style="';

      // keep cells for hidden column headers hidden
      if (column.hidden) {
        td += 'display: none;';
      }

      // keep cells aligned as their column headers are aligned
      if (column.textAlign) {
        td += 'text-align: ' + column.textAlign + ';';
      }

      td += '"';
    }

    return td + '>' + html + '</td>';
};

function loadRecords() {
  $.ajax({
    url: 'api/search',
    success: function(data){
      $('#pdfInfoTableContainer').dynatable({
        dataset: {
          records: data
        }
      });
    }
  });
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
              $("[data-tag="+tag_record.parent+"]").append("<li>"+tag_record.tag+"<ul data-tag='"+tag_record.tag+"'></ul></li>");
              used_tags.push(tag_record.tag);
            }
          } else {
            $(".tagTree").append("<li>"+tag_record.tag+"<ul data-tag='"+tag_record.tag+"'></ul></li>");
            used_tags.push(tag_record.tag);
          }
        });
      }
      $(".tagTree").bonsai();
    }
  });
}


$(function() { 
  $.dynatableSetup({
    writers: {
      tags: function(record) { 
        ret = "<ul class='taglist'>";
        if(typeof record.tags != 'undefined') {
          record.tags.forEach(function (tag) { ret += "<div class='tag'>"+tag+"</div>"; });
        }
      	ret += "</ul>"; 
      	return ret;
      },
      title: function(record) {
        if(record.title != null) {
          return "<span class='titlefield'>"+record.title+"</span>";
        } else {
          return "<span class='titlefield'><span class='defaultvalue'>"+record.path+"</span></span>";
        }
      },
      date: function(record) { return "<span class='datefield'>"+record.date+"</span>";},
      pages: function(record) { return record.pages; },          
      origin: function(record) { return "<span class='originfield'>"+record.origin+"</span>";},
      recipient: function(record) { return "<span class='recipientfield'>"+record.recipient+"</span>";},
      view: function(record) { return "<a class='viewButton' href='api/pdf/"+record.id+"' target='_blank'>view</a>"; }
    }
  });
		
  loadRecords();
  loadTags();
});
