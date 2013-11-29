<?php
/**
 * AdaptiveImages
 *
 * @copyright  2013
 * @author     Nursit
 * @licence    GNU/GPL3
 */



class AdaptiveImages {
	/**
	 * @var AdaptiveImages
	 */
	static protected $instance;

	/**
	 * @var boolean
	 */
	protected $NojsPngGifProgressiveRendering = false;

	/**
	 * @var string
	 */
	protected $LowsrcJpgBgColor = 'ffffff';


	/**
	 * @var int
	 */
	protected $LowsrcJpgQuality = 10;

	/**
	 * @var int
	 */
	protected $X15JpgQuality = 65;

	/**
	 * @var int
	 */
	protected $X20JpgQuality = 45;

	/**
	 * @var array
	 */
	protected $DefaultBkpts = array(160,320,480,640,960,1440);

	/**
	 * @var int
	 */
	protected $MaxWidth1x = 640;

	/**
	 * @var int
	 */
	protected $MinWidth1x = 320;

	/**
	 * @var int
	 */
	protected $MaxWidthMobileVersion = 320;

	/**
	 * @var int
	 */
	protected $OnDemandImages = false;


	/**
	 * Constructor
	 */
	protected function __construct(){
	}

	/**
	 * get
	 * @param $property
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __get($property){
		if(!property_exists($this,$property) OR $property=="instance") {
      throw new InvalidArgumentException("Property {$property} doesn't exist");
    }
		return $this->{$property};
	}

	/**
	 * set
	 * @param $property
	 * @param $value
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __set($property, $value){
		if(!property_exists($this,$property) OR $property=="instance") {
      throw new InvalidArgumentException("Property {$property} doesn't exist");
    }
		if (in_array($property,array("NojsPngGifProgressiveRendering","OnDemandImages")) AND !is_bool($value)){
			throw new InvalidArgumentException("Property {$property} needs a bool value");
		}
		elseif ($property=="LowsrcJpgBgColor" AND !is_string($value)){
			throw new InvalidArgumentException("Property {$property} needs a string value");
		}
		elseif ($property=="DefaultBkpts" AND !is_array($value)){
			throw new InvalidArgumentException("Property {$property} needs an array value");
		}
		elseif (!is_int($value)){
			throw new InvalidArgumentException("Property {$property} needs an int value");
		}
		if ($property=="DefaultBkpts"){
			sort($value);
		}

		return ($this->{$property} = $value);
	}

	/**
	 * Disable cloning
	 */
	protected function __clone() {
	 trigger_error("Cannot clone a singleton class", E_USER_ERROR);
	}

	/**
	 * Retrieve the AdaptiveImages object
	 *
	 * @return AdaptiveImages
	 */
	static public function getInstance() {
	 if (!(self::$instance instanceof self)) {
	   self::$instance = new self;
	 }
	 return self::$instance;
	}


