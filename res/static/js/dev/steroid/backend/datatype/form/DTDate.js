define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTDate",
	"dijit/form/DateTextBox"
], function (declare, _DTFormFieldMixin, _DTDateTime, DateTextBox) {

	return declare([DateTextBox, _DTFormFieldMixin, _DTDateTime], {
		value: "1970-01-01",

		getConstraints: function () {
			var me = this;

			var constraints = {};

			if (me.fieldConf.currentAsMaxLimit) {
				constraints.max = new Date();
			}

			return constraints;
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});