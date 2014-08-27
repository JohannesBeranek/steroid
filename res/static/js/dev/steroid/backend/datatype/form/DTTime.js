define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTTime",
	"dijit/form/TimeTextBox",
	"dojo/_base/lang"
], function (declare, _DTFormFieldMixin, _DTTime, TimeTextBox, lang) {

	return declare([TimeTextBox, _DTFormFieldMixin, _DTTime], {

		value: 'T00:00:00',

		getConstraints: function () {
			var me = this;

			var constraints = {};

			if (me.fieldConf.currentAsMaxLimit) {
				constraints.max = new Date();
			}

			return constraints;
		},
		_setValueAttr: function (value) {
			var me = this;

			if (value && typeof value != "object" && value.indexOf('T') == -1) {
				value = 'T' + value;
			}

			me.inherited(arguments, [value]);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});