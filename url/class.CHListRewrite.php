<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/util/class.ClassFinder.php';

/**
 *
 * @package steroid\cli
 *
 */
class CHListRewrite extends CLIHandler {

	protected $widgetsOnly = false;
	protected $rcs = array();

	public function performCommand( $called, $command, array $params ) {

		if ( empty( $params ) ) {
			throw new Exception( 'No params set' );
		}

		$this->storage->init();

		$recordClass = $params[ 0 ];
		$primary     = $params[ 1 ];

		ClassFinder::find($recordClass, true);

		if($recordClass == 'RCPage'){
			$this->outputByPage($primary);
		} else if($recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET){
			$this->outputByWidget( $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $primary ), Record::TRY_TO_LOAD ));
		} else {
			throw new Exception('Supplied recordClass is not a Widget or RCPage');
		}

		return EXIT_SUCCESS;
	}

	protected function outputByWidget($widget){
		$rewrites = $widget->getRewrites();

		if($rewrites === NULL){
			return;
		}

		$count = count( $rewrites );

		echo CLIHandler::COLOR_CLASSNAME . get_class($widget) . ' "' . $widget->getTitle() . '"' . CLIHandler::COLOR_DEFAULT . ' has ' . $count . ' rewrites:' ."\n";

		foreach ( $rewrites as $rewrite ) {
			if($rewrite === NULL){
				echo "Widget has file but no rewrite generated\n";
			} else {
				echo CLIHandler::USAGE_ARGUMENT_INDENT . 'Primary: ' . $rewrite->primary . ', url: ' . $rewrite->url->primary . ' (' . $rewrite->url->url . '),' . $rewrite->rewrite . "\n";
			}
		}

		return;
	}

	protected function outputByPage($primary){
		$page = RCPage::get($this->storage, array(Record::FIELDNAME_PRIMARY => $primary), Record::TRY_TO_LOAD);

		$widgets = $page->getElements();

		echo 'Rewrites for page ' . $page->getTitle() . ':' . "\n";

		foreach($widgets as $widget){
			$this->outputByWidget( $widget );
		}
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' listrewrite command' => array(
				'usage:' => array(
					'php ' . $called . ' listrewrite' => '',
				),

				'options:' => array()
			)
		) );
	}
}