<?php
/**
 *
 * @package steroid\log
 */

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/domain/class.RCDomain.php';
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTText.php';
require_once STROOT . '/lib/phpmailer/class.phpmailer.php';
require_once STROOT . '/util/class.Config.php';

require_once STROOT . '/html/markdown/Markdown.php';

/**
 *
 * @package steroid\log
 *
 */
class RCChangeLog extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_DEV;

	// FIXME: make configurable
	protected $mailRecipients = array(
		'development' => array(
			'kernler@super-fi.eu'
		),
		'production' => array(
			'kernler@super-fi.eu',
			'johannes.beranek@super-fi.eu',
			'schmidt@super-fi.eu',
			'alexander.ostleitner@gruene.at',
			'niki.nickl@gruene.at',
			'lackner@super-fi.eu'
		)
	);

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'ctime' => DTCTime::getFieldDefinition(),
			'title' => DTString::getFieldDefinition( 127 ),
			'text' => DTText::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'alert' => DTBool::getFieldDefinition()
		);
	}

	public function getFormatted() {
		return \Michelf\Markdown::defaultTransform( $this->text );
	}

	public function afterSave( $isUpdate, $isFirst, array $saveResult, array &$savePaths = NULL ) {
		if ( !$isUpdate && $isFirst ) {
			$this->sendNotificationMail();
		}
	}

	protected function sendNotificationMail() {
		if ( ( $mode = ST::getMode() ) == ST::MODE_PRODUCTION ) {
			if ( empty( $this->mailRecipients[ $mode ] ) ) {
				return;
			}

			$recipients = $this->mailRecipients[ $mode ];
		} else {
			return;
		}

		$user = User::getCurrent();
		$domainGroup = $user->getSelectedDomainGroup();
		$domains = $domainGroup->{'domainGroup:RCDomain'};
		$returnCodeFieldName = RCDomain::getDataTypeFieldName( 'DTSteroidReturnCode' );

		$primaryDomain = NULL;

		foreach ( $domains as $domain ) {
			if ( $domain->{$returnCodeFieldName} == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
				$primaryDomain = $domain;
			}
		}

		if ( !$primaryDomain ) {
			if ( isset( $domains[ 0 ] ) ) {
				$primaryDomain = $domains[ 0 ];
			} else {
				$primaryDomain = $this->storage->selectFirstRecord( 'RCDomain', array( 'where' => array( $returnCodeFieldName, '=', array( DTSteroidReturnCode::RETURN_CODE_PRIMARY ) ) ) );
			}
		}

		$mail = new PHPMailer();
		$body = '<p>' . $this->title . '</p>';
		$body .= $this->getFormatted();

		$mail->IsSMTP();
		$mail->IsHtml( true );
		$mail->CharSet = 'utf-8';
		$mail->Host = 'localhost';
		$mail->SMTPDebug = 0;
		$mail->SetFrom( 'no-reply@' . $primaryDomain->domain );

		$mail->Subject = 'New Steroid changelog from ' . $this->ctime;

		$mail->AltBody = $body;

		$mail->MsgHTML( $body );

		$mail->ClearAddresses();

		foreach ( $recipients as $email ) {
			$mail->AddAddress( $email, '' );
		}

		if ( !$mail->Send() ) {
			throw new Exception( 'mail not sent' );
		}
	}
}
