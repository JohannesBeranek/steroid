define([
	"dojo/_base/declare",
	"steroid/backend/dnd/DropContainer",
	"dojo/Deferred",
	"dojox/lang/functional",
	"dojo/dom-construct",
	"dijit/TitlePane",
	"dojo/i18n!steroid/backend/nls/Menu"
], function (declare, DropContainer, Deferred, langFunc, domConstruct, TitlePane, i18nMenu) {

	return declare([DropContainer, TitlePane], {

		class: 'STMenuItemContainer',
		accept: null,
		parentItem: null,

		constructor: function () {
			this.accept = ['SourceMenuItem'];
			this.parentItem = null;
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			me.accept.push('MenuItem' + me.form.id);
		},
		_setValueAttr: function (value) {
			var me = this;

			me.incomingValueCount = langFunc.keys(value).length;

			if (!value || !me.incomingValueCount) {
				if (me.valueSet) {
					me.valueComplete();
				}

				me.originalItems = [];
			}
		},
		itemRemoved: function (item) {
			var me = this;

			if (me._beingDestroyed) {
				return;
			}

			me.inherited(arguments);
		},
		updateItemIndexes: function () {
			var me = this;

			me.inherited(arguments);

			var sorting = 0;

			for (var i = 0; i < me.items.length; i++) {
				var item = me.items[i];

				if (item.generated && !(item.userChange || item.hasBeenDropped)) {
					sorting = (i + 1) * 256;
				} else {
					sorting++;
				}

				item.indexChange(i);
				item.updateSortingField(sorting);
			}
		},
		drop: function (item) {
			var me = this;

			var menuItem = item.getItem(me);

			menuItem.addValueSetListenerOnce(function () {
				menuItem.hasBeenDropped = true;
				me.addItem(menuItem, me.getDropIndex(false, menuItem));
			});
		},
		getDirtyNess: function () {
			var me = this;

			return me.getItemDirtyNess() + me.getArrayDirtyNess();
		},
		getArrayDirtyNess: function () {
			var me = this;

			var dirtyNess = 0;
			var itemPrimaries = me.items.length ? [] : null;

			for (var i = 0; i < me.items.length; i++) {
				if (!me.items[i].generated) {
					itemPrimaries[i] = me.items[i].getIdentity();
				}
			}

			dirtyNess += me.compareArray(itemPrimaries, me.originalItems);
			dirtyNess += me.compareArray(me.originalItems, itemPrimaries);

			return dirtyNess;
		},
		setEmptyName: function (setName) {
			var me = this;

			var customItems = false;

			for (var i = 0; i < me.items.length; i++) {
				if (!me.items[i].generated) {
					customItems = true;
					break;
				}
			}

			return setName && ((me.originalItems && me.originalItems.length) && !customItems);
		},
		isOriginalItem: function (item) {
			return !item.isNew && !item.generated;
		},
		dragOut: function (e) {
			var me = this;

			me.inherited(arguments);

			if (me.parentItem && me.parentItem.currentContainer) {
				me.parentItem.currentContainer.dragOut();
			}
		},
		destroy: function () {
			var me = this;

			delete me.parentItem;

			for (var i = 0, item; item = me.itemWatches[i]; i++) {
				item.unwatch();
			}

			delete me.itemWatches;

			for (var i = 0, item; item = me.aspects[i]; i++) {
				item.remove();
			}

			delete me.aspects;

			for (var i = 0, item; item = me.items[i]; i++) {
				item.destroyRecursive();
			}

			delete me.items;

			me.inherited(arguments);
		}
	});
});
