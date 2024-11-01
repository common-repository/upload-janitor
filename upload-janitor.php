<?php
/*
 * Plugin Name: Upload Janitor
 * Version: 0.2
 * Plugin URI: http://atastypixel.com/blog/wordpress/plugins/upload-janitor
 * Description: Clean up unused images and other files from your uploads folder
 * Author: Michael Tyson
 * Author URI: http://atastypixel.com/blog
 */


define("UPLOAD_JANITOR_STAGE_SEARCH_THEN_SELECT", 1);
define("UPLOAD_JANITOR_STAGE_CONFIRM", 2);
define("UPLOAD_JANITOR_STAGE_DELETE", 3);
define("UPLOAD_JANITOR_STAGE_DELETE_ARCHIVE", 4);

/**
 * Controller
 *
 * @author Michael Tyson
 * @package Upload Janitor
 * @since 0.1
 **/
function upload_janitor_controller() {
    if ( isset($_REQUEST['selections']) ) $_REQUEST['selections'] = upload_janitor_sanitize_files($_REQUEST['selections']);
    if ( isset($_REQUEST['archive_name']) ) $archive_name = str_replace('..', '.', $_REQUEST['archive_name']);
    
    switch ( $_REQUEST['stage'] ) {
        case UPLOAD_JANITOR_STAGE_SEARCH_THEN_SELECT:
            check_admin_referer('upload_janitor_begin');
            $files = upload_janitor_search();
            upload_janitor_select($files);
            break;
            
        case UPLOAD_JANITOR_STAGE_CONFIRM:
            check_admin_referer('upload_janitor_select');
            if ( isset($_REQUEST['archive']) ) {
                if ( !isset($archive_name) ) {
                    $result = upload_janitor_create_archive($_REQUEST['selections']);
                    if ( !is_wp_error($result) ) {
                        $archive_name = $result;
                    }
                }
                $upload = wp_upload_dir();
                $messages=upload_janitor_manual_archive(($archive_name ? trailingslashit($upload['baseurl']).$archive_name : $result));
            }
            upload_janitor_confirm($_REQUEST['selections'], $messages, $archive_name);
            break;
            
        case UPLOAD_JANITOR_STAGE_DELETE:
            check_admin_referer('upload_janitor_confirm');
            $upload = wp_upload_dir();
            if ( !isset($archive_name) || !file_exists(trailingslashit($upload['basedir']).$archive_name) ) {
                $result = upload_janitor_create_archive($_REQUEST['selections']);
                if ( !is_wp_error($result) ) {
                    $archive_name = $result;
                }
            }
            if ( (!isset($result) || !is_wp_error($result)) || isset($_REQUEST['proceed_anyway']) ) {
                $result = upload_janitor_delete($_REQUEST['selections']);
            }
            upload_janitor_report($result, $_REQUEST['selections'], $archive_name);
            break;
            
        case UPLOAD_JANITOR_STAGE_DELETE_ARCHIVE:
            check_admin_referer('upload_janitor_report');
            $result = upload_janitor_delete_archive($archive_name);
            upload_janitor_delete_archive_report($result);
            break;
            
        default:
            upload_janitor_beginning();
            break;
    }
}


// ========================
// =      Interfaces      =
// ========================

