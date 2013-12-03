AdaptiveImages
==============

## What is it?
This is the sandalone PHP implementation of "3-layer technique" for Adaptive Images generation.
See <http://blog.nursit.net/Adaptive-Images-et-Responsive-Web.html> for technical explanations and justifications.

## Requirements

PHP>=5.1 with GD library


## Using

### Simple use-case

Call `adaptHTMLPage` method on your HTML page with optional maximum display width of images in your HTML page.

<pre>
require_once "AdaptiveImages.php"
$AdaptiveImage = AdaptiveImages::getInstance();
$html = $AdaptiveImage->adaptHTMLPage($html,780);
</pre>

First view of page with adaptive Images can timeout due to all the images to generate. Reload the page to complete image generation.

### Caching

If your CMS/application allow caching of HTML part of pages, apply `adaptHTMLPart` method on this part in order to cache Adaptive Images

<pre>
require_once "AdaptiveImages.php"
$AdaptiveImage = AdaptiveImages::getInstance();
return $AdaptiveImage->adaptHTMLPart($texte, 780);
</pre>

then recall `adaptHTMLPage` method on full HTML page to finish the job

<pre>
$AdaptiveImage = AdaptiveImages::getInstance();
$html = $AdaptiveImage->adaptHTMLPage($html,780);
</pre>

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

	$AdaptiveImage = AdaptiveImages::getInstance();
	try {
		$AdaptiveImage->deliverBkptImage(_request('arg'));
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
  <pre>$AdaptiveImage->destDirectory = "local/adapt-img/";</pre>
* Default Maximum display width for images
  <pre>$AdaptiveImage->maxWidth1x = 640;</pre>
* Minimum display width for adaptive images (smaller will be unchanged)
  <pre>$AdaptiveImage->minWidth1x = 320;</pre>
* Maximum width for delivering mobile version in data-src-mobile=""
  <pre>$AdaptiveImage->maxWidthMobileVersion = 320;</pre>
* Activade On-Demand images generation
  <pre>$AdaptiveImage->onDemandImages = true;</pre>
* Background color for JPG lowsrc generation (if source has transparency layer)
  <pre>$AdaptiveImage->lowsrcJpgBgColor = '#eeeeee';</pre>
* Breakpoints width for image generation
  <pre>$AdaptiveImage->defaultBkpts = array(160,320,480,640,960,1440);</pre>
* Allow progressive rendering og PNG and GIF even without JS :
  <pre>$AdaptiveImage->nojsPngGifProgressiveRendering = true;</pre>
* JPG compression quality for JPG lowsrc
  <pre>$AdaptiveImage->lowsrcJpgQuality = 10;</pre>
* JPG compression quality for 1x JPG images
  <pre>$AdaptiveImage->x10JpgQuality = 75;</pre>
* JPG compression quality for 1.5x JPG images
  <pre>$AdaptiveImage->x15JpgQuality = 65;</pre>
* JPG compression quality for 2x JPG images
  <pre>$AdaptiveImage->x15JpgQuality = 45;</pre>
