/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
*/

(P=>{

var xmlreader;

function checkXMLFile(file)
{
	if (!file) { return; }

	if (file.size > P.PostMaxFilesize) {
		alert('File to big!');
		return;
	}

	if (!(/(application|text)\/xml$/i).test(file.type)) {
		alert('Not an xml file!');
		return;
	}

	if (!xmlreader) {
		xmlreader = new FileReader();
		xmlreader.onload = function(e){
			if (!/<\?xml.+\?>\s*<database.+>/.test(e.target.result.substring(0, 200))) {
				alert('Invalid xml file content!');
				return;
			}
		};
	}

	xmlreader.readAsText(file);
}

// attach to window onload event
P.onDOMReady(()=>{

	if (defined(FileReader)) {
		var n = P.$Q('input[name=import_xml]',1);
		if (n) {
			n.on("change",function(){checkXMLFile(n.files[0]);});
			var f = n.form,
				xhr,
				btn = f.$Q('button',1),
				pbar = f.$Q('progress',1),
				dlg = P.$('import_xml_dialog'),
				dlgE = P.$('import_xml_errors_dialog');
			dlg.$Q('button',1).on('click',function(){
				pbar.hide();
				dlg.close();
			});
			dlgE.$Q('button',1).on('click',function(){
				dlgE.close();
			});
			f.on('submit',function(e){
				e.stop();
				var data = new FormData(f), json;
				if (!xhr) {
					xhr = new PoodleXHR();
				}
				pbar.value = 0;
				pbar.show();
				btn.disabled = true;
				xhr.oncomplete = function(){
					btn.disabled = false;
				};
				xhr.onresponseline = function(line){
					json = JSON.parse(line);
					if (json) {
						if (json.progress) {
							pbar.max = json.progress.max;
							pbar.value = json.progress.value;
						}
						if (json.complete) {
							dlg.showModal();
						}
						if (json.errors) {
							var ul = dlgE.$Q('ul',1).html('');
							for (var i = 0; i < json.errors.length; ++i) {
								ul.$A('li', {textContent:json.errors[i].message});
							}
							pbar.hide();
							dlgE.showModal();
						}
					}
				};
				xhr.post(f.action, data);
			});
		}
	}

});

})(Poodle);
