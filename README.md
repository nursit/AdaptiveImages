AdaptiveImages
==============

## What is it?
This is the standalone PHP implementation of "3-layer technique" for Adaptive Images generation.
See <https://openweb.eu.org/277> for technical explanations and justifications (french version available : <openweb.eu.org/280>)

## Requirements

PHP>=5.1 with GD library
(if PHP<5.3.0 extending `AdaptiveImages` also needs to override method `getInstance()`)


## Using

### Simple use-case

Call `adaptHTMLPage` method on your HTML page with optional maximum display width of images in your HTML page.

<pre>
require_once "AdaptiveImages.php"
$AdaptiveImages = AdaptiveImages::getInstance();
$html = $AdaptiveImages->adaptHTMLPage($html,780);
</pre>

First view of page with adaptive Images can timeout due to all the images to generate. Reload the page to complete image generation.

### Caching

If your CMS/application allow caching of HTML part of pages, apply `adaptHTMLPart` method on this part in order to cache Adaptive Images

<pre>
require_once "AdaptiveImages.php"
$AdaptiveImages = AdaptiveImages::getInstance();
return $AdaptiveImages->adaptHTMLPart($texte, 780);
</pre>

then recall `adaptHTMLPage` method on full HTML page to finish the job

<pre>
$AdaptiveImages = AdaptiveImages::getInstance();
$html = $AdaptiveImages->adaptHTMLPage($html,780);
</pre>

### URL vs filepath

By default AdaptiveImages considers that relative URLs are also relative file system path.
If this is not the case in your URL scheme, you can override the 2 methods `URL2filepath` and `filepath2URL` that are used to make transpositions.

In the following example we transpose absolutes URLs to relative file system path and, if defined we add special domain `_ADAPTIVE_IMAGES_DOMAIN` to file path in the final URL (domain sharding)

<pre>
class MyAdaptiveImages extends AdaptiveImages {
	protected function URL2filepath($url){
		$url = parent::URL2filepath($url);
		// absolute URL to relative file path
		if (preg_match(",^https?://,",$url)){
			$base = url_de_base();
			if (strncmp($url,$base,strlen($base))==0)
				$url = _DIR_RACINE . substr($url,strlen($base));
			elseif (defined('_ADAPTIVE_IMAGES_DOMAIN')
			  AND strncmp($url,_ADAPTIVE_IMAGES_DOMAIN,strlen(_ADAPTIVE_IMAGES_DOMAIN))==0){
				$url = _DIR_RACINE . substr($url,strlen(_ADAPTIVE_IMAGES_DOMAIN));
			}
		}
		return $url;
	}

	protected function filepath2URL($filepath){
		$filepath = parent::filepath2URL($filepath);
		if (defined('_ADAPTIVE_IMAGES_DOMAIN')){
			$filepath = rtrim(_ADAPTIVE_IMAGES_DOMAIN,"/")."/".$filepath;
		}
		return $filepath;
	}
}
$AdaptiveImages = MyAdaptiveImages::getInstance();
</pre>

### Markup Hook

If source `<img/>` has some inline styles, it can be needed to add some wrapper and put the style on it on order to keep initial visual result.
This can be done in overriding the method `imgMarkupHook(&$markup,$originalClass,$originalStyle)`.
It is call with following arguments
- partial adaptive markup (only the `<span>` wrapper with the fallback `<img/>` inside)
- the original class attribute of `<img/>`
- the original style attribute of `<img/>`

The method must return the partial markup, with your modifications.

### OnDemand images generation

To avoid timeout on first view of HTML page you can activate OnDemand images generation. In this case, only URL of adapted images will be computed, and you need to use a Rewrite Rules and a router to call `AdaptiveImages::deliverBkptImage`.

For instance with SPIP CMS :

#### Rewrite Rule

<pre>
###
# If file or directory exists deliver it and ignore others rewrite rules
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule "." - [skip=100]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule "." - [skip=100]
#
###

###
# Adaptive Images : call action_adapt_img_dist() function if image not available

RewriteRule \badapt-img/(\d+/\d\dx/.*)$ spip.php?action=adapt_img&arg=$1 [QSA,L]

# Fin des Adaptive Images
###
</pre>

#### Router

<pre>
function action_adapt_img_dist(){

	$AdaptiveImages = AdaptiveImages::getInstance();
	try {
		$AdaptiveImages->deliverBkptImage(_request('arg'));
	}
	catch (Exception $e){
		http_status(404);
		die('Error : '.$e->getMessage());
	}
	exit;
}
</pre>

## Advanced Configuration

* Directory for storing adaptive images
  <pre>$AdaptiveImages->destDirectory = "local/adapt-img/";</pre>
* Default Maximum display width for images
  <pre>$AdaptiveImages->maxWidth1x = 640;</pre>
* Minimum display width for adaptive images (smaller will be unchanged)
  <pre>$AdaptiveImages->minWidth1x = 320;</pre>
* Maximum width for delivering mobile version in data-src-mobile=""
  <pre>$AdaptiveImages->maxWidthMobileVersion = 320;</pre>
* Activade On-Demand images generation
  <pre>$AdaptiveImages->onDemandImages = true;</pre>
* Background color for JPG lowsrc generation (if source has transparency layer)
  <pre>$AdaptiveImages->lowsrcJpgBgColor = '#eeeeee';</pre>
* Breakpoints width for image generation
  <pre>$AdaptiveImages->defaultBkpts = array(160,320,480,640,960,1440);</pre>
* Allow progressive rendering og PNG and GIF even without JS :
  <pre>$AdaptiveImages->nojsPngGifProgressiveRendering = true;</pre>
* Max width for the JPG lowsrc fallback image (thumbnail preview)
  <pre>$AdaptiveImages->maxWidthFallbackVersion = 160;</pre>
* JPG compression quality for JPG lowsrc
  <pre>$AdaptiveImages->lowsrcJpgQuality = 40;</pre>
* JPG compression quality for 1x JPG images
  <pre>$AdaptiveImages->x10JpgQuality = 75;</pre>
* JPG compression quality for 1.5x JPG images
  <pre>$AdaptiveImages->x15JpgQuality = 65;</pre>
* JPG compression quality for 2x JPG images
  <pre>$AdaptiveImages->x15JpgQuality = 45;</pre>
* GD maximum px size (width x height) of image that can be manipulated without Fatal Memory Error (0=no limit)
  <pre>$AdaptiveImages->maxImagePxGDMemoryLimit = 2000*2000;</pre>


## Real-life use case

This library is already available through plugins in:

* SPIP CMS <https://contrib.spip.net/Adaptive-Images-4458> [See the implementation](http://zone.spip.org/trac/spip-zone/browser/_plugins_/adaptive_images/trunk/adaptive_images_options.php)
* DotClear blog engine <http://plugins.dotaddict.org/dc2/details/adaptiveImages>
