define([
	"dojo/_base/declare",
	"dijit/MenuItem"
], function (declare, MenuItem) {
	return declare([MenuItem], {

		classConfig: null,
		backend: null,

		constructor: function () {
			this.classConfig = {};
		},
		onClick: function () {

			var me = this;

			me.backend.openModule(me.classConfig);
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});