function upload_janitor_beginning() {
    $upload = wp_upload_dir(); 
    $folder = str_replace(ABSPATH, '', $upload['basedir'])
    ?>
    <div class="wrap">
	<h2>Upload Janitor</h2>
	
	<div id="upload_janitor_introduction">
    <p>This facility will search through all files within <tt><?php echo $folder ?></tt> that no entries link to, and 
        provide you with the option to archive then delete these files.</p>
        
    <p>After you click 'Begin' below, you will be taken through the following process:</p>
    
    <ol>
        <li>Search and select files to be erased</li>
        <li>Confirm your choice, and optionally download an archive</li>
        <li>Archive and deletion</li>
        <li>Report</li>
    </ol>
    
    <p>All files that are to be erased will be archived first.  This archive takes the form of a <i>tar</i> file stored within the
        <tt><?php echo $folder ?></tt> folder, and will include the original paths of the erased files for easy restoration.
        You will be offered the choice to download this archive if you choose, but regardless of whether you do so, the archive will
        still be created.  You will be given the option to delete this archive at the end.
    </p>
    
    <p>Press 'Begin' below to start.</p>
    
    <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
	<?php wp_nonce_field('upload_janitor_begin') ?>
	<input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_SEARCH_THEN_SELECT ?>" />
    <input type="submit" class="button" value="Begin" onclick="jQuery('#upload_janitor_introduction').hide(); jQuery('#upload_janitor_searching').show();"/>
    </form>
    </div>
    
    <div id="upload_janitor_searching" style="display: none;">
        <h3>Searching</h3>
        
        <p style="text-align: center;">
            <img src="<?php echo trailingslashit(WP_PLUGIN_URL).trailingslashit(basename(dirname(__FILE__))).'spinner.gif'; ?>" />
        </p>
        
        <p style="text-align: center;">Upload Janitor is searching through your <tt><?php echo $folder ?></tt> folder.</p>
        
        <p style="text-align: center;">This may take a few minutes.</p>
    </div>
    
    </div>
    <?php
}

function upload_janitor_select($files) {
    $upload = wp_upload_dir(); 
    $folder = str_replace(ABSPATH, '', $upload['basedir'])
    ?>
    <div class="wrap">
	<h2>Upload Janitor</h2>
	
	<?php if ( count($files) == 0 ) : ?>
	
	<p>There were no unused files found.  Congratulations, you're clean!</p>
	    
	<?php else : ?>
	
	<h3>Select files to delete</h3>
	
	<p>The following files were found with no entries on your blog linking to them.  It's likely that they are unused
	    and that you can delete them safely.</p>
	    
	<p>Select the files you'd like to erase in the list below.  Hold down Control, or Command on a Mac, to select or deselect
	    one at a time, or hold Shift to select multiple items at the same time.</p>
	    
	<p>If you wish, you can download an archive of the selected files.  To do so, click 'Download Archive' below.  Note that
	    even if you do not do so, an archive will still be made, and will be kept inside the <tt><?php echo $folder ?></tt> folder
	    until you delete it.</p>
	    
	<p>Once you have selected the files to erase, click 'Continue' to proceed.</p>
	
    <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
    <input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_CONFIRM ?>" />
	<?php wp_nonce_field('upload_janitor_select') ?>
	<select multiple name="selections[]" style="height: 200px;">
	    <?php foreach ( (array)$files as $file ) : ?>
	    <option value="<?php echo $file ?>" selected="selected"><?php echo $file ?></option>
	    <?php endforeach; ?>
	</select>
	<div>
        <input type="submit" class="button" name="archive" value="Download Archive" />
        &nbsp;&nbsp;&nbsp;
        <input type="submit" class="button" name="continue" value="Continue" />
    </div>
    </form>

    <?php endif; ?>
    </div>
    <?php
}