	/**
	 * Process the full HTML page :
	 *  - adapt <img> in the HTML
	 *  - collect all inline <style> and put in the <head>
	 *  - add necessary JS
	 *
	 * @param string $html
	 * @return string
	 */
	public function adaptHTMLPage($html){
		#spip_timer();
		$html = $this->adaptHTMLPart($html);
		if (strpos($html,"adapt-img-wrapper")!==false){
			// les styles communs a toutes les images responsive en cours de chargement
			$ins = "<style type='text/css'>"."img.adapt-img{opacity:0.70;max-width:100%;height:auto;}"
			."span.adapt-img-wrapper,span.adapt-img-wrapper:after{display:inline-block;max-width:100%;position:relative;-webkit-background-size:100% auto;background-size:100% auto;background-repeat:no-repeat;line-height:1px;}"
			."span.adapt-img-wrapper:after{position:absolute;top:0;left:0;right:0;bottom:0;content:\"\"}"
			."</style>\n";
			// le script qui estime si la rapidite de connexion et pose une class aislow sur <html> si connexion lente
			// et est appele post-chargement pour finir le rendu (rend les images enregistrables par clic-droit aussi)
			$async_style = "html img.adapt-img{opacity:0.01}html span.adapt-img-wrapper:after{display:none;}";
			$length = strlen($html)+2000; // ~2000 pour le JS qu'on va inserer
			$ins .= "<script type='text/javascript'>/*<![CDATA[*/"
				."function adaptImgFix(n){var i=window.getComputedStyle(n.parentNode).backgroundImage.replace(/\W?\)$/,'').replace(/^url\(\W?|/,'');n.src=(i&&i!='none'?i:n.src);}"
				."(function(){function hAC(c){(function(H){H.className=H.className+' '+c})(document.documentElement)}"
				// Android 2 media-queries bad support workaround
				// muliple rules = multiples downloads : put .android2 on <html>
				// use with simple css without media-queries and send compressive image
				."var android2 = (/android 2[.]/i.test(navigator.userAgent.toLowerCase()));"
				."if (android2) {hAC('android2');}\n"
				// slowConnection detection
				."var slowConnection = false;"
				."if (typeof window.performance!==\"undefined\"){"
				."var perfData = window.performance.timing;"
				."var speed = ~~($length/(perfData.responseEnd - perfData.connectStart));" // approx, *1000/1024 to be exact
				//."console.log(speed);"
				."slowConnection = (speed && speed<50);" // speed n'est pas seulement une bande passante car prend en compte la latence de connexion initiale
				."}else{"
				//https://github.com/Modernizr/Modernizr/blob/master/feature-detects/network/connection.js
				."var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;"
				."if (typeof connection!==\"undefined\") slowConnection = (connection.type == 3 || connection.type == 4 || /^[23]g$/.test(connection.type));"
				."}"
				//."console.log(slowConnection);"
				."if(slowConnection) {hAC('aislow');}\n"
				// injecter un style async apres chargement des images
			  // pour masquer les couches superieures (fallback et chargement)
				."var adaptImg_onload = function(){"
			  ."var sa = document.createElement('style'); sa.type = 'text/css';"
			  ."sa.innerHTML = '$async_style';"
			  ."var s = document.getElementsByTagName('style')[0]; s.parentNode.insertBefore(sa, s);};"
				// http://www.webreference.com/programming/javascript/onloads/index.html
				."function addLoadEvent(func){var oldol=window.onload;if (typeof oldol != 'function'){window.onload=func;}else{window.onload=function(){if (oldol){oldol();} func();}}}"
				."if (typeof jQuery!=='undefined') jQuery(function(){jQuery(window).load(adaptImg_onload)}); else addLoadEvent(adaptImg_onload);"
			  ."})();/*]]>*/</script>\n";
			// le noscript alternatif si pas de js (pour desactiver le rendu progressif qui ne rend pas bien les PNG transparents)
			if (!$this->NojsPngGifProgressiveRendering)
				$ins .= "<noscript><style type='text/css'>.png img.adapt-img,.gif img.adapt-img{opacity:0.01}span.adapt-img-wrapper.png:after,span.adapt-img-wrapper.gif:after{display:none;}</style></noscript>";
			// inserer avant le premier <script> ou <link a defaut

			// regrouper tous les styles adapt-img dans le head
			preg_match_all(",<!--\[if !IE\]><!-->.*(<style[^>]*>.*</style>).*<!--<!\[endif\]-->,Ums",$html,$matches);
			if (count($matches[1])){
				$html = str_replace($matches[1],"",$html);
				$ins .= implode("\n",$matches[1]);
			}
			if ($p = strpos($html,"<link") OR $p = strpos($html,"<script") OR $p = strpos($html,"</head"))
				$html = substr_replace($html,"<!--[if !IE]-->$ins\n<!--[endif]-->\n",$p,0);
		}
		#var_dump(spip_timer());
		return $html;
	}


