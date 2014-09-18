<?php
/**
 *
 * @package steroid\backend
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/urlhandler/class.RCUrlHandler.php';
require_once STROOT . '/domain/class.RCDomain.php';
require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/url/class.RCUrl.php';
require_once STROOT . '/language/class.RCLanguage.php';

/**
 *
 * @package steroid\backend
 *
 */
class CHMigrateG extends CLIHandler {


	public function performCommand( $called, $command, array $params ) {
		doMigrate();

		return EXIT_SUCCESS;
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' migrate command:' => array(
				'usage:' => array(
					'php ' . $called => 'migrate an older version of steroid'
				)
			)
		) );
	}
}

function doMigrate() {
	ob_implicit_flush();

	require_once STROOT . '/util/class.ClassFinder.php';

	ClassFinder::getAll( 'RC', true );

	$db = 'tirol';
	$del = false;

	$uploadRoot = 'http://' . $db . '.gruene.sil.m-otion.at/uploads';

	$DB_NAME = 'gruene_migration';
	$DB_HOST = 'db.gruene.sil.m-otion.at';
	$DB_USER = 'gruene_migration';
	$DB_PASS = 'altistscheisse';

//	$DB_NAME = 'gruene_migration';
//	$DB_HOST = 'localhost';
//	$DB_USER = 'root';
//	$DB_PASS = NULL;

	switch ( $db ) {
		case 'noe':
			$db = 'Niederösterreich';
			break;
		case 'kaernten':
			$db = 'Kärnten';
			break;
		case 'gegenkorruption':
			$db = 'Gegenkorruption';
			break;
		default:
			break;
	}

	if ( !$del ) {
		$mysqli = new mysqli( $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME );

		if ( mysqli_connect_errno() ) {
			printf( "Connect failed: %s</br>", mysqli_connect_error() );
			exit();
		}

		$mysqli->query( "SET NAMES utf8" ) or die( $mysqli->error . __LINE__ );

		$doTags = true;
		$doArticles = true;
		$doAuthors = true;
		$doFlickr = true;
		$doTeaser = true;
		$doEvents = true;
		$doTopicCore = true;

		$doStatic = true;

		$doElements = true;

		$doPublish = true;

		$downloadImages = true;

		$doCommit = true;

		$fbPages = array(
			'salzburg' => 'https://www.facebook.com/GRUENEsalzburg',
			'Niederösterreich' => 'https://www.facebook.com/gruenenoe',
			'Kärnten' => 'https://www.facebook.com/gruenekaernten',
			'tirol' => 'https://www.facebook.com/DieGruenenTirol'
		);

		$query = "SELECT * FROM `cd_tag` WHERE is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		$topics = array();

		if ( $doTags && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'page_uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				$topics[ ] = $row;
			}
		}

		$articles = array();

		$query = "SELECT * FROM `cd_article` WHERE is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doArticles && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'page_uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) || empty( $elements ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				//article topics
				$query = "SELECT t1.title FROM cd_article_mm_tag t0 LEFT JOIN cd_tag t1 ON t1.uid = t0.tag_uid WHERE t0.article_uid = " . $row[ 'uid' ] . ' ORDER BY t0.sorting';
				$topicResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$articleTopics = array();

				if ( $topicResult->num_rows > 0 ) {
					while ( $topicRow = $topicResult->fetch_assoc() ) {
						if ( empty( $topicRow[ 'title' ] ) ) {
							continue;
						}

						$articleTopics[ ] = $topicRow;
					}
				}

				$row[ 'topics' ] = $articleTopics;

				$articles[ ] = $row;
			}
		}

		$articleCount = count( $articles );


//AUTHORS
		$authors = array();

		$query = "SELECT * FROM `cd_person` WHERE is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doAuthors && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'page_uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) || empty( $elements ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				$authors[ ] = $row;
			}
		}

//FLICKR
		$flickrs = array();

		$query = "SELECT * FROM `cd_gallery_flickr` WHERE is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doFlickr && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$flickrs[ ] = $row;
			}
		}

//TEASER
		$teasers = array();

		$query = "SELECT t0.*, t1.title as targetPageTitle FROM `cd_teaser` t0 LEFT JOIN cd_page t1 ON t0.page_uid = t1.uid WHERE t0.is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doTeaser && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$teasers[ ] = $row;
			}
		}

//EVENTS
		$events = array();

		$query = "SELECT t0.*, t2.title as topicTitle FROM `cd_event` t0 LEFT JOIN cd_event_mm_tag t1 ON t1.event_uid = t0.uid LEFT JOIN cd_tag t2 ON t2.uid = t1.tag_uid WHERE t0.is_live = 1";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doEvents && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'page_uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) || empty( $elements ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				$events[ ] = $row;
			}
		}

// TOPIC CORE
		$topicCoreParentPage = NULL;
		$topicCore = array();

		$query = "SELECT * FROM `cd_page` WHERE is_live = 1 AND title = 'Schwerpunkte'";
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$topicCoreParentPage = $row[ 'uid' ];
				break;
			}

			$query = "SELECT * FROM `cd_page` WHERE parent_uid = " . $topicCoreParentPage;
			$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );
		}

		if ( $doTopicCore && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) || empty( $elements ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				$topicCore[ ] = $row;
			}
		}