function upload_janitor_confirm($files, $messages=null, $archive_name=null) {
    $upload = wp_upload_dir(); 
    $folder = str_replace(ABSPATH, '', $upload['basedir'])
    ?>
    <div class="wrap">
	<h2>Upload Janitor</h2>
	
	<?php if ( isset($messages) ) echo $messages; ?>
	
	<?php if ( count($files) == 0 ) : ?>
	
	<p>You didn't select any files to erase.  If you'd like to change your selection, please <a href="history.go(-1);">go back</a> and try again.</p>
	    
	<?php else : ?>
	
	<h3>Confirm deletion of selected files</h3>
	
	<p>Please confirm that you wish to proceed with the deletion of the following files.</p>
	
	<p>Note that an archive will be created before any files are deleted.  This will be kept inside the <tt><?php echo $folder ?></tt> folder
    until you delete it.</p>
	
	<blockquote>
	<ul class="ul-disc">
        <?php foreach ( (array)$files as $file ) : ?>
        <li><?php echo $file ?></li>
        <?php endforeach; ?>	    
	</ul>
	</blockquote>
	    
	<p>Click 'Continue' to proceed with the process, and delete the listed files.</p>
	
    <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
    <input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_DELETE ?>" />
	<?php wp_nonce_field('upload_janitor_confirm') ?>
    <?php foreach ( (array)$files as $file ) : ?>
    <input type="hidden" name="selections[]" value="<?php echo $file ?>" />
    <?php endforeach; ?>
    <?php if ( $archive_name ) : ?><input type="hidden" name="archive_name" value="<?php echo $archive_name ?>" /><?php endif; ?>
    <input type="submit" class="button" value="Continue" />
    </form>

    <?php endif; ?>
    </div>
    <?php
}

function upload_janitor_manual_archive($url) {
    if ( is_wp_error($url) ) {
        return '
        <p>There was an error creating the archive: '.$url->get_error_message().'</p>
        ';
    }
    return '
        <script type="text/javascript" charset="utf-8">
            jQuery(function() { document.location = "'.$url.'"; });
        </script>
        <p>The download of the archive should begin shortly.  If not, <a href="'.$url.'">click here</a>.</p>
    ';
}

function upload_janitor_report($result, $files, $archive_name) {
    ?>
    <div class="wrap">
	<h2>Upload Janitor</h2>
	
	<?php if ( is_wp_error($result) ) : ?>
	
    <h3>Problem encountered</h3>
    
    <p>Encountered a problem while <?php echo (strpos($result->get_error_code(), 'archive') !== false ? 'creating archive' : 'performing deletion') ?>: <?php echo $result->get_error_message() ?>.</p>
    
    <?php if ( strpos($result->get_error_code(), 'archive') !== false ) : ?>
        
        <p>If you wish, you can choose to continue without automatically making an archive.  If you choose this option, be sure to make an
            archive manually.</p>
            
        <p>To continue anyway, press 'Continue without archiving' below, with extreme caution.</p>
        
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
        <input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_DELETE ?>" />
    	<?php wp_nonce_field('upload_janitor_confirm') ?>
        <?php foreach ( (array)$files as $file ) : ?>
        <input type="hidden" name="selections[]" value="<?php echo $file ?>" />
        <?php endforeach; ?>
        <?php if ( $archive_name ) : ?><input type="hidden" name="archive_name" value="<?php echo $archive_name ?>" /><?php endif; ?>
        <input type="hidden" name="proceed_anyway" value="1" />
        <input type="submit" class="button" value="Continue without archiving" onclick="return confirm('Are you sure you want to proceed without making an automatic archive?');" />
        </form>
        
    <?php elseif ( $result->get_error_code() == 'deletion_errors' ) : ?>
        <p>The files that could not be deleted were:</p>
        <ul>
            <?php foreach ( $result->get_error_data() as $file ) : ?>
                <li><?php echo $file ?></li>
            <?php endforeach; ?>
        </ul>
        <?php $files = $result->get_error_data() ?>
    <?php endif; ?>
    
    <p>If you'd like to retry the procedure, click 'Retry' below.</p>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
    <input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_DELETE ?>" />
	<?php wp_nonce_field('upload_janitor_confirm') ?>
    <?php foreach ( (array)$files as $file ) : ?>
    <input type="hidden" name="selections[]" value="<?php echo $file ?>" />
    <?php endforeach; ?>
    <?php if ( $archive_name ) : ?><input type="hidden" name="archive_name" value="<?php echo $archive_name ?>" /><?php endif; ?>
    <input type="submit" class="button" value="Retry" />
    </form>
	    
	<?php else : ?>
	
    <h3>Finished</h3>
    
    <p>The files you selected have been archived and deleted.</p>
    
    <p>If you wish to download the archive of the files, <a href="<?php $upload=wp_upload_dir(); echo $upload['baseurl'].'/'.$archive_name; ?>">click here</a>.</p>
    
    <?php if ( $archive_name ) : ?>
        <p>If you are absolutely confident that all is well, and you wish to delete the archive that Upload Janitor created, <a href="javascript:if ( confirm('Are you sure you want to delete the archive? This cannot be undone.') ) { document.forms.upload_janitor_delete_archive.submit(); }">click here</a>.</p>
    
        <form type="hidden" name="upload_janitor_delete_archive" method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
        <input type="hidden" name="stage" value="<?php echo UPLOAD_JANITOR_STAGE_DELETE_ARCHIVE ?>" />
    	<?php wp_nonce_field('upload_janitor_report') ?>
    	<input type="hidden" name="archive_name" value="<?php echo $archive_name ?>" />
        </form>
    <?php endif; ?>
    
    <?php endif; ?>
    
    </div>
    <?php
}

