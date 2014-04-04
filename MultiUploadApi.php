<?php

class MultiUploadApiUnpack extends ApiBase
{ 
	static public $pkgExtensions = array('tar','tgz','tar.gz','zip');
	static public $unpackDirBase = 'MultiUpload_unpack_';

	public function suffixMatches( $string, $extension ) {
		$len = strlen( $extension );
		return substr( $string, -$len ) == $extension;
	}

	# given a package (e.g. tar.gz or .zip file), unpack it into a temporary
	# location.	
	# $pkgFile is the file to be unpacked, $srcName is the name the file's
	# supposed to have ($pkgFile might be '/tmp/php192ujhK' and $srcName be
	# 'package.tar.gz').
	# In case of success, return value is array(true,location_code), where
	# location_code is the unique part of the path such that 
	# tempDir().'/'.$unpackDirBase.location_code is the name of the directory 
	# where the unpacked files are.
	# In case of error, returns array(false, error_message).
	public function unpack( $pkgFile, $srcName ) {
		global $wgMultiUploadTempDir;
		$unpackLocation = realpath( tempnam(
			$wgMultiUploadTempDir,
			MultiUploadApiUnpack::$unpackDirBase
		) );
		$prefix = realpath( $wgMultiUploadTempDir ) . '/' .
				MultiUploadApiUnpack::$unpackDirBase;
		wfDebug("unpackLocation is $unpackLocation, prefix is $prefix\n");
		if ( strncmp( $unpackLocation, $prefix, strlen( $prefix ) ) != 0 ) {
			wfDebug( "Temp directory $unpackLocation doesn't start with $prefix!\n" );
			return array( false, "Temp directory " . htmlentities( $unpackLocation ) .
				" doesn't start with " . htmlentities( $prefix ) . "!" );
		}
		$locationCode = substr( $unpackLocation, strlen( $prefix ) );
		wfDebug( "location code is " .$locationCode."\n");
		if ( file_exists( $unpackLocation ) and ! unlink( $unpackLocation ) ) {
			return array( false, "Couldn't unlink $unpackLocation" );
		}
		if ( ! mkdir( $unpackLocation ) ) {
			return array( false, "Couldn't make temp dir $unpackLocation" );
		}
		if ( ! chmod( $unpackLocation, 0700 ) ) {
			return array( false,
				"Couldn't set restricted permissions on temp dir $unpackLocation" );
		}
		# tar seems trustworthy not to extract files into other locations
		# TODO (found on Talk:WorkingWiki page): When unpacking uploaded .tar.gz files: 
		# [http://en.wikipedia.org/wiki/Tar_(file_format) Wikipedia says]: GNU tar by default
		# refuses to create or extract absolute paths, but is still vulnerable to parent-directory
		# references.  So I would need to check tar contents for ../ before extracting.  In practice
		# it seems to be catching this case — but I should trap it explicitly anyway?
		if ( $this->suffixMatches( $srcName, '.tgz' ) or $this->suffixMatches( $srcName, '.tar.gz' ) ) {
			$unpack_command = 'tar -xz -C ' . escapeshellarg( $unpackLocation )
				.' -f ' . escapeshellarg( $pkgFile );
		} else if ( $this->suffixMatches( $srcName, '.tar' ) ) {
			$unpack_command = 'tar -x -C ' . escapeshellarg( $unpackLocation )
				. ' -f ' . escapeshellarg( $pkgFile );
		}
		# unzip also rejects files outside of the extraction directory
		else if ( $this->suffixMatches( $srcName, '.zip' ) ) {
			$unpack_command = 'unzip -q ' . escapeshellarg( $pkgFile )
				. ' -d ' . escapeshellarg( $unpackLocation );
		} else {
			return array( false, "Unknown filetype $srcName" );
		}
		system( $unpack_command, $unpack_success );
		if ( $unpack_success != 0 ) {
			return array( false,
				"command “{$unpack_command}” failed with return code $unpack_success"
			);
		}
		return array( true, $locationCode );
	}

