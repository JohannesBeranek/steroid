define([
	"dojo/_base/declare",
	"steroid/backend/dnd/JoinRecord",
	"steroid/backend/dnd/Draggable",
	"dojo/aspect",
	"dojo/dom-class"
], function (declare, JoinRecord, Draggable, aspect, domClass) {

	return declare([JoinRecord, Draggable], {

		itemsMovedAspect: null,
		showLiveStatus: false,

		constructor: function () {
			this.itemsMovedAspect = null;
		},
		hookToDataTypeInstance: function (dt, fieldConf, fieldName) {
			var me = this;

			if (fieldName == me.inlineSubstitutionFieldName) {
				me.itemsMovedAspect = aspect.after(dt, "itemsMoved", function () {
					me.itemsMovedAspect.remove();
					delete me.itemsMovedAspect;
					me.itemsMoved();
				});
			}

			me.inherited(arguments);
		},
		itemsMoved: function (items) {
			return items;
		},
		beforeDomMove: function () {
			var me = this;

			me.inlineRecord.beforeDomMove();
		},
		afterDomMove: function () {
			var me = this;

			me.inlineRecord.afterDomMove();
		},
		_setValueAttr: function (value) {
			var me = this;

			me.inherited(arguments);

			if (me.showLiveStatus) {
				var status = typeof value[me.inlineSubstitutionFieldName]['_liveStatus'] == 'undefined' ? value[me.inlineSubstitutionFieldName]['liveStatus'] : value[me.inlineSubstitutionFieldName]['_liveStatus'];
				me.setLiveStatusClass(status);
			}
		},
		setLiveStatusClass: function (status) {
			var me = this;

			domClass.replace(me.domNode, 'STLiveStatus_' + status, 'STLiveStatus_0 STLiveStatus_1 STLiveStatus_2 STLiveStatus_3');
		},
		destroy: function () {
			var me = this;

			if (me.itemsMovedAspect) {
				me.itemsMovedAspect.remove();
				delete me.itemsMovedAspect;
			}

			me.inherited(arguments);
		}
	});
});
