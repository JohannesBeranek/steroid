<!DOCTYPE HTML>
<html lang="en-US">
<head>
	<meta charset="UTF-8">
	<meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>
	<meta name="robots" content="noindex,nofollow"/>

	<title>CMS gruene.at 2013</title>
	<? // TODO: remove css files we don't need anymore! ?>
	<link rel="stylesheet" href="/steroid/res/static/js/dev/dojo/resources/dojo.css"><?
	$theme = $this->config[ 'interface' ][ 'themes' ][ 'current' ];
	?>
	<link id="stylesheet-theme" rel="stylesheet" href="<?= htmlspecialchars( $theme[ 'stylesheet' ], ENT_COMPAT, "UTF-8" ) ?>" title="<?= htmlspecialchars( $theme[ 'label' ], ENT_COMPAT, 'UTF-8' ) ?>">
	<link rel="stylesheet" href="/steroid/res/static/js/dev/dojox/form/resources/ListInput.css">
	<link rel="stylesheet" href="/steroid/res/static/js/dev/dojox/layout/resources/ResizeHandle.css">
	<link rel="stylesheet" href="/steroid/res/static/js/dev/dojox/form/resources/CheckedMultiSelect.css">

	<link rel="stylesheet" href="/steroid/res/static/js/dev/dojox/widget/Toaster/Toaster.css">
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,800italic,400,700,800' rel='stylesheet' type='text/css'><? // TODO: project specific! ?>

	<link rel="stylesheet" href="/steroid/res/static/css/backend.css" media="screen">
	<?
	if ( isset( $theme[ 'stylesheet-override' ] ) ): ?>
		<link id="stylesheet-override" rel="stylesheet" href="<?= htmlspecialchars( $theme[ 'stylesheet-override' ], ENT_COMPAT, "UTF-8" ) ?>" media="screen">
	<? endif; ?>
	<link id="stylesheet-override-post" rel="stylesheet" href="/res?file=/stlocal/res/css/headings.css" media="screen"><? // TODO: project specific! ?>

	<? if ( isset( $this->config[ 'customCSSPaths' ] ) ):
		foreach ( $this->config[ 'customCSSPaths' ] as $path ): ?>
			<link rel="stylesheet" href="<?= $path ?>"/>
		<? endforeach; ?>
	<? endif; ?>

	<script type="text/javascript">
		dojoConfig = {
			modulePaths: {
				"steroid": "../steroid",
				"stlocal": "../../../../../../stlocal"
			},
			parseOnLoad: false,
			cacheBust: new Date(), <? // TODO: more intelligent mechanism which allows total and partial caching without the need to manually clear cache ?>
			waitSeconds: 30,
			locale: "<?= $this->config[ 'interface' ][ 'languages' ][ 'current' ]; ?>"
		};
	</script>

	<script type="text/javascript" src="/steroid/res/static/js/dev/dojo/dojo.js" data-dojo-config="async: true, isDebug: false"></script><?

	if ( !$this->isBackendUser && isset( $this->config[ 'loginext' ] ) ) {
		foreach ( $this->config[ 'loginext' ] as $loginExt ) {
			if ( !empty( $loginExt[ 'includeFilesBefore' ] ) ) {
				foreach ( (array)$loginExt[ 'includeFilesBefore' ] as $includeFile ) {
					?>
					<script type="text/javascript" src="<?= htmlspecialchars( $includeFile, ENT_COMPAT, "UTF-8" ) ?>"></script>
				<?
				}
			}
		}
	}

	?>
	<script type="text/javascript">
		//		require(['steroid/base'], function() {
		//			require(['steroid/shared'], function() {
		require(['dojo/_base/kernel', "dojo/_base/loader"], function () {
			<?php

		if ($this->isBackendUser) {
						?>
			require(["dojo/dom", "dojo/domReady!", "steroid/backend/Backend"], function (dom, domReady, Backend) {
				window.Backend = new Backend({ config: <?= json_encode( $this->config ); ?> });
			});
			<?php
		} else {
			?>
			require(["dojo/dom", "dojo/domReady!", "steroid/backend/Login"], function (dom, domReady, Login) {
				window.Login = new Login({ config: <?= json_encode( $this->config ); ?> });
				<?

			foreach ($this->config['loginext'] as $loginExt) {
				if (!empty($loginExt['includeFilesAfter'])) {
					foreach ((array)$loginExt['includeFilesAfter'] as $includeFile) {
						$includeFiles[] = $includeFile;
					}
				}
			}
	
			if (!empty($includeFiles)) {
				?>
				require(["<?= implode(",", $includeFiles) ?>"], function () {
					window.Login.show()
				});


				<?
							} else {
				?>
				window.Login.show();
				<?
							}
				?>
			});
			<?


					}
			?>
		});
		//			});
		//		});

	</script>
</head>
<body id="steroid" class="<?= htmlspecialchars( $this->config[ 'interface' ][ 'themes' ][ 'current' ][ 'name' ], ENT_COMPAT, 'UTF-8' ) ?>">
<div id="spinningSquaresG">
	<div id="spinningSquaresG_1" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_2" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_3" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_4" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_5" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_6" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_7" class="spinningSquaresG"></div>
	<div id="spinningSquaresG_8" class="spinningSquaresG"></div>
</div>
<div id="uiContainer"></div>
</body>
</html>