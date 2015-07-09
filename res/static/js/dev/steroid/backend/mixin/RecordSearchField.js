define([
	"dojo/_base/declare",
	"dojo/_base/json",
	"dijit/form/FilteringSelect",
	"dojo/_base/lang",
	"dijit/form/Button",
	"dojo/i18n!steroid/backend/nls/RecordSearchField",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/ModuleContainer",
	"steroid/backend/mixin/_ModuleContainerList",
	"dojo/window",
	"dijit/Dialog",
	"dojo/dom-style",
	"dojo/dom-construct",
	"dojo/aspect",
	"dojo/dom-attr",
	"dojo/dom-class",
	"dojo/when"
], function (declare, json, FilteringSelect, lang, Button, i18n, i18nRC, ModuleContainer, _ModuleContainerList, win, Dialog, domStyle, domConstruct, aspect, domAttr, domClass, when) {

	return declare([FilteringSelect], {
		style: 'padding: 0; margin: 0;',
		searchAttr: '_title',
		listButton: null,
		moduleContainer: null,
		recordSelector: null,
		mainClassConfig: null,
		fieldClassConfig: null,
//		nullItem: {
//			primary:0,
//			_title:' --- '
//		},
		autoComplete: false,
		minKeyCount: 3,
		hasDownArrow: false,
		moduleCloseAspect: null,
		moduleSelectAspect: null,
		moduleApplySelectAspect: null,
		item: null,
		recordSelector: null,
		// queryExpr: "${0}*", // default value, keep this here for documentation purposes


		// override search method, count the input length
		_startSearch: function (/*String*/key) {
			var me = this;

			if (!key || key.length < me.minKeyCount) {
				me.closeDropDown();
				return;
			}

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.query.isSearchField = true;

			me.checkListButtonEnabled();
		},
		addListButton: function () {
			var me = this;

			me.listButton = new Button({
				label: i18n.listButtonTitle,
				"class": 'browseButton',
				disabled: me.readOnly,
				onClick: function () {
					me.openList();
				}
			});

			me.listButton.startup();

			domConstruct.place(me.listButton.domNode, me.domNode, 'after');

			if (!me.fieldClassConfig) {
				console.error('User seems to have no permission required for field ' + me.fieldName);
			}

			domClass.add(me.domNode, me.fieldClassConfig.className);
		},
		openList: function () {
			var me = this;

			if (me.moduleContainer) {
				return;
			}

			var customContainer = declare([ModuleContainer, _ModuleContainerList], {
				classConfig: me.fieldClassConfig,
				style: 'width: 100%; height:100%',
				isRecordSelector: true,
				backend: me.backend,
				hasMultiple: me.hasMultiple,
				baseQuery: {
					exclude: me.getExcludeFilter(),
					mainRecordClass: me.mainClassConfig.className,
					requestFieldName: me.fieldName,
					requestingRecordClass: me.owningRecordClass
				}
			});

			me.moduleContainer = new customContainer({});

			me.moduleContainer.startup();

			// [JB 11.02.2013] using dojo/when instead of deferred/then fixes possible race condition
			when(me.moduleContainer.listPane.loadInitDef, function () {
				var screenWidth = win.getBox();

				me.moduleContainer.inDialog = new Dialog({
					title: i18nRC[me.fieldClassConfig.className + '_name'] || me.fieldClassConfig.className,
					autofocus: false,
					style: 'width: 80%;height:80%',
					content: me.moduleContainer
				});

				domStyle.set(me.moduleContainer.inDialog.containerNode, {
					height: '95%',
					padding: 0
				});

				me.moduleContainer.inDialog.startup();
				me.moduleContainer.inDialog.show();
			});

			me.moduleCloseAspect = aspect.after(me.moduleContainer, 'hasClosed', function () {
				me.moduleContainer.inDialog.destroyRecursive();

				delete me.moduleContainer;
			});

			if (me.hasMultiple) {
				me.moduleApplySelectAspect = aspect.after(me.moduleContainer, 'applySelection', function (records) {
					me.moduleContainer.doStandBy();

					for (var i in records) {
						me.itemSelected(records[i]);
					}

					me.moduleContainer.hideStandBy();
					me.moduleContainer.close();
				});
			} else {
				me.moduleSelectAspect = aspect.after(me.moduleContainer, 'recordSelected', function (records) {
					records[0].then(function (response) {
						if (response && response.items && response.items[0]) {
							var item = response.items[0];

							me.set('item', item);
						} else {
							me.backend.showError(response);
						}

						me.moduleContainer.close();
					});
				});
			}
		},
		getExcludeFilter: function () {
			var me = this, exclude; // [JB 11.02.2013] no need to double-set value for exclude, keeping it at undefined at start is okay

			if (me.recordSelector && me.hasMultiple) {
				exclude = me.recordSelector.get('STValue');
			} else if (me.item && me.item.primary && !me.fieldClassConfig.isHierarchic) {
				exclude = [ me.item.primary ];
			} else {
				exclude = null;
			}

			return json.toJson(exclude);
		},
		_getQueryString: function (/*String*/ text) { // FilteringSelect does regExp stuff which we don't need
			return text;
		},
		itemSelected: function (item) { // this is what the recordselector hooks up to
			var me = this;

			if (me.hasMultiple) {
				me.set('item', me.nullItem);
			} else {
				if (item && typeof item._liveStatus !== 'undefined') {
					domClass.replace(me.domNode, ('STLiveStatus_' + item._liveStatus), 'STLiveStatus_0 STLiveStatus_1 STLiveStatus_2');
				} else {
					domClass.remove(me.domNode, 'STLiveStatus_0 STLiveStatus_1 STLiveStatus_2');
				}
			}

			return item;
		},
		isValid: function () {
			var me = this;

			if (me.hasMultiple) { // FIXME: comment: what about not required single select?
				return true;
			}

			return me.inherited(arguments);
		},
		_openResultList: function (/*Object*/ results, /*Object*/ query, /*Object*/ options) {
			var me = this;

//			if (!me.required) {
//				results.unshift(me.nullItem);
//			}

			me.inherited(arguments, [results, query, options]);
		},
		onChange: function (newValue) {
			var me = this;

			// FIXME: comment: what about newValue? how does me.item get set here?
			me.itemSelected(me.item);

			if (!me.hasMultiple) {
				if (me.item) {
					me.query.exclude = json.toJson([ parseInt(me.item.primary, 10)]);
				} else if (me.query.exclude) {
					delete me.query.exclude;
				}

			}
		},
		_setDisabledAttr: function (disabled) {
			var me = this;

			me.inherited(arguments);

			me.checkListButtonEnabled();

			me.setSubmitName(false);
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.checkListButtonEnabled();
		},
		checkListButtonEnabled: function () {
			var me = this;

			if (!me.listButton) { // will re-check on startup
				return;
			}

			me.listButton.set('disabled', me.disabled || me.readOnly);
		},
		_setBlurValue: function () {
			//overrides function in dijit/form/_AutoCompleterMixin


			var newvalue = this.get('displayedValue');
			var pw = this.dropDown;
			if (pw && (newvalue == pw._messages["previousMessage"] || newvalue == pw._messages["nextMessage"])) {
				this._setValueAttr(this._lastValueReported, true);
			} else if (typeof this.item == "undefined") {
				;
				// Update 'value' (ex: KY) according to currently displayed text
				this.item = null;

				// new code start

				this.set('value', null);
//				this.set('displayedValue', newvalue);

				// new code end
			} else {
				if (this.value != this._lastValueReported) {
					this._handleOnChange(this.value, true);
				}
				this._refreshState();
			}
		},
		_setValueAttr: function (/*String*/ value, /*Boolean?*/ priorityChange, /*String?*/ displayedValue, /*item?*/ item) {
			var me = this;

			// FIXME: why do we need this?
			if (!value) {
				value = null;

				// fix so FilteringSelect does not trigger reverse store lookup
				if (item === undefined) {
					item = null;
				}
			}

			if (item && item.title && !item._title) {
				item._title = item.title;
			}

			me.inherited(arguments, [value, priorityChange, displayedValue, item]);

			// FIXME: why do we need this?
			if (!value) {
				domAttr.set(me.valueNode, 'value', '');
			}
		},
		_setItemAttr: function (/*item*/ item, /*Boolean?*/ priorityChange, /*String?*/ displayedValue) {
			var me = this;

			if (item && item.title && !item._title) { // FIXME: allow empty string title
				item._title = item.title;
			}

			me.inherited(arguments, [item, priorityChange, displayedValue]);

			if (!domAttr.get(me.valueNode, 'value')) {
				domAttr.set(me.valueNode, 'value', '');
			}
		},
		setSubmitName: function (setName) {
			var me = this;

			domAttr.set(me.valueNode, 'name', setName && !me.get('disabled') ? me.submitName : '');
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			if (domAttr.get(me.valueNode, 'name')) {
				me.setSubmitName(true);
			} // FIXME: else: unset submitName?
			// NOFIX: no.
		},
		reset: function () {
			var me = this;

			// [JB 11.02.2013] changed =null to delete
			delete me.query.exclude;
		},
		destroy: function () {
			var me = this;

			if (me.moduleContainer) {
				me.moduleContainer.destroyRecursive();
				delete me.moduleContainer;
			}

			if (me.moduleCloseAspect) {
				me.moduleCloseAspect.remove();
				delete me.moduleCloseAspect;
			}

			if (me.moduleApplySelectAspect) {
				me.moduleApplySelectAspect.remove();
				delete me.moduleApplySelectAspect;
			}

			if (me.moduleSelectAspect) {
				me.moduleSelectAspect.remove();
				delete me.moduleSelectAspect;
			}

			delete me.recordSelector;

			domConstruct.destroy(me.valueNode);
			delete me.valueNode;

			delete me.item;
		}
	});
});