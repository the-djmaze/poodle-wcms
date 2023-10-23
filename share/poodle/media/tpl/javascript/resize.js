/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/areaselector"
*/

(K=>{

K.onDOMReady(()=>{
//	document.on('mouseup',function(){ mousepos = null; });

	var img = K.$('image-preview');
	if (img && !img.areaselector) {
		var as = img.areaselector = new Poodle_AreaSelector(img),
			s = K.$('image-formats'),
			z = K.$('image-zoom'),
			r = K.$('image-rotate'),
			m = K.$('image-mirror'),
			p = K.$('scale-proportional'),
		   sw = K.$Q('[name="scale[w]"]',1),
		   sh = K.$Q('[name="scale[h]"]',1),
		transform = function(){
			var s = '';
			if (r && r.value > 0 && r.value < 360) {
				s += 'rotate('+r.value+'deg)';
			}
			if (m && m.checked) {
				s += ' scaleX(-1)';
			}
			img.style.transform = s;
			img.parent().$Q('.selection img', 1).style.transform = s;
			img.trigger('change');
		};

		if (s) {
			s.on('change', function(){
				var o = this.currentOption();
				as.setAspectRatio(o.attr('data-width'), o.attr('data-height'));
			});
		}

		if (z) {
			z.on('input', function(){as.setZoom(z.value);});
		}

		if (r) { r.on('input', transform); }
		if (m) { m.on('change', transform); }

		K.$('image-resize').on('click',function(e){
			var btn = this, area = as.getSelection(), file = K.$('image-filename').value.trim(),
			data = (new FormData())
				.addParam('resizeimage', 1)
				.addParam('scale[w]', as.getWidth())
				.addParam('scale[h]', as.getHeight())
				.addParam('format', s.currentOption().value)
				.addParam('file', K.$('image-filename').value);
			if (file && !btn.xhr) {
				if (r && r.value > 0 && r.value < 360) {
					data = data.addParam('actions[rotate]', r.value);
				}
				if (m && m.checked) {
					data = data.addParam('actions[mirror]', 1);
				}
				if (area) {
					data = data
						.addParam('crop[x]', area.x)
						.addParam('crop[y]', area.y)
						.addParam('crop[w]', area.width)
						.addParam('crop[h]', area.height);
				}
				btn.attr('disabled','');
				btn.xhr = new PoodleXHR;
				btn.xhr.oncomplete = function(pxhr){
					try {
						var data = pxhr.fromJSON();
						if (data && 302 == data.status) {
							self.location.href = data.location;
						}
					} catch (e) {console.error(e);}
					delete btn.xhr;
					btn.xhr = null;
					btn.attr('disabled',null);
				};
				btn.xhr.post(null, data);
			}
			e.stop();
		});

		img.on('change', function(){
			var area = as.getSelection(), v = '',
			 o = s.currentOption(),
			 f = o.attr('data-folder'),
			 w = intval(o.attr('data-width')),
			 h = intval(o.attr('data-height'));
			if (area) {
				if (25>w) {
					h = area.height;
					w = area.width;
				}
			} else {
				h = as.getHeight();
				w = as.getWidth();
			}
			if (w != sw.defaultValue && h != sh.defaultValue) {
				v += '.'+w+'x'+h;
			}
			if (r && (r.value > 0 || r.value < 360)) {
				v += '.'+r.value;
			}
			if (m && m.checked) {
				v += '.mirror';
			}
			if (v) {
				v = (f?f.replace(/^\/*$/g,'/'):'') + s.attr('data-file').replace(/\.[^.]*$/, v);
			}
			K.$('image-filename').value = v;
		})
		.on('resize', function(){
			var area = as.getSelection(), v = '';
			if (area) {
				v = '('+area.width+'x'+area.height+'px)';
			}
			K.$('image-preview-size').txt(v);
		});


		sw.on('change',function(){
			if (p.checked && !p.so) {
				p.so = 1;
				sh.setValue(Math.round(sh.defaultValue * this.value / this.defaultValue));
				as.setSize(this.value, sh.value);
				p.so = 0;
			} else {
				as.setWidth(this.value);
			}
		});
		sh.on('change',function(){
			if (p.checked && !p.so) {
				p.so = 1;
				sw.setValue(Math.round(sw.defaultValue * this.value / this.defaultValue));
				as.setSize(sw.value, this.value);
				p.so = 0;
			} else {
				as.setHeight(this.value);
			}
		});

	}
});

})(Poodle);
