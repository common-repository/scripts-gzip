<?php
/**
 * This is the blacklist file which decides which extra files to ignore.
 * 
 * The filename is checked against this blacklist before all of the basic checks (readability, external url, etc).
 * 
 * Strings to check the filename against are specified, one string per line, after the line with the question mark.
 * 
 * Default blacklist as of 2010-08-11:
 * 
 * tinymce - Because it loads extra files relative to itself, not the scripts_gzip directory. This also
 *  prevents tinymcecomments from being gzipped.
 *  
 * Send any more suggestions to edward@edwardh.se
 */
	exit;
?>
tinymce
