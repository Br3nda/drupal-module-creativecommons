/* 
 * Javascript to toggle display of creative commons form conditions
 * Peter Bull
 * digitalbicycle.org / ltc.org
 * 2004-12-21
 */


function cc_toggle(sender, id) {
  if(id=='cc_conditions') {
    if(sender.type=='radio' && !sender.checked) return;
    if(sender.value!='cc') toggle(id,false);
    else toggle(id,true);
    if(sender.value=='none') toggle('cc_metadata', false);
    else toggle('cc_metadata', true);
  }
  else if(id=='cc_optional') {
    var s = document.getElementById(id).style.display;
    var mi = document.getElementById("moreinfo");
    if(s=='block') {
      toggle(id,false);
      mi.innerHTML = "Click to include more information about your work.";
    }
    else {
      toggle(id,true);
      mi.innerHTML = "Click to hide these fields";
    }
  }
}


function toggle(id, show) {
  var i = document.getElementById(id);
  if(!i || i.length==0) return;
  if(show) i.style.display = "block";
  else i.style.display = "none";
}


function cc_popup(link) {
  window.open(link, 'characteristic_help', 'width=375,height=300,scrollbars=yes,resizable=yes,toolbar=no,directories=no,location=yes,menubar=no,status=yes');
  return false;
}


function cc_autofill(prefix, author) {
  var frm = document.getElementById('node-form');
          
/*           
  var o = '';
  var v = '';
  var io = '';
*/
        
  var p = new String('edit[' + prefix + '][metadata]');
  for (var i=0; i<frm.length; i++) {
    var n = new String(frm[i].name);
    if (n.indexOf(p) == 0) {
      var t = n.substr(p.length+1, n.length-p.length-2);
      //if (t != 'format' && (frm[i].type == 'text' || frm[i].type == 'textarea') && !frm[i].value) {

      if ((frm[i].type == 'text' || frm[i].type == 'textarea') && !frm[i].value) {
        switch (t) {
          case 'title':
            frm[i].value = frm['edit[title]'].value;
            break;

          case 'description':
            frm[i].value = frm['edit[body]'].value;
            break;
  
          case 'creator':
          case 'rights':
           if (author)
             frm[i].value = author;
           break;
          
          case 'date':
            var y = new Date();
            frm[i].value = y.getFullYear();
            break;
        }
      }
    }
  }
}

