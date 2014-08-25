define([
	"dojo/_base/declare",
	"steroid/backend/dnd/InlineEditableRecord",
	"steroid/backend/dnd/WidgetContainer",
	"dojo/store/Observable",
	"steroid/backend/STStore",
	"dojo/aspect",
	"dojo/_base/array",
	"dojo/Deferred",
	"dojo/DeferredList"
], function (declare, InlineEditableRecord, WidgetContainer, ObservableStore, STStore, aspect, array, Deferred, DeferredList) {

	return declare([InlineEditableRecord], {
		widgetContainer: null,
		STStore: null,
		submitName: null,
		isNew: false,
		dropWatch: null,
		skipLoadingDefault: false,
		itemsAlreadyExist: null,
		dndManager: null,
		itemChangeWatch: null,
		itemsMovedAspect: null,
		containerFieldName: 'area:RCElementInArea',
		parentWidget: null,

		constructor: function () {
			this.itemsAlreadyExist = [];
		},
		getContainer: function () {
			var me = this;

			return me.hasDroppable() ? me.ownFields[me.containerFieldName]._dt : null;
		},
		hasDroppable: function () {
			var me = this;

			return me.ownClassConfig.className == 'RCArea';
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			if (me.hasDroppable()) {
				me.ownFields[me.containerFieldName] = {};
			}
		},
		hookToDataTypeInstance: function (dt, fieldConf, fieldName) {
			var me = this;

			if (me.hasDroppable() && fieldName == me.containerFieldName) {
				me.itemsMovedAspect = aspect.after(dt, "itemsMoved", function (items) {
					me.itemsMovedAspect.remove();
					delete me.itemsMovedAspect;
					me.itemsMoved(items);
				});
			}

			me.inherited(arguments);
		},
		getFieldPath: function (entry, i) {
			var me = this, path;

			if (me.hasDroppable() && i == me.containerFieldName) {
				path = 'steroid/backend/dnd/WidgetContainer';
			} else {
				path = me.inherited(arguments);
			}

			return path;
		},
		getFieldConf: function (entry, i) {
			var me = this;

			var fieldConf = me.inherited(arguments);

			if (me.hasDroppable() && i == me.containerFieldName) {
				fieldConf = {
					style: 'width: 100% !important;min-height: 40px;',
					class: 'STInlineEditableWidget',
					accept: ['widget', 'area'],
					dndManager: me.dndManager,
					backend: me.backend,
					mainClassConfig: me.mainClassConfig,
					owningRecordClass: me.ownClassConfig.className,
					form: me,
					parentWidget: me.parentWidget,
					readOnly: me.readOnly
				};
			}

			return fieldConf;
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.STStore = new ObservableStore(new STStore({ // store used to get defaults from server
				backend: me.backend,
				classConfig: me.ownClassConfig
			}));

			me.addValueSetListenerOnce(function () {
				if (me.hasDroppable() && me.itemsAlreadyExist) {

					for (var i = 0; i < me.itemsAlreadyExist.length; i++) {
						me.ownFields[me.containerFieldName]._dt.addItem(me.itemsAlreadyExist[i], i);
					}

					delete me.itemsAlreadyExist;
				}

				me.itemsMoved();
			});
		},
		itemsMoved: function () {
//			return this.dropContainer.items;
		},
		getContainedItems: function () {
			var me = this;

			if (me.hasDroppable()) {
				return me.ownFields[me.containerFieldName]._dt.items;
			}

			return null;
		},
		_setValueAttr: function (value) {
			var me = this;

			if (!value) {
				me.isNew = true;

				value = me.STStore.get(null, {
					language: me.backend.config.system.languages.current.primary
				});
			}

			me.inherited(arguments, [value]);
		},
		getFieldsToHide: function () {
			var me = this;

			var fields = me.inherited(arguments);

			if (me.mainClassConfig.className != 'RCTemplate' && me.owningRecordClass != 'RCElementInArea' && me.ownClassConfig.className == 'RCArea') {
				fields.push('title');
			}

			return fields;
		},
		destroy: function () {
			var me = this;

			delete me.STStore;

			me.inherited(arguments);
		}
	});
});
