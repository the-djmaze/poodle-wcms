/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/tree"
	@import "poodle/resize"
*/

var Poodle_Mediatree = (K => {

function setDropZone()
{
	// drag dragstart dragend dragleave drop
	var z = K.$('media-drop-zone'), m = K.$('main-content');
	K.$W().on('drop', function(e){
		e.stop();
		z.setCSSProperty('background-image', null);
//		m.removeClass('dragover');
		var dt = e.dataTransfer, i;
		if (dt.items) {
			// Use DataTransferItemList interface to access the file(s)
			for (i=0; i < dt.items.length; i++) {
				if (dt.items[i].kind == "file") {
					var f = dt.items[i].getAsFile();
					if (0 == dt.items[i].type.indexOf('image/')) {
						var r = new FileReader();
						r.onload = function() {
							z.setCSSProperty('background-image', 'url("'+this.result+'")');
						};
						r.readAsDataURL(f);
					}
				}
			}
		} else {
			// Use DataTransfer interface to access the file(s)
			for (i=0; i < dt.files.length; i++) {
				dt.files[i].name;
			}
		}
		// TODO: XMLHTTPRequest

	}).on('dragover dragenter', function(e){
		e.stop();
		z.setCSSProperty('background-image', null);
		m.addClass('dragover');
	}).on('dragleave dragend', function(e){
		e.stop();
		m.removeClass('dragover');
	});
}

K.onDOMReady(()=>{
	Poodle_Mediatree.init();
	setDropZone();
});

return {
	tree:null,

	init:function()
	{
		this.tree = K.$('media_tree');
		if (this.tree) {
			this.tree.on("click", function(e){
				var o = e.target;
				if ("a" == o.lowerName()) {
					e.stop();
					var uri = o.attr('href');
					uri += (0>uri.indexOf("?") ? "?" : "&");
					uri += "tree=getFiles"+o.$D().location.search.substr(1);
					o.addClass("loading");
					o.xhr = new PoodleXHR;
					o.xhr.oncomplete = function(pxhr){
						try {
							var data=pxhr.fromJSON(), iframe=window.frameElement;
							if (data && data.files) {
								var ul = K.$Q('#main-content .media-files',1).txt(''), li,
									i=-1, file, d = K.$("media-upload-dir");
								while (file = data.files[++i]) {
									li = ul.$A('li',{title:file.name,file:file})
										.on('click',function(){
											if (iframe && iframe.callback) {
												iframe.callback(this.file);
											}
										});
									if (file.uri.match(/\.(png|jpe?g)$/)) {
										li.setCSSProperty('background-image','url('+file.uri+')');
									} else {
										li.setClass(file["class"]);
									}
									li.$A('span',{textContent:file.name.replace(/\.[^.]+$/,'')});
								}
								if (data.dir) {
									if (d) { d.value = data.dir; }
									if (iframe && iframe.setTitle) {
										iframe.setTitle(data.dir);
									}
								}
							}
						} catch (e) {console.error(e);}
						o.removeClass("loading");
					};
					o.xhr.get(uri);
				}
			});
		}
	}
};

})(Poodle);
