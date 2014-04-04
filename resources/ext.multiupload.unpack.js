( function ( $, mw ) {

function notify( message ) {
	// message could be a jQuery object, or just a string.
	try {
		// if wiki is new enough to have mw.notify(), use it.
		mw.loader.using( [ 'mediawiki.notification', 'mediawiki.notify' ], function() {
			mw.notify( message );
		} );
	} catch ( e ) {
		// otherwise, use dialog().
		mw.loader.using( 'jquery.ui.dialog', function() {
			var d_opts = {
				buttons : [ {
					text : mw.message( 'multiupload-notify-ok' ).plain(),
					click : function () {
						$(this).dialog( 'close' );
					}
				} ]
			};
			$( '<div/>' ).append( message ).dialog( d_opts );
			// TODO make it stretch to width of message
		} );
	}
}

function apiErr( code, result, message ) {
	var $errdiv = $( '<div/>' ).append( message );
	var errtxt = code;
	if ( 'error' in result && result.error.info ) {
		errtxt = 'Error: ' + result.error.info;
	} else if ( result.exception ) {
		errtxt = 'Error: ' + result.exception;
	} else if ( code == 'http' ) {
		errtxt = mw.message( 'multiupload-http-error' ).parse();
	}
	$errdiv.append( errtxt );
	if ( result.error && result.error.messages ) {
		$errdiv.append( result.error.messages );
	}
	notify( $errdiv );
}

function unpackOnServer( input, sessionkey, spinnerName ) {
	mw.loader.using( 'mediawiki.api', function () {
		(new mw.Api()).get( {
			action : 'multiupload-unpack',
			key : sessionkey,
			filename : input.files[0].name
		}, {
			ok : function ( data ) {
				mw.libs.ext.multiupload.removeTinySpinner( spinnerName );
				if ( 'multiupload-unpack' in data && 'contents' in data['multiupload-unpack'] ) {
					reloadForm( input, data['multiupload-unpack']['contents'] );
				} else {
					// TODO: if invalid token, get a new one and redo
					notify( 'Error unpacking ' + input.files[0].name );
				}
			},
			err : function ( code, result ) {
				mw.libs.ext.multiupload.removeTinySpinner( spinnerName );
				apiErr( code, result, mw.message( 'multiupload-unpack-error' ).parse() );
			}
		} );
	} );
}

function reloadForm( input, filedata ) {
	var packagename = input.files[0].name;
	var $row = $( input ).parents( 'fieldset.row' );
	var projName = $row.find( ':input.wpProjectName' ).val();
	if ( ! projName ) {
		projName = '';
	}
	var opts = {
		'wpSourceType' : 'Stash',
		'wpSessionKey' : '',
		'wpUploadFile' : null,
		'wpUploadUrl'  : null,
		'wpDestFile'   : '',
		'wpProjFilename' : '',
		'wpProjectName'  : projName,
		'wpDestTypeTouched' : 0,
		'wpDestPageTouched' : 0
	};
	mw.libs.ext.multiupload.removeRow( $row );
	for ( var i in filedata ) {
		opts.wpSessionKey = filedata[i][0];
		opts.wpDestFile = filedata[i][1];
		opts.wpProjFilename = filedata[i][1];
		$row = mw.libs.ext.multiupload.addRow( opts, $row );
		// this is hacky
		// if that stuff procedure left a hanging help message at
		// the top, remove it
		$row.find( 'tr:first-child > td.htmlform-tip' )
			.parents( 'tr' )
			.remove();
		$row.find( 'tbody' )
			.prepend( '<tr><td colspan="2"><h2>' +
				mw.message( 'multiupload-file-unpacked-from', filedata[i][1], packagename ).parse() +
				'</h2></td></tr>'
			);
	}
}


// FormDataTransport looks for these
if ( ! mw.UploadWizard ) {
	mw.UploadWizard = {};
}

if ( ! mw.UploadWizard.config ) {
	mw.UploadWizard.config = {};
}

var enableChunked = ( mw.config.get( 'wgVersion' ).match( /^1\.2[0-9]\./ ) ? true : false );
$.extend( mw.UploadWizard.config,  {
	chunkSize : 5 * 1024 * 1024,
	enableChunked : enableChunked,
	maxPhpUploadSize : mw.config.get( 'wgMultiUploadMaxPhpUploadSize' )
} );

$.extend( mw.libs.ext.multiupload, {

	unpackPackageFile : function ( input, spinnerName ) {
		var upload = {
			file : input.files[0],
			ui : { setStatus : function ( s ) { } },
			state : undefined
		};
		var progressCb = function ( progress ) {};
		var doneCb = function ( response ) {
			if ( response.upload && response.upload.sessionkey ) {
				unpackOnServer( input, response.upload.sessionkey, spinnerName );
			} else {
				apiErr( null, response, mw.message( 'multiupload-upload-package-error' ).parse() );
				mw.libs.ext.multiupload.removeTinySpinner( spinnerName );
			}
		};
		var transport = new mw.FormDataTransport( 
			mw.util.wikiScript( 'api' ),
			{
				action: 'upload',
				stash: 1,
				token: $(':input#wpEditToken').val(),
				format: 'json'
			},
			upload,
			progressCb,
			doneCb
		);
		transport.upload();
	}

} );

} )( $, mw );
