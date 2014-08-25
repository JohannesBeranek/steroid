define([
	"dojo/_base/declare",
	"steroid/backend/dnd/MenuItemContainer",
	"steroid/backend/dnd/DndManager",
	"dojo/dom-construct",
	"steroid/backend/dnd/MenuItemPanel",
	"steroid/backend/STStore",
	"dojo/store/Observable",
	"dojo/Deferred",
	"steroid/backend/dnd/MenuMenuItem",
	"dojo/_base/lang",
	"dojox/lang/functional",
	"dojo/i18n!steroid/backend/nls/Menu"
], function (declare, MenuItemContainer, DndManager, domConstruct, MenuItemPanel, STStore, ObservableStore, Deferred, MenuItem, lang, langFunc, i18nMenu) {

	return declare([MenuItemContainer], {
		itemPanel: null,
		STStore: null,
		pageLoadDef: null,
		rootPage: null,
		currentlySettingValue: false,
		isSubItem: false, // FIXME: remove "isSubItem"
		dndManager: null,
		rootWach: null,
		backend: null,
		valueSetCount: 0,
		rootPageSet: false,
		settingInitialValue: true,
		level: 0,
		initialToggle: true,

		constructor: function () {
			this.rootWatch = null;
			this.dndManager = null;
			this.STStore = null;
		},
		postCreate: function () {
			var me = this;

			// FIXME: move to subclass
			if (!me.isSubItem) {
				me.dndManager = me.backend.dndManager;
			}

			me.level = me.level || me.form.level || 1;

			me.STStore = new ObservableStore(new STStore({
				backend: me.backend,
				classConfig: me.backend.getClassConfigFromClassName('RCPage'),
				isHierarchic: true
			}));

			// FIXME: move to subclass
			if (!me.isSubItem) {
				me.addValueSetListenerOnce(function () {
					me.setupRootFieldWatch();
				});
			} else {
				me.set('open', false);
			}

			me.inherited(arguments);
		},
		_setOpenAttr: function (value) {
			var me = this;

			if (value && me.isSubItem && !me.initialToggle) { // initialToggle prevents loading everything on initial opening of titlepane
				me.addValueSetListenerOnce(function () {
					me.form.toggleChildren();
				});
			}

			me.initialToggle = false;

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			// FIXME: move to subclass
			if (!me.isSubItem) {
				me.createItemPanel();
			}

			me.initComplete();
		},
		setupRootFieldWatch: function () {
			var me = this;

			var rootField = me.form.getFieldByFieldName('root');

			me.rootWatch = rootField.watch('STValue', function (name, oldValue, newValue) {
				me.setRootPage(newValue);
			});
		},
		populate: function (value) {
			var me = this;

			if (!value || !langFunc.keys(value).length || (lang.isArray(value) && !value.length) && (!me.currentlySettingValue || me.settingInitialValue)) {
				me.valueComplete();
				me.settingInitialValue = false;
				return;
			}

			me.incomingValueCount = langFunc.keys(value).length;

			value.sort(function (a, b) {
				a = a.sorting;
				b = b.sorting;

				return a < b ? -1 : (a > b ? 1 : 0);
			});


			for (var i in value) {
				var item = me.createItem(false, false);

				item.ownIndexInParent = value[i].sorting;
				item.valueToBeSet = value[i];

				me.addItem(item, value[i].sorting);

				item.addInitListener(function (menuItem) {
					menuItem.set('value', menuItem.valueToBeSet);
				});
			}
		},
		setRootPage: function (root) {
			var me = this;

			if (!root) {
				var toBeRemoved = [];

				for (var i = 0; i < me.items.length; i++) {
					if (me.items[i].generated && !me.items[i].userChange) {
						toBeRemoved.push(me.items[i]);
					}
				}

				for (var i = 0; i < toBeRemoved.length; i++) {
					toBeRemoved[i].destroyRecursive();
				}

				return;
			}

			if (typeof root === 'object' && root.primary) {
				root = root.primary;
			}

			me.form.doStandBy();

			// FIXME: use .then when possible to avoid unnecessary deferred wrapping
			dojo.when(me.STStore.query({}, {
				parent: {
					primary: root
				}
			}), function (items) {
				me.rootPage = root;

				me.form.hideStandBy();

				me.addPages(items);
			});
		},
		setRecordClassPages: function (recordClass) {
			var me = this;

			me.form.doStandBy();

			dojo.when(me.STStore.query({
				filter: [
					{"filterFields": ["domainGroup"], "filterValue": me.backend.config.system.domainGroups.current.primary, "filterType": "recordReference"}
				]
			}, { recordClass: recordClass, hierarchic: false }), function (response) {
				me.form.hideStandBy();

				var pages = [];

				for (var i = 0; i < response.length; i++) {
					pages.push(response[i].page);
				}

				me.addPages(pages);
			});
		},
		addPages: function (pages) {
			var me = this;

			var conf = me.backend.getClassConfigFromClassName('RCMenuItem');

			var newItems = [];

			for (var i = 0; i < me.items.length; i++) {
				if (me.items[i].generated && !me.items[i].userChange) {
					me.items[i].destroyRecursive();
				}
			}

			for (var i = 0; i < pages.length; i++) {
				var pageExists = false;

				for (var j = 0; j < me.items.length; j++) {
					if (!me.items[j].generated && me.items[j].record && me.items[j].record.page && (parseInt(me.items[j].record.page.primary, 10) == parseInt(pages[i].primary, 10))) {
						pageExists = true;
					}
				}

				if (pageExists) {
					continue;
				}

				var item = me.createItem(true, false);

				item.set('value', {
					page: pages[i],
					subItemsFromPage: conf.formFields.subItemsFromPage['default'], // [JB 14.02.2013] "default" is a js keyword (switch/case)
					showInMenu: conf.formFields.showInMenu['default'],
					language: me.backend.config.system.languages.current,
					creator: me.backend.config.User.values,
					sorting: (i + 1) * 256
				});

				item.ownIndexInParent = i;

				newItems.push(item);
			}

			me.incomingValueCount = newItems.length;

			for (var i = 0; i < newItems.length; i++) {
				me.addItem(newItems[i], me.getDropIndex(true));
			}

			me.resortItems();
		},
		resortItems: function () {
			var me = this;

			var generatedItems = [];
			var userItems = [];

			for (var i = 0; i < me.items.length; i++) {
				if (!me.items[i].generated || me.items[i].userChange) {
					userItems.push(me.items[i]);
				} else {
					generatedItems.push(me.items[i]);
				}
			}

			for (var i = 0; i < userItems.length; i++) {
				var maxGeneratedIndex = null;

				for (var j = 0; j < generatedItems.length; j++) {
					if (generatedItems[j].record.sorting < userItems[i].record.sorting) {
						maxGeneratedIndex = generatedItems[j].ownIndexInParent + 1;
					}
				}

				if (maxGeneratedIndex !== null) {
					me.addItem(userItems[i], maxGeneratedIndex);
				}
			}
		},
		createItem: function (generated, isNew) {
			var me = this;

			var item = new MenuItem({
				backend: me.backend,
				mainClassConfig: me.mainClassConfig,
				ownClassConfig: me.backend.getClassConfigFromClassName('RCMenuItem'),
				dndManager: me.dndManager,
				generated: generated,
				isNew: isNew,
				type: 'MenuItem' + me.form.id,
				level: me.level + 1
			});

			item.startup();

			return item;
		},
		createItemPanel: function () {
			var me = this;

			me.itemPanel = new MenuItemPanel({
				style: 'border: 1px solid grey;padding:5px; width: auto;min-width:100px;max-width:200px; height: auto;float: left;',
				dndManager: me.dndManager,
				backend: me.backend,
				submitName: me.submitName,
				owningRecordClass: me.owningRecordClass,
				mainClassConfig: me.mainClassConfig,
				parentContainer: me
			});

			domConstruct.place(me.itemPanel.domNode, me.domNode, 'before');

			me.itemPanel.startup();
		},
		_setValueAttr: function (value) {
			var me = this;

			if (me.valueSet) {
				me.reset();
			}

			if (me.currentlySettingValue) {
				me.addValueSetListenerOnce(function () {
					me.set('value', value);
				});
			} else {
				// FIXME: move to subclass
				if (!me.isSubItem) {
					var newVal = [];

					var valArr = [];

					for (var i in value) {
						valArr.push(value[i]);
					}

					me.constructHierarchyFromValue(valArr, newVal);

					value = newVal;
				}

				var valueCount = 0;

				if (value && value.length) {
					valueCount = value.length;
				}

				me.set('title', (i18nMenu.level + me.level + ' (' + valueCount + i18nMenu.elements + ')'));

				me.currentlySettingValue = true;

				me.populate(value);

				me.addValueSetListenerOnce(function () {
					me.set('STValue', me.STSetValue(value));
				});
			}
		},
		valueComplete: function () {
			var me = this;

			if (!me.rootPageSet && !me.isSubItem && !me.settingInitialValue) {

				var rootField = me.form.getFieldByFieldName('root');

				me.setRootPage(rootField.get('value'));
				me.rootPageSet = true;
				return;
			}

			me.currentlySettingValue = false;

			me.inherited(arguments);
		},
		constructHierarchyFromValue: function (value, newVal) {
			var me = this;

			var done = [];

			var stopper = 0;

			for (var i = 0; i < value.length; i++) {
				stopper++;
				var item = value[i];

				if (!item.parent) {
					done.push(item);
					newVal.push(item);
				} else if (item.parent && item.parent.primary) {
					var parentFound = false;

					for (var j = 0; j < done.length; j++) {
						if (item.parent.primary == done[j].primary) {
							var parent = done[j];

							parentFound = true;

							if (!(parent['parent:RCMenuItem'])) {
								parent['parent:RCMenuItem'] = [];
							}

							parent['parent:RCMenuItem'].push(item);
							done.push(item);
						}
					}

					if (!parentFound) {
						value.push(item);
					}
				}
			}
		},
		destroy: function () {
			var me = this;

			// FIXME: move to subclass
			if (me.itemPanel) {
				me.itemPanel.destroyRecursive();
			}

			if (me.rootWatch) {
				me.rootWatch.unwatch();
			}

			delete me.rootWatch;
			delete me.currentlySettingValue;
			delete me.incomingValueCount;
			delete me.rootPage;
			delete me.isSubItem;
			delete me.STStore;
			delete me.backend;
			delete me.dndManager;

			me.inherited(arguments);
		},
		STSetValue: function (value) {
			var me = this;

			value = typeof value == 'undefined' || value === '' ? null : value;

			if (typeof me.originalValue == 'undefined') {
				me.originalValue = value;
			}

			return value;
		}
	});
});