//STATIC
		$staticPages = array();

		$query = "SELECT * FROM `cd_page` WHERE is_live = 1 AND type = 0 AND parent_uid != " . $topicCoreParentPage;
		$result = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

		if ( $doStatic && $result->num_rows > 0 ) {
			while ( $row = $result->fetch_assoc() ) {
				$query = "SELECT t0.*, t1.classname FROM cd_page_element t0 LEFT JOIN cd_element t1 on t1.uid = t0.element_uid WHERE t0.page_uid = " . $row[ 'uid' ];
				$elementResult = $mysqli->query( $query ) or die( $mysqli->error . __LINE__ );

				$elements = array();

				if ( $elementResult->num_rows > 0 ) {
					while ( $elementRow = $elementResult->fetch_assoc() ) {
						$elements[ ] = $elementRow;
					}
				}

				if ( empty( $row[ 'title' ] ) ) {
					continue;
				}

				$row[ 'pageElements' ] = $elements;

				$staticPages[ ] = $row;
			}
		}

		mysqli_close( $mysqli );

	}

	$storage = getStorage( getConfig() );

	$storage->init();

	$tx = $storage->startTransaction();

	try {
		$domainGroup = $storage->selectFirstRecord( 'RCDomainGroup', array( 'where' => array( 'title', '=', array( $db ) ) ) );

		if ( $del ) {
			$domainGroup->delete();
			echo 'Deleted<br/>';
			$tx->commit();
			exit;
		}

		$language = $storage->selectFirstRecord( 'RCLanguage', array( 'where' => array( 'live', '=', array( 0 ) ) ) );

		User::setCLIUser( $storage, $language, $domainGroup );

		if ( !$domainGroup ) {
			throw new Exception( 'Domain group ' . $db . ' does not exist' );
		}

		if ( isset( $fbPages[ $db ] ) ) {
			$fbpageRec = RCDomainGroupFacebookPage::get( $storage, array(
				'domainGroup' => $domainGroup,
				'url' => $fbPages[ $db ],
				'title' => 'Grüne ' . $domainGroup->getTitle()
			), false );

			$fbpageRec->save();

			$fbUrlRec = RCFacebookUrl::get( $storage, array(
				'domainGroup' => $domainGroup,
				'url' => $fbPages[ $db ],
				'title' => 'Grüne ' . $domainGroup->getTitle()
			), false );

			$fbUrlRec->save();
		}

//TOPICS
		$topicNodeTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%topic_node%' ), 'AND', 'live', '=', array( 0 ) ) ) );
		$topicNodeParentPage = $storage->selectFirstRecord( 'RCDefaultParentPage', array( 'where' => array( 'recordClass', '=', array( 'RCTopicNode' ), 'AND', 'domainGroup', '=', array( $domainGroup ) ) ) );

		$topicNodes = array();
		$newTopics = array();

		$pageElements = array();
		$pageMapping = array();

		foreach ( $topics as &$topic ) {
			if ( empty( $topic[ 'title' ] ) ) {
				continue;
			}

			$topicPage = createPage( $topic[ 'crdate' ], $topic[ 'tstamp' ], $topic[ 'title' ], 'RCTopicNode', $topicNodeParentPage->page, $topicNodeTemplate, $domainGroup, $storage );

			$topicNode = RCTopicNode::get( $storage, array(
				'title' => $topic[ 'title' ],
				'domainGroup' => $domainGroup,
				'live' => 0,
				'language' => 1,
				'ctime' => strftime( '%F %T ', $topic[ 'crdate' ] ),
				'mtime' => strftime( '%F %T ', $topic[ 'tstamp' ] ),
				'page' => $topicPage
			), false );

			foreach ( $topic[ 'pageElements' ] as $element ) {
				if ( $element[ 'classname' ] == 'TopicHead' ) {
					$elConfig = unserialize( $element[ 'config' ] );
					$topicNode->want = str_replace( '&nbsp;', " ", strip_tags( str_replace( '</li><li>', "\r\n", $elConfig[ 'goals' ] ) ) );
					$topicNode->demand = str_replace( '&nbsp;', " ", strip_tags( str_replace( '</li><li>', "\r\n", $elConfig[ 'demands' ] ) ) );
					$topicNode->canDo = str_replace( '&nbsp;', " ", strip_tags( str_replace( '</li><li>', "\r\n", $elConfig[ 'actions' ] ) ) );
					break;
				}
			}

			$newTopic = $storage->selectFirstRecord( 'RCTopic', array( 'where' => array(
				'title', '=', array( $topic[ 'title' ] ), 'AND', 'isGlobal', '=', array( 1 ), 'AND', 'live', '=', array( 0 )
			) ) );

			if ( !$newTopic ) {
				$newTopic = RCTopic::get( $storage, array(
					'title' => $topic[ 'title' ],
					'live' => 0,
					'language' => 1,
					'ctime' => strftime( '%F %T ', $topic[ 'crdate' ] ),
					'mtime' => strftime( '%F %T ', $topic[ 'tstamp' ] ),
					'domainGroup' => $domainGroup
				), false );

				$newTopic->save();
			}

			$topic[ 'newPrimary' ] = $newTopic->primary;

			$newTopics[ ] = $newTopic;

			$topicJoin = RCTopicTopicNode::get( $storage, array(
				'topic' => $newTopic,
				'topicNode' => $topicNode,
				'domainGroup' => $domainGroup
			), Record::TRY_TO_LOAD );

			$topicNode->{'topicNode:RCTopicTopicNode'} = array( $topicJoin );

			$topicNode->save();

			$topicJoin->save();

			$topicNodes[ ] = $topicNode;

			echo 'Saved RCTopicNode ' . $topicNode->title . "</br>";

			if ( $topic[ 'pageElements' ] ) {
				foreach ( $topic[ 'pageElements' ] as $element ) {
					$element[ 'newPage' ] = $topicPage->primary;

					if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
						$pageMapping[ $element[ 'page_uid' ] ] = $topicPage->primary;
					}

					if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
						$pageElements[ $element[ 'page_uid' ] ] = array();
					}

					$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
				}
			}
		}

		unset( $topic );

		// AUTHORS
		$personPageTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%person%' ), 'AND', 'live', '=', array( 0 ) ) ) );
		$personParentPage = $storage->selectFirstRecord( 'RCPage', array( 'where' => array( 'parent', '=', NULL ) ) );

		foreach ( $authors as $key => $author ) {
			$existingPerson = $storage->selectFirstRecord( 'RCPerson', array( 'where' => array(
				'firstname', '=', array( $author[ 'firstname' ] ), 'AND', 'lastname', '=', array( $author[ 'lastname' ] )
			) ) );

			$tempFunction = RCFunction::get( $storage, array(
				'language' => $language,
				'maleTitle' => 'FEHLENDE FUNKTION',
				'femaleTitle' => 'FEHLENDE FUNKTION',
				'domainGroup' => $domainGroup,
				'live' => 0
			), false );

			$tempFunction->save();

			if ( !$existingPerson ) {
				$existingPerson = RCPerson::get( $storage, array(
					'live' => 0,
					'language' => $language,
					'domainGroup' => $domainGroup,
					'firstname' => $author[ 'firstname' ],
					'lastname' => $author[ 'lastname' ],
					'title' => $author[ 'title' ],
					'email' => $author[ 'email' ],
					'facebookUrl' => $author[ 'link_fb' ],
					'twitterUrl' => $author[ 'link_tw' ],
					'gplusUrl' => $author[ 'link_gp' ],
					'citation' => $author[ 'citation' ]
				), false );

				$existingPerson->save();
			}

			$post = RCPost::get( $storage, array(
				'live' => 0,
				'language' => $language,
				'domainGroup' => $domainGroup,
				'person' => $existingPerson,
				'function' => $tempFunction
			), false );

			$post->save();

			$authors[ $key ][ 'newPrimary' ] = $post->primary;

			$personPage = createPage( $author[ 'crdate' ], $author[ 'tstamp' ], ( $author[ 'firstname' ] . ' ' . $author[ 'lastname' ] ), 'RCPersonPage', $personParentPage, $personPageTemplate, $domainGroup, $storage );

			$personPage->save();

			$personPageJoin = RCPersonPage::get( $storage, array(
				'live' => 0,
				'language' => $language,
				'domainGroup' => $domainGroup,
				'person' => $existingPerson,
				'page' => $personPage
			), false );

			$personPageJoin->save();

			echo 'Saved RCPersonPage ' . $existingPerson->firstname . ' ' . $existingPerson->lastname . "</br>";

			if ( $author[ 'pageElements' ] ) {
				foreach ( $author[ 'pageElements' ] as $element ) {
					$element[ 'newPage' ] = $personPage->primary;

					if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
						$pageMapping[ $element[ 'page_uid' ] ] = $personPage->primary;
					}

					if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
						$pageElements[ $element[ 'page_uid' ] ] = array();
					}

					$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
				}
			}
		}

		$savedArticles = 0;

