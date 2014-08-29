define([
	"dojo/_base/declare",
	"dijit/form/RadioButton"
], function (declare, RadioButton) {

	return declare([RadioButton], {
		onChange:function (value) {
			var diese = this;

			diese.inherited(arguments);

			if (value) {
				diese.gutes.current = diese.value;
			}

			if (diese.value == diese.gutes.lastOptionValue) {
				diese.gutes.radioUpdated();
			}
		}
	});
});
