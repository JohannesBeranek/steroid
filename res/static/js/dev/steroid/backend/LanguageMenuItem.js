define([
	"dojo/_base/declare",
	"dijit/MenuItem"
], function (declare, MenuItem) {
	return declare([MenuItem], {

		STLanguage: null,
		backend: null,

		constructor: function() {
			this.STLanguage = {};
		},
		onClick: function(){

			var me = this;

			me.backend.switchLanguage(me.STLanguage.primary);
		},
		destroy: function(){
			var me = this;

			delete me.backend;
			delete me.STLanguage;

			me.inherited(arguments);
		}
	});
});