//ARTICLES
		$articleTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%article%' ), 'AND', 'live', '=', array( 0 ) ) ) );

		foreach ( $articles as &$article ) {
			if ( !empty( $article[ 'link' ] ) ) {
				$articleRec = RCExternalArticle::get( $storage, array(
					'domainGroup' => $domainGroup,
					'live' => 0,
					'mtime' => strftime( '%F %T ', $article[ 'tstamp' ] ),
					'ctime' => strftime( '%F %T ', $article[ 'crdate' ] ),
					'url' => $article[ 'link' ],
					'title' => $article[ 'title' ]
				), false );

				$articleRec->save();

				echo 'Saved external article "' . $article[ 'title' ] . '"' . "</br>";
			} else {
				$articlePage = createPage( $article[ 'crdate' ], $article[ 'tstamp' ], $article[ 'title' ], 'RCArticle', NULL, $articleTemplate, $domainGroup, $storage );

				if ( $downloadImages && !empty( $article[ 'image' ] ) ) {
					$url = $uploadRoot . '/cd_article/' . $article[ 'uid' ] . '/' . urlencode( $article[ 'image' ] );

					$teaserImage = RCFile::download( $storage, $url );

					if ( $teaserImage ) {
						$teaserImage->domainGroup = $domainGroup;
						$teaserImage->ctime = strftime( '%F %T ', $article[ 'crdate' ] );
						$teaserImage->mtime = strftime( '%F %T ', $article[ 'tstamp' ] );
						$teaserImage->alt = $article[ 'image_alt' ];
						$teaserImage->title = $article[ 'image' ];

						$teaserImage->save();

						echo 'Saved RCFile ' . $teaserImage->title . "</br>";
					} else {
						echo 'Article "' . $article[ 'title' ] . '" has missing image' . "</br>";
						$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
					}
				} else {
					echo 'Article "' . $article[ 'title' ] . '" has missing image' . "</br>";
					$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
				}

				$newAuthor = NULL;

				foreach ( $authors as $author ) {
					if ( $author[ 'uid' ] == $article[ 'person_uid' ] ) {
						$newAuthor = $author[ 'newPrimary' ];
					}
				}

				$articleRec = RCArticle::get( $storage, array(
					'title' => $article[ 'title' ],
					'language' => 1,
					'domainGroup' => $domainGroup,
					'ctime' => strftime( '%F %T ', $article[ 'crdate' ] ),
					'mtime' => strftime( '%F %T ', $article[ 'tstamp' ] ),
					'teaserText' => $article[ 'text' ],
					'teaserImage' => $teaserImage,
					'pubDate' => strftime( '%F %T ', $article[ 'pub_date' ] ),
					'page' => $articlePage,
					'live' => 0
				), false );

				$articleTopics = array();

				if ( empty( $article[ 'topics' ] ) ) {
					$topicRec = RCTopic::get( $storage, array(
						'title' => 'FEHLER',
						'domainGroup' => $domainGroup,
						'language' => $language,
						'live' => 0
					), Record::TRY_TO_LOAD );

					if ( !$topicRec->exists() ) {
						$topicRec->save();

						$topicNodeJoin = RCTopicTopicNode::get( $storage, array(
							'topic' => $topicRec,
							'topicNode' => $topicNodes[ 0 ],
							'domainGroup' => $domainGroup
						), Record::TRY_TO_LOAD );

						$topicNodeJoin->save();
					}

					$article[ 'topics' ] = array(
						array(
							'title' => 'FEHLER'
						)
					);
				}

				foreach ( $article[ 'topics' ] as $key => $articleTopic ) {
					$topicRec = $storage->selectFirstRecord( 'RCTopic', array( 'where' => array(
						'title', '=', array( $articleTopic[ 'title' ] ), 'AND', '(', 'isGlobal', '=', array( 1 ), 'OR', 'domainGroup', '=', array( $domainGroup ), ')', 'AND', 'live', '=', array( 0 )
					) ), Record::TRY_TO_LOAD );

					if ( !$topicRec || !$topicRec->exists() ) {
						foreach ( $newTopics as $topicRec ) {
							if ( $topicRec->title == $articleTopic[ 'title' ] ) {
								throw new Exception( 'WTF' );
							}
						}

						echo 'MISSING TOPIC ' . $articleTopic[ 'title' ] . '</br>';
					}

					$articleTopics[ ] = RCArticleTopic::get( $storage, array(
						'article' => $articleRec,
						'topic' => $topicRec,
						'sorting' => $key
					), Record::TRY_TO_LOAD );
				}

				$articleRec->{'article:RCArticleTopic'} = $articleTopics;

				$articleRec->save();

				$article[ 'newPrimary' ] = $articleRec->primary;

				if ( $article[ 'pageElements' ] ) {
					foreach ( $article[ 'pageElements' ] as $element ) {
						$element[ 'newPage' ] = $articleRec->page->primary;

						if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
							$pageMapping[ $element[ 'page_uid' ] ] = $articleRec->page->primary;
						}

						if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
							$pageElements[ $element[ 'page_uid' ] ] = array();
						}

						$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
					}
				}

				if ( $newAuthor ) {
					$articlePost = RCArticlePost::get( $storage, array(
						'article' => $articleRec,
						'post' => $newAuthor,
						'sorting' => 0
					), false );

					$articlePost->save();
				}

				$savedArticles++;

				echo $savedArticles . '/' . $articleCount . ': Saved RCArticle "' . $article[ 'title' ] . '"' . "</br>";
			}
		}


		//FLICKR

		$flickrCount = count( $flickrs );
		$flickrDone = 0;

		foreach ( $flickrs as &$flickr ) {
			$flickrRec = getFlickrUrlRecord( $flickr[ 'url' ], $storage, $domainGroup );

			$flickrRec->save();

			$flickr[ 'newPrimary' ] = $flickrRec->primary;

			$flickrDone++;

			echo $flickrDone . '/' . $flickrCount . ': Saved RCFlickrUrl "' . $flickr[ 'url' ] . '"' . "</br>";
		}

		foreach ( $teasers as $key => $teaser ) {
			if ( $downloadImages && !empty( $teaser[ 'image' ] ) ) {
				$url = $uploadRoot . '/cd_teaser/' . $teaser[ 'uid' ] . '/' . urlencode( $teaser[ 'image' ] );
				$teaserImage = RCFile::download( $storage, $url );

				if ( $teaserImage ) {
					$teaserImage->domainGroup = $domainGroup;
					$teaserImage->ctime = strftime( '%F %T ', $teaser[ 'crdate' ] );
					$teaserImage->mtime = strftime( '%F %T ', $teaser[ 'tstamp' ] );
					$teaserImage->title = $teaser[ 'image' ];

					$teaserImage->save();

					echo 'Saved RCFile ' . $teaserImage->title . "</br>";
				} else {
					echo 'Teaser "' . $teaser[ 'title' ] . '" has missing image' . "</br>";
					$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
				}
			} else {
				echo 'Teaser "' . $teaser[ 'title' ] . '" has missing image' . "</br>";
				$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
			}

			$targetPage = NULL;

			if ( !empty( $teaser[ 'targetPageTitle' ] ) ) {
				$targetPage = $storage->selectFirstRecord( 'RCPage', array( 'where' => array( 'title', '=', array( $teaser[ 'targetPageTitle' ] ), 'AND', 'domainGroup', '=', array( $domainGroup ) ) ) );

				if ( $targetPage ) {
					echo 'Connected teaser ' . $teaser[ 'title' ] . ' to page ' . $targetPage->title . "<br/>";
				}
			}

			$teaserRec = RCSlider::get( $storage, array(
				'live' => 0,
				'language' => $language,
				'sorting' => $teaser[ 'sorting' ] ? : $key,
				'domainGroup' => $domainGroup,
				'mtime' => strftime( '%F %T ', $teaser[ 'tstamp' ] ),
				'ctime' => strftime( '%F %T ', $teaser[ 'crdate' ] ),
				'image' => $teaserImage,
				'externalUrl' => $teaser[ 'external_link' ],
				'page' => $targetPage
			), false );

			$teaserRec->save();
		}

