/*function adaptImgFix(n){
	var i = window.getComputedStyle(n.parentNode).backgroundImage.replace(/\W?\)$/, '').replace(/^url\(\W?|/, '');
	n.src = (i && i!='none' ? i : n.src);
}*/
(function (){
	// picture polyfill for browser not knowing it
	document.createElement('picture');
	function htmlAddClass(c){
		(function (H){
			H.className = H.className+' '+c
		})(document.documentElement)
	}

	// if adaptImgLazy add lazy class on <html>
	if (adaptImgLazy) {
		htmlAddClass('lazy');
	}

	// Android 2 media-queries bad support workaround
	// multiple rules = multiples downloads : put .android2 on <html>
	// use with simple css without media-queries and send compressive image
	/*var android2 = (/android 2[.]/i.test(navigator.userAgent.toLowerCase()));
	if (android2){
		htmlAddClass('android2');
	}*/
	// slowConnection detection
	var slowConnection = false;
	if (typeof window.performance!=="undefined"){
		var perfData = window.performance.timing;
		var speed = ~~(adaptImgDocLength/(perfData.responseEnd-perfData.connectStart)); // approx, *1000/1024 to be exact
		//console.log(speed);
		slowConnection = (speed && speed<50); // speed n'est pas seulement une bande passante car prend en compte la latence de connexion initiale
	}
	else {
		//https://github.com/Modernizr/Modernizr/blob/master/feature-detects/network/connection.js
		var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if (typeof connection!=="undefined") slowConnection = (connection.type==3 || connection.type==4 || /^[23]g$/.test(connection.type));
	}
	//console.log(slowConnection);
	if (slowConnection){
		htmlAddClass('aislow');
	}

	// inject async style after images have been loaded
	// in order to hide 2 top layers and show only lower one
	var adaptImg_onload = function (){
		var sa = document.createElement('style');
		sa.type = 'text/css';
		sa.innerHTML = adaptImgAsyncStyles;
		var s = document.getElementsByTagName('style')[0];
		s.parentNode.insertBefore(sa, s);
		// if no way to fire beforePrint : load now in case of
		if (!window.matchMedia && !window.onbeforeprint){
			beforePrint();
		}
	};

	// http://www.webreference.com/programming/javascript/onloads/index.html
	function addLoadEvent(func){
		var oldonload = window.onload;
		if (typeof window.onload!='function'){
			window.onload = func;
		} else {
			window.onload = function (){
				if (oldonload){
					oldonload();
				}
				func();
			}
		}
	}

	if (typeof jQuery!=='undefined') jQuery(function (){
		jQuery(window).load(adaptImg_onload)
	}); else addLoadEvent(adaptImg_onload);

	// print issue : fix all img
	/*var beforePrint = function (){
		var is = document.getElementsByClassName('adapt-img-multilayers');
		for (var i = 0; i<is.length; i++)
			adaptImgFix(is[i]);
	};
	if (window.matchMedia){
		var mediaQueryList = window.matchMedia('print');
		mediaQueryList.addListener(function (mql){
			// do not test mql.matches as we want to get background-images that are possibly not findable in print
			beforePrint();
		});
	}
	if (typeof(window.onbeforeprint)!=="undefined")
		window.onbeforeprint = beforePrint;
*/
})();