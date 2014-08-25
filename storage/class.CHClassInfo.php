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
class CHClassInfo extends CLIHandler {

	protected $classTypes = array();
	protected $count = array();

	protected $createInSelectionOnly = false;
	protected $widgetInfo = false;
	protected $noPrimary = false;
	protected $sortingOnly = false;

	public function performCommand( $called, $command, array $params ) {
		$this->storage->init();

		if ( empty( $params ) ) {
			$this->classTypes = array(
				ClassFinder::CLASSTYPE_DATATYPE,
				ClassFinder::CLASSTYPE_RECORD,
				ClassFinder::CLASSTYPE_UNITTEST,
				ClassFinder::CLASSTYPE_URLHANDLER,
				ClassFinder::CLASSTYPE_CLIHANDLER
			);
		} else {
			foreach ( $params as $param ) {
				switch ( $param ) {
					case '-r':
					case '--recordclass':
						$this->classTypes[ ] = ClassFinder::CLASSTYPE_RECORD;
						break;
					case '-d':
					case '--datatype':
						$this->classTypes[ ] = ClassFinder::CLASSTYPE_DATATYPE;
						break;
					case '-t':
					case '--unittest':
						$this->classTypes[ ] = ClassFinder::CLASSTYPE_UNITTEST;
						break;
					case '-c':
					case '--clihandler':
						$this->classTypes[ ] = ClassFinder::CLASSTYPE_CLIHANDLER;
						break;
					case '-h':
					case '--urlhandler':
						$this->classTypes[ ] = ClassFinder::CLASSTYPE_URLHANDLER;
						break;
					case '-s':
					case '--createinselectiononly':
						$this->createInSelectionOnly = true;
						$this->classTypes = array( ClassFinder::CLASSTYPE_RECORD );
						break;
					case '-w':
					case '--widgetinfo':
						$this->widgetInfo = true;
						$this->classTypes = array( ClassFinder::CLASSTYPE_RECORD );
						break;
					case '-p':
					case '--noprimary':
						$this->noPrimary = true;
						$this->classTypes = array( ClassFinder::CLASSTYPE_RECORD );
						break;
					case '-o':
					case '--sorting':
						$this->sortingOnly = true;
						$this->classTypes = array( ClassFinder::CLASSTYPE_RECORD );
						break;
//				default:
//					$temp = explode( ':', $param );
//					$this->classTypes[ array_shift( $temp ) ] = $temp;
//					break;
				}
			}
		}

		foreach ( $this->classTypes as $classType ) {
			$classes = ClassFinder::getAll( $classType, true );
			$this->count[ $classType ] = 0;

			$this->outputInfo( $classes, $classType );
		}

		$this->outputMeta();

		return EXIT_SUCCESS;
	}

	protected function outputInfo( $classes, $classType ) {
		switch ( $classType ) {
			case ClassFinder::CLASSTYPE_RECORD:
				if ( $this->widgetInfo ) {
					$this->outputWidgetInfo( $classes );
				} else {
					$this->outputRecordClassInfo( $classes );
				}
				break;
//			case ClassFinder::CLASSTYPE_DATATYPE:
//				$this->outputDataTypeInfo($classes);
//				break;
			default:
				$this->outputDefault( $classes, $classType );
				break;
		}
	}

	protected function outputDataTypeInfo( $classes ) {

	}

	protected function groupDataTypes( &$grouped, $parentClass, $classes ) {
		foreach ( $classes as $className => $classInfo ) {
			if ( get_parent_class( $className ) == $parentClass ) {
				$grouped[ $parentClass ][ $className ] = array();
			}
		}
	}

	protected function outputDefault( $classes, $classType ) {
		echo "\nType: " . CLIHandler::RESULT_COLOR_WARNING . $classType . CLIHandler::COLOR_DEFAULT . "\n";

		ksort( $classes );

		$this->count[ $classType ] += count( $classes );

		foreach ( $classes as $className => $classInfo ) {
			echo CLIHandler::COLOR_CLASSNAME . $className . CLIHandler::COLOR_DEFAULT;
			echo ' Location: ' . $classInfo[ 'fullPath' ] . "\n";
		}
	}

	protected function outputMeta() {
		echo "\n\n";

		foreach ( $this->classTypes as $classType ) {
			echo "Total " . CLIHandler::COLOR_CLASSNAME . $classType . ": " . CLIHandler::COLOR_DEFAULT . $this->count[ $classType ] . "\n";
		}

		echo "\n";
	}

