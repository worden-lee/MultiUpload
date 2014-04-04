<?php
/* MultiUpload extension for MediaWiki 1.13 and later
 * Copyright (C) Lee Worden <worden.lee@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}
 
# ===== Extension credits that will show up on Special:Version =====
$wgExtensionCredits['specialpage'][] = array(
	'name'         => 'MultiUpload',
	'version'      => '3.0',
	'author'       => 'Travis Derouin and Lee Worden', 
	'url'          => '',
	'description'  => 'Special page to upload multiple files at once',
);

# ===== Configuration variables =====

# start with 1 file selector shown
$wgMultiUploadInitialNumberOfImportRows = 1;

# Can't have a huge number of files to upload on the single form -
# for instance, because of php restrictions on number 
# and length of POST values 
$wgMultiUploadMaxImportFilesPerPage = 20;

# where we unpack .tar and .zip files
$wgMultiUploadTempDir = '/tmp';

# ===== Register the special page =====

$wgSpecialPages['MultiUpload'] = 'SpecialMultiUpload';
$wgAutoloadClasses['SpecialMultiUpload']
	= dirname(__FILE__) . '/SpecialMultiUpload.php';

# ===== resource loader =====

$resourceModuleTemplate = array(
	'localBasePath' => dirname(__FILE__).'/resources',
);

$wgResourceModules['special.upload.patched'] = $resourceModuleTemplate + array(
	'scripts' => array(
		'mediawiki.special.upload.js.patched', 
		'upload.js.patched',
	),
	'styles' => array(
		'ext.multiupload.mw1.19.compatibility.css', # actually useful in 1.23 as well
	),
	'messages' => array(
		'widthheight',
		'size-bytes',
		'size-kilobytes',
		'size-megabytes',
		'size-gigabytes',
		'largefileserver',
	),
	'dependencies' => array( 
		'mediawiki.libs.jpegmeta', 
		'mediawiki.api',
		'mediawiki.Title',
		'mediawiki.legacy.wikibits',
		'mediawiki.util',
		'jquery.spinner',
	),
);

// slightly different patched code for older versions of MW
if ( version_compare( $wgVersion, '1.22', '<' ) ) {
	$wgResourceModules['special.upload.patched']['scripts'] = array(
		'mediawiki.special.upload.js.patched.1.21.3',
		'upload.js.patched.1.21.3',
	);
}

$wgResourceModules['ext.multiupload.top'] = $resourceModuleTemplate + array(
	'scripts' => array( 'ext.multiupload.top.js' ),
	'styles' => array( 'ext.multiupload.top.css' ),
	'position' => 'top',
);

$wgResourceModules['ext.multiupload.unpack'] = $resourceModuleTemplate + array(
	'scripts' => array(
		'ext.multiupload.unpack.js',
		'mw.FormDataTransport.js',
	),
	'dependencies' => array(
		'mediawiki.api',
		'ext.multiupload.top',
	),
	'messages' => array(
		'multiupload-upload-package-error', 
		'multiupload-unpack-error',
		'multiupload-http-error',
		'multiupload-file-unpacked-from',
	),
);

$wgResourceModules['ext.multiupload.shared'] = $resourceModuleTemplate + array(
	'scripts' => array(
		'ext.multiupload.shared.js',
	),
	'styles' => array(
		'ext.multiupload.shared.css',
	),
	'dependencies' => array(
		'ext.multiupload.top',
		'special.upload.patched',
	),
	'messages' => array(
		'multiupload-row-name-base',
		'multiupload-unpack-button',
	),
);

$wgResourceModules['ext.multiupload'] = $resourceModuleTemplate + array(
	'scripts' => array( 'ext.multiupload.js' ),
	'dependencies' => array(
		'ext.multiupload.shared',
	),
);

# api.php actions

# TODO this here
$wgAPIModules['multiupload-unpack'] = 'MultiUploadApiUnpack';
$wgAutoloadClasses['MultiUploadApiUnpack']
	= dirname(__FILE__) . '/MultiUploadApi.php';

# (potentially) multilingual messages
$wgExtensionMessagesFiles['MultiUpload']
	= dirname(__FILE__) . '/MultiUpload.i18n.php';

# special page aliases
$wgExtensionMessagesFiles['MultiUploadAlias']
	= dirname(__FILE__) . '/MultiUpload.alias.php';

?>