	/**
	* Search a directory (recursively) for files
	*
	* @param $dir Path to directory to search
	* @return mixed Array of relative filenames on success, or false on failure
	*/
	public function recursiveFindFiles( $dir ) {
		if ( is_dir( $dir ) ) {
			if ( $dhl = opendir( $dir ) ) {
				$files = array();
				while ( ( $file = readdir( $dhl ) ) !== false ) {
					if ( $file == '.' || $file == '..' ) {
						continue;
					}
					$path = $dir . '/' . $file;
					if ( is_dir ( $path ) ) {
						$files_within = $this->recursiveFindFiles( $path );
						if ( is_array( $files_within ) and count( $files_within ) > 0 ) {
							foreach ( $files_within as $file_within ) {
								$files[] = $file . '/' . $file_within;
							}
						}
					} else if ( is_file( $path ) ) {
						$files[] = $file;
					}
				}
				return $files;
			} // else
			return false;
		} else if ( is_file( $dir ) ) {
			return array( $dir );
		} // else
		return false;
	}

	public function recursiveUnlink( $filename, $del_self ) {
		if ( ! is_link( $filename ) and is_dir( $filename ) and
				( $handle = opendir( $filename ) ) ) {
			while( ( $entry = readdir($handle) ) !== false ) {
				if ( $entry !== '.' and $entry !== '..' ) {
					$this->recursiveUnlink( $filename.'/'.$entry, true );
				}
			}
		}
		if ( $del_self ) {
			if ( is_dir( $filename ) and ! is_link( $filename ) ) {
				rmdir( $filename );
			} else if ( file_exists( $filename ) ) {
				unlink( $filename );
			}
		}
	}

	public function execute() {
		$params = $this->extractRequestParams();
		error_log("MultiUploadApiUnpack, params is " . json_encode($params) . "\n");

		// we are called with a session key representing a file that's been 
		// uploaded and stashed.  first is to find the physical file.
		// NOTE: could possibly do this easier using UploadFromBase - when I
		// wrote this I thought that wasn't working but it actually is.
		// See https://sourceforge.net/p/workingwiki/bugs/472.
		$sessionkey = $params['key'];
		$repo = RepoGroup::singleton()->getLocalRepo();
		$stash = new UploadStash( $repo );
		$metadata = $stash->getMetadata( $sessionkey );
		$file = $repo->getLocalReference( $metadata['us_path'] );
		$path = $file->getPath();

		// second is to unpack it.  Code for that is in ImportQueue.
		$packagefilename = $params['filename'];
		list( $success, $unpack_code ) = $this->unpack( $path, $packagefilename );
		if ( ! $success ) {
			$this->dieUsage(
				'Could not unpack uploaded package: ' . $unpack_code,
				'unknownerror'
			);
		}
		global $wgMultiUploadTempDir;
		$unpackdir = realpath( $wgMultiUploadTempDir ) . '/'
			.  MultiUploadApiUnpack::$unpackDirBase
			. $unpack_code;
		// done with the package - delete it
		$stash->removeFile( $sessionkey );
		// hmm, that didn't work when I tested it.  Make sure it gets deleted.
		unlink( $path );

		// now to traverse that directory, stash all the files and remember their 
		// names
		$filenames = $this->recursiveFindFiles( $unpackdir );
		natsort( $filenames );
		$filedata = array();
		foreach ( $filenames as $filename ) {
			$stashed_file = $stash->stashFile( $unpackdir . '/' . $filename, 'file' );
			$filedata[] = array( $stashed_file->getFileKey(), $filename );
		}
		$this->recursiveUnlink( $unpackdir, true );

		$res = array(
			'contents' => $filedata,
		);
		$this->getResult()->addValue( null, 'multiupload-unpack', $res );
	}

	public function getAllowedParams() {
		return array(
			'key' => array(
				ApiBase::PARAM_TYPE => 'string',
				#ApiBase::PARAM_REQUIRED => false
			),
			'filename' => array(
				ApiBase::PARAM_TYPE => 'string',
				#ApiBase::PARAM_REQUIRED => false
			),
		);
	}

	public function isWriteMode() {
		return true;
	}

	public function getParamDescription() {
		return array(
			'key' => '"filekey" obtained when uploading the package file to stash',
			'filename' => 'filename of the package',
		);
	}

	public function getDescription() {
		return 'Unpack a zip or tar file before importing its contents.';
	}

	public function getVersion() {
		return __CLASS__ . ': (version unknown.  By Lee Worden.)';
	}
}

?>