	/**
	 * Adapt each <img> from HTML part
	 *
	 * @param string $html
	 * @param null $max_width_1x
	 * @return string
	 */
	public function adaptHTMLPart($html,$max_width_1x=null){
		static $bkpts = array();
		if (!is_null($max_width_1x))
			$max_width_1x = $this->MaxWidth1x;

		if ($max_width_1x AND !isset($bkpts[$max_width_1x])){
			$b = $this->$DefaultBkpts;
			while (count($b) AND end($b)>$max_width_1x) array_pop($b);
			// la largeur maxi affichee
			if (!count($b) OR end($b)<$max_width_1x) $b[] = $max_width_1x;
			$bkpts[$max_width_1x] = $b;
		}
		$bkpt = (isset($bkpts[$max_width_1x])?$bkpts[$max_width_1x]:null);

		$replace = array();
		preg_match_all(",<img\s[^>]*>,Uims",$html,$matches,PREG_SET_ORDER);
		if (count($matches)){
			foreach($matches as $m){
				$ri = $this->ProcessImgTag($m[0], $bkpt, $max_width_1x);
				if ($ri!==$m[0]){
					$replace[$m[0]] = $ri;
				}
			}
			if (count($replace)){
				$html = str_replace(array_keys($replace),array_values($replace),$html);
			}
		}

		return $html;
	}



	/**
	 * ?action=adapt_img
	 * OnDemand production and delivery of BkptImage from it's URL
	 * strong path
	 *   local/adapt-img/w/x/file
	 *   ex : 320/20x/file
	 *   w est la largeur affichee de l'image
	 *   x est la resolution (10x => 1, 15x => 1.5, 20x => 2)
	 *   file le chemin vers le fichier source
	 */
	public function DeliverBkptImage($path){

		$file = adaptive_images_bkpt_image_from_path($path, $mime);
		if (!$file
		  OR !$mime){
			http_status(404);
			throw new InvalidArgumentException("unable to find {$path} image");
		}

		header("Content-Type: ". $mime);
		#header("Expires: 3600"); // set expiration time

		if ($cl = filesize($file))
			header("Content-Length: ". $cl);

		readfile($file);
	}


	/**
	 * Process an image for a resolution breakpoint
	 *
	 * @param string $src
	 *   source image
	 * @param int $wkpt
	 *   breakpoint width (display width) for which the image is built
	 * @param int $wx
	 *   real width in px of image
	 * @param string $x
	 *   resolution 10x 15x 20x
	 * @param string $extension
	 *   extension
	 * @param bool $force
	 *   true to force immediate image building if not existing or if too old
	 * @return string
	 *   name of image file
	 */
	protected function ProcessBkptImage($src, $wkpt, $wx, $x, $extension, $force=false){
		$dest = _DIR_VAR."adapt-img/$wkpt/$x/$src";
		if (($exist=file_exists($dest)) AND filemtime($dest)>=filemtime($src))
			return $dest;

		$force = ($force?true:!$this->OnDemandImages);

		// si le fichier existe mais trop vieux et que l'on ne veut pas le produire immediatement : supprimer le vieux fichier
		// ainsi le hit passera par la regexp et tommbera sur l'action adapt_img qui le produira
		if ($exist AND !$force)
			@unlink($dest);

		if (!$force)
			return $dest;

		// creer l'arbo
		$dirs = explode("/",$dest);
		$d = "";
		while(count($dirs)>1
			AND (
			  is_dir($f="$d/".($sd=array_shift($dirs)))
			  OR
			  $f = $this->MkDir($d,$sd)
			)
		) $d = $f;

		$i = $this->image_reduire($src,$wx,10000);

		if (in_array($extension,array('jpg','jpeg')) AND $x!='10x')
			$i = $this->image_aplatir($i,'jpg',$this->LowsrcJpgBgColor,$x=='15x' ? $this->X15JpgQuality : $this->X20JpgQuality);
		$i = $this->TagAttribute($i,"src");
		@copy($i,$dest);

		return file_exists($dest)?$dest:$src;
	}


	/**
	 * Produire une image d'apres son URL
	 * utilise par ?action=adapt_img pour la premiere production a la volee
	 * ou depuis adaptive_images_process_img() si on a besoin de l'image tout de suite
	 *
	 * @param string $arg
	 * @param string $mime
	 * @return string
	 */
	protected function ProcessBkptImageFromPath($arg,&$mime){
		$base = _DIR_VAR."adapt-img/";
		if (strncmp($arg,$base,strlen($base))==0)
			$arg = substr($arg,strlen($base));

		$arg = explode("/",$arg);
		$wkpt = intval(array_shift($arg));
		$x = array_shift($arg);
		$src = implode("/",$arg);

		$parts = pathinfo($src);
		$extension = strtolower($parts['extension']);
		$mime = $this->ExtensionToMimeType($extension);
		$dpi = array('10x'=>1,'15x'=>1.5,'20x'=>2);

		if (!$wkpt
		  OR !isset($dpi[$x])
		  OR !file_exists($src)
		  OR !$mime){
			return "";
		}
		$wx = intval(round($wkpt * $dpi[$x]));

		$file = $this->ProcessBkptImage($src, $wkpt, $wx, $x, $extension, true);
		return $file;
	}