function upload_janitor_delete_archive_report($result) {
    ?>
    <div class="wrap">
	<h2>Upload Janitor</h2>
	<?php if ( is_wp_error($result) ) : ?>
	
    <h3>Problem encountered</h3>

    <p>Encountered a problem while deleting the archive: <?php echo $result->get_error_message() ?>.</p>
    
    <?php else : ?>
    
    <h3>Finished</h3>
    
    <p>The archive has been deleted.</p>
    <?php endif; ?>
    </div>
    <?php
}

// ========================
// =      Processors      =
// ========================

function upload_janitor_search($subdir=null) {
    $output = array();
    $upload = wp_upload_dir();
    
    if ( !$subdir ) {
        $subdir = $upload['basedir'];
        while ( $subdir{-1} == '/' ) $subdir = substr($substr, 0, -1);
    }
    
    if ( ($dir = opendir($subdir)) )
    while ( ($filename = readdir($dir)) ) {
        set_time_limit(30);
        
        if ( $filename{0} == '.' ) continue;
        
        $path = $subdir.'/'.$filename;
        if ( is_dir($path) ) {
            $output = array_merge($output, upload_janitor_search($path));
        } else {
            $path = substr($path, strlen(trailingslashit($upload['basedir'])));
            $result = upload_janitor_path_found_in_entries($path);
            if ( !is_wp_error($result) && $result == false ) {
                $output[] = $path;
            }
        }
    }
    
    return $output;
}


