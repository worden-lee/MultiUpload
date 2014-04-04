<?php
/*
 * Implements Special:MultiUpload
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
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup Upload
 */

/* use the local, patched version of SpecialUpload.php for now */
global $wgVersion;
if ( version_compare( $wgVersion, '1.22', '<' ) ) {
	$wgAutoloadLocalClasses['SpecialUpload']
	   = $wgAutoloadLocalClasses['UploadForm']
	   = $wgAutoloadLocalClasses['UploadSourceField']
	   = dirname(__FILE__) . '/SpecialUpload.1.21.3.php';
} else {
	$wgAutoloadLocalClasses['SpecialUpload']
	   = $wgAutoloadLocalClasses['UploadForm']
	   = $wgAutoloadLocalClasses['UploadSourceField']
	   = dirname(__FILE__) . '/SpecialUpload.php';
}

/**
 * Special page for uploading multiple files in one submission.
 *
 */
class SpecialMultiUpload extends SpecialUpload {

	function __construct( $request = null ) {
		SpecialPage::__construct( 'MultiUpload', 'upload' );
	}

	public $mFrom;   // which numbered rows to display, process
	public $mTo;

	public $mRows;

	protected function handleRequestData() {
		$request = $this->getRequest();
		$this->checkToken( $request );
		$this->mFrom = 1; #$request->getVal( 'from', 1 );
		global $wgMultiUploadInitialNumberOfImportRows;
		$this->mTo = $request->getVal( 'wpLastRowIndex', 
			$wgMultiUploadInitialNumberOfImportRows );
		# stick an invisible template row in front
		$this->mRows = array(
			$this->createRow( 'template' ),
		);
		$i = $this->mFrom;
		while ( $i <= $this->mTo ) {
			$this->mRows[] = $row = $this->createRow( $i );
			$row->handleRequestData();
			$i++;
		}
		$this->showUploadForm( $this->getUploadForm() );
	}
 
	protected function numberOfRows() {
		return $wgMultiUploadInitialNumberOfImportRows;
	}

	protected function createRow( $i ) {
		$row = new UploadRow( $this, $i );
		$row->setContext( $this->getContext() );
		return $row;
	}

	/**
	 * Get an UploadForm instance with title and text properly set.
	 *
	 * @param $message String: HTML string to add to the form
	 * @param $sessionKey String: session key in case this is a stashed upload
	 * @param $hideIgnoreWarning true if warning's already been dealt with
	 * @return UploadForm
	 */
	protected function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
		# Initialize form
		$form = new MultiUploadForm( $this, $this->mRows, $this->mTo, $this->getContext() );
		$form->setTitle( $this->getTitle() );
		# todo add header, footer, etc.

		# Check the edit token.
		# Unlike Special:Upload, no fine distinctions about
		# whether they're uploading vs. cancelling, etc.
		if( !$this->mTokenOk && $this->getRequest()->wasPosted() ) {
			$form->addPreText( $this->msg( 'session_fail_preview' )->parse() );
		}

		return $form;
	}

        public function getGlobalFormDescriptors() {
		return array();
	}
};

/**
 * Subclass of HTMLForm that provides the form section of SpecialMultiUpload
 */
class MultiUploadForm extends UploadForm {
	protected $mPage;
	protected $mRows;
	protected $mLastIndex;

	public function __construct( $page, $rows, $lastIndex, IContextSource $context = null ) {
		$this->mPage = $page;
		$this->mRows = $rows;
		$this->mLastIndex = $lastIndex;
		parent::__construct( array(), $context );
		$this->mSourceIds = array();
		$this->mMessagePrefix = 'multiupload';
		$this->setSubmitText( wfMessage( 'multiupload-submit' )->parse() );
		/* Sure, this could/should go in ext.multiupload.top.css,
		 * but how to do the image path? */
		global $wgStylePath;
		$this->getOutput()->addHeadItem( 'multiupload.top',
			"<style>#mw-upload-form > :first-child { display:none; }\n"
			.".client-js #mw-upload-form > * { display:none; }\n"
			.".client-js #mw-upload-form { background-image:url($wgStylePath/common/images/spinner.gif); min-height:20px; min-width:20px; background-repeat:no-repeat; }\n"
			.'</style>' );
	}

	protected function constructData( array $options = array(), IContextSource $context = null ) {
	}

