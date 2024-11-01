=== Upload Janitor ===

Donate link: http://atastypixel.com/blog/wordpress/plugins/upload-janitor
Tags: upload, clean, unused, delete, files, images
Requires at least: 2.6
Tested up to: 2.9.1
Stable tag: 0.2

Clean up unused images and other files from your uploads folder.

== Description ==

Reclaim disk space and clean up your uploads folder by deleting old uploads you are no longer linking to.

This plugin will identify unused files within your uploads folder, and give you the option of archiving then deleting
some or all of these files.

Before any action is taken, Upload Janitor will automatically make a 'tar' archive of all files to be
erased, including their original paths, so you can restore if necessary.

== Installation ==

1. Unzip the package, and upload `upload-janitor` to the `wp-content/plugins` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit 'Upload Janitor' within the 'Tools' menu in WordPress to use

== How does it work? ==

This plugin inspects every file within the uploads folder.  For each file, it searches pages or posts that
reference the file.  That is, the plugin searches for the part of each file's path that comes after the path 
to the uploads folder, such as `2010/01/my great but forgotten image.jpg`.  

The path is searched as-is, as well as URL-encoded with '%20' for spaces, and the same with '+' for spaces -
`2010/01/my%20great%20but%20forgotten%20image.jpg` and `2010/01/my+great+but+forgotten+image.jpg`.  HTML entity-encoded
forms of all of these are also searched.

If no matches are found, then the file is considered unused.

Note that this plugin plays it safe, and does not distinguish between older post/page revisions and the current version 
of a post/page.  If a revision references a file, the file will be considered still in use.

== Restoring ==

If something goes wrong, you can always restore.  If you have shell access to your site, this is easy.  Simply log in,
navigate to your `wp-content/plugins` directory, and locate the Upload Janitor archive - it will look like 
`upload_janitor_archive_YYYY-mm-dd.tar.gz`.  Then, type:

    `tar zxf <archive name> .`
  
This will restore all files within the archive.

If you do not have shell access to your server, you will have to download the archive, extract it, then upload the
contents back to your server.  The archive will be accessible at http://your-blog.com/wp-content/uploads/upload_janitor_archive_YYYY-mm-dd.tar.gz


== Changelog ==

= 0.2 =
* Tweak for compatibility with some apparently buggy PHP installations
* Additional error reporting for 'tar' archiver

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.2 =
* Please upgrade if you are experiencing errors or a blank screen when using Upload Janitor

= 0.1 =
* Initial release