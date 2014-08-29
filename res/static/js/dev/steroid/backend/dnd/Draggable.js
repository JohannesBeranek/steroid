define([
	"dojo/_base/declare",
	"dijit/TitlePane",
	"dojo/on",
	"dojo/_base/lang"
], function (declare, TitlePane, on, lang) {

	return declare([TitlePane], {
		currentContainer: null,
		mouseDownHandler: null,
		dndManager: null,

		postCreate: function () {
			var me = this;

			if (!me.readOnly) {
				me.setupMouseDownHandler();
			}

			me.inherited(arguments);
		},
		setupMouseDownHandler: function () {
			var me = this;

			me.mouseDownHandler = on(me.titleBarNode, 'mousedown', lang.hitch(me, 'mouseDown'));
		},
		mouseDown: function (e) {
			var me = this;

			me.dndManager.draggableMouseDown(e, me);
		},
		beforeDomMove: function () {
			// stub
		},
		afterDomMove: function () {
			// stub
		},
		destroy: function () {
			var me = this;

			if (me.mouseDownHandler) {
				me.mouseDownHandler.remove();
			}

			delete me.mouseDownHandler;
			delete me.dndManager;

			me.inherited(arguments);
		}
	});
});
