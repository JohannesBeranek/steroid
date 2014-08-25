define([
	"dojo/_base/declare",
	"steroid/backend/dnd/MenuItem",
	"dojo/i18n!steroid/backend/nls/Menu"
], function (declare, MenuItem, i18nMenu) {

	return declare([MenuItem], {
		owningContainer: null,

		getFieldConf: function (entry, i) {
			var me = this;

			var entry = me.inherited(arguments);

			if (i == 'parent') {
				entry['dataType'] = 'DTRecordReference';
				entry['fieldConf']['dataType'] = 'DTRecordReference';
			}

			if (i == 'page' || i == 'menu' || i == 'parent') {
				entry['fieldConf']['readOnly'] = true;
			}

			return entry;
		},
		collectTitle: function (origin) {
			var me = this;

			return me.record._title + ((me.generated && !me.userChange) ? ' (' + i18nMenu.generated + ')' : '');
		},
		getFieldPath: function (entry, i) {
			var me = this;

			var path = me.inherited(arguments);

			if (i == 'parent') {
				path = 'steroid/backend/datatype/form/DTRecordReference';
			}

			return path;
		},
		remove: function () {
			var me = this;

			me.owningContainer.removeItem(me);

			me.inherited(arguments);
		},
		setSubmitName: function (setName) {
			var me = this;

			if ((me.generated && !me.userChange)) {
				setName = false;
			}

			me.inherited(arguments, [setName]);
		}
	});
});