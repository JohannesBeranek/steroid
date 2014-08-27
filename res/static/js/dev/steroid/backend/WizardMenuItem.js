define([
	"dojo/_base/declare",
	"dijit/MenuItem"
], function (declare, MenuItem) {
	return declare([MenuItem], {

		wizardConfig: null,
		backend: null,

		constructor: function () {
			this.wizardConfig = {};
		},
		onClick: function () {

			var me = this;

			me.backend.openModule(me.wizardConfig);
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});