	/**
	 * extrait les infos d'une image,
	 * calcule les variantes en fonction des breakpoints
	 * si l'image est de taille superieure au plus petit breakpoint
	 * et renvoi un markup responsive si il y a lieu
	 *
	 * @param string $img
	 * @param array $bkpt
	 * @param int $max_width_1x
	 * @return string
	 */
	protected function ProcessImgTag($img, $bkpt, $max_width_1x){
		if (!$img) return $img;
		if (strpos($img, "adapt-img")!==false)
			return $img;
		if (is_null($bkpt) OR !is_array($bkpt))
			$bkpt = $this->$DefaultBkpts;

		if (!function_exists("taille_image"))
			include_spip("inc/filtres");
		if (!function_exists("image_reduire"))
			include_spip("inc/filtres_images_mini");
		if (!function_exists("image_aplatir"))
			include_spip("filtres/images_transforme");

		list($h, $w) = $this->imgSize($img);
		if (!$w OR $w<=$this->MinWidth1x) return $img;

		$src = trim($this->TagAttribute($img, 'src'));
		if (strlen($src)<1){
			$src = $img;
			$img = "<img src='".$src."' />";
		}
		$src_mobile = $this->TagAttribute($img, 'data-src-mobile');

		// on ne touche pas aux data:uri
		if (strncmp($src, "data:", 5)==0)
			return $img;

		$images = array();
		if ($w<end($bkpt))
			$images[$w] = array(
				'10x' => $src,
				'15x' => $src,
				'20x' => $src,
			);
		$src = preg_replace(',[?][0-9]+$,', '', $src);

		// si on arrive pas a le lire, on ne fait rien
		if (!file_exists($src))
			return $img;

		$parts = pathinfo($src);
		$extension = $parts['extension'];

		// on ne touche pas aux GIF animes !
		if ($extension=="gif" AND $this->isAnimatedGif($src))
			return $img;

		// calculer les variantes d'image sur les breakpoints
		$fallback = $src;
		$wfallback = $w;
		$dpi = array('10x' => 1, '15x' => 1.5, '20x' => 2);
		$wk = 0;
		foreach ($bkpt as $wk){
			if ($wk>$w) break;
			$is_mobile = (($src_mobile AND $wk<=$this->MaxWidthMobileVersion) ? true : false);
			foreach ($dpi as $k => $x){
				$wkx = intval(round($wk*$x));
				if ($wkx>$w)
					$images[$wk][$k] = $src;
				else {
					$images[$wk][$k] = $this->ProcessBkptImage($is_mobile ? $src_mobile : $src, $wk, $wkx, $k, $extension);
				}
			}
			if ($wk<=$max_width_1x AND ($is_mobile OR !$src_mobile)){
				$fallback = $images[$wk]['10x'];
				$wfallback = $wk;
			}
		}

		// Build the fallback img : High-compress JPG
		// Start from the larger or the mobile version if available
		if ($wk>$w && $w<$max_width_1x){
			$fallback = $images[$w]['10x'];
			$wfallback = $w;
		}

		// l'image n'a peut etre pas ete produite car _ADAPTIVE_IMAGES_ON_DEMAND_PRODUCTION = true
		// on la genere immediatement car on en a besoin
		if (!file_exists($fallback)){
			$mime = "";
			$this->ProcessBkptImageFromPath($fallback, $mime);
		}
		// la qualite est reduite si la taille de l'image augmente, pour limiter le poids de l'image
		// regle de 3 au feeling, _ADAPTIVE_IMAGES_LOWSRC_JPG_QUALITY correspond a une image de 450kPx
		// et on varie dans +/-50% de _ADAPTIVE_IMAGES_LOWSRC_JPG_QUALITY
		$q = round($this->LowsrcJpgQuality-((min($max_width_1x, $wfallback)*$h/$w*min($max_width_1x, $wfallback))/75000-6));
		$q = min($q, round($this->LowsrcJpgQuality)*1.5);
		$q = max($q, round($this->LowsrcJpgQuality)*0.5);
		$fallback = $this->image_aplatir($fallback, 'jpg', $this->LowsrcJpgBgColor, $q);
		$images["fallback"] = $this->TagAttribute($fallback, "src");

		// l'image est reduite a la taille maxi (version IE)
		$img = $this->image_reduire($img, $max_width_1x, 10000);
		// generer le markup
		return $this->ImgAdaptiveMarkup($img, $images, $w, $h, $extension, $max_width_1x);
	}