// EVENTS
		$eventPageTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%event%' ), 'AND', 'live', '=', array( 0 ) ) ) );
		$eventParentPage = $storage->selectFirstRecord( 'RCDefaultParentPage', array( 'where' => array( 'recordClass', '=', array( 'RCEvent' ), 'AND', 'domainGroup', '=', array( $domainGroup ) ) ) );

		$newEvents = array();

		foreach ( $events as $key => $event ) {
			if ( empty( $event[ 'title' ] ) ) {
				continue;
			}

			foreach ( $newEvents as $newEvent ) {
				if ( $newEvent->titleCustom == $event[ 'title' ] && $newEvent->dstartCustom == strftime( '%F', $event[ 'datetime' ] ) && $newEvent->tstartCustom == strftime( '%T', $event[ 'datetime' ] ) ) {
					continue 2; //prevent duplicate uid values
				}
			}

			$address = RCAddress::get( $storage, array(
				'domainGroup' => $domainGroup,
				'title' => $event[ 'location' ],
				'city' => $event[ 'city' ],
				'street' => $event[ 'street' ]
			), false );

			$address->save();

			echo 'Saved RCAddress ' . $event[ 'location' ] . '<br/>';

			$eventPage = createPage( $event[ 'crdate' ], $event[ 'tstamp' ], $event[ 'title' ], 'RCEvent', $eventParentPage->page, $eventPageTemplate, $domainGroup, $storage );

			$eventPage->save();

			$eventRec = RCEvent::get( $storage, array(
				'live' => 0,
				'language' => $language,
				'domainGroup' => $domainGroup,
				'mtime' => strftime( '%F %T ', $event[ 'tstamp' ] ),
				'ctime' => strftime( '%F %T ', $event[ 'crdate' ] ),
				'titleCustom' => $event[ 'title' ],
				'location' => $address,
				'dstartCustom' => strftime( '%F', $event[ 'datetime' ] ),
				'tstartCustom' => strftime( '%T', $event[ 'datetime' ] ),
				'dendCustom' => strftime( '%F', $event[ 'datetime_end' ] ),
				'tendCustom' => strftime( '%T', $event[ 'datetime_end' ] ),
				'page' => $eventPage
			), false );

			$eventRec->save();

			$events[ $key ][ 'newPrimary' ] = $eventRec->primary;

			$eventTopics = array();

			foreach ( $newTopics as $topic ) {
				if ( $topic->title == $event[ 'topicTitle' ] ) {
					$eventTopic = RCEventTopic::get( $storage, array(
						'event' => $eventRec,
						'topic' => $topic
					), false );

					$eventTopic->save();
				}
			}

			echo 'Saved RCEvent ' . $eventRec->title . "</br>";

			$newEvents[ ] = $eventRec;

			if ( $event[ 'pageElements' ] ) {
				foreach ( $event[ 'pageElements' ] as $element ) {
					$element[ 'newPage' ] = $eventPage->primary;

					if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
						$pageMapping[ $element[ 'page_uid' ] ] = $eventPage->primary;
					}

					if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
						$pageElements[ $element[ 'page_uid' ] ] = array();
					}

					$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
				}
			}
		}

		// TOPIC CORE

		$topicCoreTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%topic_core%' ), 'AND', 'live', '=', array( 0 ) ) ) );
		$topicCoreParentPage = $storage->selectFirstRecord( 'RCDefaultParentPage', array( 'where' => array( 'recordClass', '=', array( 'RCTopicCore' ), 'AND', 'domainGroup', '=', array( $domainGroup ) ) ) );

		foreach ( $topicCore as &$core ) {
			$firstSingleImage = NULL;
			$firstHeadLine = NULL;

			foreach ( $core[ 'pageElements' ] as $key => $element ) {
				if ( $element[ 'area' ] == 'colTop' && $element[ 'classname' ] == 'SingleImage' && $element[ 'sorting' ] == 0 ) {
					$firstSingleImage = $element;
					unset( $core[ 'pageElements' ][ $key ] );
				}

				if ( $element[ 'area' ] == 'colTop' && $element[ 'classname' ] == 'Headline' && $element[ 'sorting' ] == 1 ) {
					$firstHeadLine = $element;
					unset( $core[ 'pageElements' ][ $key ] );
				}
			}

			if ( $firstHeadLine ) {
				$config = unserialize( $firstHeadLine[ 'config' ] );
				$subHeadline = $config[ 'title' ];
			}

			if ( $downloadImages && $firstSingleImage ) {
				$config = unserialize( $firstSingleImage[ 'config' ] );
				$fileName = $config[ 'el_image' ];

				$url = $uploadRoot . '/cd_page_element/' . $firstSingleImage[ 'uid' ] . '/' . urlencode( $fileName );

				$teaserImage = RCFile::download( $storage, $url );

				if ( $teaserImage ) {
					$teaserImage->domainGroup = $domainGroup;
					$teaserImage->ctime = strftime( '%F %T ', $firstSingleImage[ 'crdate' ] );
					$teaserImage->mtime = strftime( '%F %T ', $firstSingleImage[ 'tstamp' ] );
					$teaserImage->title = $fileName;

					$teaserImage->save();
				} else {
					echo 'Topiccore "' . $core[ 'title' ] . '" has missing image' . "</br>";
					$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
				}

				echo 'Saved RCFile ' . $teaserImage->title . "</br>";
			} else {
				echo 'Topiccore "' . $core[ 'title' ] . '" has missing image' . "</br>";
				$teaserImage = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
			}

			$corePage = createPage( $core[ 'crdate' ], $core[ 'tstamp' ], $core[ 'title' ], 'RCTopicCore', $topicCoreParentPage->page, $topicCoreTemplate, $domainGroup, $storage );

			$coreRec = RCTopicCore::get( $storage, array(
				'title' => $core[ 'title' ],
				'domainGroup' => $domainGroup,
				'live' => 0,
				'language' => 1,
				'ctime' => strftime( '%F %T ', $core[ 'crdate' ] ),
				'mtime' => strftime( '%F %T ', $core[ 'tstamp' ] ),
				'image' => $teaserImage,
				'page' => $corePage,
				'subHeadline' => $subHeadline
			), false );

			$coreRec->save();

			$core[ 'newPrimary' ] = $coreRec->primary;

			if ( $core[ 'pageElements' ] ) {
				foreach ( $core[ 'pageElements' ] as $element ) {
					$element[ 'newPage' ] = $corePage->primary;

					if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
						$pageMapping[ $element[ 'page_uid' ] ] = $corePage->primary;
					}

					if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
						$pageElements[ $element[ 'page_uid' ] ] = array();
					}

					$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
				}
			}
		}

