define([
	"dojo/_base/declare"
], function (declare) {
	return declare(null, {
		field: null,
		readOnly: false,

		constructor: function (field) {
			var me = this;

			me.field = field;
		},
		startup: function () {

		},

		reset: function () {

		},
		setReadOnly: function (readOnly) {
			var me = this;

			me.readOnly = readOnly;
		},
		destroy: function () {
			var me = this;

			delete me.field;
		}
	});
});