function adaptImgFix(n){
	var i = window.getComputedStyle(n.parentNode).backgroundImage.replace(/\W?\)$/, '').replace(/^url\(\W?|/, '');
	n.src = (i && i!='none' ? i : n.src);
}
(function (){
	function htmlAddClass(c){
		(function (H){
			H.className = H.className+' '+c
		})(document.documentElement)
	}

	// Android 2 media-queries bad support workaround
	// muliple rules = multiples downloads : put .android2 on <html>
	// use with simple css without media-queries and send compressive image
	var android2 = (/android 2[.]/i.test(navigator.userAgent.toLowerCase()));
	if (android2){
		htmlAddClass('android2');
	}
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
})();