//	STATIC PAGES
		if ( $doStatic ) {
			$newPages = array();
			$root = array();

			$ignoreStaticPagesByTitle = array(
				'Artikel',
				'Schwerpunkte'
			);

			foreach ( $staticPages as &$page ) {
				if ( in_array( $page[ 'title' ], $ignoreStaticPagesByTitle ) ) {
					continue;
				}

				foreach ( $staticPages as &$parent ) {
					if ( $page[ 'parent_uid' ] == $parent[ 'uid' ] ) {
						if ( !isset( $parent[ 'children' ] ) ) {
							$parent[ 'children' ] = array();
						}

						$parent[ 'children' ][ ] = & $page;
						continue 2;
					}
				}

				if ( empty( $page[ 'parent_uid' ] ) ) { // filter out pages that have a parent_uid for which no page exists
					$root[ ] = & $page;
				}
			}

			unset( $page );
			unset( $parent );

			$newRootPage = $storage->selectFirstRecord( 'RCPage', array( 'where' => array(
				'domainGroup',
				'=',
				array( $domainGroup ),
				'AND',
				'parent',
				'=',
				NULL,
				'AND',
				'live',
				'=',
				array( 0 )
			) ) );

			$staticTemplate = $storage->selectFirstRecord( 'RCTemplate', array( 'where' => array( 'filename', 'LIKE', array( '%subpage%' ), 'AND', 'live', '=', array( 0 ) ) ) );

			foreach ( $root as $rootPage ) {
				if ( $rootPage[ 'pageElements' ] ) {
					foreach ( $rootPage[ 'pageElements' ] as $element ) {
						$element[ 'newPage' ] = $newRootPage->primary;

						if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
							$pageMapping[ $element[ 'page_uid' ] ] = $newRootPage->primary;
						}

						if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
							$pageElements[ $element[ 'page_uid' ] ] = array();
						}

						$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
					}
				}

				if ( !empty( $rootPage[ 'children' ] ) ) {
					createStaticPages( $rootPage[ 'children' ], $newRootPage, $storage, $domainGroup, $staticTemplate, $pageElements, $pageMapping, $doPublish );
				}
			}
		}

		// PUBLISH
		if ( $doPublish ) {
			publish( false, $storage, $domainGroup );
		}

		// PAGE ELEMENTS
		if ( $doElements ) {
			foreach ( $pageElements as $pageUid => $elements ) {
				createElements( $pageUid, $elements, $storage, $domainGroup, $uploadRoot, $flickrs, $fbpageRec, $pageMapping, $articles, $authors, $topics, $fbUrlRec, $downloadImages );
			}
		}

		// PUBLISH AGAIN INCLUDING STATIC PAGES, SO WE HAVE ELEMENTS IN FE
		if ( $doPublish ) {
			publish( true, $storage, $domainGroup );
		}

		if ( !$doCommit ) {
			throw new Exception( 'END' );
		}

		echo "DONE</br/>";

//	throw new Exception();
	} catch ( Exception $e ) {
		echo $e->getMessage() . ': ' . $e->getLine() . ': ' . $e->getFile() . "</br>" . $e->getTraceAsString();
		$tx->rollback();
		exit;
	}

	$tx->commit();


}