	protected function constructForm( IContextSource $context ) {
		$descriptor = $this->getGlobalFormDescriptors() 
			+ $this->mPage->getGlobalFormDescriptors();
		foreach ($this->mRows as $row) {
			$rowdesc = $row->getFormDescriptors();
			$descriptor = $descriptor + $rowdesc;
		}
		HTMLForm::__construct( $descriptor, $context, 'upload' );
	}

	protected function getGlobalFormDescriptors() {
		return array(
			'LastRowIndex' => array(
				'type' => 'hidden',
				'id' => 'wpLastRowIndex',
				'default' => $this->mLastIndex,
			),
		);
	}

	public function getLegend( $key ) {
		$parts = explode( '-', $key );
		$msg = array_shift($parts);
		return wfMessage( "{$this->mMessagePrefix}-$msg", $parts )->parse();
	}

	protected function addJsConfigVars( $out ) {
		parent::addJsConfigVars( $out );
		$jsconfig = array(
			'wpFirstRowIndex' => $this->mPage->mFrom,
			'wpLastRowIndex' => $this->mPage->mTo,
			'wgMultiUploadMaxPhpUploadSize' => min(
				wfShorthandToInteger( ini_get( 'upload_max_filesize' ) ),
				wfShorthandToInteger( ini_get( 'post_max_size' ) )
			),

		);
		foreach ($this->mRows as $row) {
			$jsconfig = $jsconfig + $row->jsConfigVars();
		}
		$out->addJsConfigVars( $jsconfig );
	}

	protected function addRLModules( $out ) {
		$out->addModules( array(
			'ext.multiupload.top',
			'ext.multiupload',
		) );
	}
}

/**
 *  Hoping this gets merged into core, won't have to do it here
 */
if ( ! class_exists( 'FauxWebRequestUpload' ) ) {
	/**
	 * A WebRequestUpload that can be faked.
	 */
	class FauxWebRequestUpload extends WebRequestUpload {
		/**
		 * Constructor. Should only be called by FauxRequest.
		 *
		 * @param $request WebRequest The associated request
		 * @param array $data Data in the same format that would be found
		 *          in the $_FILES array.  If provided, will be used
		 *          instead of $_FILES[$key].
		 */
		public function __construct( $request, $data ) {
			$this->request = $request;
			$this->fileInfo = $data;
			$this->doesExist = true;
		}
	};
	/**
	 * allow DerivativeRequest to include fake uploaded files
	 */
	class DerivativeRequestWithFiles extends DerivativeRequest {
		/**
		 * @param $key string
		 * @return WebRequestUpload
		 */
		public function getUpload( $key ) {
			if (array_key_exists($key, $this->data)) {
				return new FauxWebRequestUpload( $this, $this->data[$key] );
			} else {
				return new WebRequestUpload( $this, $key );
			}
		}
	};
} else {
	/**
	 * If the feature is in MW core, just use it
	 */
	class DerivativeRequestWithFiles extends DerivativeRequest { };
}

class UploadRow extends SpecialUpload {
	var $mPage;
	var $mRowNumber;
	var $mRequest;
	var $mFormMessage;
	var $mSessionKey;
	var $mHideIgnoreWarning;
	var $mExtraButtons;

	/**
	 * different constructor, let it know which row it is and
	 * the upload object it belongs to
	 */
	public function __construct( $page, $number ) {
		$this->mPage = $page;
		$this->setContext( $page->getContext() );
		$this->mRowNumber = $number;
		$this->mRequest = null;
		$this->mFormMessage = '';
		$this->mSessionKey = '';
		$this->mHideIgnoreWarning = '';
		$this->mExtraButtons = array();
	}

	/**
	 * UploadBase and various parent class methods expect certain
	 * form field names that don't have a row number appended.  Here
	 * we create a fake request object that responds to those field 
	 * names.
	 */
	public function getRequest() {
		if ( ! $this->mRequest ) {
			$webrequest = $this->mPage->getRequest();
			$i = $this->mRowNumber;
			$values_kept = $values_altered = array();
			foreach ($webrequest->getValues() + $_FILES as $key=>$value) {
				$matches = null;
				$prefix_match = preg_match('/^(.*?)(\d+)$/', $key, $matches);
				if ($prefix_match === false) {
					/// ERROR
				} else if ($prefix_match == 0) {
					// key has no row number
					$values_kept[$key] = $value;
				} else if ($matches[2] == $this->mRowNumber) {
					// key has my row number
					$values_altered[$matches[1]] = $value;
				}	// else it has some other row number
			}
			#error_log( "request $i : " . json_encode( $values_kept + $values_altered ) );
			$this->mRequest = new DerivativeRequestWithFiles( $webrequest, 
				$values_kept + $values_altered, 
				$webrequest->wasPosted() );
		}
		return $this->mRequest;
	}

