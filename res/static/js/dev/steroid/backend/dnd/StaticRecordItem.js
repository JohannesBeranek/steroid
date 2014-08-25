define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_SubFormMixin",
	"dijit/TitlePane",
	"dojo/_base/array",
	"dojo/on",
	"dojo/dom-construct"
], function (declare, _SubFormMixin, TitlePane, array, on, domConstruct) {

	return declare([TitlePane, _SubFormMixin], {
		submitName: null,
		toggleable: false,
		valueSetListener: null,
		closeNode: null,
		class: 'STStaticRecord',
		ownIndexInParent: 0,
		readOnly: false,

		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			for (var fieldName in me.ownFields) {
				if (array.indexOf(me.submitFieldsIfDirty, fieldName) < 0 && !me.ownClassConfig.titleFields[fieldName]) {
					delete me.ownFields[fieldName];
				}
			}
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.set('open', false);

			me.valueSetListener = me.addValueSetListener(function () {
				me.set('title', me.collectTitle(me));
			});

			if (!me.readOnly) {
				me.setupCloseButton();
			}
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			if (readOnly) {
				me.removeCloseButton();
			} else {
				me.setupCloseButton();
			}
		},
		remove: function () {
			var me = this;

			me.destroyRecursive();
		},
		setupCloseButton: function () {
			var me = this;

			if (me.closeNode || me.closeHandle) {
				return;
			}

			me.closeNode = domConstruct.create('div', { class: 'closeNode STWidgetIcon_close' });
			me.titleBarNode.appendChild(me.closeNode);

			me.closeHandle = on(me.closeNode, 'click', function () {
				me.remove();
			});
		},
		removeCloseButton: function () {
			var me = this;

			if (me.closeNode) {
				domConstruct.destroy(me.closeNode);
				delete me.closeNode;
			}

			if (me.closeHandle) {
				me.closeHandle.remove();
				delete me.closeHandle;
			}
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.updateSubmitName(me.submitName + '[' + fieldName + ']');
			}
		},
		destroy: function () {
			var me = this;

			me.removeCloseButton();

			me.removeValueSetListener(me.valueSetListener);

			me.inherited(arguments);
		}
	});
});
