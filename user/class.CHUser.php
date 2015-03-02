<?php
/**
 *
 * @package steroid\user
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/storage/class.RBStorage.php';

require_once STROOT . '/user/class.User.php';

require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/user/permission/class.RCPermission.php';
require_once STROOT . '/user/permission/class.RCPermissionEntity.php';
require_once STROOT . '/user/permission/class.RCPermissionPermissionEntity.php';
require_once STROOT . '/user/permission/class.RCDomainGroupLanguagePermissionUser.php';
require_once STROOT . '/user/permission/class.DTStaticInlinePermissionEdit.php';

/**
 *
 * @package steroid\user
 */

class CHUser extends CLIHandler {

	public function performCommand( $called, $command, array $params ) {
		if ( count( $params ) < 1 ) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );

			return EXIT_FAILURE;
		}

		$this->storage->init();


		switch ( $params[ 0 ] ) {
			case 'list':
				$userRecords = $this->storage->selectRecords( 'RCUser', array( 'fields' => array( 'primary', 'username' ), 'orderBy' => array( 'username' => 'ASC' ) ) );

				foreach ( $userRecords as $userRecord ) {
					echo $userRecord->username . ' | ' . $userRecord->primary . "\n";
				}

				break;
			case 'show':
				if ( count( $params ) != 2 ) {
					$this->notifyError( "show command expects exactly one additional parameter\n" );
					return EXIT_FAILURE;
				}

				$userRecords = $this->storage->selectRecords( 'RCUser', array( 'fields' => array( Record::FIELDNAME_PRIMARY, 'username' ), 'where' => array( 'username', '=', array( $params[ 1 ] ) ) ) );


				foreach ( $userRecords as $userRecord ) {
					print_r( $userRecord->getValues() );
				}

				break;
			case 'syncperm':
				$tx = $this->storage->startTransaction();

				try {
					$permRecords = $this->storage->selectRecords( 'RCPermission' );

					foreach ( $permRecords as $permRecord ) {
						$permissionJoins = $permRecord->{'permission:RCPermissionPermissionEntity'};

						$joinValues = array();

						foreach ( $permissionJoins as $join ) {
							if ( $join->fieldPermission ) {
								$join->fieldPermission->load();
							}

							$join->permissionEntity->load();

							$permissionEntityValues = $join->permissionEntity->getValues();

							unset( $permissionEntityValues[ Record::FIELDNAME_PRIMARY ] );

							$joinValues[ ] = array(
								'fieldPermission' => $join->fieldPermission ? $join->fieldPermission->getValues() : NULL,
								'permissionEntity' => $permissionEntityValues
							);
						}

//						$generatedPermissions = DTStaticInlinePermissionEdit::generatePermissions($this->storage, $joinValues);

						$permRecord->{'permission:RCPermissionPermissionEntity'} = $joinValues;

						$permRecord->save();
					}

					$tx->commit();
				} catch ( Exception $e ) {
					$tx->rollback();
					throw $e;
				}

				echo 'Permissions have been synced.' . "\n";
				break;
			case 'syncdevperm':
				$this->syncDevPerm();

				echo 'Dev Permission has been synced.' . "\n";
				break;

			case 'makedev':
				if ( count( $params ) != 2 ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				}

				$userRecord = $this->makeDev($params[1]);

				echo 'User with primary ' . $userRecord->{Record::FIELDNAME_PRIMARY} . ' should now have all permissions for backend.' . "\n";
				break;
			case 'giveperm':
				if (count($params) !== 3) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				}
				
				$userRecord = $this->givePerm($params[1], $params[2]);

				echo 'User with primary ' . $userRecord->{Record::FIELDNAME_PRIMARY} . ' should now have perm on all domain groups.' . "\n";
				break;
				
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}

		return EXIT_SUCCESS;
	}

	public function syncDevPerm(){
		$tx = $this->storage->startTransaction();

		try {
			$devPermRecord = $this->getDevPermRecord();

			if ( $devPermRecord === null ) {
				$devPermRecord = RCPermission::get( $this->storage, array( 'title' => User::PERMISSION_TITLE_DEV ), false );
			}

			// find all record classes
			$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD );

			$recordClassNames = array_keys( $recordClasses );

			// clean up db
			$existingPermEntities = $this->storage->selectRecords( 'RCPermissionEntity' );

			foreach ( $existingPermEntities as $existingPermEntity ) {
				if ( !in_array( $existingPermEntity->recordClass, $recordClassNames, true ) ) {
					$existingPermEntity->delete();
				} else {
					$existingPermEntity->load();
				}
			}

			$joinRecs = array();

			// add each of them to RCPermissionEntity if they don't exist there already
			foreach ( $recordClasses as $recordClass => $definition ) {
				$joinRec = array(
					'permissionEntity' => array(
						'recordClass' => $recordClass,
						'mayWrite' => 1,
						'restrictToOwn' => 0,
						'isDependency' => 0
					)
				);

				$joinRecs[ ] = $joinRec;

			}


			$devPermRecord->{'permission:RCPermissionPermissionEntity'} = $joinRecs;

			$devPermRecord->save();

			$tx->commit();
		} catch ( Exception $e ) {
			$tx->rollback();
			throw $e;
		}
	}

	final public function givePerm($permissionPrimary, $userPrimary) {
		$tx = $this->storage->startTransaction();

		try {
			// check if we already have a special dev permission
			$devPerm = $this->getPermRecord($permissionPrimary);

			if ( $devPerm === null ) {
				throw new LogicException( 'Unable to find perm with given primary.' );
			}

			// fetch user
			$userRecord = RCUser::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $userPrimary ) );

			// connect permission to user
			$userPermissions = $userRecord->{'user:RCDomainGroupLanguagePermissionUser'};
			$devDomainGroups = array();
			$devLanguages = array();

			foreach ( $userPermissions as $userPermission ) {
				if ( $userPermission->permission === $devPerm ) {
					$devDomainGroups[ ] = $userPermission->domainGroup;
					$devLanguages[ ] = $userPermission->language;
				}
			}

			$allDomainGroups = $this->storage->selectRecords( 'RCDomainGroup' );
			$allLanguages = $this->storage->selectRecords( 'RCLanguage', array( 'where' => array( RCLanguage::getDataTypeFieldName( 'DTSteroidLive' ), '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) );

			$addedDomainGroups = 0;
			$addedLanguages = 0;

			foreach ( $allDomainGroups as $dg ) {
				foreach ( $allLanguages as $lang ) {
					if ( !in_array( $dg, $devDomainGroups, true ) ) {
						$userPermission = RCDomainGroupLanguagePermissionUser::get( $this->storage, array( 'domainGroup' => $dg, 'user' => $userRecord, 'permission' => $devPerm, 'language' => $lang ), false );
						$userPermission->save();

						$userPermissions[ ] = $userPermission;
						$addedDomainGroups++;
					}

					if ( !in_array( $lang, $devLanguages, true ) ) {
						$userPermission = RCDomainGroupLanguagePermissionUser::get( $this->storage, array( 'domainGroup' => $dg, 'user' => $userRecord, 'permission' => $devPerm, 'language' => $lang ), false );
						$userPermission->save();

						$userPermissions[ ] = $userPermission;
						$addedLanguages++;
					}
				}
			}

			if ( $addedDomainGroups ) {
				echo 'Added ' . $addedDomainGroups . ' domaingroups to user' . "\n";
			}

			if ( $addedLanguages ) {
				echo 'Added ' . $addedLanguages . ' languages to user' . "\n";
			}

			// allow user to backend
			// $userRecord->is_backendAllowed = 1;
			$userRecord->save();

			$tx->commit();
		} catch ( Exception $e ) {
			$tx->rollback();
			throw $e;
		}

		return $userRecord;
	}

	public function makeDev($userPrimary){
		if ($userPrimary === NULL){
			throw new Exception('userPrimary must be set');
		}

		$tx = $this->storage->startTransaction();

		try {
			// check if we already have a special dev permission
			$devPerm = $this->getDevPermRecord();

			if ( $devPerm === null ) {
				throw new LogicException( 'Must first execute syncdevperm.' );
			}

			// fetch user
			$userRecord = RCUser::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $userPrimary ) );

			// connect permission to user
			$userPermissions = $userRecord->{'user:RCDomainGroupLanguagePermissionUser'};
			$devDomainGroups = array();
			$devLanguages = array();

			foreach ( $userPermissions as $userPermission ) {
				if ( $userPermission->permission === $devPerm ) {
					$devDomainGroups[ ] = $userPermission->domainGroup;
					$devLanguages[ ] = $userPermission->language;
				}
			}

			$allDomainGroups = $this->storage->selectRecords( 'RCDomainGroup' );
			$allLanguages = $this->storage->selectRecords( 'RCLanguage', array( 'where' => array( RCLanguage::getDataTypeFieldName( 'DTSteroidLive' ), '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) );

			$addedDomainGroups = 0;
			$addedLanguages = 0;

			foreach ( $allDomainGroups as $dg ) {
				foreach ( $allLanguages as $lang ) {
					if ( !in_array( $dg, $devDomainGroups, true ) ) {
						$userPermission = RCDomainGroupLanguagePermissionUser::get( $this->storage, array( 'domainGroup' => $dg, 'user' => $userRecord, 'permission' => $devPerm, 'language' => $lang ), false );
						$userPermission->save();

						$userPermissions[ ] = $userPermission;
						$addedDomainGroups++;
					}

					if ( !in_array( $lang, $devLanguages, true ) ) {
						$userPermission = RCDomainGroupLanguagePermissionUser::get( $this->storage, array( 'domainGroup' => $dg, 'user' => $userRecord, 'permission' => $devPerm, 'language' => $lang ), false );
						$userPermission->save();

						$userPermissions[ ] = $userPermission;
						$addedLanguages++;
					}
				}
			}

			if ( $addedDomainGroups ) {
				echo 'Added ' . $addedDomainGroups . ' domaingroups to user' . "\n";
			}

			if ( $addedLanguages ) {
				echo 'Added ' . $addedLanguages . ' languages to user' . "\n";
			}

			// allow user to backend
			$userRecord->is_backendAllowed = 1;
			$userRecord->save();

			$tx->commit();
		} catch ( Exception $e ) {
			$tx->rollback();
			throw $e;
		}

		return $userRecord;
	}

	protected function getDevPermRecord() {
		return $this->storage->selectFirstRecord( 'RCPermission', array( 'where' => array( 'title', '=', array( User::PERMISSION_TITLE_DEV ) ) ) );
	}
	
	protected function getPermRecord( $permissionPrimary ) {
		return $this->storage->selectFirstRecord( 'RCPermission', array( 'where' => array( 'primary', '=', array( $permissionPrimary ))));
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command . ' command' => array(
				'usage:' => array(
					$command . ' method [param ...]' => 'call user method optionally passing params',
				),
				'available methods:' => array(
					'list' => 'list available users by their username + primary',
					'show username' => 'list data of all user with given username',
					'makedev USERPRIMARY' => 'give user with given primary dev permission',
					'giveperm PERMPRIMARY USERPRIMARY' => 'give user permission on all domain groups',
					'syncdevperm' => 'syncs dev permission',
					'syncperm' => 'syncs all permissions'
				)
			)
		) );
	}
}

?>
