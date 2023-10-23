/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"

	Detect timezone and set cookie 'PoodleTimezone'
	Only one of the many identical zone entries is used as we don't know the
	exact geographical location of a visitor.
	You can use an IP-Geo database for reliability (unles proxy/vpn is used)
*/

(K=>{

var
	AF='Africa/',
	AS='Asia/',
	AT='Atlantic/',
	AU='Australia/',
	EU='Europe/',
	PA='Pacific/',
	US='America/',
	timezones = {
		'-660,0':PA+'Niue',
		'-600,0':PA+'Honolulu',
		'-600,N':US+'Adak',
		'-540,N':US+'Anchorage',
		'-480,0':US+'Metlakatla',
		'-480,N':US+'Los_Angeles',
		'-420,0':US+'Phoenix',
		'-420,N':US+'Denver',
		'-360,0':US+'Regina',
		'-360,N':US+'Chicago',
		'-360,S':PA+'Easter',
		'-300,0':US+'Jamaica',
		'-300,N':US+'New_York',
		'-270,0':US+'Caracas',
		'-240,0':US+'Aruba',
		'-240,N':US+'Halifax',
		'-240,S':US+'Asuncion',
		'-210,N':US+'St_Johns',
		'-180,0':US+'Paramaribo',
		'-180,N':US+'Godthab',
		'-180,S':US+'Sao_Paulo',
		'-120,0':US+'Noronha',
		 '-60,0':AT+'Cape_Verde',
		 '-60,N':AT+'Azores',
		   '0,0':'UTC',
		   '0,N':EU+'London',
		  '60,0':AF+'Lagos',
		  '60,N':EU+'Brussels',
		  '60,S':AF+'Windhoek',
		 '120,0':AF+'Tripoli',
		 '120,N':EU+'Istanbul',
		 '180,0':AF+'Nairobi',
		 '210,N':AS+'Tehran',
		 '240,0':EU+'Moscow',
		 '240,N':AS+'Yerevan',
		 '270,0':AS+'Kabul',
		 '300,0':AS+'Ashgabat',
		 '330,0':AS+'Colombo',
		 '360,0':AS+'Bishkek',
		 '420,0':AS+'Bangkok',
		 '480,0':AS+'Singapore',
		 '540,0':AS+'Pyongyang',
		 '600,0':AS+'Yakutsk',
		 '600,S':AU+'Hobart',
		 '630,0':AU+'Lord_Howe',
		 '630,N':AU+'Adelaide',
		 '660,0':AS+'Vladivostok',
		 '660,N':AU+'Sydney',
		 '720,0':AS+'Kamchatka',
		 '720,S':PA+'Auckland',
		 '765,S':PA+'Chatham',
		 '780,0':PA+'Enderbury',
		 '780,S':PA+'Apia',
		 '840,0':PA+'Kiritimati'
	};

if (!K.getCookie('PoodleTimezone')) {
	var jan = intval(-(new Date(2012, 0, 1, 0, 0, 0, 0)).getTimezoneOffset()),
		jul = intval(-(new Date(2012, 6, 1, 0, 0, 0, 0)).getTimezoneOffset()),
		diff = jan - jul,
		id = jan + (diff < 0 ? ",N" : ",0");
	if (diff > 0) { id = jul + ",S"; }
	id = timezones[id];
	if (!id) {
		id = -Math.round(new Date().getTimezoneOffset()/60);
		id = id ? 'Etc/GMT'+(id<0?'':'+')+id : 'UTC';
	}
	K.setCookie('PoodleTimezone', id);
}

})(Poodle);
