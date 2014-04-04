<?php
/* MultiUpload extension for MediaWiki 1.19 and later
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

$messages = array();

$messages['en'] = array(
  'multiupload' => 'MultiUpload',
  'multiupload-row' => 'File $1',
  'multiupload-submit' => 'Upload files',
  'multiupload-uploadedto' => 'Uploaded file $1.',
  'multiupload-row-name-base' => 'File $1',
  'multiupload-unpack-button' => 'Unpack',
  'multiupload-notify-ok' => 'OK',
  'multiupload-upload-package-error' => 'Error uploading package file: ',
  'multiupload-unpack-error' => 'Error unpacking package file: ',
  'multiupload-http-error' => 'Couldn\'t connect to server.',
  'multiupload-file-unpacked-from' =>
    'File <b>$1</b> from package <b>$2</b>',
  # for future use
  'duplicate-destination' =>
    'The destination filename is the same as ‘Upload file $1’, above.',
  'duplicate-upload' =>
    'This file is a duplicate of the file being uploaded by ‘Upload file $1’, above.',
);

?>
