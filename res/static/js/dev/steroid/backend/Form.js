define([
	"dojo/_base/declare",
	"dijit/form/Form",
	"steroid/backend/mixin/_SubFormMixin",
	"dojo/_base/lang",
], function (declare, Form, _SubFormMixin, lang) {
	return declare([Form, _SubFormMixin], {
		isNew: false,
		isMain: true,

		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			if (me.isFilterPane) {
				me.ownFields = lang.clone(me.ownClassConfig.filterFields);
			}

			me.mainClassConfig = me.ownClassConfig;
		},
		loadRecord: function (record) {
			var me = this;

			me.isNew = false;

			me.backend.doStandBy(me);

			me.set('value', record);
		},
		newRecord: function (record) {
			var me = this;

			me.isNew = true;

			me.backend.doStandBy(me);

			me.set('value', record);
		},
		valueComplete: function () {
			var me = this;

			me.inherited(arguments);

			me.backend.hideStandBy(me);
		}
	});
});