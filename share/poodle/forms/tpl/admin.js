/*
	@import "poodle"
*/

(K=>{

function addRow()
{
	this.prevElement().cloneNode(true).placeBefore(this.parent()).$Q('a',1).on('click',removeRow);
}

function removeRow()
{
	this.parent().remove();
}

K.onDOMReady(()=>{
	var n, i=0, nodes = K.$Q('#form-fields .values a');
	while (n = nodes[i++]) { n.on('click', n.hasClass('add')?addRow:removeRow); }

	n = K.$Q('#form-results input[name="del_all"]',1);
	if (n) {
		n.on('change', function(){this.form.checkAll('del[]', !this.checked);});
	}

	n = K.$Q('#form-mailto .add button',1);
	n.on('click',function(){
		var p = this.parent();
		p.cloneNode(true).placeBefore(p).removeClass('add').$Q('button',1).remove();
	});
});

})(Poodle);
