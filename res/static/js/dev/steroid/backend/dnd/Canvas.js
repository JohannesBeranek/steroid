define([
	"dojo/_base/declare",
	"steroid/backend/dnd/WidgetContainer",
	"dojo/Deferred",
	"steroid/backend/mixin/_Resizeable",
	"dojo/dom-style"
], function (declare, WidgetContainer, Deferred, _Resizeable, domStyle) {

	return declare([WidgetContainer, _Resizeable], {
		sizeableHoriz: true,
		sizeableVert: false,
		resizeHandleVisible: false,
		containerPadding: 10,
		pixelBased: false,
		"class": 'STWidgetContainer STCanvas STResizeable',

		constructor: function () {
			this.parentWidget = this;
		},
		setBaseWidths: function (csvWidths) {
			// FIXME: under which circumstances should csvWidths not be valid? shouldn't we handle those cases?
			if (csvWidths && csvWidths.length) {
				var tmp = csvWidths.split(','),
					widths = [],
					width;

				for (var i = 0, ilen = tmp.length; i < ilen; i++) {
					if (width = parseInt(tmp[i], 10)) {
						widths.push(width);
					}
				}

				var me = this;
				me.setParentDimensions({ w: widths });
			}
		},
		parentDimensionsChanged: function () {
			var me = this;

			var maxW = me.getMax('w', me.parentDimensions);

			me.setCurrentDimensions({ w: maxW });

			domStyle.set(me[me.resizeNode], 'max-width', maxW + 'px');
		},
		getStringForCurrentEqualsMax: function (value) {
			return this.pixelBased ? value + 'px' : null;
		},
		getFieldByFieldName: function (fieldName) {
			return this.form.getFieldByFieldName(fieldName);
		},
		mutateDomDimension: function (dim, value) {
			return value;
		},
		destroy: function () {
			var me = this;

			delete me.parentWidget;

			me.inherited(arguments);
		}
	});
});
