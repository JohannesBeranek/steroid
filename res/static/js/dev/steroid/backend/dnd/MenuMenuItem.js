define([
	"dojo/_base/declare",
	"steroid/backend/dnd/MenuItem",
	"steroid/backend/dnd/Draggable",
	"dojo/_base/array",
	"dijit/form/Button",
	"dojo/dom-style",
	"dojo/_base/lang",
	"dojo/i18n!steroid/backend/nls/Menu"
], function (declare, MenuItem, Draggable, array, Button, domStyle, lang, i18nMenu) {

	return declare([MenuItem, Draggable], {
		dndManager: null,
		hasBeenDropped: false,

		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				var childrenDT = me.getFieldByFieldName(me.getDataTypeFieldName('DTMenuItemChildren'));

				childrenDT.parentItem = me;
				childrenDT.dndManager = me.dndManager;

				me.dndManager.registerContainer(childrenDT);
			});
		},
		toggleChildren: function () {
			var me = this;

			var childrenDT = me.getFieldByFieldName(me.getDataTypeFieldName('DTMenuItemChildren'));
			var page = me.getFieldByFieldName('page').get('value');
			var showChildren = me.getFieldByFieldName('subItemsFromPage').get('value');
			var recordClass = me.getFieldByFieldName('pagesFromRecordClass').get('value');

			for (var i = 0; i < childrenDT.items.length; i++) {
				if (childrenDT.items[i].generated) {
					childrenDT.items[i].remove();
				}
			}

			if (recordClass && showChildren) {
				childrenDT.setRecordClassPages(recordClass);
			} else if (page && showChildren) {
				childrenDT.setRootPage(page);
			}
		},
		indexChange: function (idx) {
			var me = this;

			me.ownIndexInParent = idx;

			if (!me.isDestroying && !me._beingDestroyed) {
				me.updateFieldSubmitNames();
			}
		},
		updateSortingField: function (value) {
			var me = this;

			if (!me.generated || me.userChange || me.hasBeenDropped) {
				var sortingField = me.getFieldByFieldName(me.getDataTypeFieldName('DTSteroidSorting'));

				sortingField.set('value', value);

				me.hasBeenDropped = false;
			}
		},
		updateFieldSubmitNames: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.updateSubmitName(me.submitName + '[' + me.ownIndexInParent + '][' + fieldName + ']');
			}
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			me.updateFieldSubmitNames();
		},
		getItem: function () {
			return this;
		},
		setFieldDirty: function (setName, fieldDirtyNess, fieldName) {
			var me = this;

			return (!me.generated || me.userChange) && ((me.isNew || me.userChange) || (setName && (fieldDirtyNess > 0 || array.indexOf(me.submitFieldsIfDirty, fieldName) >= 0)));
		},
		getFieldsToHide: function () {
			var me = this;

			return ['menu'];
		},
		destroy: function () {
			var me = this;

			delete me.dndManager;

			me.inherited(arguments);
		}
	});
});
