/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/wysiwyg"
*/
if (window.PoodleWYSIWYG) {

var K=Poodle;

// MOO.WYSIWYG.choose_widget
PoodleWYSIWYG.prototype.chooseMedia = function(e,btn)
{
	var w_id='wysiwyg_media_window', pop = K.$(w_id);
	if (!pop) {
		pop = K.$B().$A([
		'div',{id:w_id, 'class':'windowbg', hidden:''},
			['div',{'class':'window', style:{width:'90%'}},
				['div', {'class':'header'},
					btn.title+': /',
					['span',{ref:'t'}],
					['a', {'class':'close', innerHTML:'⨯', title:_('Close'), onclick:function(){pop.hide();}}]
				],
				['div', {'class':'body',style:{height:'500px'}},
					['iframe', {ref:'iframe',
						style:{border:0,display:'block',height:'100%',width:'100%'},
						src:K.HOST+K.PATH+'media-explorer'}
					]
				]
			]
		]);
	}

	pop.iframe.setTitle = function(s){pop.t.txt(s);};
	pop.iframe.callback = function(file) {
		pop.hide();
		var w = btn.wysiwyg, v='<a href="'+file.uri+'" class="'+file['class']+'">'+file.name+'</a>';
		if (file['class'].includes('mime-image')) {
			v = '<img src="'+file.uri+'"/>';
		}
		if (w) {
			w.execCommand('inserthtml',v);
		}
	};

	pop.show();
};

PoodleWYSIWYG.toolbarButtons.push(['inserthtml', 'Media', PoodleWYSIWYG.VALUE_FUNCTION, 'Media', 'Media', 'chooseMedia']);

}
