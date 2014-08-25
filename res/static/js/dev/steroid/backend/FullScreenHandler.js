define([
	"dojo/_base/declare"
], function (declare) {
	var SingletonClass = declare(null, {
		methodNames: {
			exitFullScreen: null,
			fullScreen: null,
			fullScreenCheck: null
		}, // static!

		fullScreenParams: null,
		fullScreenChange: null,
		fullScreenActive: null,

		isAvailable: false,

		constructor: function () {
			var me = this;

			// exit is always called on document, but we still need to save it's name, as the document reference seems to change on fullscreen (illegal invocation in chrome otherwise)
			var exitFullScreenMethods = ['exitFullScreen', 'mozCancelFullScreen', 'webkitCancelFullScreen'];

			// to support fullscreening different elements, we only want to save the fullscreen function name
			var fullScreenMethods = ['requestFullScreen', 'mozRequestFullScreen', 'webkitRequestFullScreen'];

			// as these are simple booleans, it's not possible in javascript to save a reference to them, so we have to save the name
			var fullScreenChecks = ['fullScreen', 'mozFullScreen', 'webkitIsFullScreen'];

			var fullScreenParams = [
				[],
				[],
				[Element.ALLOW_KEYBOARD_INPUT]
			];

			var fullScreenChangeNames = ['fullscreenchange', 'mozfullscreenchange', 'webkitfullscreenchange'];

			for (var i = 0; i < 3; i++) {
				if (document.body[fullScreenMethods[i]]) {
					me.methodNames.fullScreen = fullScreenMethods[i];
					me.methodNames.fullScreenCheck = fullScreenChecks[i];
					me.methodNames.exitFullScreen = exitFullScreenMethods[i];
					me.fullScreenParams = fullScreenParams[i];
					me.fullScreenChange = fullScreenChangeNames[i];
					me.isAvailable = true;
					break;
				}
			}

			this.methodNames.init = true;
		},
		toggle: function (el, callback) {
			var me = this;

			if (me.isFullScreen()) {
				me.disableFullScreen();
			} else {
				me.enableFullScreen(el, callback);
			}

		},
		isFullScreen: function () {
			return this.fullScreenActive || (this.fullScreenActive = document[this.methodNames.fullScreenCheck]); // use fullScreenActive only if it's set to true
		},
		enableFullScreen: function (el, callback) {
			var me = this;
			el[me.methodNames.fullScreen].apply(el, me.fullScreenParams); // for webkit pass: Element.ALLOW_KEYBOARD_INPUT

			me.fullScreenActive = (typeof dojo.isSafari == 'undefined') || !dojo.isSafari; // safari has problems with Element.ALLOW_KEYBOARD_INPUT

			if (me.fullScreenActive) {
				if (typeof callback == 'undefined' || !callback) {
					callback = function () {
					};
				}

				var callbackWrapper = function () { // this should always be called to keep this.fullScreenActive updated
					if (!document[me.methodNames.fullScreenCheck]) {
						callback();
						document.removeEventListener(me.fullScreenChange, callbackWrapper, false);
						me.fullScreenActive = false;
					}
				};

				document.addEventListener(me.fullScreenChange, callbackWrapper, false);
			}
		},
		disableFullScreen: function () {
			this.fullScreenActive = false;

			document[this.methodNames.exitFullScreen]();
		}
	});

	return new SingletonClass();
});
