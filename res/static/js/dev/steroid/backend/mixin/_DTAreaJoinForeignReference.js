define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dojo/dom-class",
	"steroid/backend/dnd/DndManager",
	"dojo/Deferred",
	"steroid/backend/dnd/WidgetPanel",
	"steroid/backend/dnd/Canvas",
	"dojo/dom-construct"
], function (declare, _DTFormFieldMixin, domClass, DndManager, Deferred, WidgetPanel, Canvas, domConstruct) {

	return declare([Canvas], {
//		style: 'border: 1px solid black',
		class: 'STDTAreaJoinForeignReference',

		postCreate: function () {
			var me = this;

			me.dndManager = me.backend.dndManager;

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.createWidgetPanel();
		},
		createWidgetPanel: function () {
			var me = this;

			me.fieldConf.widgets = me.backend.config.recordClasses.widget;

			me.widgetPanel = new WidgetPanel({
				dndManager: me.dndManager,
				widgetConf: me.fieldConf.widgets,
				backend: me.backend,
				submitName: me.submitName,
				owningRecordClass: me.owningRecordClass,
				mainClassConfig: me.mainClassConfig,
				parentWidget: me
			});

			domConstruct.place(me.widgetPanel.domNode, me.domNode, 'before');

			me.widgetPanel.startup();
		},
		destroy: function () {
			var me = this;

			//do not destroy widgetPanel here, as it will be destroyed by the detailPane

			me.dndManager.destroyRecursive();
			delete me.dndManager;

			me.inherited(arguments);
		}
	});
});