function createStaticPages( $pages, $newParent, $storage, $domainGroup, $staticTemplate, &$pageElements, &$pageMapping, $doPublish ) {
	foreach ( $pages as $page ) {
		$newPage = $storage->selectFirstRecord( 'RCPage', array( 'where' => array(
			'domainGroup',
			'=',
			array( $domainGroup ),
			'AND',
			'live',
			'=',
			array( 0 ),
			'AND',
			'parent',
			'=',
			array( $newParent ),
			'AND',
			'title',
			'=',
			array( $page[ 'title' ] )
		) ) );

		if ( !$newPage ) {
			$newPage = createPage( $page[ 'crdate' ], $page[ 'tstamp' ], $page[ 'title' ], 'RCPage', $newParent, $staticTemplate, $domainGroup, $storage );

			$newPage->save();

			echo 'Created static page ' . $page[ 'title' ] . ' as child of ' . $newParent->title . '<br/>';
		} else {
			echo 'Found existing static page ' . $page[ 'title' ] . ' as child of ' . $newPage->parent->title . '<br/>';
		}

		if ( $page[ 'pageElements' ] ) {
			foreach ( $page[ 'pageElements' ] as $element ) {
				$element[ 'newPage' ] = $newPage->primary;

				if ( !isset( $pageMapping[ $element[ 'page_uid' ] ] ) ) {
					$pageMapping[ $element[ 'page_uid' ] ] = $newPage->primary;
				}

				if ( !isset( $pageElements[ $element[ 'page_uid' ] ] ) ) {
					$pageElements[ $element[ 'page_uid' ] ] = array();
				}

				$pageElements[ $element[ 'page_uid' ] ][ ] = $element;
			}
		}

		if ( $doPublish ) {
			if ( $newPage->getLiveStatus() == Record::RECORD_STATUS_PREVIEW ) {
				$missing = array();

				$livePage = $newPage->copy( array( 'live' => 1 ), $missing );

				$livePage->save();
			}
		}

		if ( !empty( $page[ 'children' ] ) ) {
			createStaticPages( $page[ 'children' ], $newPage, $storage, $domainGroup, $staticTemplate, $pageElements, $pageMapping, $doPublish );
		}
	}
}

function printPageHierarchy( $pages, $level = 0 ) {
	foreach ( $pages as $page ) {
		for ( $i = 0; $i < $level; $i++ ) {
			echo ' - ';
		}

		echo $page[ 'title' ] . '<br/>';

		if ( !empty( $page[ 'children' ] ) ) {
			printPageHierarchy( $page[ 'children' ], $level + 1 );
		}
	}
}

function createPage( $crdate, $tstamp, $title, $pageType, $parent, $template, $domainGroup, $storage ) {
	$extraParams = array(
		'language' => 1,
		'domainGroup' => $domainGroup,
		'recordClasses' => array( $pageType )
	);

	$default = RCPage::getDefaultValues( $storage, array_diff( array_keys( RCPage::getFormFields( $storage ) ), array( 'page:RCMenuItem' ) ), $extraParams );

	unset( $default[ 'id' ] );
	unset( $default[ Record::FIELDNAME_PRIMARY ] );

	$page = RCPage::get( $storage, $default, false );

	$page->ctime = strftime( '%F %T ', $crdate );
	$page->mtime = strftime( '%F %T ', $tstamp );
	$page->pageType = $pageType;
	$page->title = $title;
	$page->live = 0;
	$page->parent = $parent;
	$page->template = $template;

	return $page;
}

