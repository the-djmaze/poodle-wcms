/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/tree"
*/
(K => {

function initItemEditor(li, attr)
{
	var a = li.$Q('a',1), i, t=PAM.tree;
	if (a.i_label) { a.i_label.close(); return; }

	// Disable DnD to allow Firefox selecting text
	t.$Q('li').forEach(li => li.attr('draggable','false'));

	var input_attr = {
		name:'mitem_label',
		value:a.txt(),
		close:function(saved) {
			if (li.attr('data-mitem-id')) {
				a.txt(saved?this.value:this.defaultValue);
				a.i_label = null;
				a.focus();
			} else {
				li.remove();
			}
			// Enable DnD again when nothing is in edit mode
			if (!t.$Q('input').length) {
				t.$Q('li').forEach(li => li.attr('draggable','true'));
			}
		},
		save:function() {
			var i = this;
			if (i.value != i.defaultValue || a.i_uri.value != a.i_uri.defaultValue) {
				i.postValue();
			} else i.close();
		}
	}, p;
	for (p in attr) { input_attr[p] = attr[p]; }
	input_attr.defaultValue = input_attr.value; // IE8
	a.txt('');

	i = a.i_label = a.$A('input',input_attr)
		.on('keyup',function(e){
			if (13==e.keyCode) { this.save(); }
			if (27==e.keyCode) { this.close(); }
		});
	a.$A(a.$D().createTextNode(' uri: '));
	a.i_uri = a.$A('input', {name:'mitem_uri', value:li.attr('data-mitem-uri'), list:'resource_uris'})
		.on('keyup',function(e){
			if (13==e.keyCode) { i.save(); }
			if (27==e.keyCode) { i.close(); }
		});

	// save icon
	i.icon = a.$A('button',{
		type:'button',
		'class':'icon-save',
		input:i,
		title:_("save")
	}).on('click', e => { i.save(); e.stop(); });

	// cancel icon
	a.$A('button',{
		type:'button',
		'class':'icon-cancel',
		input:i,
		title:_("cancel")
	}).on('click', e => { i.close(); e.stop(); });

	i.postValue = () => {
		if (!i.xhr) {
			var id = intval(li.attr('data-mitem-id')), pli = li.getParentByTagName('li');
			i.xhr = new PoodleXHR;
			i.icon.addClass('loading');
			i.attr('disabled','');
			i.xhr.oncomplete = pxhr => {
				try {
					var data=pxhr.fromJSON();
					if (data && data.mitem_id) {
						li.attr('data-mitem-id', data.mitem_id);
						li.attr('data-mitem-uri', data.mitem_uri);
						li.attr('title', data.mitem_uri);
						if (pli) pli.addClass("folds");
						i.close(1);
						PAM.initLI(li);
						return;
					}
				} catch (e) {console.error(e);}
				i.close();
			};
			var data = {
				mitem_id: id,
				parent_id: pli ? intval(pli.attr('data-mitem-id')) : 0
			};
			data[i.name] = i.value;
			data[a.i_uri.name] = a.i_uri.value;
			i.xhr.post(i.href, data);
		}
	};

	i.focus();
}

var PAM =
{
	tree:null,

	init:function()
	{
		this.tree = K.$('edit_menu');
		if (this.tree) {
			var i=-1, li, nodes = this.tree.$Q('li');
			while (li = nodes[++i]) {
				this.initLI(li);
			}
		}
	},

	initLI:function(li)
	{
		if (li._bm_tree) return;
		li._bm_tree = 1;
		var s = K.$C('span',{'class':'details'});
		li.insertBefore(s, li.$Q('ul',1));
		// New sub-item
		s.$A('button',{type:'button','class':'icon-add'}).on('click', this.addItem);
		if (li.$Q('a',1)) {
			// Edit
			s.$A('button',{type:'button','class':'icon-edit'}).on('click', this.editItem);
			// Delete
			s.$A('button',{type:'button','class':'icon-delete'}).on('click', this.deleteItem);
		}
		if (li.attr('data-mitem-id')) {
			li.attr('draggable','true')
				.on('selectstart', e => e.stop())
				.on('dragstart', e => {
					var dt = e.dataTransfer;
					dt.effectAllowed = 'move';
					dt.setData('text', li.attr('data-mitem-id'));
					e.stopPropagation();
					li.mpos = li.getMousePos(e);
				})
				.on('dragenter', e => e.stop())
				.on('dragover', e => {
					var dli = e.getDraggingNode();
					if (dli.attr('data-mitem-id')) {
						if (dli == li) {
							var n, l = intval(li.css('padding-left')), x = (li.getMousePos(e).x - li.mpos.x);
							if (l < x && (n = li.prevElement())) {
								// Moved to the right and make sub of previous element
								n.$Q('ul',1).insertBefore(li, null);
							} else
							if (-l > x && (n = li.getParentByTagName('li')) && !li.nextElement()) {
								// Moved to the left and place after parent element
								li.placeAfter(n);
							}
						} else if (!dli.contains(li)) {
							if (li.clientHeight/2 >= li.getMousePos(e).y) {
								// Move above
								dli.placeBefore(li);
							} else {
								// Move below
								dli.placeAfter(li);
							}
						}
						e.dataTransfer.dropEffect = 'move';
						e.stop();
					}
				})
				.on('drop', e => {
					var dli = e.getDraggingNode(), a = li.$Q('a',1),
					 parent = dli.getParentByTagName('li'),
					 prevli = dli.prevElement(),
					   data = {
							move_item: intval(dli.attr('data-mitem-id')),
							parent_id: parent ? intval(parent.attr('data-mitem-id')) : 0,
							after_id:  prevli ? intval(prevli.attr('data-mitem-id')) : 0
					   };
					a.xhr = new PoodleXHR;
					a.addClass('loading');
					a.xhr.oncomplete = (/*pxhr*/) => {
						a.removeClass('loading');
/*
						var data=pxhr.fromJSON();
						if (data && data.moved) {
							// do something?
						}
*/
					};
					a.xhr.post(null, data);
					e.stop();
				});
		}
	},

	addItem:function()
	{
		var li = this.getParentByTagName('li'),
			ul = li.hasClass('root') ? li.getParentByTagName('ul') : li.$Q('ul',1);
		if (ul) {
			if (li.hasClass('unfolds')) {
				li.replaceClass('unfolds', "folds");
			}
			li = ul.$A('li');
			li.$A('a');
			li.$A('ul');
			initItemEditor(li);
		}
	},

	editItem:function()
	{
		initItemEditor(this.getParentByTagName('li'));
	},

	deleteItem:function()
	{
		var li = this.getParentByTagName('li'), a=li.$Q('a',1);
		if (a.i_label) {
			a.i_label.close();
		} else if (confirm(_("Delete item?"))) {
			a.xhr = new PoodleXHR;
			a.addClass('loading');
			a.xhr.oncomplete = pxhr => {
				try {
					var data=pxhr.fromJSON();
					if (data && data.deleted) {
						li.remove();
						return;
					}
				} catch (e) {console.error(e);}
			};
			a.xhr.post(a.href, 'delete_item='+intval(li.attr('data-mitem-id')));
		}
	}

};

window.Poodle_Admin_Menutree = PAM;

K.onDOMReady(()=>{
	PAM.init();
//	window.on('beforeunload', e => e.stop());
});

})(Poodle);
