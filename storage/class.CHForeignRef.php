<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/url/class.RCUrlRewrite.php';

/**
 *
 * @package steroid\cli
 *
 */
class CHForeignref extends CLIHandler {

	protected $widgetsOnly = false;
	protected $rcs = array();

	public function performCommand( $called, $command, array $params ) {

		foreach($params as $param) {
			switch ( $param ) {
				case '-w':
				case '--widgetsonly':
					$this->widgetsOnly = true;
					break;
				default:
					$this->rcs[] = $param;
					break;
			}
		}

		if(!empty($this->rcs)){
			$rcs = array();
			require_once STROOT . '/area/class.RCArea.php'; // needed to avoid include loop

			$tmp = ClassFinder::find($this->rcs, true);

			foreach($tmp as $class){
				$rcs[$class[ClassFinder::CLASSFILE_KEY_CLASSNAME]] = $class;
			}
		} else {
			$rcs = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );
		}

		foreach ( $rcs as $rc => $def ) {
			if ( $this->widgetsOnly && $rc::BACKEND_TYPE !== Record::BACKEND_TYPE_WIDGET ) {
				continue;
			}

			$this->output($rc);
		}

		return EXIT_SUCCESS;
	}

	protected function output($rc) {
		$refs = $rc::getForeignReferences();

		if ( empty( $refs ) ) {
			return;
		}

		echo CLIHandler::COLOR_CLASSNAME . $rc . ":\n" . CLIHandler::COLOR_DEFAULT;

		foreach ( $refs as $ref ) {
			$class = $ref[ 'recordClass' ];

			echo CLIHandler::USAGE_ARGUMENT_INDENT . CLIHandler::COLOR_CLASSNAME . $class . CLIHandler::COLOR_DEFAULT . ': ' . $class::BACKEND_TYPE . "\n";

			$this->outputRecordReferences( $class );
		}
	}

	protected function outputRecordReferences($rc){
		$fieldDefs = $rc::getOwnFieldDefinitions();

		foreach($fieldDefs as $fieldName => $fieldDef){
			if(is_subclass_of($fieldDef['dataType'], 'BaseDTRecordReference')){
				if(isset( $fieldDef[ 'recordClass' ])){
					$class = $fieldDef[ 'recordClass' ];
					$class = CLIHandler::COLOR_CLASSNAME . $class . CLIHandler::COLOR_DEFAULT . ': ' . $class::BACKEND_TYPE;
				} else {
					$class = CliHandler::RESULT_COLOR_FAILURE . $fieldName . '->Dynamic' . CLIHandler::COLOR_DEFAULT;
				}

				echo CLIHandler::USAGE_ARGUMENT_INDENT . CLIHandler::USAGE_ARGUMENT_INDENT . $class . "\n";
			}
		}
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' foreignref command' => array(
				'usage:' => array(
					'php ' . $called . ' foreignref' => '',
				),

				'options:' => array(
					'-w, --widgetsonly' => 'only show widgets',
				)
			)
		) );
	}
}