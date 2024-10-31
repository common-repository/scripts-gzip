<?php
error_reporting(0);
/*
 * I'm gonna leave this debug function here in case I'll be changing this file in the future.
 * 
 * Which will probably happen at some time or other.
function dbg($variable)
{
	echo '<pre>';
	var_dump($variable);
	echo '</pre>';
}
*/

class scripts_gzip
{
	private $getData = array(
		array(
			'key' => 'css',
			'file_name' => 'css.css',
			'mime_type' => 'text/css',
		),
		array(
			'key' => 'js',
			'file_name' => 'js.js',
			'mime_type' => 'application/javascript',
		),
	);
	
	private $urls = array();
	
	public function __construct()
	{
		foreach($this->getData as $data)
		{
			if (isset($_GET[$data['key']]))
			{
				// Explode the files
				$files = $_GET[$data['key']];
				$files = urldecode($files);
				$files = explode(',', $files);
				$fileData = $this->collectData(array(
					'files' => $files,
					'mime_type' => $data['mime_type'],
				));
				
				// Remove newlines from the merged CSS file.
				if ($data['key'] == 'css')
					$fileData['data'] = str_replace("\n", "", $fileData['data']);

				$this->file_download(array(
					'file_name' => $data['file_name'],
					'compress' => true,
					'file_data' => $fileData['data'],
					'file_time' => $fileData['latestModified'],
					'mime_type' => $data['mime_type'],
					'content-description' => null,
					'content-disposition' => null,
					'cache-control_maxage' => null,
				));
			}
		}
	}
	
	private function collectData($options)
	{
		$returnValue = array('data' => '', 'latestModified' => null);
		foreach($options['files'] as $file)
		{
			$oldDirectory = getcwd();
			$parsedURL = parse_url($file);
			$file = $parsedURL['path'];
			$file = preg_replace('/\.\./', '', $file);	// Avoid people trying to get into directories they're not allowed into.
			$parents = '../../../';					// wp-content / plugins / scripts_gzip
			$realFile =  $parents . $file;
			
			if ($options['mime_type'] == 'text/css')
				if (!scgz_checkCSS($realFile, array()))
					continue;

			if ($options['mime_type'] == 'application/javascript')
				if (!scgz_checkJS($realFile, array()))
					continue;

			if ($options['mime_type'] == 'text/css')
			{
				chdir($parents);
				$base = getcwd() . '/';
				// Import all the files that need importing.
				$contents = $this->import(array(
					'contents' => '',
					'base' => $base,
					'file' => $file,
				));
				// Now replace all relative urls with ones that are relative from this directory.
				$contents = $this->replaceURLs(array(
					'contents' => $contents,
					'base' => $base,
					'parents' => $parents,
				));
			}
			else
			{
				$contents = file_get_contents($realFile);
			}
	
			$returnValue['data'] .= $contents;
			
			if ($options['mime_type'] == 'application/javascript')
				$returnValue['data'] .= ";\r\n";
				
			chdir($oldDirectory);

			$returnValue['latestModified'] = max($returnValue['latestModified'], filemtime($realFile));
		}
		return $returnValue;
	}
	
	/**
	 * Import a CSS @import.
	 */
	private function import($options)
	{
		$dirname = dirname($options['file']);
		if (!is_readable($dirname))
			return $options['contents'];

		// SECURITY: file may not be outside our WP install.
		if (!str_startsWith(realpath($options['file']), $options['base']))
			return $options['contents'];

		// SECURITY: file may not be a php file.
		if (strpos($options['file'], '.php') !== false)
			return $options['contents'];
	
		// Change the directory to the new file.
		chdir($dirname);
		$newContents = file_get_contents(basename($options['file']));

		$newContents = str_replace(";", ";\n", $newContents);
		
		// Import @imports recursively.
		$pattern = '/\@import url\(.*\).*/';		// @import url();
		preg_match_all($pattern, $newContents, $matches);
		foreach($matches[0] as $match)
		{
			$url = preg_replace('/.*\([\'|\"](.*)[\'|\"]\).*/', '\1', $match);
			
			// Ignore full paths. We only want relative (local) addresses.
			if (str_startsWith($url, 'http'))
				continue;
				
			$oldDirectory = getcwd();
				
			$newOptions = $options;
			$newOptions['file'] = $url;
			$importedContents = $this->import($newOptions);
			
			chdir($oldDirectory);	// God know how many directories we've been to. Go back to where we were.
			
			// Replace the @import line with the contents of the specified file.
			$newContents = str_replace($match, $importedContents, $newContents);
		}
		
		// WORKAROUND: Uncompress the css, because minimized CSS makes life hell for poor match_all.
		$newContents = str_replace(";", ";\n", $newContents);
		$newContents = str_replace("}", "}\n", $newContents);

		// Find all urls in the new content and queue them to be replaced, at a medium pace.
		$pattern = '/(url\([\']?["]?)(?!.*?(http|\/wp-content)).*\)/';		// @import url(); Ignore stuff starting with /wp-content and http.
		preg_match_all($pattern, $newContents, $urlMatches);
		foreach($urlMatches[0] as $urlMatch)
		{
			$urlMD5 = md5(rand(0, time()));
			$newContents = str_replace($urlMatch, $urlMD5, $newContents);
			$this->urls[$urlMD5] = array(
				'url' => $urlMatch,
				'cwd' => getcwd(),
			);
		}
		
		return $options['contents'] . $newContents;
	}
	
