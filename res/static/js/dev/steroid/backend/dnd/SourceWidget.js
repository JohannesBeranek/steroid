define([
	"dojo/_base/declare",
	"steroid/backend/dnd/Draggable",
	"steroid/backend/mixin/_hasInitListeners",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/dnd/Widget"
], function (declare, Draggable, _hasInitListeners, i18nRC, Widget) {

	return declare([Draggable, _hasInitListeners], {

		widgetConf: null,
		type: null,
		backend: null,
		submitName: null,
		owningRecordClass: null,
		parentWidget: null,
		readOnly: false,

		constructor: function () {
			this.widgetConf = null;
		},
		postCreate: function () {
			var me = this;

			me.setupContent();

			me.inherited(arguments);
		},
		setupContent: function () {
			var me = this;

			var i18n = me.widgetConf.i18nExt || i18nRC;

			me.set('title', i18n[me.widgetConf.className + '_name']);
			me.set('content', i18n[me.widgetConf.className]['widget_description']);
		},
		getWidget: function (container) {
			var me = this;

			var foreignRecordClass = null;

			switch (container.owningRecordClass) {
				case 'RCPage':
					foreignRecordClass = 'RCPageArea';
					break;
				case 'RCTemplate':
					foreignRecordClass = 'RCTemplateArea';
					break;
				case 'RCRTE':
					foreignRecordClass = 'RCRTEArea';
					break;
				case 'RCArea':
					foreignRecordClass = 'RCElementInArea';
					break;
			}

			var foreignClassConfig = me.backend.getClassConfigFromClassName(foreignRecordClass);

			var inlineClassConfig = me.backend.getClassConfigFromClassName(me.widgetConf.className);

			var inlineSubstitutionFieldName = container.owningRecordClass == 'RCArea' ? 'element' : 'area';

			var widget = new Widget({
				backend: me.backend,
				ownClassConfig: foreignClassConfig,
				inlineClassConfig: inlineClassConfig,
				inlineSubstitutionFieldName: inlineSubstitutionFieldName,
				owningRecordClass: container.owningRecordClass,
				mainClassConfig: me.mainClassConfig,
				submitName: me.submitName,
				dndManager: me.dndManager,
				type: me.type,
				isNew: true,
				i18nExt: me.i18nExt,
				parentWidget: container.parentWidget
			});

			widget.startup();

			return widget;
		}
	});
});
