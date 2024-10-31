=== Plugin Name ===
Tags: css, script, js, compress, merge, gzip, optimize, optimization
Requires at least: 2.8
Tested up to: 3.0.1
Stable tag: trunk

A Wordpress plugin to merge and compress the CSS and JS links on the page.

== Description ==

A Wordpress plugin to merge and compress the CSS and JS links on the page. There are several optimizer plugins available but this one was written to be generally effective (not have tons of options) and easy to install and remove (no settings are saved).

It does not cache, minify, have a "PRO" support forum that costs money, keep any logs or have any fancy graphics. It only does one thing, merge+compress, and it does it relatively well.  

The plugin rewrites url() addresses in the CSS files if necessary, leaving http:// and / links alone. If it breaks anything, don't be afraid to email me (edward@mindreantre.se) and tell me what breaks and where (preferrably with a link so I can see for myself).

For scripts_gzip to work your theme must:

* Have a get_header() command
* Have a wp_footer() command
* Use quotation marks in the @import("filename") rules in the CSS files

If there are extra css or js files being included that you want ignored, edit the blacklist.php file.

If you want to put the CSS and/or JS links in a specific place in your theme, use the HTML codes: &lt;!--SCRIPTS_GZIP-CSS--&gt; and &lt;!--SCRIPTS_GZIP-JS--&gt;. Else the new css and js will be put the their default locations.

== Installation ==

1. Unzip and copy the zip contents (including directory) into the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress. The plugin does not create or save any settings.
1. Edit the blacklist.php to ignore any extra files not automatically ignored by the various internal checks. Make a backup copy of the file to preserve your blacklist between upgrades.

== Screenshots ==

1. Before scripts_gzip. Lots of separate calls to the server.
1. After scripts_gzip. Scripts and CSS files have been combined and compressed.

== Upgrading ==

Don't forget to backup your blacklist.php before upgrading!

== Changelog ==
= 0.9.3 =
* Blacklist is assumed to always be readable.
* Changed bloginfo('url') to bloginfo('wpurl') for those that have weird installs.
= 0.9.2 =
* Blacklist added. Edit the blacklist.php file manually.
= 0.9.1 =
* Security fix. Files are now whitelisted instead of blacklisted.
= 0.9 =
* Changed javascript content type to application/javascript
* Have had to finally exclude a plugin: anything TinyMCE related. Yepp, nothing is as non-optimization-friendly as Tinymce.
* Splits links and scripts into seperate lines in order to find them all.
* SCRIPTS_GZIP-CSS and SCRIPTS_GZIP-JS template tags available.
= 0.8 =
* Version bump for WP3.0 support
* Removes empty lines from the merged CSS files. 
* More info in the readme.txt about what needs to be done in order for the plugin to work correctly
= 0.7 =
* Javascripts are assembled at the beginning of the head, not at the end of the document.
* FIX: External javascripts are ignored
= 0.6 =
* Newly improved CSS gatherer / importer
= 0.5.6 =
* case insensitive
* url(/...) links are ignored just like url(http://)
= 0.5.5 =
* @imports are now imported and parsed
= 0.5.4 =
* Conditional Microsoft <!--[if defines are left alone
= 0.5.3 =
* preg_match isn't as greedy anymore (links don't have to be on separate lines)
* url()s with http in them aren't replaced anymore
= 0.5.2 =
* Scripts must be local. No scripts on other machines.
* Now works with Wordpress installs not in the root directory
* Parses PHP code in included files
* Error_reporting set to 0
= 0.5.1 =
* Now uses get_header as start hook, instead of init
* Assumes CSS with no media to be "screen"
* More robust CSS link finding
* New screenshots
= 0.5.0 =
* Initial public release
