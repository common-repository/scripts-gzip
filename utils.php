<?php
/**
 * Checks if a file can be included / displayed to the user.
 * 
 * Checks for
 * 1. is local
 * 2. is readable
 * 3. file must end with either .js or .css, depending on type. 
 * 
 * @param string $type Type of file: 'js' or 'css'.
 * @param string $file Complete filename on disk.
 * @param array $options Various options.
 */
function scgz_check($type, $file, $options)
{
	// Check that the filename is not included in the blacklist.
	if (!scgz_checkBlacklist($file))
		return false;
		
	// Remove the wpurl from the file, if necessary.
	if (isset($options['wpurl']))
		$file = str_replace($options['wpurl'], '', $file);
		
	// Remove the ?* part
	$file = preg_replace('/\?.*/', '', $file);
	
	// File must be local, no http:// or ftp://
	if (strpos($file, '://') !== false)
		return false;
		
	// File must be readable.
	if (!is_readable($file))
		return false;
		
	// File must end with either .js or .css
	$fileLower = strtolower($file);
	
	switch ($type)
	{
		case 'js':
		case 'css':
			if (strpos($fileLower, '.' . $type) !== strlen($fileLower) - strlen($type) - 1)
				return false;
			break;
	}

	return true;
}

function scgz_checkJS($file, $options)
{
	if (!scgz_check('js', $file, $options))
		return false;

	return true;
}

function scgz_checkCSS($file, $options)
{
	if (!scgz_check('css', $file, $options))
		return false;
		
	return true;
}

/**
 * Checks if the filename contains any of the blacklisted strings.
 * Enter description here ...
 * @param string $filename The filename to check against.
 * @return bool True is the file passes the blacklist check.
 */
function scgz_checkBlacklist($filename)
{
	scgz_loadBlacklist();
	global $scgz_Blacklist;
	
	foreach($scgz_Blacklist as $string)
		if (strpos($filename, $string) !== false)
			return false;
	return true;
}

/**
 * Loads the blacklist from disk.
 * 
 * The blacklist is the blacklist.php file, which contains some php code in the beginning and then a list
 * of keywords that triggers the blacklist.
 */
function scgz_loadBlacklist()
{
	global $scgz_Blacklist;
	if (!is_array($scgz_Blacklist))
	{
		$blacklist = dirname(__FILE__) . '/' . 'blacklist.php';
		$scgz_Blacklist = file_get_contents($blacklist);
		$scgz_Blacklist = preg_replace('/.*\?\>/s', '', $scgz_Blacklist);
		$scgz_Blacklist = trim($scgz_Blacklist);
		$scgz_Blacklist = explode("\n", $scgz_Blacklist);
	}
}
?>