define([
	"dojo/_base/declare",
	"steroid/backend/dnd/Draggable",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/dnd/MenuMenuItem"
], function (declare, Draggable, i18nRC, MenuItem) {

	return declare([Draggable], {

		backend: null,
		submitName: null,
		owningRecordClass: null,
		parentContainer: null,
		type: 'SourceMenuItem',
		readOnly: false,

		postCreate: function () {
			var me = this;

			me.set('content', i18nRC.RCMenuItem._description);

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.set('title', i18nRC['RCMenuItem_name']);
		},
		getItem: function (container) {
			var me = this;

			var conf = me.backend.getClassConfigFromClassName('RCMenuItem');

			var item = new MenuItem({
				backend: me.backend,
				mainClassConfig: me.mainClassConfig,
				ownClassConfig: me.backend.getClassConfigFromClassName('RCMenuItem'),
				dndManager: me.dndManager,
				type: 'MenuItem' + container.form.id
			});

			item.startup();

			item.set('value', {
				language: me.backend.config.system.languages.current,
				creator: me.backend.config.User.values,
				showInMenu: conf.formFields.showInMenu['default']
			});

			return item;
		}
	});
});
