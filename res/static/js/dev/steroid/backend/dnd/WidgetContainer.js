define([
	"dojo/_base/declare",
	"steroid/backend/dnd/DropContainer",
	"steroid/backend/dnd/Widget",
	"dojox/lang/functional",
	"dojo/_base/lang",
	"dojo/query",
	"dojo/NodeList-traverse"
], function (declare, DropContainer, Widget, langFunc, lang, query, NodeList) {

	return declare([DropContainer], {
		accept: [], // FIXME: this might be reused across all instances - if this is intended behaviour, comment!
		containerType: null,
		owningRecordClass: null,
		changeWatch: 1,
		parentWidget: null,
		class: 'STWidgetContainer',
		waitForWidgetValueSet: null,

		drop: function (item) {
			var me = this;

			dojo.when(item.getWidget(me), function (widget) {
				widget.addValueSetListenerOnce(function () {
					me.addItem(widget, me.getDropIndex(false, item));
				});
			});
		},
		addItem: function (item, dropIndex) {
			var me = this;

			item = me.inherited(arguments);

			item.setParent(me.parentWidget);

			return item;
		},
		getDirtyNess: function () {
			var me = this;

			var itemDirty = me.getItemDirtyNess();

			if (itemDirty > 0) {
				return itemDirty;
			}

			return me.inherited(arguments);
		},
		getWidgetForeignRecordClass: function () {
			return 'RCElementInArea';
		},
		getInlineSubstitutionFieldName: function () {
			return 'element';
		},
		getWidgetType: function (value) {
			return value.class === 'RCArea' ? 'area' : 'widget';
		},
		getInlineRecordClassName: function (value) {
			return value.class;
		},
		dropValid: function (widget) {
			var me = this;

			return me.inherited(arguments) && !me.findParent(widget.domNode);
		},
		findParent: function (targetNode) {
			var me = this;

			// FIXME: under which circumstances should targetNode be invalid? comment!
			if (targetNode && me.domNode) {
				// FIXME: as id should be unique on whole page, why query for id only on parents of domNode? If id is not unique, this is a bug! 
				var parent = query(me.domNode).parents('#' + targetNode.id);

				return parent.length;
			}

			return 0;
		},
		_setValueAttr: function (value) {
			var me = this;

			// TODO: when changing value to array, this can be changed to just use .length!
			me.incomingValueCount = langFunc.keys(value).length;
			me.waitForWidgetValueSet = [];

			if (!me.incomingValueCount) {
				me.valueComplete();

				me.originalItems = [];
			} else {
				var foreignClassConfig = me.backend.getClassConfigFromClassName(me.getWidgetForeignRecordClass());

				var inlineSubstitutionFieldName = me.getInlineSubstitutionFieldName();

				// TODO: as i is only used for sorting, can we change this to array instead of object?
				for (var i in value) {
					var type = me.getWidgetType(value[i]);

					var inlineClassConfig = me.backend.getClassConfigFromClassName(me.getInlineRecordClassName(value[i]));

					var isNew = !(value[i] && value[i].primary && parseInt(value[i].primary, 10));

					// TODO: do we really need to reduplicate all these values?
					var widget = new Widget({
						backend: me.backend,
						ownClassConfig: foreignClassConfig,
						inlineClassConfig: inlineClassConfig,
						inlineSubstitutionFieldName: inlineSubstitutionFieldName,
						inlineRecordValue: value[i],
						owningRecordClass: me.owningRecordClass,
						mainClassConfig: me.mainClassConfig,
						submitName: me.submitName,
						dndManager: me.dndManager,
						type: type,
						isNew: isNew,
						parentWidget: me.parentWidget,
						indexToBeSet: i,
						valueToBeSet: value[i],
						readOnly: inlineClassConfig.isDependency
					});

					widget.startup();

					widget.addInitListener(function (initializedWidget) {
						initializedWidget.set('value', initializedWidget.valueToBeSet);

						initializedWidget.addValueSetListenerOnce(function (widgetWithValue) {
							me.waitForWidgetValueSet.push(initializedWidget);

							if (me.waitForWidgetValueSet.length == me.incomingValueCount) {
								me.waitForWidgetValueSet.sort(function (a, b) {
									var idxA = foreignClassConfig.sortingField ? a.record[foreignClassConfig.sortingField] : a.indexToBeSet;
									var idxB = foreignClassConfig.sortingField ? b.record[foreignClassConfig.sortingField] : b.indexToBeSet;

									return idxA == idxB ? 0 : idxA > idxB ? 1 : -1;
								});

								for (var i = 0, item; item = me.waitForWidgetValueSet[i]; i++) {
									me.addItem(item, i);
								}
							}
						});
					});
				}
			}
		},
		setItemsReadOnly: function (readOnly) {
			// override for widgets so we can have readOnly areas with non-readOnly widgets inside
		},
		dragOut: function (e) {
			var me = this;

			me.inherited(arguments);

			// FIXME: comment why parentWidget / parrentWidget.currentContainer might be falsy!
			// COMMENT: because it might not have one?
			if (me.parentWidget && me.parentWidget.currentContainer) {
				me.parentWidget.currentContainer.dragOut();
			}
		},
		_getStateAttr: function () {
			var me = this;

			// FIXME: either change to using temp var for length, or comment why items.length may change in loop!
			for (var i = 0; i < me.items.length; i++) {
				var itemState = me.items[i].get('state');

				if (itemState !== '') {
					return itemState;
				}
			}

			return '';
		},
		destroy: function () {
			var me = this;

			delete me.parentWidth;

			me.inherited(arguments);
		}
	});
});
