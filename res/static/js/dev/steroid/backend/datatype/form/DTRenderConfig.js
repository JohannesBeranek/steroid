define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTJSON",
	"dojo/_base/json"
], function (declare, DTJSON, json) {

	return declare([DTJSON], {
		hideField: true,
		imageWatch: null,
		isImage: false,

		startup: function () {
			var me = this;

			me.inherited(arguments);

			var fileField = me.form.getFieldByFieldName(me.form.getDataTypeFieldName('DTFile'));

			me.imageWatch = fileField.watch('isImage', function (name, oldValue, newValue, field) {
				me.set('isImage', newValue);
			});
		},
		updateValue: function () {
			var me = this;

			var val = {};

			for (var i = 0, item; item = me.extensions[i]; i++) {
				item.buildRenderConfig(val);
			}

			me.set('value', me.isEmpty(val) ? null : val);
		},
		isEmpty: function (obj) {
			for (var prop in obj) {
				if (obj.hasOwnProperty(prop))
					return false;
			}

			return true;
		},
		destroy: function () {
			var me = this;

			if (me.imageWatch) {
				me.imageWatch.unwatch();
				delete me.imageWatch;
			}

			me.inherited(arguments);
		}
	});
});