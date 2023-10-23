/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
	@import "poodle/qr.js"
*/

Poodle.onDOMReady(()=>{
	function response(value) {
		console.log(value);
		if (value) {
			var q = new URLSearchParams(window.location.search);
			window.location.replace(q.get('redirect_uri'));
		}
	}
	var node = Poodle.$Q('input[name*="sqrl_uri"',1);
	if (node) {
		var d = Poodle.$C('div', {id:'qr'}), qr = new qrcode(0, 'M'),
		  uri = node.value.replace(/&nut=.+/, '&sqrl_check').replace(/sqrl:/,'');
		qr.addData(node.value);
		qr.make();
		node.replaceWith(d);
//		d.innerHTML = qr.createImgTag();
		d.innerHTML = qr.createTableTag();

		d.xhr = new PoodleXHR;
		d.xhr.oncomplete = function(){
			response(d.xhr.fromJSON());
		};
		d.xhr.onresponseline = function(line){
			response(JSON.parse(line));
		};
		d.xhr.get(uri);
/*
		var countDown = 5;
		setInterval(()=>{
//			document.getElementById('reloadDisplay').innerHTML = sqrlReload.countDownDesc + ' ' + countDown;
			if (0 >= countDown--) {
				d.xhr.get(uri);
				countDown = 5;
			}
		}, 1000);
*/
	}

});
