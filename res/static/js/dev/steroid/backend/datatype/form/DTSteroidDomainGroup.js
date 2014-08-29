define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTRecordSelector",
	"dojo/_base/lang"
], function (declare, DTRecordSelector, lang) {

	return declare([DTRecordSelector], {
		hideField:true,

		_setValueAttr: function(value, a,b,c){
			var me = this;

			if(!value && !me.fieldConf.mayBeEmpty){
				value = me.backend.config.system.domainGroups.current;
			}

			me.inherited(arguments, [value]);
		},
		isValid: function(){
			return true;
		}
	});
});
