<?php
/**
 * AdaptiveImages
 *
 * @version    2.1.0
 * @copyright  2013-2020
 * @author     Nursit
 * @licence    GNU/GPL3
 * @source     https://github.com/nursit/AdaptiveImages
 */


class AdaptiveImages {
	/**
	 * @var Array
	 */
	static protected $instances = array();

	/**
	 * Use progressive rendering for PNG and GIF when JS disabled ?
	 * @var boolean
	 */
	protected $nojsPngGifProgressiveRendering = false;

	/**
	 * Background color for JPG lowsrc generation
	 * (if source has transparency layer)
	 * @var string
	 */
	protected $lowsrcJpgBgColor = '#ffffff';


	/**
	 * JPG compression quality for JPG lowsrc
	 * @var int
	 */
	protected $lowsrcJpgQuality = 40;

	/**
	 * JPG compression quality for 1x JPG images
	 * @var int
	 */
	protected $x10JpgQuality = 75;

	/**
	 * JPG compression quality for 1.5x JPG images
	 * @var int
	 */
	protected $x15JpgQuality = 65;

	/**
	 * JPG compression quality for 2x JPG images
	 * @var int
	 */
	protected $x20JpgQuality = 45;

	/**
	 * Breakpoints width for image generation
	 * @var array
	 */
	protected $defaultBkpts = array(480,960,1440);

	/**
	 * Maximum display width for images
	 * @var int
	 */
	protected $maxWidth1x = 640;

	/**
	 * Minimum display width for adaptive images (smaller will be unchanged)
	 * @var int
	 */
	protected $minWidth1x = 480;

	/**
	 * Minimum filesize for adaptive images (smaller will be unchanged)
	 * @var int
	 */
	protected $minFileSize = 20480; // 20ko

	/**
	 * Maximum width for delivering mobile version in data-src-mobile=""
	 * @var int
	 */
	protected $maxWidthMobileVersion = 480;

	/**
	 * target width for fallback thumbnail
	 * @var int
	 */
	protected $lowsrcWidth = 160;

	/**
	 * Set to true to generate adapted image only at first request from users
	 * (speed up initial page generation)
	 * @var int
	 */
	protected $onDemandImages = false;


	/**
	 * Allowed format images to be adapted
	 * @var array
	 */
	protected $acceptedFormats = array('gif','png','jpeg','jpg');

	/**
	 * directory for storing adaptive images
	 * @var string
	 */
	protected $destDirectory = "local/adapt-img/";

	/**
	 * Maximum number of px for image that can be loaded in memory by GD
	 * can be used to avoid Fatal Memory Error on large image if PHP memory limited
	 * @var string
	 */
	protected $maxImagePxGDMemoryLimit = 0;

	/**
	 * Choose the markup Methd : '3layers' (default) or 'srcset'
	 * @var string
	 */
	protected $markupMethod = '3layers';

	/**
	 * Should images always be in an instrinsic container, even if using srcset method?
	 * @var bool
	 */
	protected $alwaysIntrinsic = true;

	/**
	 * Set to true to delay loading with .lazy class on <html>
	 * need extra js to add .lazy on adapt-img-wrapper, remove .lazy on <html>
	 * and then remove .lazy on each .adapt-img-wrapper when visible
	 * @var int
	 */
	protected $lazyload = false;

