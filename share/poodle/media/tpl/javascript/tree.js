/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/tree"
*/
var Poodle_Mediatree = (K=>{

function getURI(v)
{
	var p = v ? v.indexOf('/poodle_media/') : -1;
	return (-1 < p) ? v.substr(p+14) : '';
}

function initFolderNameEditor(a, input_attr)
{
	a.on('click',function(e){if(this.input){
		if(!this.input.hasFocus()){this.input.focus();}
		e.stop();
	}}).txt('');

	var i = a.input = a.$A('input',input_attr)
		.on('keyup',function(e){
			if (13==e.keyCode) { this.save(); }
			if (27==e.keyCode) { this.close(); }
		});

	// add save icon
	i.icon = a.$A('button',{type:'button','class':'icon-save',input:i})
		.on('click',function(e){this.input.save();e.stop()});

	// add cancel icon
	a.$A('button',{type:'button','class':'icon-cancel',input:i})
		.on('click',function(e){this.input.close();e.stop();});

	i.postValue = function()
	{
		var i = this;
		if (!i.xhr) {
			console.log(i.name+': '+(i.defaultValue?i.defaultValue+' to: ':'')+i.value);
			i.xhr = new PoodleXHR;
			i.icon.addClass('loading');
			i.attr('disabled','');
			i.xhr.oncomplete = function(pxhr){
				try {
					var data=pxhr.fromJSON();
					if (data && data.href) {
						i.href = data.href;
						i.close(1);
						return;
					}
					if (data && data.DOM) {
						var li = K.$C(data.DOM);
						i.li.replaceWith(li);
						Poodle_Mediatree.initFolders(li.parent());
						return;
					}
				} catch (e) { console.error(e); }
				i.close();
			};
			i.xhr.post(i.href, ''.addQueryParam(i.name,i.value));
		}
	};

	K.$('media_tree_col').style.width = '325px';

	return i;
}

K.onDOMReady(()=>{
	Poodle_Mediatree.init();
//	window.on('beforeunload',function(e){e.stop()});
});

return {
	tree:null,

	init:function()
	{
		this.tree = K.$('media_tree');
		if (this.tree) {
			this.tree.on('PoodleTreeModified',function(e){
				Poodle_Mediatree.initFolders(e.detail||this);
			}).trigger('PoodleTreeModified');
		}
	},

	initFolders:function(parent)
	{
		var i=-1, s, li, div, nodes, sub = parent.$Q('li.sub > .details',1);
		if (sub && !sub._bm_tree) {
			sub._bm_tree = 1;
			li = sub.getParentByTagName('li');
			s = K.$C('span',{'class':'options'});
			sub.insertBefore(s, sub.firstChild);
			// New folder
			s.$A('button',{type:'button','class':'icon-add'}).on('click', this.addFolder);
		}

		nodes = parent.$Q('li.unfolds > .details, li.folds > .details');
		while (div=nodes[++i]) {
			if (!div._bm_tree) {
				div._bm_tree = 1;
				li = div.getParentByTagName('li');

				s = K.$C('span',{'class':'options'});
				div.insertBefore(s, div.firstChild);

				// New folder
				s.$A('button',{type:'button','class':'icon-add'}).on('click', this.addFolder);

				if (sub || li.parentNode != Poodle_Mediatree.tree) {
					// Edit folder name
					s.$A('button',{type:'button','class':'icon-edit'}).on('click', this.renameFolder);

					// Delete folder
					s.$A('button',{type:'button','class':'icon-delete'}).on('click', this.deleteFolder);
				}

				// Drag & Drop
				li.on('dragenter', function(e){e.stop();}); // to get IE to work
				li.on('dragover', function(e){
					// or text/plain or text/html?
					if (getURI(e.getDragData('text/uri-list'))) {
						e.dataTransfer.dropEffect = 'move';
						e.stop(); // allows us to drop
					}
				});
				li.on('drop', function(e){
					var li=this, a = e.getDraggingNode(), to = getURI(li.$Q('a',1).href), ul = li.$Q('ul',1);
					if (a && to) {
						console.log('moving item '+getURI(a.href));
						a.xhr = new PoodleXHR;
						a.addClass('loading');
						a.xhr.oncomplete = function(pxhr){
							try {
								var data=pxhr.fromJSON();
								if (data && data.href) {
									a.href = data.href;
									li = a.getParentByTagName('li');
									if (ul && li) {
										ul.$A(li);
									} else {
										li.remove();
									}
									return;
								}
							} catch (e) { console.error(e); }
						};
						a.xhr.post(a.href, ''.addQueryParam('move_to',to));
					}
					e.stop(); // stop the browser from redirecting
				});
			}
		}
	},

	addFolder:function()
	{
		var p=this.getParentByTagName('li'), ul = p.$Q('ul',1), li, a;
		if (p.hasClass('root')) {
			li = K.$C('li',{'class':'unfolds'});
			a = li.$A('a');
			// add input field
			initFolderNameEditor(a, {
				name:'create_folder',
				href:document.location.href,
				li:li,
				close:function(){ this.li.remove(); },
				save:function() { if (this.value) this.postValue(); }
			});
			p.parent().insertBefore(li,null);
			a.input.focus();
		} else
		if (ul) {
			li = K.$C('li',{'class':'unfolds'});
			a = li.$A('a');
			// add input field
			initFolderNameEditor(a, {
				name:'create_folder',
				href:p.$Q('a',1).attr('href'),
				li:li,
				close:function(){ this.li.remove(); },
				save:function() { if (this.value) this.postValue(); }
			});

			ul.insertBefore(li, ul.firstChild);
			a.input.focus();
			Poodle_Mediatree.tree.expandItem(p);
		}
	},

	deleteFolder:function()
	{
		var li = this.getParentByTagName('li'), a=li.$Q('a',1);
		if (a.input) {
			a.input.close();
		} else if (confirm(_("Delete folder?"))) {
			console.log('deleting folder');
			a.xhr = new PoodleXHR;
			a.addClass('loading');
			a.xhr.oncomplete = function(pxhr){
				try {
					var data=pxhr.fromJSON();
					if (data && data.deleted) {
						li.remove();
						return;
					}
				} catch (e) {console.error(e);}
			};
			a.xhr.post(a.href, 'delete_folder=1');
		}
	},

	renameFolder:function()
	{
		var a = this.getParentByTagName('li').$Q('a',1), v;
		if (a.input) {
			a.input.close();
		} else {
			v = a.txt();
			initFolderNameEditor(a, {
				name:'rename_folder',
				value:v,
				href:a.attr('href'),
				a:a,
				close:function(saved) {
					var i = this;
					i.a.attr('href',i.href);
					i.a.txt(saved?i.value:i.defaultValue);
					i.a.input = null;
					i.a.focus();
				},
				save:function() {
					var i = this;
					if (i.value != i.defaultValue) {
						i.postValue();
					} else i.close();
				}
			});
			a.input.focus();
			a.attr('href',null);
		}
	}
};

})(Poodle);
