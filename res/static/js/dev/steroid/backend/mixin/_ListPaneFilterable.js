define([
	"dojo/_base/declare"
], function (declare) {
	return declare(null, {
		domainGroupSwitched: function (domainGroup) {
			var me = this;

			me.inherited(arguments);

			if (me.filterPane && me.filterPane.form) {
				var domainGroupFilterField = me.filterPane.form.getFieldByFieldName(me.filterPane.form.getDataTypeFieldName('DTSteroidDomainGroup'));

				if (domainGroupFilterField) { // only set value if filter is active
					for (var i = 0, ilen = domainGroupFilterField.domNode.classList.length; i < ilen; i++) {
						if (domainGroupFilterField.domNode.classList[i] == 'active') {
							domainGroupFilterField.set('value', domainGroup);
							break;
						}
					}
				}
			}
		}
	});
});