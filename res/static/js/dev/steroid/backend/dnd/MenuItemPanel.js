define([
	"dojo/_base/declare",
	"dojox/layout/TableContainer",
	"steroid/backend/dnd/SourceMenuItem",
	"dojo/on",
	"dojo/dom-geometry",
	"dojo/dom-class"
], function (declare, TableContainer, SourceMenuItem, on, domGeom, domClass) {
	return declare([TableContainer], {
		orientation: 'vert',
		showLabels: false,
		backend: null,
		submitName: null,
		owningRecordClass: null,
		parentContainer: null,
		"class": 'STMenuItemPanel',

		constructor: function () {
			this.parentContainer = null;
		},
		startup: function () {
			var me = this;

			var item = new SourceMenuItem({
				open: false,
				submitName: me.submitName,
				backend: me.backend,
				owningRecordClass: me.owningRecordClass,
				dndManager: me.dndManager,
				mainClassConfig: me.mainClassConfig,
				parentContainer: me.parentContainer
			});

			me.addChild(item);

			me.scrollHandle = on(me.backend.moduleContainer.detailPane.formContainer.containerNode, 'scroll', function (e) {
				me.scrolled();
			});

			me.inherited(arguments);
		},
		scrolled: function () {
			var me = this;

			var pos = domGeom.position(me.domNode, true);
			var canvasPos = domGeom.position(me.parentContainer.domNode, true);

			if (pos.y < 76 && !domClass.contains(me.domNode, 'fixed')) {
				domClass.add(me.domNode, 'fixed');
				domClass.add(me.parentContainer.domNode, 'panelFixed');
			}

			if (pos.y < canvasPos.y && domClass.contains(me.domNode, 'fixed')) {
				domClass.remove(me.domNode, 'fixed');
				domClass.remove(me.parentContainer.domNode, 'panelFixed');
			}
		},
		findContainer: function (container) {
			var me = this;

			if (container == me) {
				return container;
			}

			return false;
		}
	});
});