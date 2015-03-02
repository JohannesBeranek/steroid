<?php
/**
 *
 * @package steroid\log
 */

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTDisplayText.php';
require_once STROOT . '/util/class.NLS.php';
require_once STROOT . '/user/class.User.php';

/**
 *
 * @package steroid\log
 *
 */
class RCMessageBox extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_UTIL;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'ctime' => DTCTime::getFieldDefinition(),
			'mtime' => DTCTime::getFieldDefinition(),
			'title' => DTString::getFieldDefinition( 127, false, NULL, true ),
			'text' => DTText::getFieldDefinition( NULL, true ),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition( false ),
			'user' => DTRecordReference::getFieldDefinition( 'RCUser' ),
			'sendToAll' => DTBool::getFieldDefinition(),
			'alert' => DTBool::getFieldDefinition(),
			'nlsMessage' => DTString::getFieldDefinition( 127, false, NULL, true ),
			'nlsTitle' => DTString::getFieldDefinition( 127, false, NULL, true ),
			'nlsRC' => DTString::getFieldDefinition( 127, false, NULL, true ),
			'nlsData' => DTText::getFieldDefinition( NULL, true ),
			'nlsTitleData' => DTText::getFieldDefinition( NULL, true )
		);
	}

	public static function getFormFields( IRBStorage $storage, array $fields = NULL ) {
		$fields = parent::getFormFields( $storage, $fields );

		$fields[ 'nlsMessage' ][ 'showInForm' ] = false;
		$fields[ 'nlsRC' ][ 'showInForm' ] = false;
		$fields[ 'nlsData' ][ 'showInForm' ] = false;
		$fields[ 'nlsTitle' ][ 'showInForm' ] = false;
		$fields[ 'nlsTitleData' ][ 'showInForm' ] = false;
		$fields[ 'domainGroup' ][ 'showInForm' ] = true;

		return $fields;
	}

	protected function getReceivers() {
		if ( $this->sendToAll ) {
			return User::getBackendUsers( $this->storage );
		}

		if ( $this->user ) {
			return array( $this->user );
		}

		return User::getUsersByDomainGroup( $this->storage, $this->domainGroup );
	}

	protected function afterSave( $isUpdate, $isFirst, array $saveResult, array &$savePaths = NULL ) {
		$conf = Config::get( 'localconf' );

		$modeConf = $conf->getSection( 'mode' );

		if ( $modeConf[ 'installation' ] === 'production' && !$isUpdate && $isFirst ) {
			$users = $this->getReceivers();

			$emailProviders = ClassFinder::getAll( ClassFinder::CLASSTYPE_EMAIL_PROVIDER, true );

			if ( !empty( $emailProviders ) ) {
				usort( $emailProviders, function ( $a, $b ) {
					$classA = $a[ 'className' ];
					$classB = $b[ 'className' ];
					return $classA::PRIORITY > $classB::PRIORITY ? 1 : ( $classA::PRIORITY < $classB::PRIORITY ? -1 : 0 );
				} );

				$providerArr = reset( $emailProviders );
				$provider = $providerArr[ 'className' ];

				$emailConf = $conf->getSection( 'email' );
				$from = $emailConf[ 'systemFromAddress' ];

				foreach ( $users as $user ) {
					$mtranetUsers = $user->{'user:RCMtranetUser'};
					if ( !$user->backendPreference || empty( $mtranetUsers ) ) {
						continue;
					}

					$to = $modeConf[ 'installation' ] === 'development' ? 'steroid@m-otion.at' : $mtranetUsers[ 0 ]->muser;

					if ( !empty( $this->nlsMessage ) ) {
						$text = NLS::getTranslation( $this->nlsMessage, $this->nlsRC, $user->backendPreference->language );

						if ( !empty( $this->nlsData ) ) {
							$text = NLS::fillPlaceholders( $text, $this->nlsData );
						}

						$text = $text . $this->text;
					} else {
						$text = $this->text;
					}

					$text = !empty( $text ) ? NLS::replaceObjectNames( $text, $user->backendPreference->language ) : '';

					if ( !empty( $this->nlsTitle ) ) {
						$title = NLS::getTranslation( $this->nlsTitle, $this->nlsRC, $user->backendPreference->language );

						if ( !empty( $this->nlsTitleData ) ) {
							$title = NLS::fillPlaceholders( $title, $this->nlsTitleData );
						}

						$title = $title . $this->title;
					} else {
						$title = $this->title;
					}

					$title = !empty( $title ) ? NLS::replaceObjectNames( $title, $user->backendPreference->language ) : '';

					$provider::send( $to, $from, $title, NULL, $text ); // we ignore the response here as there isn't much we can do in case of an error anyway
				}
			}
		}

		parent::afterSave( $isUpdate, $isFirst, $saveResult, $savePaths );
	}
}