	/**
	 *
	 * @param string $img
	 * @param array $rwd_images
	 *   tableau
	 *     width => file
	 * @param int $width
	 * @param int $height
	 * @param string $extension
	 * @param int $max_width_1x
	 * @return string
	 */
	function ImgAdaptiveMarkup($img, $rwd_images, $width, $height, $extension, $max_width_1x){
		$class = $this->TagAttribute($img,"class");
		if (strpos($class,"adapt-img")!==false) return $img;
		ksort($rwd_images);
		$cid = "c".crc32(serialize($rwd_images));
		$style = "";
		if ($class) $class = " $class";
		$class = "$cid$class";
		$img = $this->SetTagAttribute($img,"class","adapt-img-ie $class");

		// image de fallback fournie ?
		$fallback_file = "";
		if (isset($rwd_images['fallback'])){
			$fallback_file = $rwd_images['fallback'];
			unset($rwd_images['fallback']);
		}
		// sinon on affiche la plus petite image
		if (!$fallback_file){
			$fallback_file = reset($rwd_images);
			$fallback_file = $fallback_file['10x'];
		}
		// embarquer le fallback en DATA URI si moins de 32ko (eviter une page trop grosse)
		$fallback_file = $this->Base64EmbedFile($fallback_file);

		$prev_width = 0;
		$medias = array();
		$lastw = array_keys($rwd_images);
		$lastw = end($lastw);
		$wandroid = 0;
		foreach ($rwd_images as $w=>$files){
			if ($w==$lastw) {$islast = true;}
			if ($w<=$this->MaxWidthMobileVersion) $wandroid = $w;
			// il faut utiliser une clause min-width and max-width pour que les regles soient exlusives
			if ($prev_width<$max_width_1x){
				$hasmax = (($islast OR $w>=$max_width_1x)?false:true);
				$mw = ($prev_width?"and (min-width:{$prev_width}px)":"").($hasmax?" and (max-width:{$w}px)":"");
				$htmlsel = "html:not(.android2)";
				$htmlsel = array(
					'10x' => "$htmlsel",
					'15x' => "$htmlsel:not(.aislow)",
					'20x' => "$htmlsel:not(.aislow)",
				);
			}
			$mwdpi = array(
				'10x' => "screen $mw",
				'15x' => "screen and (-webkit-min-device-pixel-ratio: 1.5) and (-webkit-max-device-pixel-ratio: 1.99) $mw,screen and (min--moz-device-pixel-ratio: 1.5) and (max--moz-device-pixel-ratio: 1.99) $mw",
				'20x' => "screen and (-webkit-min-device-pixel-ratio: 2) $mw,screen and (min--moz-device-pixel-ratio: 2) $mw",
			);
			foreach($files as $kx=>$file){
				if (isset($mwdpi[$kx])){
					// $file = "filedelay.api/5/$file"; // debug : injecter une tempo dans le chargement de l'image pour tester l'enrichissement progressif
					//$file = $file."?rwd"; // debug  : etre sur qu'on charge bien l'image issue des medias queries
					$mw = $mwdpi[$kx];
					$not = $htmlsel[$kx];
					$medias[$mw] = "@media $mw{{$not} span.$cid,{$not} span.$cid:after{background-image:url($file);}}";
				}
			}
			$prev_width = $w+1;
		}

		// Une regle CSS simple pour android qui (selon les versions/nav) n'arrive pas a s'y retrouver dans les media-queries
		// et charge toutes les images
		// donc une seule image, JPG 320 - 1.5x (compromis)
		if ($wandroid){
			$file = $rwd_images[$wandroid]['15x'];
			$medias['android2'] = "html.android2 span.$cid,html.android2 span.$cid:after{background-image:url($file);}";
		}

		// Media Queries
		$style .= implode("",$medias);

		$out = "<!--[if IE]>$img<![endif]-->\n";
		$img = $this->SetTagAttribute($img,"src",$fallback_file);
		$img = $this->SetTagAttribute($img,"class","adapt-img $class");
		$img = $this->SetTagAttribute($img,"onmousedown","adaptImgFix(this)");
		// $img = SetTagAttribute($img,"onkeydown","adaptImgFix(this)"); // usefull ?
		$out .= "<!--[if !IE]><!--><span class=\"adapt-img-wrapper $cid $extension\">$img</span>\n<style>$style</style><!--<![endif]-->";

		return $out;
	}