	/**
	 * Name of a function to call to generate the thumbnail instead of the internal process
	 * @var string
	 */
	protected $thumbnailGeneratorCallback = null;

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
		if(!property_exists($this,$property) OR $property=="instances") {
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
		if(!property_exists($this,$property) OR $property=="instances") {
      throw new InvalidArgumentException("Property {$property} doesn't exist");
    }
		if (in_array($property,array("nojsPngGifProgressiveRendering","onDemandImages","lazyload","alwaysIntrinsic"))){
			if (!is_bool($value))
				throw new InvalidArgumentException("Property {$property} needs a bool value");
		}
		elseif (in_array($property,array("lowsrcJpgBgColor","destDirectory","thumbnailGeneratorCallback","markupMethod"))){
			if (!is_string($value))
				throw new InvalidArgumentException("Property {$property} needs a string value");
		}
		elseif (in_array($property,array("defaultBkpts","acceptedFormats"))){
			if (!is_array($value))
				throw new InvalidArgumentException("Property {$property} needs an array value");
		}
		elseif (!is_int($value)){
			throw new InvalidArgumentException("Property {$property} needs an int value");
		}
		if ($property=="defaultBkpts"){
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
		$class_name = (function_exists("get_called_class")?get_called_class():"AdaptiveImages");
		if(!array_key_exists($class_name, self::$instances)) {
      self::$instances[$class_name] = new $class_name();
    }
    return self::$instances[$class_name];
	}

	/**
	 * Log function for internal warning if we can avoid to throw an Exception
	 * Do nothing, should be overriden with your personal log function
	 * @param $message
	 */
	protected function log($message){

	}

	/**
	 * Convert URL path to file system path
	 * By default just remove existing timestamp
	 * Should be overriden depending of your URL mapping rules vs DOCUMENT_ROOT
	 * can also remap Absolute URL of current website to filesystem path
	 * @param $url
	 * @return string
	 */
	protected function URL2filepath($url){
		// remove timestamp on URL
		if (($p=strpos($url,'?'))!==FALSE)
			$url=substr($url,0,$p);

		return $url;
	}

	/**
	 * Convert file system path to URL path
	 * By default just add timestamp for webperf issue
	 * Should be overriden depending of your URL mapping rules vs DOCUMENT_ROOT
	 * can map URL on specific domain (domain sharding for Webperf purpose)
	 * @param string $filepath
	 * @param bool $relative
	 * @return string
	 */
	protected function filepath2URL($filepath, $relative=false){
		// be carefull : maybe file doesn't exists yet (On demand generation)
		if ($t = @filemtime($filepath))
			$filepath = "$filepath?$t";
		return $filepath;
	}

	/**
	 * This hook allows to personalize markup depending on source img style and class attributes
	 * This do-noting method should be adapted to source markup generated by your CMS
	 *
	 * For instance : <img style="display:block;float:right" /> could be adapted in
	 * <span style="display:block;float:right"><span class="adapt-img-wrapper"><img class="adapt-img"/></span></span>
	 *
	 * @param string $markup
	 * @param string $originalClass
	 * @param string $originalStyle
	 * @return mixed
	 */
	protected function imgMarkupHook(&$markup,$originalClass,$originalStyle){
		return $markup;
	}

	/**
	 * Translate src of original image to URL subpath of adapted image
	 * the result will makes subdirectory of $destDirectory/320/10x/ and other variants
	 * the result must allow to retrive src from url in adaptedURLToSrc() methof
	 * @param string $src
	 * @return string
	 */
	protected function adaptedSrcToURL($src){
		$url = $this->filepath2URL($src, true);
		if (($p=strpos($url,'?'))!==FALSE)
			$url=substr($url,0,$p);
		// avoid / starting url : replace / by root/
		if (strncmp($url,"/",1)==0)
			$url = "root".$url;
		return $url;
	}

	/**
	 * Translate URL of subpath of adapted image to original image src
	 * This reverse the adaptedSrcToURL() method
	 * @param string $url
	 * @return string
	 */
	protected function adaptedURLToSrc($url){
		// replace root/ by /
		if (strncmp($url,"root/",5)==0)
			$url = substr($url,4);
		$src = $this->URL2filepath($url);
		return $src;
	}

	/**
	 * Process the full HTML page :
	 *  - adapt all <img> in the HTML
	 *  - collect all inline <style> and put in the <head>
	 *  - add necessary JS
	 *
	 * @param string $html
	 *   HTML source page
	 * @param int $maxWidth1x
	 *   max display width for images 1x
	 * @param array|null $bkpt
	 * @return string
	 *  HTML modified page
	 */
	public function adaptHTMLPage($html,$maxWidth1x=null,$bkpt=null){
		// adapt all images that need it, if not already
		$html = $this->adaptHTMLPart($html, $maxWidth1x, $bkpt);

		// if there is adapted images in the page, add the necessary CSS and JS
		if (strpos($html,"adapt-img-wrapper")!==false){
			$ins_style = "";
			// collect all adapt-img <style> in order to put it in the <head>
			preg_match_all(",(<style[^>]*adaptive[^>]*>(.*)</style>),Ums",$html,$matches);
			if (count($matches[2])){
				$html = str_replace($matches[1],"",$html);
				$ins_style .= "\n<style>".implode("\n",$matches[2])."\n</style>";
				// in case of this was only including <style>
				$html = str_replace("<!--[if !IE]><!--><!--<![endif]-->","",$html);
			}

			// Common styles for all adaptive images during loading

			$noscript = "";
			switch ($this->markupMethod) {
				case 'srcset':
					$minwidthdesktop = $this->maxWidthMobileVersion + 0.5;
					$base_style = "<style type='text/css'>"
					."img.adapt-img,.lazy img.adapt-img{max-width:100%;height:auto;}"
					.".adapt-img-wrapper {display:block;max-width:100%;position:relative;background-position:center;-webkit-background-size:100% auto;-webkit-background-size:cover;background-size:cover;background-repeat:no-repeat;line-height:1px;overflow:hidden}"
					.".adapt-img-wrapper.intrinsic::before{content:'';display:block;height:0;width:100%;}.adapt-img-wrapper.intrinsic img{position:absolute;left:0;top:0;width:100%;height:auto;}"
					."@media (min-width:{$minwidthdesktop}px){.adapt-img-wrapper.intrinsic-desktop::before{content:'';display:block;height:0;width:100%;}.adapt-img-wrapper.intrinsic-desktop img{position:absolute;left:0;top:0;width:100%;height:auto;}}"
					.".adapt-img-background{width:100%;height:0}"
					."@media print{html .adapt-img-wrapper{background:none}"
					."</style>\n";
					// JS that evaluate connection speed and add a aislow class on <html> if slow connection
					// and onload JS that adds CSS to finish rendering
					$async_style = "picture.adapt-img-wrapper{background-size:0;}";
					$length = 1500; // ~1500 bytes for CSS and minified JS we add here
					// minified version of AdaptiveImages-light.js (using https://closure-compiler.appspot.com/home)
					$js = <<<JS
(function(){function d(a){var b=document.documentElement;b.className=b.className+" "+a}function f(a){var b=window.onload;window.onload="function"!=typeof window.onload?a:function(){b&&b();a()}}document.createElement("picture");adaptImgLazy&&d("lazy");var a=!1;if("undefined"!==typeof window.performance)a=window.performance.timing,a=(a=~~(adaptImgDocLength/(a.responseEnd-a.connectStart)))&&50>a;else{var c=navigator.connection||navigator.mozConnection||navigator.webkitConnection;"undefined"!==typeof c&&
(a=3==c.type||4==c.type||/^[23]g$/.test(c.type))}a&&d("aislow");var e=function(){var a=document.createElement("style");a.type="text/css";a.innerHTML=adaptImgAsyncStyles;var b=document.getElementsByTagName("style")[0];b.parentNode.insertBefore(a,b);window.matchMedia||window.onbeforeprint||beforePrint()};"undefined"!==typeof jQuery?jQuery(function(){jQuery(window).load(e)}):f(e)})();
JS;
					break;
				case '3layers':
				default:
					$base_style = "<style type='text/css'>"."img.adapt-img,.lazy img.adapt-img{max-width:100%;height:auto;}img.adapt-img.blur{filter:blur(5px)}"
					.".adapt-img-wrapper,.adapt-img-wrapper::after{display:block;max-width:100%;position:relative;-webkit-background-size:100% auto;-webkit-background-size:cover;background-size:cover;background-repeat:no-repeat;line-height:1px;overflow:hidden}"
					.".adapt-img-background{width:100%;height:0}.adapt-img-background::after{display:none;width:100%;height:0;}"
					."html body .adapt-img-wrapper.lazy,html.lazy body .adapt-img-wrapper,html body .adapt-img-wrapper.lazy::after,html.lazy body .adapt-img-wrapper::after{background-image:none}"
					.".adapt-img-wrapper::after{position:absolute;top:0;left:0;right:0;bottom:0;content:\"\"}"
					."@media print{html .adapt-img-wrapper{background:none}html .adapt-img-wrapper img {opacity:1}html .adapt-img-wrapper::after{display:none}}"
					."</style>\n";
					// JS that evaluate connection speed and add a aislow class on <html> if slow connection
					// and onload JS that adds CSS to finish rendering
					$async_style = "html img.adapt-img{opacity:0.01}html .adapt-img-wrapper::after{display:none;}";
					$length = 2000; // ~2000 bytes for CSS and minified JS we add here
					// minified version of AdaptiveImages.js (using https://closure-compiler.appspot.com/home)
					$js = <<<JS
function adaptImgFix(d){var e=window.getComputedStyle(d.parentNode).backgroundImage.replace(/\W?\)$/,"").replace(/^url\(\W?|/,"");d.src=e&&"none"!=e?e:d.src}(function(){function d(a){var b=document.documentElement;b.className=b.className+" "+a}function e(a){var b=window.onload;window.onload="function"!=typeof window.onload?a:function(){b&&b();a()}}document.createElement("picture");adaptImgLazy&&d("lazy");/android 2[.]/i.test(navigator.userAgent.toLowerCase())&&d("android2");var c=!1;if("undefined"!==typeof window.performance)c=window.performance.timing,c=(c=~~(adaptImgDocLength/(c.responseEnd-c.connectStart)))&&50>c;else{var f=navigator.connection||navigator.mozConnection||navigator.webkitConnection;"undefined"!==typeof f&&(c=3==f.type||4==f.type||/^[23]g$/.test(f.type))}c&&d("aislow");var h=function(){var a=document.createElement("style");a.type="text/css";a.innerHTML=adaptImgAsyncStyles;var b=document.getElementsByTagName("style")[0];b.parentNode.insertBefore(a,b);window.matchMedia||window.onbeforeprint||g()};"undefined"!==typeof jQuery?jQuery(function(){jQuery(window).load(h)}):e(h);var g=function(){for(var a=document.getElementsByClassName("adapt-img"),b=0;b<a.length;b++)adaptImgFix(a[b])};window.matchMedia&&window.matchMedia("print").addListener(function(a){g()});"undefined"!==typeof window.onbeforeprint&&(window.onbeforeprint=g)})();
JS;
					// alternative noscript if no js (to de-activate progressive rendering on PNG and GIF)
					if (!$this->nojsPngGifProgressiveRendering)
						$noscript = "<noscript><style type='text/css'>.png img.adapt-img,.gif img.adapt-img{opacity:0.01} .adapt-img-wrapper.png::after,.adapt-img-wrapper.gif::after{display:none;}</style></noscript>";

					break;
			}
			$length += strlen($html)+strlen($ins_style);
			$ins = "<script type='text/javascript'>/*<![CDATA[*/var adaptImgDocLength=$length;adaptImgAsyncStyles=\"$async_style\";adaptImgLazy=".($this->lazyload?"true":"false").";{$js}/*]]>*/</script>\n";
			$ins .= $noscript;
			$ins .= $ins_style;

			// insert before first <script or <link
			$ins = "$base_style<!--[if !IE]><!-->$ins\n<!--<![endif]-->\n";
			if ($p = strpos($html,"<link")
				or $p = strpos($html,"<script")
				or $p = strpos($html,"</head")
				or $p = strpos($html,"</body")) {
				$html = substr_replace($html,$ins,$p,0);
			}
			else {
				$html .= $ins;
			}
		}
		return $html;
	}


	/**
	 * Adapt each <img> from HTML part
	 *
	 * @param string $html
	 *   HTML source page
	 * @param int $maxWidth1x
	 *   max display width for images 1x
	 * @param bool $asBackgroundOrSizes
	 *   true : markup with image as a background only
	 *   string|array : sizes attribut for srcset method
	 * @param array|null $bkpt
	 * @return string
	 * @throws Exception
	 */
	public function adaptHTMLPart($html,$maxWidth1x=null,$bkpt=null,$asBackgroundOrSizes=false){
		$asBackground = false;
		$sizes = null;
		if ($asBackgroundOrSizes === true) {
			$asBackground = true;
		}
		elseif(is_array($asBackgroundOrSizes) or is_string($asBackgroundOrSizes)) {
			$sizes = $asBackgroundOrSizes;
		}

		static $bkpts = array();
		if (is_null($maxWidth1x) OR !intval($maxWidth1x))
			$maxWidth1x = $this->maxWidth1x;

		if (is_null($bkpt)){
			if ($maxWidth1x AND !isset($bkpts[$maxWidth1x])){
				$b = $this->defaultBkpts;
				while (count($b) AND end($b)>$maxWidth1x) array_pop($b);
				// la largeur maxi affichee
				if (!count($b) OR end($b)<$maxWidth1x) $b[] = $maxWidth1x;
				$bkpts[$maxWidth1x] = $b;
			}
			$bkpt = (isset($bkpts[$maxWidth1x])?$bkpts[$maxWidth1x]:null);
		}
		else {
			while (count($bkpt) AND end($bkpt)>$maxWidth1x) array_pop($bkpt);
		}

		$replace = array();
		preg_match_all(",<img\s[^>]*>,Uims",$html,$matches,PREG_SET_ORDER);
		if (count($matches)){
			foreach($matches as $m){
				$ri = $this->processImgTag($m[0], $bkpt, $maxWidth1x, $sizes, $asBackground);
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
	 * OnDemand production and delivery of BkptImage from it's URL
	 * @param string path
	 *   local/adapt-img/w/x/file
	 *   ex : 320/20x/file
	 *   w is the display width
	 *   x is the dpi resolution (10x => 1, 15x => 1.5, 20x => 2)
	 *   file is the original source image file path
	 * @throws Exception
	 */
	public function deliverBkptImage($path){

		try {
			$mime = "";
			$file = $this->processBkptImageFromPath($path, $mime);
		}
		catch (Exception $e){
			$file = "";
		}
		if (!$file
		  OR !$mime){
			http_status(404);
			throw new InvalidArgumentException("Unable to find {$path} image");
		}

		header("Content-Type: ". $mime);
		#header("Expires: 3600"); // set expiration time

		if ($cl = filesize($file))
			header("Content-Length: ". $cl);

		readfile($file);
	}


	/**
	 * Build an image variant for a resolution breakpoint
	 * file path of image is constructed from source file, width and resolution on scheme :
	 * bkptwidth/resolution/full/path/to/src/image/file
	 * it allows to reverse-build the image variant from the path
	 *
	 * if $force==false and $this->onDemandImages==true we only compute the file path
	 * and the image variant will be built on first request
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
	 * @param int $quality
	 *   to set an output image quality outside the predefined preset
	 * @return string
	 *   name of image file
	 * @throws Exception
	 */
	protected function processBkptImage($src, $wkpt, $wx, $x, $extension, $force=false, $quality=null){
		$dir_dest = $this->destDirectory."$wkpt/$x/";
		$dest = $dir_dest . $this->adaptedSrcToURL($src);

		if (($exist=file_exists($dest)) AND filemtime($dest)>=filemtime($src))
			return $dest;

		$force = ($force?true:!$this->onDemandImages);

		// if file already exists but too old, delete it if we don't want to generate it now
		// it will be generated on first request
		if ($exist AND !$force)
			@unlink($dest);

		if (!$force)
			return $dest;

		if (is_null($quality)){
			switch ($x) {
				case '10x':
					$quality = $this->x10JpgQuality;
					break;
				case '15x':
					$quality = $this->x15JpgQuality;
					break;
				case '20x':
					$quality = $this->x20JpgQuality;
					break;
			}
		}

		$i = $this->imgSharpResize($src,$dest,$wx,10000,$quality);
		if ($i AND $i!==$dest AND $i!==$src){
			throw new Exception("Error in imgSharpResize: return \"$i\" whereas \"$dest\" expected");
		}
		if (!file_exists($i)){
			throw new Exception("Error file \"$i\" not found: check the right to write in ".$this->destDirectory);
		}
		return $i;
	}


	/**
	 * Build an image variant from it's URL
	 * this function is used when $this->onDemandImages==true
	 * needs a RewriteRule such as following and a router to call this function on first request
	 *
	 * RewriteRule \badapt-img/(\d+/\d\dx/.*)$ spip.php?action=adapt_img&arg=$1 [QSA,L]
	 *
	 * @param string $URLPath
	 * @param string $mime
	 * @return string
	 * @throws Exception
	 */
	protected function processBkptImageFromPath($URLPath,&$mime){
		$base = $this->destDirectory;
		$path = $URLPath;
		// if base path is provided, remove it
		if (strncmp($path,$base,strlen($base))==0)
			$path = substr($path,strlen($base));

		$path = explode("/",$path);
		$wkpt = intval(array_shift($path));
		$x = array_shift($path);
		$url = implode("/",$path);

		// translate URL part to file path
		$src = $this->adaptedURLToSrc($url);

		$parts = pathinfo($src);
		$extension = strtolower($parts['extension']);
		$mime = $this->extensionToMimeType($extension);
		$dpi = array('10x'=>1,'15x'=>1.5,'20x'=>2);

		// check that path is well formed
		if (!$wkpt
		  OR !isset($dpi[$x])
		  OR !file_exists($src)
		  OR !$mime){
			throw new Exception("Unable to build adapted image $URLPath");
		}
		$wx = intval(round($wkpt * $dpi[$x]));

		$file = $this->processBkptImage($src, $wkpt, $wx, $x, $extension, true);
		return $file;
	}


	/**
	 * Process one single <img> tag :
	 * extract informations of src attribute
	 * and data-src-mobile attribute if provided
	 * compute images versions for provided breakpoints
	 *
	 * Don't do anything if img width is lower than $this->minWidth1x
	 * or img filesize smaller than $this->minFileSize
	 *
	 * @param string $img
	 *   html img tag
	 * @param array $bkpt
	 *   breakpoints
	 * @param int $maxWidth1x
	 *   max display with of image (in 1x)
	 * @param string|array $sizes
	 *   informations for sizez attribut in the srcset method
	 * @param bool $asBackground
	 *   markup with image as a background only
	 * @return string
	 *   html markup : original markup or adapted markup
	 * @throws Exception
	 */
	protected function processImgTag($img, $bkpt, $maxWidth1x, $sizes = null, $asBackground = false){
		if (!$img) return $img;

		// don't do anyting if has adapt-img (already adaptive) or no-adapt-img class (no adaptative needed)
		if (strpos($img, "adapt-img")!==false)
			return $img;
		if (is_null($bkpt) OR !is_array($bkpt))
			$bkpt = $this->defaultBkpts;

		list($w,$h) = $this->imgSize($img);
		$src = trim($this->tagAttribute($img, 'src'));
		if (strlen($src)<1){
			$src = $img;
			$img = "<img src='".$src."' />";
		}

		$adapt = true;
		// Don't do anything if img is to small or unknown width
		if (!$w OR $w<=$this->minWidth1x) {
			$adapt = false;
		}
		else {
			$srcMobile = $this->tagAttribute($img, 'data-src-mobile');

			// don't do anything with data-URI images
			if (strncmp($src, "data:", 5)==0) {
				$adapt = false;
			}
			else {
				$src = $this->URL2filepath($src);
				// don't do anyting if we can't find file
				if (!$src or !file_exists($src)) {
					$adapt = false;
				}
				else {
					// Don't do anything if img filesize is to small
					$filesize=@filesize($src);
					if ($filesize AND $filesize<$this->minFileSize) {
						$adapt = false;
					}
					else {
						$parts = pathinfo($src);
						$extension = $parts['extension'];
						// don't do anyting if it's an animated GIF
						if ($extension=="gif" AND $this->isAnimatedGif($src)) {
							$adapt = false;
						}
					}
				}
			}
		}

		if (!$adapt) {
			if (!$asBackground) {
				return $img;
			}

			$images[$w] = array(
				'10x' => $src,
			);
			// build the markup for background
			return $this->imgAdaptiveMarkup($img, $images, $w, $h, $extension, $maxWidth1x, $sizes, $asBackground);
		}

		if ($srcMobile) {
			$srcMobile = $this->URL2filepath($srcMobile);
			list($wmobile, $hmobile) = @getimagesize($srcMobile);
			if (!$wmobile) {
				$wmobile = $w;
			}
		}

		$images = array();
		if ($w<end($bkpt))
			$images[$w] = array(
				'10x' => $src,
				'15x' => $src,
				'20x' => $src,
			);

		// build images (or at least URLs of images) on breakpoints
		$fallback = $src;
		$wfallback = $w;
		$dpi = array('10x' => 1, '15x' => 1.5, '20x' => 2);
		$wk = 0;
		foreach ($bkpt as $wk){
			if ($wk>$w) break;
			$is_mobile = (($srcMobile AND $wk<=$this->maxWidthMobileVersion) ? true : false);
			if ($is_mobile) {
				// say we have a mobile version for width under
				$images['maxWidthMobile'] = $this->maxWidthMobileVersion;
			}
			foreach ($dpi as $k => $x){
				$wkx = intval(round($wk*$x));
				if ($wkx>($is_mobile ? $wmobile: $w))
					$images[$wk][$k] = $is_mobile ? $srcMobile : $src;
				else {
					$images[$wk][$k] = $this->processBkptImage($is_mobile ? $srcMobile : $src, $wk, $wkx, $k, $extension);
				}
			}
			if ($wk<=$maxWidth1x
				AND ($wk<=$this->lowsrcWidth)
				AND ($is_mobile OR !$srcMobile)){
				$fallback = $images[$wk]['10x'];
				$wfallback = $wk;
			}
		}

		if (!$asBackground) {

			$fallback_directory = $this->destDirectory."fallback/";
			if (!is_null($this->thumbnailGeneratorCallback) && is_callable($this->thumbnailGeneratorCallback)) {
				$options = [
					'dir' => $fallback_directory,
					'images' => $images,
					'src' => $src,
					'srcMobile' => $srcMobile,
					'lowsrcWidth' => $this->lowsrcWidth,
					'lowsrcJpgQuality' => $this->lowsrcJpgQuality,
				];
				$callback = $this->thumbnailGeneratorCallback;
				if ($res = $callback($img, $options)) {
					list($image, $class) = $res;
					$images["fallback"] = $image;
					$images["fallback_class"] = $class;
				}
			}

			// default method for fallback generation if no external callback provided or if it failed
			if (empty($images["fallback"])) {

				// Build the fallback img : High-compressed JPG
				// Start from the mobile version if available or from the larger version otherwise
				if ($wk>$w
					AND $w<$maxWidth1x
					AND $w<$this->lowsrcWidth){
					$fallback = $images[$w]['10x'];
					$wfallback = $w;
				}

				$process_fallback = true;
				if ($wfallback > $this->lowsrcWidth) {

					$bigger_mistake = $h;
					$best_width = $this->lowsrcWidth;
					// optimise this $wfallback to avoid a too big rounding mistake in the height thumbnail resizing
					foreach ([1,1.25,1.333,1.5,1.666,1.75,2] as $x) {
						$wfallback = round($x * $this->lowsrcWidth);
						list($fw,$fh) = $this->computeImageSize($w, $h, $wfallback,10000);
						$mistake = abs(($h - ($fh * $w / $fw)) * $maxWidth1x / $w);
						if ($mistake < $bigger_mistake) {
							$best_width = $wfallback;
							$bigger_mistake = $mistake;
							// if less than 1px of rounding mistake, let's take this size
							if ($mistake < 1) {
								break;
							}
						}
					}
					$wfallback = $best_width;


					$q = $this->lowsrcQualityOptimize($wfallback, $this->lowsrcJpgQuality, $w, $h, $maxWidth1x);
					$fallback = $this->processBkptImage($is_mobile ? $srcMobile : $src, $wfallback, $wfallback, '10x', $extension, true, $q);
					// if it's already a jpg nothing more to do here, otherwise double compress produce artefacts
					if ($extension === 'jpg') {
						$process_fallback = false;
					}
				}


				// if $this->onDemandImages == true image has not been built yet
				// in this case ask for immediate generation
				if (!file_exists($fallback)){
					$mime = ""; // not used here
					$this->processBkptImageFromPath($fallback, $mime);
				}

				if ($process_fallback) {
					$q = $this->lowsrcQualityOptimize($wfallback, $this->lowsrcJpgQuality, $w, $h, $maxWidth1x);
					$images["fallback"] = $this->img2JPG($fallback, $fallback_directory, $this->lowsrcJpgBgColor, $q);
				}
				else {
					$infos = $this->readSourceImage($fallback, $fallback_directory, 'jpg');
					if ($infos['creer']) {
						@copy($fallback, $infos["fichier_dest"]);
					}
					$images["fallback"] =  $infos["fichier_dest"];
				}
				$images["fallback_class"] = 'blur';
			}
		}

		// limit $src image width to $maxWidth1x for old IE
		$src = $this->processBkptImage($src,$maxWidth1x,$maxWidth1x,'10x',$extension,true);
		list($w,$h) = $this->imgSize($src);
		$img = $this->setTagAttribute($img,"src",$this->filepath2URL($src));
		$img = $this->setTagAttribute($img,"width",$w);
		$img = $this->setTagAttribute($img,"height",$h);

		// ok, now build the markup
		return $this->imgAdaptiveMarkup($img, $images, $w, $h, $extension, $maxWidth1x, $sizes, $asBackground);
	}

	/**
	 * Compute an "optimal" jpg quality for the fallback image
	 * @param $width_fallback
	 * @param $lowsrcBaseQuality
	 * @param $width
	 * @param $height
	 * @param $maxWidth1x
	 * @return float|mixed
	 */
	protected function lowsrcQualityOptimize($width_fallback, $lowsrcBaseQuality, $width, $height, $maxWidth1x){
		// $this->lowsrcJpgQuality give a base quality for a 450kpx image size
		// quality is varying around this value (+/- 50%) depending of image pixel size
		// in order to limit the weight of fallback (empirical rule)
		$q = round($lowsrcBaseQuality-((min($maxWidth1x, $width_fallback)*$height/$width*min($maxWidth1x, $width_fallback))/75000-6));
		$q = min($q, round($this->lowsrcJpgQuality)*1.5);
		$q = max($q, round($this->lowsrcJpgQuality)*0.5);

		return $q;
	}

	/**
	 * Build html markup with CSS rules in <style> tag
	 * from provided img tag an array of bkpt images
	 *
	 * @param string $img
	 *   source img tag
	 * @param array $bkptImages
	 *     falbback => file
	 *     width =>
	 *        10x => file
	 *        15x => file
	 *        20x => file
	 * @param int $width
	 * @param int $height
	 * @param string $extension
	 * @param int $maxWidth1x
	 * @param string|array $sizes
	 * @param bool $asBackground
	 * @return string
	 */
	protected function imgAdaptiveMarkup($img, $bkptImages, $width, $height, $extension, $maxWidth1x, $sizes=null, $asBackground = false){
		$class = $this->tagAttribute($img, "class");
		if (strpos($class, "adapt-img")!==false){
			return $img;
		}
		ksort($bkptImages);

		// provided fallback image?
		$fallback_file = "";
		$fallback_class = "";
		if (isset($bkptImages['fallback'])){
			$fallback_file = $bkptImages['fallback'];
			unset($bkptImages['fallback']);
		}
		if (isset($bkptImages['fallback_class'])){
			$fallback_class = $bkptImages['fallback_class'];
			unset($bkptImages['fallback_class']);
		}

		// else we use the smallest one
		if (!$fallback_file){
			$fallback_file = reset($bkptImages);
			$fallback_file = $fallback_file['10x'];
		}

		$maxWidthMobile = null;
		if (isset($bkptImages['maxWidthMobile'])) {
			$maxWidthMobile = $bkptImages['maxWidthMobile'];
			unset($bkptImages['maxWidthMobile']);
		}

		if (!$asBackground and $this->markupMethod === 'srcset') {
			return $this->imgAdaptiveSrcsetMarkup($img, $fallback_file, $fallback_class, $bkptImages, $width, $height, $extension, $maxWidth1x, $maxWidthMobile, $sizes);
		}

		// default method
		return $this->imgAdaptive3LayersMarkup($img, $fallback_file, $fallback_class, $bkptImages, $width, $height, $extension, $maxWidth1x, $asBackground);
	}

	/**
	 * Build html markup with CSS rules in <style> tag
	 * from provided img tag and array of bkpt images
	 *
	 * @param string $img
	 *   source img tag
	 * @param string $fallback_file
	 *   file for fallback/preview
	 * @param string $fallback_class
	 *   class for fallback/preview img
	 * @param array $bkptImages
	 *     falbback => file
	 *     width =>
	 *        10x => file
	 *        15x => file
	 *        20x => file
	 * @param int $width
	 * @param int $height
	 * @param string $extension
	 * @param int $maxWidth1x
	 * @param int $maxWidthMobile
	 * @param string|array $sizes
	 * @return string
	 */
	protected function imgAdaptiveSrcsetMarkup($img, $fallback_file, $fallback_class, $bkptImages, $width, $height, $extension, $maxWidth1x, $maxWidthMobile=null, $sizes = null){
		$originalClass = $class = $this->tagAttribute($img,"class");
		$intrinsic = "";
		if (strpos(" $class "," intrinsic ") !== false) {
			$intrinsic = " intrinsic";
		}
		elseif ($this->alwaysIntrinsic) {
			$intrinsic = " intrinsic";
			$class = trim("$class intrinsic");
		}

		$cid = "c".crc32(serialize($bkptImages));

		// embed fallback as a DATA URI if not more than 32ko
		$fallback_file = $this->base64EmbedFile($fallback_file);

		$srcset = array();
		if ($maxWidthMobile) {
			$srcset['mobile'] = array(
				'20x' => array(), // est-ce qu'on veut envoyer du mobile en 2x ?
				'15x' => array(), // est-ce qu'on veut envoyer du mobile en 1.5x ?
				'10x' => array(),
			);
		}

		$srcset['all'] = array(
			'20x' => array(),
			'15x' => array(),
			'10x' => array(),
		);
		$default_file = "";

		foreach ($bkptImages as $w=>$files){
			foreach($files as $kx=>$file){
				$srcset_key = 'all';
				if ($maxWidthMobile and $w<=$maxWidthMobile) {
					$srcset_key = 'mobile';
				}
				if (isset($srcset[$srcset_key][$kx])){
					$url = $this->filepath2URL($file);
					$ww = round($w * intval($kx) / 10);
					$srcset[$srcset_key][$kx][] = "$url {$ww}w";
				}
			}
			// set the default src : will be the largest 1x file smaller than the max-width
			if (isset($files['10x']) and (!$default_file or $w <= $maxWidth1x)) {
				$default_file = $files['10x'];
			}
		}

		// Media-Queries
		$style = "";
		$originalStyle = $this->tagAttribute($img,"style");
		if ($intrinsic){
			$ratio = round($height/$width * 100,2);
			// if there is a mobile variation, set the intrinsic only in desktop version, cause the mobile image could have different ratio
			// moreover, the source switch maybe not synchronized with the intrinsic ratio switch
			if ($maxWidthMobile) {
				$intrinsic = " intrinsic-desktop";
				$minWidthDesktop = $maxWidthMobile + 0.5;
				$style .= "@media (min-width: {$minWidthDesktop}px){.intrinsic-desktop.{$cid}::before {padding-bottom:{$ratio}%}}";
			}
			else {
				$style .= ".intrinsic.{$cid}::before {padding-bottom:{$ratio}%}";
			}
		}

		$srcset_base = implode(', ', $srcset['all']['10x']);
		unset($srcset['all']['10x']);

		// base sizes rule: fix the max width
		$sizes_rule = array();
		$default_size = false;
		$need_max_size = true;
		if (is_string($sizes)) {
			$sizes = explode(',', $sizes);
			$sizes = array_map('trim', $sizes);
		}
		if (is_array($sizes)) {
			while(count($sizes)) {
				$s = array_shift($sizes);
				$sizes_rule[] = $s;
				if (strpos($s, "(") === false and intval(trim($s))) {
					$default_size = intval(trim($s));
				}
				if (strpos($s, "min-width") !== false and strpos($s, "max-width") === false) {
					$need_max_size = false;
				}
			}
		}
		// set a defaut size rule if not provided (one without media query)
		if (!$default_size) {
			$default_size = 100;
			$sizes_rule[] = "{$default_size}vw";
		}
		if ($need_max_size) {
			// set the max-size for super large screens
			$maxScreenWidth = intval(ceil($maxWidth1x * 100 / $default_size));
			array_unshift($sizes_rule, "(min-width: {$maxScreenWidth}px) {$maxWidth1x}px");
		}

		$sizes_rule = implode(', ', $sizes_rule);

		$sources = array();
		foreach ($srcset as $dest=>$srcset_dest) {
			if ($dest === 'mobile') {
				$mq_max_width = "(max-width:{$maxWidthMobile}px) and ";
			}
			else {
				$mq_max_width = "";
			}
			foreach ($srcset_dest as $kx => $files) {
				$files = implode(', ', $files);
				$dp = intval($kx)/10;
				$sources[] = "<source media=\"{$mq_max_width}(-webkit-min-device-pixel-ratio: {$dp}), {$mq_max_width}(min-resolution: {$dp}dppx)\" srcset=\"$files\" sizes=\"$sizes_rule\" >";
			}
		}
		$sources = "<!--[if IE 9]><video style=\"display: none;\"><![endif]-->".implode("",$sources)."<!--[if IE 9]></video><![endif]-->";

		$img = $this->setTagAttribute($img,"src",$this->filepath2URL($default_file));
		$img = $this->setTagAttribute($img,"class",trim("adapt-img $class"));
		if ($srcset_base) {
			$img = $this->setTagAttribute($img,"srcset",$srcset_base);
		}
		$img = $this->setTagAttribute($img,"sizes",$sizes_rule);

		// markup can be adjusted in hook, depending on style and class
		$markup = "<picture class=\"adapt-img-wrapper{$intrinsic} $cid $extension\" style=\"background-image:url($fallback_file)\">\n$sources\n$img</picture>";
		$markup = $this->imgMarkupHook($markup,$originalClass,$originalStyle);

		if ($style) {
			$markup .= "<!--[if !IE]><!--><style title='adaptive'>".$style."</style><!--<![endif]-->";
		}
		return $markup;
	}

	/**
	 * Build html markup with CSS rules in <style> tag
	 * from provided img tag and array of bkpt images
	 *
	 * @param string $img
	 *   source img tag
	 * @param string $fallback_file
	 *   file for fallback/preview
	 * @param string $fallback_class
	 *   class for fallback/preview img
	 * @param array $bkptImages
	 *     falbback => file
	 *     width =>
	 *        10x => file
	 *        15x => file
	 *        20x => file
	 * @param int $width
	 * @param int $height
	 * @param string $extension
	 * @param int $maxWidth1x
	 * @param bool $asBackground
	 * @return string
	 */
	protected function imgAdaptive3LayersMarkup($img, $fallback_file, $fallback_class, $bkptImages, $width, $height, $extension, $maxWidth1x, $asBackground = false){
		$originalClass = $class = $this->tagAttribute($img,"class");

		$cid = "c".crc32(serialize($bkptImages));
		$style = "";
		$img = $this->setTagAttribute($img,"class","adapt-img-ie $class");
		$class = trim("$fallback_class $class");

		// embed fallback as a DATA URI if not more than 32ko
		$fallback_file = $this->base64EmbedFile($fallback_file);
		// if it is not a vectorial image, encapsulate it in a svg to avoid the pixel rounding flickering
		if (strpos($fallback_file, "image/svg") === false) {
			$svg_wrapper = <<<SVG
<svg viewBox="0 0 $width $height" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image width="$width" height="$height" xlink:href="$fallback_file" preserveAspectRatio="none"/></svg>
SVG;
			$fallback_file = 'data:'.$this->extensionToMimeType('svg').';base64,'.base64_encode($svg_wrapper);
		}

		$prev_width = 0;
		$medias = array();
		$lastw = array_keys($bkptImages);
		$lastw = end($lastw);
		$wandroid = 0;
		$islast = false;
		foreach ($bkptImages as $w=>$files){
			if ($w==$lastw) {$islast = true;}
			if ($w<=$this->maxWidthMobileVersion) $wandroid = $w;
			// use min-width and max-width in order to avoid override
			if ($prev_width<$maxWidth1x){
				$hasmax = (($islast OR $w>=$maxWidth1x)?false:true);
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
					$mw = $mwdpi[$kx];
					$not = $htmlsel[$kx];
					$url = $this->filepath2URL($file);
					$medias[$mw] = "@media $mw{{$not} .$cid,{$not} .$cid::after{background-image:url($url);}}";
				}
			}
			$prev_width = $w+1;
		}

		// One single CSS rule for old android browser (<3) which isn't able to manage override properly
		// we chose JPG 320px width - 1.5x as a compromise
		if ($wandroid){
			$file = (isset($bkptImages[$wandroid]['15x']) ? $bkptImages[$wandroid]['15x'] : $bkptImages[$wandroid]['10x']);
			$url = $this->filepath2URL($file);
			$medias['android2'] = "html.android2 .$cid,html.android2 .$cid::after{background-image:url($url);}";
		}

		// Media-Queries
		$style .= implode("",$medias);
		$originalStyle = $this->tagAttribute($img,"style");

		if ($asBackground) {
			// if we just want a background image: a span with a class
			$ratio = round(100 * $height/$width, 2);
			$out = "<span class=\"adapt-img-wrapper adapt-img-background $cid $extension\" style='padding-bottom: {$ratio}%;'></span>\n<style>".$style."</style>";
		}
		else {
			$out = "<!--[if IE]>$img<![endif]-->\n";

			$img = $this->setTagAttribute($img,"src",$fallback_file);
			$img = $this->setTagAttribute($img,"class",trim("adapt-img adapt-img-multilayers $class"));
			$img = $this->setTagAttribute($img,"onmousedown","adaptImgFix(this)");
			// $img = setTagAttribute($img,"onkeydown","adaptImgFix(this)"); // useful ?

			// markup can be adjusted in hook, depending on style and class
			$markup = "<picture class=\"adapt-img-wrapper $cid $extension\">$img</picture>";
			$markup = $this->imgMarkupHook($markup,$originalClass,$originalStyle);

			$out .= "<!--[if !IE]><!-->$markup\n<style title='adaptive'>".$style."</style><!--<![endif]-->";
		}

		return $out;
	}



	/**
	 * Get height and width from an image file or <img> tag
	 * use width and height attributes of provided <img> tag if possible
	 * store getimagesize result in static to avoid multiple disk access if needed
	 *
	 * @param string $img
	 * @return array
	 *  (width,height)
	 */
	protected function imgSize($img) {

		static $largeur_img =array(), $hauteur_img= array();
		$srcWidth = 0;
		$srcHeight = 0;

		$source = $this->tagAttribute($img,'src');

		if (!$source) $source = $img;
		else {
			$srcWidth = $this->tagAttribute($img,'width');
			$srcHeight = $this->tagAttribute($img,'height');
			if ($srcWidth AND $srcHeight)
				return array($srcWidth,$srcHeight);
			$source = $this->URL2filepath($source);
		}

		// never process on remote img
		if (!$source OR preg_match(';^(\w{3,7}://);', $source)){
			return array(0,0);
		}

		if (isset($largeur_img[$source]))
			$srcWidth = $largeur_img[$source];
		if (isset($hauteur_img[$source]))
			$srcHeight = $hauteur_img[$source];
		if (!$srcWidth OR !$srcHeight){
			if (file_exists($source)
				AND $srcsize = @getimagesize($source)){
				if (!$srcWidth)	$largeur_img[$source] = $srcWidth = $srcsize[0];
				if (!$srcHeight)	$hauteur_img[$source] = $srcHeight = $srcsize[1];
			}
		}
		return array($srcWidth,$srcHeight);
	}


	/**
	 * Find and get attribute value in an HTML tag
	 * Regexp from function extraire_attribut() in
	 * https://core.spip.net/projects/spip/repository/entry/spip/ecrire/inc/filtres.php#L2013
	 * @param $tag
	 *   html tag
	 * @param $attribute
	 *   attribute we look for
	 * @param $full
	 *   if true the function also returns the regexp match result
	 * @return array|string
	 */
	protected function tagAttribute($tag, $attribute, $full = false) {
		if (preg_match(
		',(^.*?<(?:(?>\s*)(?>[\w:.-]+)(?>(?:=(?:"[^"]*"|\'[^\']*\'|[^\'"]\S*))?))*?)(\s+'
		.$attribute
		.'(?:=\s*("[^"]*"|\'[^\']*\'|[^\'"]\S*))?)()([^>]*>.*),isS',

		$tag, $r)) {
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

		if ($full)
			return array($att, $r);
		else
			return $att;
	}


	/**
	 * change or insert an attribute of an html tag
	 *
	 * @param string $tag
	 *   html tag
	 * @param string $attribute
	 *   attribute name
	 * @param string $value
	 *   new value
	 * @param bool $protect
	 *   protect value if true (remove newlines and convert quotes)
	 * @param bool $removeEmpty
	 *   if true remove attribute from html tag if empty
	 * @return string
	 *   modified tag
	 */
	protected function setTagAttribute($tag, $attribute, $value, $protect=true, $removeEmpty=false) {
		// preparer l'attribut
		// supprimer les &nbsp; etc mais pas les balises html
		// qui ont un sens dans un attribut value d'un input
		if ($protect) {
			$value = preg_replace(array(",\n,",",\s(?=\s),msS"),array(" ",""),strip_tags($value));
			$value = str_replace(array("'",'"',"<",">"),array('&#039;','&#034;','&lt;','&gt;'), $value);
		}

		// echapper les ' pour eviter tout bug
		$value = str_replace("'", "&#039;", $value);
		if ($removeEmpty AND strlen($value)==0)
			$insert = '';
		else
			$insert = " $attribute='$value'";

		list($old, $r) = $this->tagAttribute($tag, $attribute, true);

		if ($old !== NULL) {
			// Remplacer l'ancien attribut du meme nom
			$tag = $r[1].$insert.$r[5];
		}
		else {
			// preferer une balise " />" (comme <img />)
			if (preg_match(',/>,', $tag))
				$tag = preg_replace(",\s?/>,S", $insert." />", $tag, 1);
			// sinon une balise <a ...> ... </a>
			else
				$tag = preg_replace(",\s?>,S", $insert.">", $tag, 1);
		}

		return $tag;
	}

	/**
	 * Provide Mime Type for Image file Extension
	 * @param $extension
	 * @return string
	 */
	protected function extensionToMimeType($extension){
		static $MimeTable = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'svg' => 'image/svg+xml',
		);

		return (isset($MimeTable[$extension])?$MimeTable[$extension]:'image/jpeg');
	}


	/**
	 * Detect animated GIF : don't touch it
	 * https://www.php.net/manual/en/function.imagecreatefromgif.php#59787
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
	function base64EmbedFile ($filename, $maxsize = 32768) {
		$extension = substr(strrchr($filename,'.'),1);

		if (!file_exists($filename)
			OR filesize($filename)>$maxsize
			OR !$content = file_get_contents($filename))
			return $filename;

		$base64 = base64_encode($content);
		$encoded = 'data:'.$this->extensionToMimeType($extension).';base64,'.$base64;

		return $encoded;
	}


	/**
	 * Convert image to JPG and replace transparency with a background color
	 *
	 * @param string $source
	 *   source file name (or img tag)
	 * @param string $destDir
	 *   destination directory
	 * @param string $bgColor
	 *   hexa color
	 * @param int $quality
	 *   JPG quality
	 * @return string
	 *   file name of the resized image (or source image if fail)
	 * @throws Exception
	 */
	function img2JPG($source, $destDir, $bgColor='#000000', $quality=85) {
		$infos = $this->readSourceImage($source, $destDir, 'jpg');

		if (!$infos) return $source;

		$couleurs = $this->colorHEX2RGB($bgColor);
		$dr= $couleurs["red"];
		$dv= $couleurs["green"];
		$db= $couleurs["blue"];

		$srcWidth = $infos["largeur"];
		$srcHeight = $infos["hauteur"];

		if ($infos["creer"]) {
			if ($this->maxImagePxGDMemoryLimit AND $srcWidth*$srcHeight>$this->maxImagePxGDMemoryLimit){
				$this->log("No resize allowed : image is " . $srcWidth*$srcHeight . "px, larger than ".$this->maxImagePxGDMemoryLimit."px");
				return $infos["fichier"];
			}
			$fonction_imagecreatefrom = $infos['fonction_imagecreatefrom'];

			if (!function_exists($fonction_imagecreatefrom))
				return $infos["fichier"];
			$im = @$fonction_imagecreatefrom($infos["fichier"]);

			if (!$im){
				throw new Exception("GD image creation fail for ".$infos["fichier"]);
			}

			$this->imagepalettetotruecolor($im);
			$im_ = imagecreatetruecolor($srcWidth, $srcHeight);
			if ($infos["format_source"] == "gif" AND function_exists('ImageCopyResampled')) {
				// if was a transparent GIF
				// make a tansparent PNG
				@imagealphablending($im_, false);
				@imagesavealpha($im_,true);
				if (function_exists("imageAntiAlias")) imageAntiAlias($im_,true);
				@ImageCopyResampled($im_, $im, 0, 0, 0, 0, $srcWidth, $srcHeight, $srcWidth, $srcHeight);
				imagedestroy($im);
				$im = $im_;
			}

			// allocate background Color
			$color_t = ImageColorAllocate( $im_, $dr, $dv, $db);

			imagefill ($im_, 0, 0, $color_t);

			// JPEG has no transparency layer, no need to copy
			// the image pixel by pixel
			if ($infos["format_source"] == "jpg") {
				$im_ = &$im;
			} else
			for ($x = 0; $x < $srcWidth; $x++) {
				for ($y=0; $y < $srcHeight; $y++) {

					$rgb = ImageColorAt($im, $x, $y);
					$a = ($rgb >> 24) & 0xFF;
					$r = ($rgb >> 16) & 0xFF;
					$g = ($rgb >> 8) & 0xFF;
					$b = $rgb & 0xFF;

					$a = (127-$a) / 127;

					// faster if no transparency
					if ($a == 1) {
						$r = $r;
						$g = $g;
						$b = $b;
					}
					// faster if full transparency
					else if ($a == 0) {
						$r = $dr;
						$g = $dv;
						$b = $db;

					}
					else {
						$r = round($a * $r + $dr * (1-$a));
						$g = round($a * $g + $dv * (1-$a));
						$b = round($a * $b + $db * (1-$a));
					}
					$a = (1-$a) *127;
					$color = ImageColorAllocateAlpha( $im_, $r, $g, $b, $a);
					imagesetpixel ($im_, $x, $y, $color);
				}
			}
			if (!$this->saveGDImage($im_, $infos, $quality)){
				throw new Exception("Unable to write ".$infos['fichier_dest'].", check write right of $destDir");
			}
			if ($im!==$im_)
				imagedestroy($im);
			imagedestroy($im_);
		}
		return $infos["fichier_dest"];
	}

	/**
	 * Resize without bluring, and save image with needed quality if JPG image
	 * @author : Arno* from https://zone.spip.org/trac/spip-zone/browser/_plugins_/image_responsive/action/image_responsive.php
	 *
	 * @param string $source
	 * @param string $dest
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param int|null $quality
	 * @return string
	 *   file name of the resized image (or source image if fail)
	 * @throws Exception
	 */
	function imgSharpResize($source, $dest, $maxWidth = 0, $maxHeight = 0, $quality=null){
		$infos = $this->readSourceImage($source, $dest);
		if (!$infos) return $source;

		if ($maxWidth==0 AND $maxHeight==0)
			return $source;

		if ($maxWidth==0) $maxWidth = 10000;
		elseif ($maxHeight==0) $maxHeight = 10000;

		$srcFile = $infos['fichier'];
		$srcExt = $infos['format_source'];

		$destination = dirname($infos['fichier_dest']) . "/" . basename($infos['fichier_dest'], ".".$infos["format_dest"]);

		// compute width & height
		$srcWidth = $infos['largeur'];
		$srcHeight = $infos['hauteur'];
		list($destWidth,$destHeight) = $this->computeImageSize($srcWidth, $srcHeight, $maxWidth, $maxHeight);

		if ($infos['creer']==false)
			return $infos['fichier_dest'];

		// If source image is smaller than desired size, keep source
		if ($srcWidth
		  AND $srcWidth<=$destWidth
		  AND $srcHeight<=$destHeight){

			$infos['format_dest'] = $srcExt;
			$infos['fichier_dest'] = $destination.".".$srcExt;
			@copy($srcFile, $infos['fichier_dest']);

		}
		else {
			if ($this->maxImagePxGDMemoryLimit AND $srcWidth*$srcHeight>$this->maxImagePxGDMemoryLimit){
				$this->log("No resize allowed : image is " . $srcWidth*$srcHeight . "px, larger than ".$this->maxImagePxGDMemoryLimit."px");
				return $srcFile;
			}
			$destExt = $infos['format_dest'];
			if (!$destExt){
				throw new Exception("No output extension for {$srcFile}");
			}

			$fonction_imagecreatefrom = $infos['fonction_imagecreatefrom'];

			if (!function_exists($fonction_imagecreatefrom))
				return $srcFile;
			$srcImage = @$fonction_imagecreatefrom($srcFile);
			if (!$srcImage){
				throw new Exception("GD image creation fail for {$srcFile}");
			}

			// Initialization of dest image
			$destImage = ImageCreateTrueColor($destWidth, $destHeight);

			// Copy and resize source image
			$ok = false;
			if (function_exists('ImageCopyResampled')){
				// if transparent GIF, keep the transparency
				if ($srcExt=="gif"){
					$transparent_index = ImageColorTransparent($srcImage);
					if($transparent_index!=(-1)){
						$transparent_color = ImageColorsForIndex($srcImage,$transparent_index);
						if(!empty($transparent_color)) {
							$transparent_new = ImageColorAllocate($destImage,$transparent_color['red'],$transparent_color['green'],$transparent_color['blue']);
							$transparent_new_index = ImageColorTransparent($destImage,$transparent_new);
							ImageFill($destImage, 0,0, $transparent_new_index);
						}
					}
				}
				if ($destExt=="png"){
					// keep transparency
					if (function_exists("imageAntiAlias")) imageAntiAlias($destImage, true);
					@imagealphablending($destImage, false);
					@imagesavealpha($destImage, true);
				}
				$ok = @ImageCopyResampled($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
			}
			if (!$ok)
				$ok = ImageCopyResized($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);

			if ($destExt=="jpg" && function_exists('imageconvolution')){
				$intSharpness = $this->computeSharpCoeff($srcWidth, $destWidth);
				$arrMatrix = array(
					array(-1, -2, -1),
					array(-2, $intSharpness+12, -2),
					array(-1, -2, -1)
				);
				imageconvolution($destImage, $arrMatrix, $intSharpness, 0);
			}
			// save destination image
			if (!$this->saveGDImage($destImage, $infos, $quality)){
				throw new Exception("Unable to write ".$infos['fichier_dest'].", check write right of $dest");
			}

			if ($srcImage)
				ImageDestroy($srcImage);
			ImageDestroy($destImage);
		}

		return $infos['fichier_dest'];

	}

	/**
	 * @author : Arno* from http:s//zone.spip.org/trac/spip-zone/browser/_plugins_/image_responsive/action/image_responsive.php
	 *
	 * @param int $intOrig
	 * @param int $intFinal
	 * @return mixed
	 */
	function computeSharpCoeff($intOrig, $intFinal) {
	  $intFinal = $intFinal * (750.0 / $intOrig);
	  $intA     = 52;
	  $intB     = -0.27810650887573124;
	  $intC     = .00047337278106508946;
	  $intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
	  return max(round($intRes), 0);
	}

	/**
	 * Read and preprocess informations about source image
	 *
	 * @param string $img
	 * 		HTML img tag <img src=... /> OR source filename
	 * @param string $dest
	 * 		Destination dir of new image
	 * @param null|string $outputFormat
	 * 		forced extension of output image file : jpg, png, gif
	 * @return bool|array
	 * 		false in case of error
	 *    array of image information otherwise
	 * @throws Exception
	 */
	protected function readSourceImage($img, $dest, $outputFormat = null) {
		if (strlen($img)==0) return false;
		$ret = array();

		$source = trim($this->tagAttribute($img, 'src'));
		if (strlen($source) < 1){
			$source = $img;
			$img = "<img src='$source' />";
		}
		# gerer img src="data:....base64"
		# don't process base64
		else if (preg_match('@^data:image/(jpe?g|png|gif);base64,(.*)$@isS', $source)) {
			return false;
		}
		else
			$source = $this->URL2filepath($source);

		// don't process distant images
		if (!$source OR preg_match(';^(\w{3,7}://);', $source)){
			return false;
		}

		$extension_dest = "";
		if (preg_match(",\.(gif|jpe?g|png)($|[?]),i", $source, $regs)) {
			$extension = strtolower($regs[1]);
			$extension_dest = $extension;
		}
		if (!is_null($outputFormat)) $extension_dest = $outputFormat;

		if (!$extension_dest) return false;

		if (@file_exists($source)){
			list ($ret["largeur"],$ret["hauteur"]) = $this->imgSize(strpos($img,"width=")!==false?$img:$source);
			$date_src = @filemtime($source);
		}
		else
			return false;

		// error if no known size
		if (!($ret["hauteur"] OR $ret["largeur"]))
			return false;


		// dest filename : dest/md5(source) or dest if full name provided
		if (substr($dest,-1)=="/"){
			$nom_fichier = md5($source);
			$fichier_dest = $dest . $nom_fichier . "." . $extension_dest;
		}
		else
			$fichier_dest = $dest;

		$creer = true;
		if (@file_exists($f = $fichier_dest)){
			if (filemtime($f)>=$date_src)
				$creer = false;
		}
		// mkdir complete path if needed
		if ($creer
		  AND !is_dir($d=dirname($fichier_dest))){
			mkdir($d,0777,true);
			if (!is_dir($d)){
				throw new Exception("Unable to mkdir {$d}");
			}
		}

		$ret["fonction_imagecreatefrom"] = "imagecreatefrom".($extension != 'jpg' ? $extension : 'jpeg');
		$ret["fichier"] = $source;
		$ret["fichier_dest"] = $fichier_dest;
		$ret["format_source"] = ($extension != 'jpeg' ? $extension : 'jpg');
		$ret["format_dest"] = $extension_dest;
		$ret["date_src"] = $date_src;
		$ret["creer"] = $creer;
		$ret["tag"] = $img;

		if (!function_exists($ret["fonction_imagecreatefrom"])) return false;
		return $ret;
	}

	/**
	 * Compute new image size according to max Width and max Height and initial width/height ratio
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @return array
	 */
	function computeImageSize($srcWidth, $srcHeight, $maxWidth, $maxHeight) {
		$ratioWidth = $srcWidth/$maxWidth;
		$ratioHeight = $srcHeight/$maxHeight;

		if ($ratioWidth <=1 AND $ratioHeight <=1) {
			return array($srcWidth,$srcHeight);
		}
		else if ($ratioWidth < $ratioHeight) {
			$destWidth = intval(round($srcWidth/$ratioHeight));
			$destHeight = $maxHeight;
		}
		else {
			$destWidth = $maxWidth;
			$destHeight = intval(round($srcHeight/$ratioWidth));
		}
		return array ($destWidth, $destHeight);
	}

	/**
	 * SaveAffiche ou sauvegarde une image au format PNG
	 * Utilise les fonctions specifiques GD.
	 *
	 * @param resource $img
	 *   GD image resource
	 * @param array $infos
	 *   image description
	 * @param int|null $quality
	 *   compression quality for JPG images
	 * @return bool
	 */
	protected function saveGDImage($img, $infos, $quality=null) {
		$fichier = $infos['fichier_dest'];
		$tmp = $fichier.".tmp";
		switch($infos['format_dest']){
			case "gif":
				$ret = imagegif($img,$tmp);
				break;
			case "png":
				$ret = imagepng($img,$tmp);
				break;
			case "jpg":
			case "jpeg":
				$ret = imagejpeg($img,$tmp,min($quality,100));
				break;
		}
		if(file_exists($tmp)){
			$taille_test = getimagesize($tmp);
			if ($taille_test[0] < 1) return false;

			@unlink($fichier); // le fichier peut deja exister
			@rename($tmp, $fichier);
			return $ret;
		}
		return false;
	}


	/**
	 * Convert indexed colors image to true color image
	 * available in PHP 5.5+ https://www.php.net/manual/fr/function.imagepalettetotruecolor.php
	 * @param resource $img
	 * @return bool
	 */
	protected function imagepalettetotruecolor(&$img) {
		if (function_exists("imagepalettetotruecolor"))
			return imagepalettetotruecolor($img);

		if ($img AND !imageistruecolor($img) AND function_exists('imagecreatetruecolor')) {
			$w = imagesx($img);
			$h = imagesy($img);
			$img1 = imagecreatetruecolor($w,$h);
			// keep alpha layer if possible
			if(function_exists('ImageCopyResampled')) {
				if (function_exists("imageAntiAlias")) imageAntiAlias($img1,true);
				@imagealphablending($img1, false);
				@imagesavealpha($img1,true);
				@ImageCopyResampled($img1, $img, 0, 0, 0, 0, $w, $h, $w, $h);
			} else {
				imagecopy($img1,$img,0,0,0,0,$w,$h);
			}

			$img = $img1;
			return true;
		}
		return false;
	}


	/**
	 * Translate HTML color to hexa color
	 * @param string $color
	 * @return string
	 */
	protected function colorHTML2Hex($color){
		static $html_colors=array(
			'aqua'=>'00FFFF','black'=>'000000','blue'=>'0000FF','fuchsia'=>'FF00FF','gray'=>'808080','green'=>'008000','lime'=>'00FF00','maroon'=>'800000',
			'navy'=>'000080','olive'=>'808000','purple'=>'800080','red'=>'FF0000','silver'=>'C0C0C0','teal'=>'008080','white'=>'FFFFFF','yellow'=>'FFFF00');
		if (isset($html_colors[$lc=strtolower($color)]))
			return $html_colors[$lc];
		return $color;
	}

	/**
	 * Translate hexa color to RGB
	 * @param string $color
	 *   hexa color (#000000 to #FFFFFF).
	 * @return array
	 */
	protected function colorHEX2RGB($color) {
		$color = $this->colorHTML2Hex($color);
		$color = ltrim($color,"#");
		$retour["red"] = hexdec(substr($color, 0, 2));
		$retour["green"] = hexdec(substr($color, 2, 2));
		$retour["blue"] = hexdec(substr($color, 4, 2));

		return $retour;
	}

}