	/**
	 * Replaces all URLs with relative addresses. 
	 * 
	 * Relative to the scripts_gzip directory, that is.
	 */
	private function replaceURLs($options)
	{
		$contents = $options['contents'];
		
		foreach($this->urls as $md5 => $data)
		{
			$filename = preg_replace('/.*\([\'|\"]?(.*)[\'|\"]?\).*/', '\1', $data['url']);
			$filename = trim($filename, '"');		// WORKAROUND: Regexp isn't picking up those last quotes.
			$filename = trim($filename, "'");		// WORKAROUND: Regexp isn't picking up those last quotes.
			$directory = str_replace($options['base'], '', $data['cwd']);
			$newURL = 'url("' . $options['parents'] . $directory . '/' . $filename . '")';
			$contents = str_replace($md5, $newURL, $contents);
		}
		return $contents;
	}
	
	/**
	 * Outputs a file via header().
	 * 
	 * Can be a virtual file, with the data stored in file_data, or a real file where file_name points to the file.
	 * 
	 * @param	$options	array
	 * 	'file_name' => string						Name of the file. Real or virtual, just so that the browser has something to work with.
	 * 	'filename' => [string]						Physical file to read from, if no file_data specified.
	 * 	'file_data' => [string]						File data. If no file data is specified, data is from $options['filename'].
	 * 	'file_time' => [int]						Time of the file. If the file is virtual, then this must be specified.
	 * 	'content-disposition' => ['inline']			What to do with the file. Default: show it inline.
	 * 	'allow_304' => [true]						Allow a "file not modified" header.
	 * 	'compress' => [false]						Try to compress data.
	 * 	'filename_clean' => [true]					Clean up the filename, removing all .. and shit.
	 * 	'compress' => [false]						Gzip the data before sending it.
	 * 	'exit' => [true]							Exit after displaying file.
	 * 	'mime_type' => [string]						Leave empty to autodetect mime type.
	 */
	private function file_download($options)
	{
		if (!function_exists('apache_request_headers'))
		{
		    eval('
		        function apache_request_headers() {
		            foreach($_SERVER as $key=>$value) {
		                if (substr($key,0,5)=="HTTP_") {
		                    $key=str_replace(" ","-",ucwords(strtolower(str_replace("_"," ",substr($key,5)))));
		                    $out[$key]=$value;
		                }
		            }
		            return $out;
		        }
		    ');
		}

		$options = array_merge(array(
			'content-description' => 'File Transfer',
			'content-disposition' => 'attachment',
			'allow_304' => true,
			'cache-control_maxage' => 3600,
			'file_expires' => 60*60*24*7,			// One week of standard expiration
			'file_name' => null,
			'filename' => null,
			'filename_clean' => true,
			'file_data' => null,
			'file_size' => null,
			'file_time' => null,
			'mime_type' => null,
			'compress' => false,
			'exit' => true,
		), $options);
		
		$filename = $options['file_name'];
		
		$fileTime = ($options['file_time'] === null ?
			($options['filename'] != '' ? filemtime($options['file_name']) : time())			// Is there is a real file specified, get its time.
			: $options['file_time']);
		$lastModified = gmdate('D, d M Y H:i:s', $fileTime) . ' GMT';		// When was the file last modified?
		
		if ($options['allow_304'])
		{
			// Getting headers sent by the client.
			$clientHeaders = apache_request_headers();
			$clientHeaders['If-Modified-Since'] = isset ($clientHeaders['If-Modified-Since']) ? $clientHeaders['If-Modified-Since'] : '';
			if ($clientHeaders['If-Modified-Since'] == $lastModified)
			{
				header('HTTP/1.1 304 Not Modified', true, 304);
				if ($options['exit'])
					exit;
				else
					return;
			}
		}
		
		$path_parts = pathinfo($filename);
		$mime = isset($options['mime_type']) ? $options['mime_type']: file::mime($filename);
		
		if ($options['filename_clean'])
		$filename = preg_replace('/\.\./', '', $options['file_name']);		// Safety first! Remove everything up to last /

		// Load the file contents
		if ($options['file_data'] === null)
		{
			$fileSize = filesize($options['filename']);
		}
		else
		{
			$fileData = $options['file_data'];
			$fileSize = strlen($fileData);
		}
		
		$expires = gmdate('D, d M Y H:i:s', $fileTime+$options['file_expires']) . ' GMT';		//$fileTime
		
		$filename = utf8_encode($filename);
	
		if ($options['compress'])
			ob_start ("ob_gzhandler");
			
		header("HTTP/1.1 200 OK");
		header('Content-type: '.$mime);
		if (!$options['compress'])
			header('Content-length: ' . $fileSize);		// Compression removes our ability to say how much data we're sending.
		if ($options['cache-control_maxage'] !== null)
			header('Cache-Control: maxage='.$options['cache-control_maxage']);
		header('Pragma: public');
		header('Expires: ' . $expires);
		header('Last-Modified: ' . $lastModified);
		header('Etag: "' . md5($filename . $fileSize) . '"');
		if ($options['content-description'] !== null)
			header('Content-Description: ' . $options['content-description']);
		if ($options['content-disposition'] !== null)
			header('Content-Disposition: '.$options['content-disposition'].'; filename="'.basename($filename).'"');
		header('Accept-Ranges: bytes');
	
		if ($options['file_data'] === null)
			readfile($options['filename']);
		else
			echo($fileData);
			
		while (@ob_end_flush());		// Otherwise it takes forever to flush a large gzipped buffer.
			
		if ($options['exit'])
			exit;
	}
}	// class

/**
 * Returns whether $string starts with $startsWithString.
 */
function str_startsWith($string, $startsWithString, $caseSensitive = false)
{
	$firstPart = substr($string, 0, strlen($startsWithString));
	if (!$caseSensitive)
	{
		$firstPart = strtolower($firstPart);
		$startsWithString = strtolower($startsWithString);
	}
	return $firstPart == $startsWithString;
}

include('utils.php');

new scripts_gzip();

?>