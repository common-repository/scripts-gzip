<?php                                                                                                                                                                                                                                                          
/*                                                                                                                                                                                                                                                             
Plugin Name: Scripts Gzip
Plugin URI: http://mindreantre.se/program/scripts_gzip/
Description: Merges CSS and/or JS scripts on all pages into one call and sends them gzipped.
Version: 0.9.3
Author: Edward Hevlund
Author URI: http://www.mindreantre.se
Author Email: edward@mindreantre.se
*/

class Scripts_Gzip
{
	private $regexp_url = '[a-z\?\=\&\;\~\:\/\.0-9\-\_]';
	private $wpurl;
	
	public function __construct()
	{
		$wpurl = get_bloginfo('url') . '/';
		$this->wpurl = $wpurl;

		add_action( 'get_header', array(&$this, 'buffer_start') );
		add_action( 'wp_footer', array(&$this, 'buffer_end') );
	}
	
	public function buffer_start()
	{
		ob_start();
	}
	 
	public function buffer_end()
	{
		$buffer = ob_get_contents();
		ob_end_clean();
		echo $this->parse($buffer);
	}
	
	/**
	 * Removes <!--[if stuff, which could contain CSS links.
	 */
	private function cleanCSS($buffer)
	{
		while (strpos($buffer, '<!--[if') !== false)
		{
			$start = strpos($buffer, '<!--[if');
			$stop = strpos($buffer, '<![endif]-->') + 12;
			$length = $stop - $start;
			$string = str_pad('', $length);
			$buffer = substr_replace($buffer, $string, $start, $length);
		}
		return $buffer;
	}

	private function parse_css($buffer)
	{
		$buffer = str_replace("<link", "\n<link", $buffer);
		
		$bufferOrig = $buffer;
		
		$buffer = $this->cleanCSS($buffer);
		
		// Get a list of all media types for the css links.
		$pattern = '/<link.*rel\=.*stylesheet.*?\/>/';		// media\=.*
		preg_match_all($pattern, $buffer, $matches);
		$cssLinks = reset($matches);
		
		if (count($cssLinks) < 1)
			return $bufferOrig;
			
		$stylesheets = array();
		$stringsToRemove = array();
		foreach($cssLinks as $cssLink)
		{
			// Extract the url of the css file.
			$href = preg_replace('/.*href=["|\']?('.$this->regexp_url.'*)["|\']?.*/i', '\1', $cssLink);
			
			$href = str_replace($this->wpurl, '', $href);
			
			if (!scgz_checkCSS($href, array()))
				continue;
			
			$stringsToRemove[] = array('strpos' => strpos($buffer, $cssLink), 'length' => strlen($cssLink), 'string' => $cssLink);
			
			// Assume a media type of screen is none specified. Bad webdesigner!
			if (strpos($cssLink, 'media=') === false)
				$cssLink .= 'media="screen"';
				
			$mediaType = preg_replace('/.*media=["|\']?([a-z]*).*/i', '\1', $cssLink);
			
			if ($mediaType == '')
				$mediaType = 'screen';
				
			$stylesheets[ $mediaType ][] = $href;
		}
		
		if (count($stringsToRemove) < 1)
			return $bufferOrig;

		// Save the position where we can put the new style link(s). The correct/safest position is where the first style starts.
		$newPosition = reset($stringsToRemove);
		$newPosition = $newPosition['strpos'];
		
		// Now remove the strings from the buffer. In reverse order otherwise the strpos for the next string becomes invalid.
		$stringsToRemove = array_reverse($stringsToRemove);
		foreach($stringsToRemove as $removeData)
			$bufferOrig = substr_replace($bufferOrig, '', $removeData['strpos'], $removeData['length']);
		
		// Compile the new links.
		$link = '';
		$pluginDirectory = get_bloginfo('wpurl') . '/' . PLUGINDIR . '/' . basename(dirname(__FILE__));
		foreach($stylesheets as $media=>$cssLinks)
		{
			$links = implode(',', $cssLinks);			
			$links = urlencode($links);
			$link .= '<link rel="stylesheet" href="'.$pluginDirectory.'/gzip.php?css='.$links.'" type="text/css" media="'.$media.'" />' . "\n";
		}

		// If the theme has a "insert the new css link here" tag, use it.
		$cssTagString = '<!--SCRIPTS_GZIP-CSS-->';
		$cssTag = strpos($bufferOrig, $cssTagString);
		if ($cssTag !== false)
			$bufferOrig = str_replace($cssTagString, $link, $bufferOrig);
		else
			$bufferOrig = substr_replace($bufferOrig, $link, $newPosition, 0);
			
		// Clean up the newlines before the links
		$bufferOrig = str_replace("\n<link", "<link", $bufferOrig);
		
		return $bufferOrig;
	}

	private function parse_js($buffer)
	{
		$buffer = str_replace("<script", "\n<script", $buffer);

		$bufferOrig = $buffer;
		
		$buffer = $this->cleanCSS($buffer);

		// Get a list of all media types for the css links.
		$pattern = '/<script.*text\/javascript.*src.*<\/script>/';
		preg_match_all($pattern, $buffer, $matches);
		$jsLinks = reset($matches);
		
		if (count($jsLinks) < 1)
			return $bufferOrig;
			
		$scripts = array();
		$stringsToRemove = array();
		foreach($jsLinks as $jsLink)
		{
			$linkData = preg_replace('/.*src=["|\']?('.$this->regexp_url.'*)["|\']?.*/i', '\1', $jsLink);		// Return a string.
			
			if (!scgz_checkJS($linkData, array('wpurl' => $this->wpurl)))
				continue;
				
			$stringsToRemove[] = array('strpos' => strpos($buffer, $jsLink), 'length' => strlen($jsLink), 'string' => $jsLink);
			$scripts[] = $linkData;
		}
		
		if (count($stringsToRemove) < 1)
			return $bufferOrig;
		
		$stringsToRemove = array_reverse($stringsToRemove);
		foreach($stringsToRemove as $removeData)
			$bufferOrig = substr_replace($bufferOrig, '', $removeData['strpos'], $removeData['length']);
			
		$scripts = implode(',', $scripts);
		$wpurl = str_replace('/', '\\/', $this->wpurl);
		$scripts = preg_replace('/'.$wpurl.'/', '', $scripts);
		$scripts = urlencode($scripts);
		$link = '<script type="text/javascript" src="'.plugins_url('gzip.php',__FILE__).'?js='.$scripts.'"></script>' . "\n";

		// If the theme has a "insert the new js link here" tag, use it.
		$jsTagString = '<!--SCRIPTS_GZIP-JS-->';
		$jsTag = strpos($bufferOrig, $jsTagString);
		if ($jsTag !== false)
			$bufferOrig = str_replace($jsTagString, $link, $bufferOrig);
		else
			// Add the js link to the beginning of the head.
			$bufferOrig = preg_replace('/<head(.*)>/', '<head\1>' . $link, $bufferOrig);
					
		// Clean up the newlines before the script links
		$bufferOrig = str_replace("\n<script", "<script", $bufferOrig);
		
		return $bufferOrig;
	}
	
	public function parse($buffer)
	{
		$buffer = $this->parse_css($buffer);
		$buffer = $this->parse_js($buffer);
		return $buffer;
	}
}

include('utils.php');
$scripts_gzip = new Scripts_Gzip();
?>
