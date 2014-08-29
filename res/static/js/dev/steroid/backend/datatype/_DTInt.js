define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType"
], function (declare, _DataType) {

	return declare([_DataType], {

		getWidth: function(){
			var me = this;

			var constraints = me.getConstraints();

			var length = constraints.max.toString().length;

			return length*10;
		},
		getConstraints:function () {
			var me = this;

			var range = Math.pow(2, me.fieldConf.bitWidth);

			var constraints = {
				min:me.fieldConf.constraints && me.fieldConf.constraints.min ? me.fieldConf.constraints.min : (me.fieldConf.unsigned ? 0 : (-range / 2)),
				max:me.fieldConf.constraints && me.fieldConf.constraints.max ? me.fieldConf.constraints.max : (me.fieldConf.unsigned ? range : (range / 2)) - 1
			};

			return constraints;
		}
	});
});