define([
	"dojo/_base/declare",
	"steroid/backend/dnd/Canvas"
], function (declare, Canvas) {

	return declare([Canvas], {
		accept: ['area'],
		widthWatch: null,

		constructor: function () {
			this.widthWatch = null;
		},
		getWidgetForeignRecordClass: function () {
			return 'RCTemplateArea';
		},
		getInlineSubstitutionFieldName: function () {
			return 'area';
		},
		getWidgetType: function (value) {
			return 'area';
		},
		getInlineRecordClassName: function (value) {
			return 'RCArea';
		},
		postCreate: function () {
			var me = this;

			var widthField = me.form.getFieldByFieldName('widths');

			widthField.addValueSetListenerOnce(function () {
				me.setBaseWidths(widthField.get('value'));
			});

			me.widthWatch = widthField.watch('value', function (name, oldValue, newValue) {
				if (this.get('state') == '') {
					me.setBaseWidths(newValue);
				}
			});

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			me.widthWatch.unwatch();
			delete me.widthWatch;

			me.inherited(arguments);
		}
	});
});
