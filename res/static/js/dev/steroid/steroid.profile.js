/**
 * This is the default application build profile used by the boilerplate. While it looks similar, this build profile
 * is different from the package build profile at `app/package.js` in the following ways:
 *
 * 1. you can have multiple application build profiles (e.g. one for desktop, one for tablet, etc.), but only one
 *    package build profile;
 * 2. the package build profile only configures the `resourceTags` for the files in the package, whereas the
 *    application build profile tells the build system how to build the entire application.
 *
 * Look to `util/build/buildControlDefault.js` for more information on available options and their default values.
 */

var profile = (function () {
	return {
		// `basePath` is relative to the directory containing this profile file; in this case, it is being set to the
		// src/ directory, which is the same place as the `baseUrl` directory in the loader configuration. (If you change
		// this, you will also need to update run.js.)
		basePath: '../',

		// This is the directory within the release directory where built packages will be placed. The release directory
		// itself is defined by `build.sh`. You should probably not use this; it is a legacy option dating back to Dojo
		// 0.4.
		// If you do use this, you will need to update build.sh, too.
		// releaseName: '',

		// Builds a new release.
		action: 'release',

		// Strips all comments and whitespace from CSS files and inlines @imports where possible.
		cssOptimize: 'comments',

		// Excludes tests, demos, and original template files from being included in the built version.
		mini: true,

		// Uses Closure Compiler as the JavaScript minifier. This can also be set to "shrinksafe" to use ShrinkSafe,
		// though ShrinkSafe is deprecated and not recommended.
		// This option defaults to "" (no compression) if not provided.
//		optimize: 'closure',

		// We're building layers, so we need to set the minifier to use for those, too.
		// This defaults to "shrinksafe" if not provided.
//		layerOptimize: 'closure',

		// Strips all calls to console functions within the code. You can also set this to "warn" to strip everything
		// but console.error, and any other truthy value to strip everything but console.warn and console.error.
		// This defaults to "normal" (strip all but warn and error) if not provided.
//		stripConsole: 'all',

		// The default selector engine is not included by default in a dojo.js build in order to make mobile builds
		// smaller. We add it back here to avoid that extra HTTP request. There is also a "lite" selector available; if
		// you use that, you will need to set the `selectorEngine` property in `app/run.js`, too. (The "lite" engine is
		// only suitable if you are not supporting IE7 and earlier.)
		selectorEngine: 'lite',

		packages: [
			{
				name: "dojo",
				location: "dojo"
			},
			{
				name: "dijit",
				location: "dijit"
			},
			{
				name: "dojox",
				location: "dojox"
			},
			{
				name: "steroid",
				location: "steroid"
			}
		],

		// Builds can be split into multiple different JavaScript files called "layers". This allows applications to
		// defer loading large sections of code until they are actually required while still allowing multiple modules to
		// be compiled into a single file.
		layers: {
			// This is the main loader module. It is a little special because it is treated like an AMD module even though
			// it is actually just plain JavaScript. There is some extra magic in the build system specifically for this
			// module ID.
			'dojo/dojo': {
				// In addition to the loader `dojo/dojo` and the loader configuration file `app/run`, we are also including
				// the main application `app/main` and the `dojo/i18n` and `dojo/domReady` modules because, while they are
				// all conditional dependencies in `app/main`, we do not want to have to make extra HTTP requests for such
				// tiny files.
				include: ['dojo/dojo', 'dojo/domReady', 'dojo/_base/declare'],

				// By default, the build system will try to include `dojo/main` in the built `dojo/dojo` layer, which adds
				// a bunch of stuff we do not want or need. We want the initial script load to be as small and quick to
				// load as possible, so we configure it as a custom, bootable base.
				boot: true,
				customBase: true
			},
			'steroid/backend': {
				include: ["steroid/backend/User", "steroid/backend/DomainGroupSelector", "steroid/backend/LanguageSelector", "steroid/backend/MenuTime", "steroid/backend/ServerComm", "steroid/backend/ModuleMenuItem", "steroid/backend/WizardMenuItem", "steroid/backend/DetailPane", "steroid/backend/STStore", "steroid/backend/ErrorDialog", "steroid/backend/ReferenceDialog", "steroid/backend/ModuleContainer", "steroid/backend/mixin/_ModuleContainerList", "steroid/backend/mixin/_hasStandBy", "steroid/backend/WelcomeScreen", "steroid/backend/Toaster", "steroid/backend/mixin/_hasInitListeners", "steroid/backend/dnd/Clipboard", "steroid/backend/dnd/DndManager", "steroid/backend/stats/stats", "steroid/backend/LanguageMenuItem", "steroid/backend/Form", "steroid/backend/YesNoDialog", "steroid/backend/Localizor", "steroid/backend/ListPane", "steroid/backend/mixin/_ListPaneFilterable", "steroid/backend/dnd/DropContainer", "steroid/backend/dnd/Widget", "steroid/backend/dnd/ClipboardWidget", "steroid/backend/dnd/ClipboardRecord", "steroid/backend/dnd/ClipboardPage", "steroid/backend/dnd/InlineRecord", "steroid/backend/stats/themes/general", "steroid/backend/mixin/_SubFormMixin", "steroid/backend/dnd/DraggableJoinRecord", "steroid/backend/mixin/_Resizeable", "steroid/backend/dnd/InlineEditableRecord", "steroid/backend/FilterPane", "steroid/backend/dnd/SourceWidget", "steroid/backend/dnd/JoinRecord", "steroid/backend/dnd/Draggable", "steroid/backend/mixin/ResizeHandle", "steroid/backend/datatype/list/_DTListMixin", "steroid/backend/datatype/_DTString", "steroid/backend/datatype/list/_DTRecordAsTagMixin", "steroid/backend/datatype/_DTRecordReference", "steroid/backend/datatype/list/DTString", "steroid/backend/datatype/list/DTInt", "steroid/backend/datatype/list/DTRecordReference", "steroid/backend/datatype/form/DTRecordSelector", "steroid/backend/datatype/form/_DTFormFieldMixin", "steroid/backend/mixin/RecordSearchField", "steroid/backend/dnd/StaticRecordItem", "steroid/backend/datatype/_DTRecordAsTagMixin", "steroid/backend/datatype/_DTInt", "steroid/backend/datatype/_DataType", "steroid/backend/FieldExtensionContainer", "steroid/backend/datatype/form/DTInt", "steroid/backend/mixin/_hasContentLengthIndicator", "steroid/backend/datatype/form/DTSelect", "steroid/backend/datatype/form/DTString", "steroid/backend/datatype/_DTEnum", "steroid/backend/dnd/PageUrlJoinRecord", "steroid/backend/mixin/_DTAreaJoinForeignReference", "steroid/backend/dnd/PageCanvas", "steroid/backend/datatype/_DTSet", "steroid/backend/dnd/STMenu", "steroid/backend/dnd/WidgetPanel", "steroid/backend/dnd/Canvas", "steroid/backend/dnd/PageMenuItem", "steroid/backend/dnd/MenuItem", "steroid/backend/dnd/WidgetContainer", "steroid/backend/datatype/form/DTMenuItemForeignReference", "steroid/backend/dnd/MenuItemContainer", "steroid/backend/dnd/MenuItemPanel", "steroid/backend/dnd/MenuMenuItem", "steroid/backend/datatype/form/DTBool", "steroid/backend/dnd/SourceMenuItem", "steroid/backend/datatype/form/DTDateTime", "steroid/backend/datatype/form/RadioButton", "steroid/backend/datatype/_DTDateTime"]
			}
		},

		resourceTags: {
			amd: function (filename, mid) {
				return /\.js$/.test(filename);
			},
			copyOnly: function (filename, mid) {
				return false;
			},
			test: function (filename, mid) {
				return false;
			}
		},

		// Providing hints to the build system allows code to be conditionally removed on a more granular level than
		// simple module dependencies can allow. This is especially useful for creating tiny mobile builds.
		// Keep in mind that dead code removal only happens in minifiers that support it! Currently, only Closure Compiler
		// to the Dojo build system with dead code removal.
		// A documented list of has-flags in use within the toolkit can be found at
		// <http://dojotoolkit.org/reference-guide/dojo/has.html>.
		staticHasFeatures: {
			// The trace & log APIs are used for debugging the loader, so we do not need them in the build.
			'dojo-trace-api': 0,
			'dojo-log-api': 0,

			// This causes normally private loader data to be exposed for debugging. In a release build, we do not need
			// that either.
			'dojo-publish-privates': 0,

			// This application is pure AMD, so get rid of the legacy loader.
			'dojo-sync-loader': 0,

			// `dojo-xhr-factory` relies on `dojo-sync-loader`, which we have removed.
			'dojo-xhr-factory': 0,

			// We are not loading tests in production, so we can get rid of some test sniffing code.
			'dojo-test-sniff': 0
		}
	};
})();