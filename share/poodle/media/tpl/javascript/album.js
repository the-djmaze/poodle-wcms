/*
	@import "poodle"
	@import "poodle/media/photoswipe"
	@import "poodle/media/photoswipe-ui"
*/

Poodle.onDOMReady(()=>{

var K = Poodle, album = K.$('media-album-items'), i = 0, pswp = K.$Q('.pswp',1);

if (album) {
	var figures = album.$T('figure');

	if (pswp && defined(PhotoSwipe)) {
		console.log('Media album uses PhotoSwipe');
		var node, item, items = [], webp = null;
		for (; i < figures.length; ++i) {
			node = figures[i].$Q('a',1);
			// create slide object
			item = {
				src: node.attr('href'),
				w: intval(node.data('width')),
				h: intval(node.data('height')),
				el: figures[i]
			};
			node = figures[i].$Q('figcaption',1);
			if (node) {
				item.title = node.innerHTML;
			}
			node = figures[i].$Q('img',1);
			if (node) {
				item.msrc = node.currentSrc || node.attr('src');
				item.thumb = node;
				if (null === webp) {
					webp = 0 < item.msrc.indexOf('.webp');
				}
				if (webp) {
					item.src = item.src.replace(/(\/[^\/]+\.jpe?g)$/, '/webp/$1.webp');
				}
			}
			items.push(item);
		}

		album.on('click', function(e){
			if (e.target.rel == 'external') {
				return;
			}
			e.stop();
			var figure = e.target.getParentByTagName('figure');
			if (!figure) {
				return;
			}
			// find index of clicked item by looping through all child nodes
			for (i = 0; i < items.length; ++i) {
				if (items[i].el === figure) {
					var options = {
						index: i,
						galleryUID: 1,
						getThumbBoundsFn: function(index) {
							// See Options -> getThumbBoundsFn section of documentation for more info
							if (items[index].thumb) {
								var rect = items[index].thumb.getBoundingPageRect();
								return {x:rect.x, y:rect.y, w:rect.width};
							}
							return {x:0, y:0, w:0};
						}
						,shareEl: false
//						,showAnimationDuration: 0
					};
					// Pass data to PhotoSwipe and initialize it
					var gallery = new PhotoSwipe(pswp, PhotoSwipeUI_Default, items, options);
					gallery.init();
					break;
				}
			}
		});

	} else {
		console.log('Media album uses simple overlay');
		var current,
		w_id='media-album-items-overlay', overlay = K.$(w_id);

		var prev = function()
		{
			if (current && current.previousElementSibling) {
				current.previousElementSibling.click();
			} else {
				this.hide();
			}
		},

		next = function()
		{
			if (current && current.nextElementSibling) {
				current.nextElementSibling.click();
			} else {
				this.hide();
			}
		};

		if (!overlay) {
			overlay = K.$B().$A([
			'div',{id:w_id, 'class':'windowbg', hidden:''},
				['div',{'class':'window'},
					['div', {'class':'header'},
						['a',{ref:'t'}],
						['a', {'class':'close', innerHTML:'⨯', title:_('close'), onclick:function(){overlay.hide();}}]
					],
					['div', {'class':'body'},
						['img', {ref:'image'}],
						['i',{ref:'prev','class':'prev', innerHTML:'◀', onclick:prev}],
						['i',{ref:'next','class':'next', innerHTML:'▶', onclick:next}]
					]
				]
			]);
		}

		for (; i < figures.length; ++i) {
			figures[i].on('click',function(e){
				if (e.target.rel != "external") {
					e.stop();
					var img = this.$Q('img',1), a = this.$Q('a',1);
					if (img) {
						current = this;
						if (a) {
							overlay.t.href = a.href;
						}
						overlay.t.txt(img.title);
						overlay.image.src = (img.currentSrc || img.src).replace('/thumbnail.','/overlay.');
						overlay.prev[this.previousElementSibling?'show':'hide']();
						overlay.next[this.nextElementSibling?'show':'hide']();
						overlay.show();
					}
				}
			});
		}
	}
}

});