	/**
	 * Get height and width from an image file or <img> tag
	 * @param string $img
	 * @return array
	 *  (height,width)
	 */
	protected function imgSize($img) {

		static $largeur_img =array(), $hauteur_img= array();
		$srcWidth = 0;
		$srcHeight = 0;

		$logo = $this->TagAttribute($img,'src');

		if (!$logo) $logo = $img;
		else {
			$srcWidth = $this->TagAttribute($img,'width');
			$srcHeight = $this->TagAttribute($img,'height');
		}

		// never process on remote img
		if (preg_match(';^(\w{3,7}://);', $logo)){
			return array(0,0);
		}
		// remove timestamp on URL
		if (($p=strpos($logo,'?'))!==FALSE)
			$logo=substr($logo,0,$p);

		$srcsize = false;
		if (isset($largeur_img[$logo]))
			$srcWidth = $largeur_img[$logo];
		if (isset($hauteur_img[$logo]))
			$srcHeight = $hauteur_img[$logo];
		if (!$srcWidth OR !$srcHeight){
			if (file_exists($logo)
				AND $srcsize = @getimagesize($logo)){
				if (!$srcWidth)	$largeur_img[$logo] = $srcWidth = $srcsize[0];
				if (!$srcHeight)	$hauteur_img[$logo] = $srcHeight = $srcsize[1];
			}
		}
		return array($srcHeight, $srcWidth);
	}


	/**
	 * recuperer un attribut d'une balise html
	 * la regexp est mortelle : cf. tests/filtres/TagAttribute.php
	 * Si on a passe un tableau de balises, renvoyer un tableau de resultats
	 * (dans ce cas l'option $complet n'est pas disponible)
	 * @param $balise
	 * @param $attribut
	 * @param $complet
	 * @return array|null|string
	 */
	protected function TagAttribute($balise, $attribut, $complet = false) {
		if (preg_match(
		',(^.*?<(?:(?>\s*)(?>[\w:.-]+)(?>(?:=(?:"[^"]*"|\'[^\']*\'|[^\'"]\S*))?))*?)(\s+'
		.$attribut
		.'(?:=\s*("[^"]*"|\'[^\']*\'|[^\'"]\S*))?)()([^>]*>.*),isS',

		$balise, $r)) {
			if ($r[3][0] == '"' || $r[3][0] == "'") {
				$r[4] = substr($r[3], 1, -1);
				$r[3] = $r[3][0];
			} elseif ($r[3]!=='') {
				$r[4] = $r[3];
				$r[3] = '';
			} else {
				$r[4] = trim($r[2]);
			}
			$att = str_replace("&#39;", "'", $r[4]);
		}
		else
			$att = NULL;

		if ($complet)
			return array($att, $r);
		else
			return $att;
	}