	protected function outputRecordClassInfo( $classes ) {
		$backendTypes = array();

		foreach ( $classes as $className => $classInfo ) {
			$backendTypes[ $className::BACKEND_TYPE ][ $className ] = $classInfo;
		}

		ksort( $backendTypes );

		foreach ( $backendTypes as $backendType => $classes ) {
			if ( $this->hasFilters() ) {
				$classes = $this->filterRecordClasses( $backendType, $classes );
			}

			ksort( $classes );

			if ( empty( $classes ) ) {
				continue;
			}

			$this->count[ ClassFinder::CLASSTYPE_RECORD ] += count( $classes );

			echo "\nBackend type: " . CLIHandler::RESULT_COLOR_WARNING . $backendType . CLIHandler::COLOR_DEFAULT . "\n";

			foreach ( $classes as $className => $classInfo ) {
				echo CLIHandler::COLOR_CLASSNAME . $className . CLIHandler::COLOR_DEFAULT;
				echo ' Location: ' . $classInfo[ 'fullPath' ] . "\n";
				echo "Allow create in selection: " . ( $className::ALLOW_CREATE_IN_SELECTION ? CLIHandler::RESULT_COLOR_SUCCESS . "True" : CLIHandler::RESULT_COLOR_FAILURE . 'False' ) . CLIHandler::COLOR_DEFAULT . "\n";
				echo "Has primary field: " . ( $className::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ? CLIHandler::RESULT_COLOR_SUCCESS . "True" : CLIHandler::RESULT_COLOR_FAILURE . 'False' ) . CLIHandler::COLOR_DEFAULT . "\n";
				echo "Has sorting field: " . ( $className::fieldDefinitionExists( Record::FIELDNAME_SORTING ) ? CLIHandler::RESULT_COLOR_SUCCESS . "True" : CLIHandler::RESULT_COLOR_FAILURE . 'False' ) . CLIHandler::COLOR_DEFAULT . "\n";
				echo "Has domainGroup field: " . ( $className::getDataTypeFieldName( 'DTSteroidDomainGroup' ) ? CLIHandler::RESULT_COLOR_SUCCESS . "True" : CLIHandler::RESULT_COLOR_FAILURE . 'False' ) . CLIHandler::COLOR_DEFAULT . "\n";
			}
		}
	}

	protected function outputWidgetInfo( $classes ) {
		$widgetTypes = array();

		foreach ( $classes as $className => $classInfo ) {
			if ( $className::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ) {
				$widgetTypes[ $className::WIDGET_TYPE ][ $className ] = $classInfo;
			}
		}

		ksort( $widgetTypes );

		foreach ( $widgetTypes as $widgetType => $classes ) {

			ksort( $classes );

			$this->count[ ClassFinder::CLASSTYPE_RECORD ] += count( $classes );

			echo "\nWidget type: " . CLIHandler::RESULT_COLOR_WARNING . $widgetType . CLIHandler::COLOR_DEFAULT . "\n";

			foreach ( $classes as $className => $classInfo ) {
				echo CLIHandler::COLOR_CLASSNAME . $className . CLIHandler::COLOR_DEFAULT;
				echo ' Location: ' . $classInfo[ 'fullPath' ] . "\n";
			}
		}
	}

	protected function hasFilters() {
		return $this->createInSelectionOnly || $this->widgetInfo || $this->noPrimary || $this->sortingOnly;
	}

	protected function filterRecordClasses( $backendType, $classes ) {
		$filtered = $classes;

		if ( $this->createInSelectionOnly ) {
			foreach ( $filtered as $className => $classInfo ) {
				if ( !$className::ALLOW_CREATE_IN_SELECTION ) {
					unset( $filtered[ $className ] );
				}
			}
		} else if ( $this->noPrimary ) {
			foreach ( $filtered as $className => $classInfo ) {
				if ( $className::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
					unset( $filtered[ $className ] );
				}
			}
		} else if ( $this->sortingOnly ) {
			foreach ( $filtered as $className => $classInfo ) {
				if ( !$className::fieldDefinitionExists( Record::FIELDNAME_SORTING ) ) {
					unset( $filtered[ $className ] );
				}
			}
		}
		return $filtered;
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' classinfo command' => array(
				'usage:' => array(
					'php ' . $called . ' classinfo' => '',
				),

				'options:' => array(
					'-r, --recordclass' => 'only show record classes',
					'-d, --datatype' => 'only show datatypes',
					'-t, --unittest' => 'only show unittests',
					'-c, --clihandler' => 'only show cli handlers',
					'-h, --urlhandler' => 'only show url handlers',
					'-s, --createinselectiononly' => 'only show record classes that can be created in record selection',
					'-w, --widgetinfo' => 'only show widget info',
					'-p, --noprimary' => 'only show records that have no primary field',
					'-o, --sorting' => 'only show records that have sorting field'
				)
			)
		) );
	}
}