function createElements( $pageUid, $elements, $storage, $domainGroup, $uploadRoot, $flickrs, $fbpageRec, &$pageMapping, $articles, $authors, $topics, $fbUrlRec, $downloadImages ) {
	$areaMapping = array(
		'colTop' => array(
			'key' => 'left',
			'cols' => 3
		),
		'col0' => array(
			'key' => 'left',
			'cols' => 2
		),
		'col1' => array(
			'key' => 'left',
			'cols' => 1
		),
		'col2' => array(
			'key' => 'right',
			'cols' => 1
		),
		'colLeft' => array(
			'key' => 'left',
			'cols' => 1
		),
		'colMid' => array(
			'key' => 'middle',
			'cols' => 2
		),
		'coldoubleleft' => array(
			'key' => 'left',
			'cols' => 2
		),
		'coldoubleright' => array(
			'key' => 'right',
			'cols' => 2
		),
		'colquadtop' => array(
			'key' => 'middle',
			'cols' => 4
		),
		'colBottom' => array(
			'key' => 'middle',
			'cols' => 2
		)
	);

	$areas = array();

	foreach ( $elements as $element ) {
		if ( !$element || !isset( $element[ 'config' ] ) ) {
			Log::Write( 'missing element', $element );
			continue;
		}

		$config = unserialize( $element[ 'config' ] );
		$className = NULL;
		$values = array(
			'ctime' => strftime( '%F %T ', $element[ 'crdate' ] ),
			'mtime' => strftime( '%F %T ', $element[ 'tstamp' ] ),
			'live' => 0
		);

		switch ( $element[ 'classname' ] ) {
			case 'Text':
				$className = 'RCRTE';
				$values[ 'title' ] = $config[ 'title' ];
				$values[ 'text' ] = preg_replace( '/(<[^>]+) style=".*?"/i', '$1', $config[ 'text' ] );;
				break;
			case 'Headline':
				$className = 'RCRTE';

				$string = $config[ 'title' ];

				if ( $config[ 'type' ] == "1" ) {
					$string = '<h2>' . $string . '</h2>';
				} else {
					$string = '<h3>' . $string . '</h3>';
				}


				$values[ 'title' ] = 'Headline';
				$values[ 'text' ] = $string;
				break;
			case 'Citation':
				$className = 'RCCitation';
				$values[ 'by' ] = $config[ 'by' ];
				$values[ 'text' ] = $config[ 'text' ];
				break;
			case 'SingleVideo':
				$videoConf = json_decode( $config[ 'video' ] );

				switch ( $videoConf->type ) {
					case 'youtube':
						$className = 'RCYoutubeGallery';

						$videoRec = getYoutubeUrlRecord( $videoConf->video, $storage, $domainGroup );

						$values[ 'youtubeGallery:RCYoutubeGalleryYoutubeUrl' ] = array();

						$values[ 'youtubeGallery:RCYoutubeGalleryYoutubeUrl' ][ ] = RCYoutubeGalleryYoutubeUrl::get( $storage, array(
							'youtubeUrl' => $videoRec,
							'sorting' => 0
						), false );
						break;
					case 'vimeo':
						$className = 'RCVimeoGallery';

						$videoRec = RCVimeoUrl::get( $storage, array(
							'url' => $videoConf->video,
							'domainGroup' => $domainGroup
						), Record::TRY_TO_LOAD );

						$videoRec->save();

						$values[ 'vimeoUrl' ] = $videoRec;
						break;
					default:
						throw new Exception( 'Unhandled video type: ' . $videoConf->type );
				}

				$values[ 'title' ] = $config[ 'title' ] ? : 'Video';
				$values[ 'showDescription' ] = 0;

				break;
			case 'SingleImage':
				$className = 'RCSingleImage';
				$image = NULL;

				if ( $downloadImages ) {
					$url = $uploadRoot . '/cd_page_element/' . $element[ 'uid' ] . '/' . urlencode( $config[ 'el_image' ] );
					$image = RCFile::download( $storage, $url );
				}

				if ( !$image ) {
					continue 2;
				}

				$image->domainGroup = $domainGroup;
				$image->ctime = strftime( '%F %T ', $element[ 'crdate' ] );
				$image->mtime = strftime( '%F %T ', $element[ 'tstamp' ] );
				$image->title = $config[ 'el_image' ];

				$image->save();

				$values[ 'image' ] = $image;
				$values[ 'link' ] = $config[ 'link' ];
				$values[ 'width' ] = isset( $config[ 'width' ] ) ? $config[ 'width' ] : NULL;
				$values[ 'caption' ] = $config[ 'text' ];
				break;
			case 'Download':
				$className = 'RCDownloadBox';

				$values[ 'downloadBox:RCDownloadBoxFile' ] = array();

				if ( !$downloadImages ) {
					continue 2;
				}

				for ( $i = 0; $i < $config[ 'download' ][ 'count' ]; $i++ ) {
					if ( !( isset( $config[ 'download' ] ) && isset( $config[ 'download' ][ 'file' ] ) && isset( $config[ 'download' ][ 'file' ][ $i ] ) ) ) {
						continue 3;
					}

					$url = $uploadRoot . '/cd_page_element/' . $element[ 'uid' ] . '/' . urlencode( $config[ 'download' ][ 'file' ][ $i ] );
					$file = RCFile::download( $storage, $url );

					if ( !$file ) {
						continue 3;
					}

					$file->domainGroup = $domainGroup;
					$file->ctime = strftime( '%F %T ', $element[ 'crdate' ] );
					$file->mtime = strftime( '%F %T ', $element[ 'tstamp' ] );
					$file->title = $config[ 'download' ][ 'title' ][ $i ];

					$file->save();

					$values[ 'downloadBox:RCDownloadBoxFile' ][ ] = RCDownloadBoxFile::get( $storage, array(
						'file' => $file,
						'sorting' => $i
					), false );
				}

				$values[ 'title' ] = $config[ 'title' ] ? : 'Downloads';
				$values[ 'showExtension' ] = 1;
				break;
			case 'Textarea':
				$className = 'RCRTE';
				$values[ 'title' ] = $config[ 'title' ];
				$values[ 'text' ] = $config[ 'text' ];
				break;
			case 'Gallery':
				$className = 'RCFlickrGallery';

				$values[ 'title' ] = $config[ 'title' ] ? : 'Galerie';

				foreach ( $flickrs as $key => $flickr ) {
					if ( $flickr[ 'uid' ] == (int)$config[ 'gallery' ] ) {
						$values[ 'flickrGallery:RCFlickrGalleryFlickrUrl' ] = array(
							RCFlickrGalleryFlickrUrl::get( $storage, array(
								'flickrUrl' => $flickr[ 'newPrimary' ],
								'sorting' => $key
							), false )
						);
					}
				}

				break;
			case 'YoutubePlaylist':
				$className = 'RCYoutubeGallery';

				$values[ 'title' ] = $config[ 'title' ] ? : 'Videos';
				$values[ 'youtubeGallery:RCYoutubeGalleryYoutubeUrl' ] = array();

				if ( !empty( $config[ 'playlist' ] ) ) {
					$videoRec = getYoutubeUrlRecord( $config[ 'playlist' ], $storage, $domainGroup );

					$values[ 'youtubeGallery:RCYoutubeGalleryYoutubeUrl' ][ ] = RCYoutubeGalleryYoutubeUrl::get( $storage, array(
						'youtubeUrl' => $videoRec,
						'sorting' => 0
					), false );
				}

				if ( !empty( $config[ 'singlelist' ] ) ) {
					$videos = explode( "\n", $config[ 'singlelist' ] );

					foreach ( $videos as $key => $video ) {
						$videoRec = getYoutubeUrlRecord( $video, $storage, $domainGroup );

						$values[ 'youtubeGallery:RCYoutubeGalleryYoutubeUrl' ][ ] = RCYoutubeGalleryYoutubeUrl::get( $storage, array(
							'youtubeUrl' => $videoRec,
							'sorting' => $key + 1
						), false );
					}
				}
				break;
			case 'Eventlist':
				$className = 'RCEventCalendar';

				$values[ 'title' ] = $config[ 'title' ] ? : 'Termine';
				$values[ 'showCalendar' ] = 1;

				$values[ 'eventCalendar:RCEventCalendarDomainGroup' ] = array(
					array(
						'domainGroup' => $domainGroup
					)
				);
				break;
			case 'Personlist':
				$className = 'RCPersonList';

				$values[ 'title' ] = $config[ 'title' ];
				$values[ 'type' ] = (int)$config[ 'list' ] ? RCPersonList::TYPE_LIST : RCPersonList::TYPE_STACK;
				break;
			case 'FBLikeBox':
				$className = 'RCLikeBox';

				$values[ 'facebookPage' ] = $fbpageRec;
				break;
			case 'Pageteasersingle':
				$className = 'RCPageTeaser';

				$values[ 'title' ] = $config[ 'title' ];
				$values[ 'text' ] = $config[ 'text' ];
				$pageUid = (int)$config[ 'link_page_' ];
				$image = NULL;

				if ( isset( $pageMapping[ $pageUid ] ) ) {
					$values[ 'page' ] = $pageMapping[ $pageUid ];
				} else if ( !empty( $config[ 'link' ] ) ) {
					$className = 'RCExternalPageTeaser';
					$values[ 'url' ] = $config[ 'link' ];
				} else {
					continue 2;
				}

				if ( $downloadImages ) {
					$url = $uploadRoot . '/cd_page_element/' . $element[ 'uid' ] . '/' . urlencode( $config[ 'page_image_' ] );
					$image = RCFile::download( $storage, $url );
				}

				if ( $image ) {
					$image->domainGroup = $domainGroup;
					$image->ctime = strftime( '%F %T ', $element[ 'crdate' ] );
					$image->mtime = strftime( '%F %T ', $element[ 'tstamp' ] );
					$image->title = $config[ 'page_image_' ];

					$image->save();
				} else {
					echo 'Pageteaser "' . $config[ 'title' ] . '" has missing image' . "</br>";
					$image = $storage->selectFirstRecord( 'RCFile', array( 'where' => array( 'title', '=', array( 'Missing' ) ) ) );
				}

				$values[ 'image' ] = $image;
				break;
			case 'Articleteasersingle':
				$className = 'RCArticleTeaser';

				$found = false;

				foreach ( $articles as $article ) {
					if ( $article[ 'uid' ] == (int)$config[ 'article' ] ) {
						$values[ 'article' ] = $article[ 'newPrimary' ];
						$found = true;
					}
				}

				if ( !$found ) {
					Log::Write( 'Article for teaser not found ' . $config[ 'article' ] );
				}
				break;
			case 'Articlelist':
				$className = 'RCArticleList';

				$values[ 'title' ] = $config[ 'title' ] ? : 'Artikel';
				$values[ 'articleList:RCArticleListTopic' ] = array();

				$tags = explode( ',', $config[ 'tags' ] );

				foreach ( $tags as $uid ) {
					foreach ( $topics as $topic ) {
						if ( $topic[ 'uid' ] == (int)$uid ) {
							$values[ 'articleList:RCArticleListTopic' ][ ] = array(
								'topic' => $topic[ 'newPrimary' ]
							);

							break;
						}
					}
				}
				break;
			case 'FBStream':
				$className = 'RCLiveTicker';

				$values[ 'title' ] = $config[ 'title' ] ? : 'Liveticker';
				break;
			case 'Aggregatedlist':
				$className = 'RCAggregatedList';

				foreach ( $articles as $article ) {
					if ( $article[ 'uid' ] == (int)$config[ 'first' ] ) {
						$values[ 'article' ] = $article[ 'newPrimary' ];
						break;
					}
				}
				break;
			case 'Sharing':
			case 'Blogfeed':
			case 'Redir':
			case 'Fetchurl':
			case 'Articleteaserdouble':
			case 'Articleextlist':
			case 'Html':
				// can be text, iframe or issuu embed
				//TODO
			case 'TopicHead':
				//ignore because that's already been added to topicCore pages
			case 'Newsletter':
				//ignore because we have a page for that
			case 'Author':
				//ignore because we don't have correct posts
			case 'Grid':
				//ignore for now
				//TODO: find a way to parse link value and recognize file downloads from own subweb
				continue 2;
				break;
			default:
				throw new Exception( 'Unhandled element type: ' . $element[ 'classname' ] );
		}

		$targetAreaConf = $areaMapping[ $element[ 'area' ] ];

		if ( !isset( $pageMapping[ $pageUid ] ) ) {
			continue;
		}

		$targetPage = RCPage::get( $storage, array( 'primary' => $pageMapping[ $pageUid ] ), Record::TRY_TO_LOAD );
		$pageAreas = $targetPage->{'page:RCPageArea'};

		foreach ( $pageAreas as $pageArea ) {
			if ( $pageArea->key == $targetAreaConf[ 'key' ] ) {
				$targetArea = $pageArea;
				break;
			}
		}

		$elementRec = $className::get( $storage, $values, false );

		$elementRec->save();

		$elementInArea = RCElementInArea::get( $storage, array(
			'area' => $targetArea->area,
			'class' => $className,
			'element' => $elementRec,
			'columns' => $targetAreaConf[ 'cols' ],
			'hidden' => (bool)$element[ 'hidden' ],
			'sorting' => $element[ 'sorting' ]
		), false );

		$elementInArea->save();

		echo 'Saved element ' . $className . ' in area ' . $targetArea->key . ' on page ' . $targetPage->getTitle() . '<br/>';
	}
}