	/**
	 * modifier (ou inserer) un attribut html dans une balise
	 *
	 * http://doc.spip.org/@SetTagAttribute
	 *
	 * @param string $balise
	 * @param string $attribut
	 * @param string $val
	 * @param bool $proteger
	 * @param bool $vider
	 * @return string
	 */
	protected function SetTagAttribute($balise, $attribut, $val, $proteger=true, $vider=false) {
		// preparer l'attribut
		// supprimer les &nbsp; etc mais pas les balises html
		// qui ont un sens dans un attribut value d'un input
		if ($proteger) {
			$val = preg_replace(array(",\n,",",\s(?=\s),msS"),array(" ",""),strip_tags($val));
			$val = str_replace(array("'",'"'),array('&#039;', '&#034;'), $val);
		}

		// echapper les ' pour eviter tout bug
		$val = str_replace("'", "&#039;", $val);
		if ($vider AND strlen($val)==0)
			$insert = '';
		else
			$insert = " $attribut='$val'";

		list($old, $r) = $this->TagAttribute($balise, $attribut, true);

		if ($old !== NULL) {
			// Remplacer l'ancien attribut du meme nom
			$balise = $r[1].$insert.$r[5];
		}
		else {
			// preferer une balise " />" (comme <img />)
			if (preg_match(',/>,', $balise))
				$balise = preg_replace(",\s?/>,S", $insert." />", $balise, 1);
			// sinon une balise <a ...> ... </a>
			else
				$balise = preg_replace(",\s?>,S", $insert.">", $balise, 1);
		}

		return $balise;
	}


	/**
	 * Mkdir $base/${subdir}/
	 *
	 * @param $base
	 * @param string $subdir
	 * @param bool $nobase
	 * @param bool $tantpis
	 * @return string
	 * @throws Exception
	 */
	protected function MkDir($base, $subdir='', $nobase = false, $tantpis=false) {
		static $dirs = array();

		$base = str_replace("//", "/", $base);

		# suppr le dernier caractere si c'est un / ou un _
		$base = rtrim($base, '/_');

		if (!strlen($subdir)) {
			$n = strrpos($base, "/");
			if ($n === false) return $nobase ? '' : ($base .'/');
			$subdir = substr($base, $n+1);
			$base = substr($base, 0, $n+1);
		} else {
			$base .= '/';
			$subdir = str_replace("/", "", $subdir);
		}

		$baseaff = $nobase ? '' : $base;
		if (isset($dirs[$base.$subdir]))
			return $baseaff.$dirs[$base.$subdir];

		$path = $base.$subdir; # $path = 'IMG/distant/pdf' ou 'IMG/distant_pdf'

		if (!@is_dir("$path/") AND !@mkdir($path)){
			throw new Exception("Unable to mkdir {$path}");
		}

		return $baseaff.($dirs[$base.$subdir] = "$subdir/");
	}

	/**
	 * Provide Mime Type for Image file Extension
	 * @param $extension
	 * @return string
	 */
	protected function ExtensionToMimeType($extension){
		static $MimeTable = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
		);

		return (isset($MimeTable[$extension])?$MimeTable[$extension]:'image/jpeg');
	}


	/**
	 * Detect animated GIF : don't touch it
	 * http://it.php.net/manual/en/function.imagecreatefromgif.php#59787
	 *
	 * @param string $filename
	 * @return bool
	 */
	protected function isAnimatedGif($filename){
		$filecontents = file_get_contents($filename);

		$str_loc = 0;
		$count = 0;
		while ($count<2) # There is no point in continuing after we find a 2nd frame
		{

			$where1 = strpos($filecontents, "\x00\x21\xF9\x04", $str_loc);
			if ($where1===FALSE){
				break;
			} else {
				$str_loc = $where1+1;
				$where2 = strpos($filecontents, "\x00\x2C", $str_loc);
				if ($where2===FALSE){
					break;
				} else {
					if ($where1+8==$where2){
						$count++;
					}
					$str_loc = $where2+1;
				}
			}
		}

		if ($count>1){
			return (true);

		} else {
			return (false);
		}
	}

	/**
	 * Embed image file in Base 64 URI
	 *
	 * @param string $filename
	 * @param int $maxsize
	 * @return string
	 *     URI Scheme of base64 if possible,
	 *     or URL from source file
	 */
	function Base64EmbedFile ($filename, $maxsize = 32768) {
		$extension = substr(strrchr($filename,'.'),1);

		if (!file_exists($filename)
			OR filesize($filename)>$maxsize
			OR !$content = file_get_contents($filename))
			return $filename;

		$base64 = base64_encode($content);
		$encoded = 'data:'.$this->ExtensionToMimeType($extension).';base64,'.$base64;

		return $encoded;
	}

	protected function image_aplatir(){

	}
	protected function image_reduire(){

	}
}



?>
