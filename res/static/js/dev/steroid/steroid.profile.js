var profile = (function(){
	return {
		basePath: './../',
		releaseDir: '../build',
		releaseName: 'steroid',
		action: 'release',
		packages: [{
			name: "dojo",
			location: "dojo"
		},{
			name: "dijit",
			location: "dijit"
		},{
			name: "dojox",
			location: "dojox"
		},{
			name: "dgrid",
			location: "dgrid"
		},{
			name: "put-selector",
			location: "put-selector"
		},{
			name: "xstyle",
			location: "xstyle"
		},{
			name: "steroid",
			location: "steroid"
		}],
		
		layers: {
			"steroid/steroid": {
				include: [ "steroid/backend/Backend" ]
			},
			"steroid/login": { 
				include: [ "steroid/backend/Login" ]
			}
		},
		
		layerOptimize: "closure",
		optimize: "closure",
		cssOptimize: "comments",
		mini: true,
		stripConsole: "all",
		selectorEngine: "lite", // "acme"
		staticHasFeatures: {
			"config-deferredInstrumentation": 0,
			"config-dojo-loader-catches": 0,
			"config-tlmSiblingOfDojo": 0,
			"dojo-amd-factory-scan": 0,
			"dojo-combo-api": 0,
			"dojo-config-api": 1,
			"dojo-config-require": 0,
			"dojo-debug-messages": 0,
			"dojo-dom-ready-api": 1,
			"dojo-firebug": 0,
			"dojo-guarantee-console": 0,
			"dojo-has-api": 1,
			"dojo-inject-api": 1,
			"dojo-loader": 1,
			"dojo-log-api": 0,
			"dojo-modulePaths": 0,
			"dojo-moduleUrl": 0,
			"dojo-publish-privates": 0,
			"dojo-requirejs-api": 0,
			"dojo-sniff": 0,
			"dojo-sync-loader": 0,
			"dojo-test-sniff": 0,
			"dojo-timeout-api": 0,
			"dojo-trace-api": 0,
			"dojo-undef-api": 0,
			"dojo-v1x-i18n-Api": 1,
			"dom": 1,
			"host-browser": 1,
			"extend-dojo": 1
		},
		defaultConfig: {
			hasCache: {
				"dojo-built": 1,
				"dojo-loader": 1,
				"dom": 1,
				"host-browser": 1,
				"config-selectorEngine": "lite"
			},
			async: 1,
			debug: 0
		},
		
		resourceTags: {
			amd: function(filename, mid) {
				return /\.js$/.test(filename);
			}
		}
	};
})();