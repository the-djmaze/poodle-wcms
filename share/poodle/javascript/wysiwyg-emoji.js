/*	Poodle WCMS, Copyright (c) MH X Solutions since 2016. All rights reserved.

	@import "poodle/wysiwyg"
*/
if (window.PoodleWYSIWYG) {
(()=>{

var W = PoodleWYSIWYG, emojis_window;

W.prototype.chooseEmoji = function(e,btn)
{
	var o, i, pop = emojis_window, w = btn.wysiwyg;
	if (!pop) {
		pop = emojis_window = document.$B().$A([
		"div",{id:"wysiwyg-emojis",hidden:""
			,onclick:function(e){
				o = e.target;
				if ('i' === o.lowerName()) {
					this.hide().btn.removeClass("active");
					w.execCommand('inserthtml', o.textContent);
				}
			}
		}]);
		var icons = Poodle.Emoji.emojis;
		for (i in icons) {
			pop.$A('i',{
				title:icons[i],
				'class':'emoji emoji-' + (((i.charCodeAt(0) & 0x3FF) << 10) + (i.charCodeAt(1) & 0x3FF) + 0x10000).toString(16).toUpperCase(),
				textContent: i
			});
		}
	}
	w.popupDiv(btn, pop);
};

if (Poodle.Emoji) {
	W.toolbarButtons.push([null, 'Emoji', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert emoji', 'Emoji', 'chooseEmoji']);
}

})();}