function getYoutubeUrlRecord( $url, $storage, $domainGroup ) {
	$url = YoutubeSync::parseUrl( $url );
	$url = $url[ 'normalized' ];

	$rec = RCYoutubeUrl::get( $storage, array(
		'url' => $url
	), Record::TRY_TO_LOAD );

	if ( !$rec->exists() ) {
		$rec->domainGroup = $domainGroup;
	}

	return $rec;
}

function getFlickrUrlRecord( $url, $storage, $domainGroup ) {
	$url = FlickrSync::parseUrl( $url );
	$url = $url[ 'normalized' ];

	$rec = RCFlickrUrl::get( $storage, array(
		'url' => $url
	), Record::TRY_TO_LOAD );

	if ( !$rec->exists() ) {
		$rec->domainGroup = $domainGroup;
	}

	return $rec;
}

function publish( $andStatic = false, $storage, $domainGroup ) {
	$rcs = array(
		'RCTopic',
		'RCFunction',
		'RCPerson',
		'RCTopicNode',
		'RCTopicCore',
		'RCPost',
		'RCArticle',
		'RCExternalArticle',
		'RCSlider',
		'RCEvent'
	);

	foreach ( $rcs as $className ) {
		if ( $liveFieldName = $className::getDataTypeFieldName( 'DTSteroidLive' ) && $domainGroupFieldName = $className::getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
			echo 'Working on ' . $className . '<br/>';
			$records = $storage->selectRecords( $className, array( 'where' => array( 'domainGroup', '=', array( $domainGroup ) ) ) );

			$recordCount = count( $records );
			$recordsDone = 0;

			foreach ( $records as $record ) {
				$missing = array();

				$liveRec = $record->copy( array( 'live' => 1 ), $missing );

				$liveRec->save();

				$recordsDone++;

				echo 'Published ' . $recordsDone . ' of ' . $recordCount . ' ' . $className . 's<br/>';
			}
		}
	}

	if ( $andStatic ) {
		$rootPage = $storage->selectFirstRecord( 'RCPage', array( 'where' => array(
			'parent',
			'=',
			NULL,
			'AND',
			'domainGroup',
			'=',
			array( $domainGroup ),
			'AND',
			'live',
			'=',
			array( 0 )
		) ) );

		publishStatic( $rootPage );
	}
}

function publishStatic( $page ) {
	$missing = array();
	$livePage = $page->copy( array( 'live' => 1 ), $missing );
	$livePage->save();

	echo 'Published static page ' . $livePage->getTitle() . '<br/>';

	$children = $page->{'parent:RCPage'};

	if ( $children ) {
		foreach ( $children as $child ) {
			if ( $child->pageType == 'RCPage' ) {
				publishStatic( $child );
			}
		}
	}
}


?>