function upload_janitor_create_archive($files) {
    $upload = wp_upload_dir();
    
    set_time_limit(1200);
    
    $counter = 0;
    do {
        $archive_name = 'upload_janitor_archive_'.date('Y-m-d').($counter>0 ? "-$counter" : '').'.tar.gz';
        $counter++;
    } while ( file_exists(trailingslashit($upload['basedir']).$archive_name) );
    
    // Open 'tar' util
    $proc = proc_open("tar zcf ".escapeshellarg(trailingslashit($upload['basedir']).$archive_name)." -C ".escapeshellarg($upload['basedir'])." -T -",
                      array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w")), $pipes);
    if ( !is_resource($proc) ) {
        return new WP_Error('archive_tar_failed', "Couldn't start the 'tar' utility");
    }
    
    // Write list of files to archive
    fwrite($pipes[0], join("\n", $files)."\n");
    fclose($pipes[0]);

    // Read standard output
    $stdout = "";
    while ( !feof($pipes[1]) ) {
        $stdout .= fgets($pipes[1], 1024);
    }
    fclose($pipes[1]);
    
    // Read standard error
    $stderr = "";
    while ( !feof($pipes[2]) ) {
        $stderr .= fgets($pipes[2], 1024);
    }
    fclose($pipes[2]);
    
    $result = proc_close($proc);
    
    $archive_exists = file_exists(trailingslashit($upload['basedir']).$archive_name);
    
    if ( $result != 0 ) {
        $error = new WP_Error('archive_failed', "The 'tar' utility reported error number $result".($stdout || $stderr ? " and said $stdout $stderr" : ""));
        if ( $archive_exists ) $error->archive_name = $archive_name;
        return $error;
    }
    
    if ( !$archive_exists ) {
        return new WP_Error('archive_failed', "Creation of the archive failed");
    }
    
    return $archive_name;
}

function upload_janitor_delete($files) {
    set_time_limit(count($files) * 2);
    global $wpdb;
    $errors = array();
    
    $upload = wp_upload_dir();
    foreach ( $files as $file ) {
        
        $path = trailingslashit($upload['basedir']).$file;
        if ( file_exists($path) ) {
            if ( @unlink($path) ) {
                $wpdb->query("DELETE FROM $wpdb->posts WHERE $wpdb->posts.post_type = 'attachment' AND $wpdb->posts.guid = '".addslashes_gpc(trailingslashit($upload['baseurl']).$file)."' LIMIT 1");
            } else {
                $errors[] = $file;
                continue;
            }
        }
        
        $dir = dirname($file);
        while ( $dir != '.' ) {
            if ( !upload_janitor_dir_empty(trailingslashit($upload['basedir']).$dir) ) break;
            @rmdir(trailingslashit($upload['basedir']).$dir);
            $dir = dirname($dir);
        }
    }
    
    return (count($errors) == 0 ? true : new WP_Error('deletion_errors', "Some errors were encountered while deleting", $errors));
}

function upload_janitor_delete_archive($name) {
    if ( !$name ) {
        return new WP_Error('no_archive', "There is no archive to delete");
    }
    $upload = wp_upload_dir();
    if ( @unlink(trailingslashit($upload['basedir']).$name) ) {
        return true;
    } else {
        return new WP_Error('delete_archive_failed', "Couldn't delete the archive");
    }
}


// ========================
// =       Helpers        =
// ========================

function upload_janitor_sanitize_files($files) {
    foreach ( $files as $index => $file ) {
        if ( strpos($file, '..') !== false ) $files[$index] = str_replace('..', '', $file);
    }
    return $files;
}

function upload_janitor_path_found_in_entries($path) {
    global $wpdb;
    
    $terms = array();
    
    $terms[] = $path;
    $terms[] = str_replace('%2F', '/', urlencode($path));
    $terms[] = str_replace(array('+','%2F'), array('%20','/'), urlencode($path));
    $terms[] = htmlspecialchars($path);
    $terms[] = htmlspecialchars(str_replace('%2F', '/', urlencode($path)));
    $terms[] = htmlspecialchars(str_replace(array("+","%2F"), array("%20","/"), urlencode($path)));

    $subquery = join(' OR ', array_map(create_function('$term', "return '$wpdb->posts.post_content LIKE \"%'.str_replace(array('%', '_'), array('\%', '\_'), addslashes_gpc(\$term)).'%\"';"), $terms));

    $query = "SELECT COUNT(*) AS count FROM $wpdb->posts WHERE ($subquery) AND $wpdb->posts.post_type != 'attachment'";
    $result = $wpdb->get_row($query);
    
    if ( is_wp_error($result) ) return $result;
    
    return ( (string)$result->count !== '0' );
}

function upload_janitor_dir_empty($path) {
    if ( !($dir=opendir($path) ) ) return false;
    while (($file = readdir($dir))) {
        if ( $file != '.' && $file != '..' ) return false;
    }
    return true;
}

// ========================
// =    Initialisation    =
// ========================



/**
 * Set up
 *
 * @author Michael Tyson
 * @package Upload Janitor
 * @since 0.1
 */
function upload_janitor_setup() {
	add_management_page( 'Upload Janitor', 'Upload Janitor', 5, __FILE__, 'upload_janitor_controller' );
}

add_action( 'admin_menu', 'upload_janitor_setup' );
