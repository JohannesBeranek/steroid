define([
	"dojo/_base/declare",
	"steroid/backend/dnd/Canvas",
	"steroid/backend/STStore",
	"dojo/store/Observable",
	"dojox/lang/functional"
], function (declare, Canvas, STStore, ObservableStore, langFunc) {

	return declare([Canvas], {
		accept: [],
		templateWatch: null,
		templateStore: null,
		templateHasBeenSet: false,
		currentTemplatePrimary: null,

		constructor: function () {
			this.templateWatch = null;
			this.templateStore = null;
		},
		getWidgetForeignRecordClass: function () {
			return 'RCPageArea';
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
		formInitialized: function () {
			var me = this;

			var templateField = me.form.getFieldByFieldName('template');

			me.templateWatch = templateField.watch('STValue', function (name, oldValue, newValue) {
				if (!me.templateHasBeenSet) {
					me.currentTemplatePrimary = parseInt(newValue, 10);
					me.templateHasBeenSet = true;
				}

				me.doStandBy();
				me.templateChanged(newValue);
			});

			var templateValue;

			// [JB 12.03.2013] workaround for race condition which could occur if template field has already been set when watch is installed
			if (templateValue = templateField.get('STValue')) {
				if (!me.templateHasBeenSet) {
					me.currentTemplatePrimary = parseInt(templateValue, 10);
					me.templateHasBeenSet = true;
				}
				me.templateChanged(templateValue);
			}
		},
		postCreate: function () {
			var me = this;

			me.templateStore = new ObservableStore(new STStore({
				backend: me.backend,
				classConfig: me.backend.getClassConfigFromClassName('RCTemplate')
			}));

			me.form.addInitListener(function () {
				me.formInitialized();
			});

			if (me.dndManager) {
				me.dndManager.registerContainer(me.backend.Clipboard.widgetContainer);
			}

			me.inherited(arguments);
		},
		templateChanged: function (value) {
			var me = this;

			var primary = parseInt(value, 10);

			if (primary) {
				dojo.when(me.templateStore.get(primary, {
					requestingRecordClass: 'RCPage'
				}), function (response) {
					if (response && response.items && response.items[0]) {
						var template = response.items[0];

						if (template) {
							if (template.widths) {
								me.setBaseWidths(template.widths);
							} // FIXME: handle cases where template.widths is not set - should this even happen?
							// no thanks

							if (me.templateHasBeenSet && me.currentTemplatePrimary !== template.primary) {
								me.setNewAreas(template['template:RCTemplateArea']);
							}
						}
					} else { // FIXME: comment under which circumstances this could happen!
						//NOFIX: no I will not list every possible error the server could have!
						me.backend.showError(response);
					}

					me.hideStandBy();
				});
			} // FIXME: handle cases where primary is not set, comment!
			// no thanks
		},
		setNewAreas: function (areas) {
			var me = this;

			var widgets = {};
			var oldItems = [];

			if (me.items && me.items.length) {
				for (var i = 0; i < me.items.length; i++) {
					var item = me.items[i];

					oldItems.push(item);

					if (item.inlineRecord.ownFields[item.inlineRecord.containerFieldName]._dt && item.inlineRecord.ownFields[item.inlineRecord.containerFieldName]._dt.items.length) {
						if (!widgets[item.record.key]) {
							widgets[item.record.key] = [];
						}

						var jlen = item.inlineRecord.ownFields[item.inlineRecord.containerFieldName]._dt.items.length;

						for (var j = 0; j < jlen; j++) {
							widgets[item.record.key].push(item.inlineRecord.ownFields[item.inlineRecord.containerFieldName]._dt.items[j]);
						}
					}
				}
			}

			for (var i in areas) {
				areas[i].primary = null;
				areas[i].area.primary = null;
				areas[i].area.id = null;
				areas[i].sorting = parseInt(i, 10);
			}

			me.set('value', areas);

			for (var i = 0; i < me.items.length; i++) {
				if (!me.items[i].inlineRecord.ownFields[me.items[i].inlineRecord.containerFieldName]._dt.items.length) {
					//FIXME: this will stop working once we use prefilled widgets
					for (var j in widgets) {
						if (j == me.items[i].record.key) {
							me.items[i].inlineRecord.ownFields[me.items[i].inlineRecord.containerFieldName]._dt.incomingValueCount = widgets[j];

							for (var k = widgets[j].length-1; k >= 0 ; k--) {
								if (!widgets[j][k]._beingDestroyed) {
									me.items[i].inlineRecord.ownFields[me.items[i].inlineRecord.containerFieldName]._dt.addItem(widgets[j][k], parseInt(widgets[j][k].record.sorting, 10));
								}
							}

							delete widgets[j];
							continue;
						}
					}
				}
			}

			var i = 0;

			if(widgets && me.items && me.items.length){ //no corresponding new area, so just stuff everything into the first one
				for (var j in widgets) {
					me.items[i].inlineRecord.ownFields[me.items[i].inlineRecord.containerFieldName]._dt.incomingValueCount = widgets[j];

					for (var k = widgets[j].length - 1; k >= 0; k--) {
						if (!widgets[j][k]._beingDestroyed) {
							me.items[i].inlineRecord.ownFields[me.items[i].inlineRecord.containerFieldName]._dt.addItem(widgets[j][k], parseInt(widgets[j][k].record.sorting, 10));
						}
					}

					delete widgets[j];
				}
			}

			for (var i = 0; i < oldItems.length; i++) {
				oldItems[i].remove();
			}
		},
		reset: function () {
			var me = this;

			me.currentTemplatePrimary = null;
			me.templateHasBeenSet = false;

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			delete me.templateStore;

			if (me.templateWatch) {
				me.templateWatch.unwatch();
			}

			delete me.templateWatch;

			me.inherited(arguments);
		}
	});
});
