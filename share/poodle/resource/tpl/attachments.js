/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"
	@import "poodle/areaselector"
*/

(K=>{

function select(target)
{
	var w_id='wysiwyg_media_window', pop = K.$(w_id);
	if (!pop)
	{
		pop = K.$B().$A([
		'div',{id:w_id, 'class':'windowbg', hidden:''},
			['div',{'class':'window'},
				['div', {'class':'header'},
					'Media: /',
					['span',{ref:'t'}],
					['a', {'class':'close', innerHTML:'⨯', title:_('close'), onclick:function(){pop.hide();}}]
				],
				['div', {'class':'body',style:{height:'500px'}},
					['iframe', {
						ref:'iframe',
						style:{border:0,display:'block',height:'100%',width:'100%'},
						src:K.HOST+K.PATH+'media-explorer'}
					]
				]
			]
		]);
	}
	pop.iframe.callback = function(file) {
		target.value = file.uri.replace(/.*\/media\//,'');
		pop.hide();
	};
	if (pop.hasAttribute('hidden')) {
		pop.show();
	}
}

function setCrop(x,y,w,h)
{
	K.$Q('[name="attachment_crop[x]"]',1).attr('value', x);
	K.$Q('[name="attachment_crop[y]"]',1).attr('value', y);
	K.$Q('[name="attachment_crop[w]"]',1).attr('value', w);
	K.$Q('[name="attachment_crop[h]"]',1).attr('value', h);
}

K.onDOMReady(()=>{
	var o = K.$('attachment_media_item');
	if (o) {
		o.on('focus',function(){select(this);});
	}

	/**
	 * If the file is an image and the web browser supports FileReader,
	 * present a preview in the file list
	 */
	var img = K.$('image-preview');
	o = K.$('resource_attachment_file');
	if (img && o && defined(o.files) && typeof FileReader !== 'undefined') {
		var as = new Poodle_AreaSelector(img),
		     s = K.$Q('[name="attachment_type_id"]',1);
		if (s) {
			s.on('change', function(){
				var o = this.currentOption();
				as.setAspectRatio(o.attr('data-width'), o.attr('data-height'));
			});
		}
/*
		as.setZoom(100);
*/
		img.on('resize',function(){
			var area = as.getSelection();
			if (area) setCrop(area.left, area.top, area.width, area.height);
			else setCrop(0,0,0,0);
		});

		img.parent().hide();
		o.on('change',function(){
			var file = this.files[0];
			if ((/image/i).test(file.type)) {
				var reader = new FileReader();
				reader.onload = function(evt){img.src = evt.target.result;};
				reader.readAsDataURL(file);
				img.parent().show();
			} else {
				img.parent().hide();
				setCrop(0,0,0,0);
			}
		});
	}

});

})(Poodle);