	protected function handleRequestData() {
		$request = $this->getRequest();
		$this->mUploadSuccessful = $request->getCheck( 'wpUploadSuccessful' );
		parent::handleRequestData();
	}

	/**
	 * We don't do our own form output - we give all output to the
	 * page object to aggregate into a single form
	 */
	protected function showUploadForm( $form ) {
		// it gets called
		//wfDebug(" *** SHOWUPLOADFORM SHOULD NOT BE CALLED! *** \n");
	}

	/**
	 * Unlike the superclass, don't actually create a form object when
	 * this is called, wait and do it when the page object is ready to
	 * assemble its full output form.
	 *
	 * @param $message String: HTML string to add to the form
	 * @param $sessionKey String: session key in case this is a stashed upload
	 * @param $hideIgnoreWarning Boolean: whether to hide "ignore warning" check box
	 * @return UploadForm
	 * todo lots of redundancy here
	 */
	public function getUploadForm( $message = '', $sessionKey = '', $hideIgnoreWarning = false ) {
	}

	public function showUploadError( $message ) {
		$this->mFormMessage .= $this->getUploadError( $message );
	}

	protected function showRecoverableUploadError( $message ) {
		$this->mSessionKey = $this->mUpload->stashSession();
		$this->mFormMessage .= $this->getRecoverableUploadError( $message );
	}

	protected function showUploadWarning( $warnings ) {
		$warningHtml = $this->getUploadWarning( $warnings );
		if ($warningHtml === false ) {
			return false;
		}

		$this->mSessionKey = $this->mUpload->stashSession();
		$this->mFormMessage .= $warningHtml;
		$this->mHideIgnoreWarning = true;
		# Special:Upload changes the 'Upload' button to 
		# 'Submit modified file description', and adds two
		# additional submit buttons.  We add the additional
		# two as check boxes, and just leave the 
		# 'Upload' button below all rows.
		$this->mExtraButtons = array( 
			'UploadIgnoreWarning' => 'ignorewarning',
			'CancelUpload' => 'reuploaddesc',
		);

		return true;
	}

	/**
	 * This is apparently a pretty bad one.
	 * Special:Upload replaces the whole page with an error page
	 * when this happens.  I'll just do it as an error message added
	 * to the form.  But if it happens, you should probably start 
	 * over clean.
	 */
	protected function showFileDeleteError() {
		$this->mFormMessage .= '<div class="error">'
			. $this->getOutput()->msg(
				'filenotfound', $this->mUpload->getTempPath() )
			. '</div>';
	}

	/**
	 * Suppress error message that file is empty, because this 
	 * happens normally when you don't fill all the rows of the form.
	 */
	protected function processVerificationError( $details ) {
		if ( $details['status'] === UploadBase::EMPTY_FILE
		     and $this->mDesiredDestName === '' ) {
			return;
		}
		parent::processVerificationError( $details );
	}

	protected function createFormRow() {
		return new UploadFormRow( $this,
			$this->getFormOptions( $this->mSessionKey,
				 $this->mHideIgnoreWarning ),
			$this->getContext() ); 
	}

	public function getFormDescriptors() {
		# Initialize form
		$form = $this->createFormRow();

		$preText = '';

		if ( $this->mDesiredDestName ) {
			$preText .= $this->getViewDeletedLinks();
		}
	
		# Give a notice if the user is uploading a file that has been deleted or moved
		# Note that this is independent from the message 'filewasdeleted' that requires JS
		$desiredTitleObj = Title::makeTitleSafe( NS_FILE, $this->mDesiredDestName );

		$delNotice = ''; // empty by default
		if ( $desiredTitleObj instanceof Title && !$desiredTitleObj->exists() ) {
			LogEventsList::showLogExtract( $delNotice, array( 'delete', 'move' ),
				$desiredTitleObj,
				'', array( 'lim' => 10,
						 'conds' => array( "log_action != 'revision'" ),
						 'showIfEmpty' => false,
						 'msgKey' => array( 'upload-recreate-warning' ) )
			);
		}
		$preText .= $delNotice;

		$preText .= $this->mFormMessage;
		
		return $form->descriptor( $preText, $this->mExtraButtons,
		       $this->mUploadSuccessful );
	}

	protected function shouldProcessUpload() {
		return ( !$this->mUploadSuccessful &&
			 $this->mPage->mTokenOk && !$this->mCancelUpload &&
			 ( $this->getRequest()->getVal('wpDestFile') &&
			   $this->mUploadClicked ) );
	}

	protected function uploadSucceeded() {
		$this->mDesiredDestName = $this->mLocalFile->getTitle()->getDBKey();
	}
       
	public function jsConfigVars() {
		return array(
			'wgMultiUploadAutoFill'.$this->mRowNumber =>
				(!$this->mForReUpload &&
				// if mDestFile was provided in the request,
				// don't overwrite it by autofilling
				$this->mDesiredDestName === ''),
			);
	}
}

class UploadFormRow extends UploadForm {
	var $mRow;

	function __construct( $row, array $options = array(), IContextSource $context = null ) {
		$this->mRow = $row;
		$this->constructData($options, $context);
		#HTMLForm::__construct( array(), $context, 'upload' );
		#$this->mSourceIds = array();
	}

	protected function twocolumndescriptor( $text, $section ) {
		return array(
			'type' => 'info',
			'raw' => true,
			'rawrow' => true,
			'default' => '<tr><td colspan="2">' . $text . '</td></tr>',
			'section' => $section,
		);
	}

	protected function uploadedMessage() {
		$destTitle = Title::newFromText( $this->mDestFile, NS_FILE );
		return '<div class="multiupload-success-message">'
			. wfMessage( 'multiupload-uploadedto', 
				Linker::linkKnown( 
					$destTitle,
					$destTitle->getText()
				) )->text()
			. '</div>';
	}

	protected function uploadSucceededDescriptor( $i, $sectionlabel ) {
			return array( 
				'UploadedMessage'.$i => 
					$this->twocolumndescriptor( 
						$this->uploadedMessage(), 
						$sectionlabel ),
				'DestFile'.$i => array(
					'type' => 'hidden',
					'default' => $this->mDestFile,
			       		'section' => $sectionlabel ),
				'UploadSuccessful'.$i => array(
					'type' => 'hidden',
					'default' => true,
			       		'section' => $sectionlabel ),
			);
	}

	public function descriptor( $preText = '', $extraButtons = array(),
			$uploadSuccessful = false ) {
		$descriptor = array();
		$i = $this->mRow->mRowNumber;
		$sectionlabel = 'row-'.$i;
		if ( $uploadSuccessful ) {
			return $this->uploadSucceededDescriptor( $i, $sectionlabel );
		}
		$sectionDescriptors = $this->getSourceSection()
			   + $this->getDescriptionSection()
			   + $this->getOptionsSection();
		$header = '';
		foreach (array($preText, $this->mHeader) + $this->mSectionHeaders as $head) {
			if ($head != '') {
				if ($header != '') {
					$header .= "<br/>\n";
				}
				$header .= $head;
			}
		}
		$preTextSection = array();
		if ($header != '') {
			$preTextSection['Message'] = $this->twocolumndescriptor(
				$header, $sectionlabel );
		}
		# a couple markers for the javascript animations
		if ( isset( $sectionDescriptors['DestFile'] ) ) {
			$sectionDescriptors['DestFile']['cssclass'] = 'multiupload-first-to-collapse multiupload-width-exemplar';
		}
		foreach ($preTextSection + $sectionDescriptors as $name=>$field) {
			if (isset($field['id'])) {
				# put the ids that Special:Upload uses into
				# the class attributes, without numbers appended,
				# so that javascript routines can find them that
				# way
				if ( isset( $field['cssclass'] ) ) {
					$field['cssclass'] .= 
						' '.  $field['id'];
				} else {
					$field['cssclass'] =  $field['id'];
				}
				# add the row number to the actual id, for use
				# as distinct form fields.
				$field['id'] = $field['id'].$i;
			}
			if (isset($field['radio-name'])) {
				$field['radio-name'] = $field['radio-name'].$i;
			}
			if ( isset( $field['section'] ) ) {
				if ( isset( $field['cssclass'] ) ) {
					$field['cssclass'] .= ' ';
				} else {
					$field['cssclass'] = '';
				}
				$field['cssclass'] .= 'mw-htmlform-section-' 
					. str_replace( '/', '-', $field['section'] );
			}
			$field['section'] = $sectionlabel;
			$descriptor["$name$i"] = $field;
		}
		foreach ($extraButtons as $key=>$msg) {
			$descriptor[$key] = array(
				'type' => 'check',
				'id' => $key,
				'label-message' => $msg,
				'section' => $sectionlabel,
			);
		}
		return $descriptor;
	}
}
