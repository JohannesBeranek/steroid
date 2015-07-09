define([
	"dojo/_base/declare",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/DetailPane",
	"dojo/i18n!steroid/backend/nls/Backend",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dijit/layout/BorderContainer",
	"dijit/layout/ContentPane",
	"steroid/backend/ServerComm",
	"steroid/backend/Form",
	"dojox/layout/TableContainer",
	"dijit/layout/AccordionContainer",
	"dojox/form/BusyButton",
	"dijit/form/Button",
	"dojox/lang/functional",
	"dojo/Deferred",
	"dojo/dom-style",
	"dojo/dom-geometry",
	"dojo/_base/lang",
	"dojo/dom-construct",
	"dojo/hash",
	"dojo/aspect",
	"dijit/MenuBar",
	"dijit/MenuBarItem",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"dijit/form/DateTextBox",
	"dijit/form/TimeTextBox",
	"steroid/backend/ModuleMenuItem",
	"dojo/_base/fx",
	"dojo/_base/array",
	"steroid/backend/ReferenceDialog",
	"steroid/backend/YesNoDialog",
	"steroid/backend/STStore",
	"dojo/store/Observable",
	"steroid/backend/mixin/_hasStandBy",
	"steroid/backend/mixin/_hasInitListeners",
	"dojo/dom-class"
], function (declare, i18nRC, i18nDetailPane, i18nBackend, i18nErr, BorderContainer, ContentPane, STServerComm, STForm, TableContainer, AccordionContainer, BusyButton, Button, langFunc, Deferred, domStyle, domGeom, lang, domConstruct, hash, aspect, MenuBar, MenuBarItem, DropDownMenu, PopupMenuBarItem, DateTextBox, TimeTextBox, ModuleMenuItem, fx, array, ReferenceDialog, YesNoDialog, STStore, ObservableStore, _hasStandBy, _hasInitListeners, domClass) {

	return declare([BorderContainer, _hasStandBy, _hasInitListeners], {

		parentContainer: null,
		classConfig: null,
		record: null,
		recordPrimary: null,
		formWatch: null,
		actionButtons: null,
		pubdateItems: null,
		closingDef: null,
		closeDialog: null,
		STStore: null,
		formContainer: null,
		suspendDirtyCheck: true,
		form: null,
		useFieldSets: true,
		readOnly: false,

		constructor: function () {
			this.parentContainer = {};
			this.classConfig = {};
			this.record = {};
			this.actionButtons = {};
			this.pubdateItems = {};
		},
		postCreate: function () {
			var me = this;

			me.standByNode = me.domNode;

			me.inherited(arguments);

			me.init();

		},
		init: function () {
			var me = this;

			me.STStore = new ObservableStore(new STStore({
				backend: me.backend,
				classConfig: me.classConfig
			}));

			var mixins = [STForm];

			if (me.classConfig.customJS && me.classConfig.customJS.indexOf('form') !== -1) {
				var path;

				// TODO: unify
				if (me.classConfig.isCore) {
					path = "steroid/backend/form/" + me.classConfig.className;
				} else {
					path = me.classConfig.classLocation + "/res/static/js/form/" + me.classConfig.className;
				}

				require([path], function (customForm) {
					mixins.push(customForm);

					me.createForm(mixins);
				});
			} else {
				me.createForm(mixins);
			}
		},
		createForm: function (form) {
			var me = this;

			var form = declare(form, {});

			me.form = new form({
				encType: 'multipart/form-data',
				action: '',
				backend: me.backend,
				detailPane: me,
				method: 'post',
				style: 'background-color: transparent;',
				isFilterPane: me.isFilterPane,
				ownClassConfig: me.classConfig,
				mainClassConfig: me.classConfig,
				useFieldSets: me.useFieldSets,
				onSubmit: function (event) {
					event.preventDefault();
					return false;
				}
			});

			if (!me.isFilterPane) {
				me.formWatch = me.form.watch('STValue', function (name, oldValue, newValue) {
					if (!me.backend.suspendValueWatches && !me.form.currentlySettingValue) {
						me.form.setSubmitName(me.form.getDirtyNess() > 0);
						me.setButtonStates();

						if (me.classWithPubDate()) {
							me.setPubDateStates();
						}
					}
				});

				me.form.addValueSetListener(function () {
					me.record = me.form.record;
					me.recordPrimary = me.record ? me.record.primary : null;

					if (me.classWithPubDate()) {

						if (me.record.publishDate) {
							me.changePubdateMenu('Publish', dojo.date.locale.format(new Date(me.record.publishDate)));
						}

						if (me.record.unpublishDate) {
							me.changePubdateMenu('Unpublish', dojo.date.locale.format(new Date(me.record.unpublishDate)));
						}

					}

					me.setButtonStates();

					if (me.classWithPubDate()) {
						me.setPubDateStates();
					}
				});
			}

			me.setUpFormContainer();

			if (me.isFilterPane) {
				me.form.addInitListener(function () {
					me.setFakeFilterRecord();
				});
			} else {
				me.setUpRecordLabel();
			}

			me.form.addValueSetListener(function () {
				me.backend.suspendValueWatches = false;
//				me.setButtonStates();
			});

			if (me.i18nExt) {
				me.set('title', me.i18nExt[me.classConfig.className + '_name']);
			} else {
				me.set('title', i18nRC[me.classConfig.className + '_name'] || me.classConfig.className);
			}

			me.initComplete();
		},
		setPubDateStates: function () {
			me = this;

			//me.actionButtons['publishRecord'].on('mouseup', function() {
			me.actionButtons['nowPublish'].set('disabled', !me.recordCanPublish(me.formValid, me.formDirty, me.record));
			//});

			//me.actionButtons['hideRecord'].on('mouseup', function() {
			me.actionButtons['nowUnpublish'].set('disabled', !me.recordCanHide(me.record));
			//});

		},
		changePubdateMenu: function (dovar, date) {
			var me = this;
			delayButton = me.actionButtons['delay' + dovar];
			timeItem = me.pubdateItems['time' + dovar];
			dateItem = me.pubdateItems['date' + dovar];
			pubdateItem = me.pubdateItems['existing' + dovar];

			delayButton.domNode.style.display = "none";
			timeItem.domNode.style.display = "none";
			dateItem.domNode.style.display = "none";
			pubdateItem.domNode.style.display = "table-row";
			pubdateItem.set('label', date);

		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			if (me.form) {
				me.form.set('readOnly', readOnly);
			}

			me.setButtonStates();
		},
		setFakeFilterRecord: function () {
			var me = this;

			var val = {};

			for (var fieldName in me.classConfig.filterFields) {
				val[fieldName] = me.classConfig.filterFields[fieldName]['default'];
			}

			me.loadRecord(val);
		},
		setUpRecordLabel: function () {
			var me = this;

			me.recordClassLabel = domConstruct.create('div', { innerHTML: me.isFilterPane ? i18nDetailPane.filterPaneLabel : me.i18nExt ? me.i18nExt[me.classConfig.className + '_name'] : i18nRC[me.classConfig.className + '_name'] || me.classConfig.className, "class": 'STDetailPaneRecordClassLabel' });
			me.containerNode.appendChild(me.recordClassLabel);
		},
		setUpFormContainer: function () {
			var me = this;

			me.formContainer = new ContentPane({
				region: 'center',
				style: 'overflow-y:scroll;overflow-x:hidden;margin-bottom:20px;z-index: 9;background-color: transparent;'
			});

			me.formContainer.set('content', me.form);

			me.addChild(me.formContainer);
		},
		setUpActionButtons: function (actions) {
			var me = this;

			if (!me.isFilterPane) {
				me.removeActionButtons();
				me.createActionButtons(actions);
			}
		},
		removeActionButtons: function () {
			var me = this;

			for (var i in me.actionButtons) {
				me.actionButtons[i].destroyRecursive();
				delete me.actionButtons[i];
			}

			if (me.menuBar) {
				me.menuBar.destroyRecursive();

				delete me.menuBar;
			}
		},
		createBaseActionButtons: function () {
			var me = this;

			if (!me.menuBar) {
				me.menuBar = new MenuBar({
					region: 'bottom'
				});

				me.menuBar._orient = ['above'];

				me.addChild(me.menuBar);
			}

			if (!me.actionButtons.close) {
				me.actionButtons.close = new MenuBarItem({
					label: i18nDetailPane.BTClose,
					action: 'close',
					"class": 'STForceIcon STAction_close',
					disabled: false,
					onClick: function () {
						me.close();
					}
				});

				me.menuBar.addChild(me.actionButtons.close);
			}

			if (!me.actionButtons.reset) {
				me.actionButtons.reset = new MenuBarItem({
					label: i18nDetailPane.BTReset,
					action: 'reset',
					"class": 'STForceIcon STAction_reset',
					disabled: true,
					onClick: lang.hitch(me, 'resetForm')
				});

				me.menuBar.addChild(me.actionButtons.reset);
			}
		},
		createActionButtons: function (actions) {
			var me = this;

			me.createBaseActionButtons();

			if (actions && actions[0]) {
				for (var i in actions) { // FIXME: use array for deterministic order
					var action = actions[i];
					var button = me.actionButtons[action];

					var style = '';
					var menuPopup = '';

					switch (action) {
						case 'deleteRecord':
						case 'revertRecord':
							style = 'float: right; margin-left: 36px';
							break;
						case 'saveRecord':
						case 'previewRecord':
						case 'translateRecord':
							style = 'margin-left: 36px';
							break;
						case 'publishRecord':
							if (me.classWithPubDate()) {
								var menu = me.enablePubDateMenu("Publish");
								menuPopup = menu;
							}
							break;
						case 'hideRecord':
							style = 'float: right; margin-left: 36px';
							if (me.classWithPubDate()) {
								var menu = me.enablePubDateMenu("Unpublish");
								menuPopup = menu;
							}
							break;
					}

					if (!button) {

						if ((action === 'publishRecord' || action === 'hideRecord') && me.classWithPubDate()) {
							var dovar;

							if (action === 'publishRecord') {
								dovar = 'Publish';
							} else {
								dovar = 'Unpublish';
							}

							if (me.classWithPubDate() && action === 'hideRecord') {
								var disabled = false;
							} else {
								var disabled = true;
							}

							button = new PopupMenuBarItem({
								label: i18nDetailPane['BT' + action],
								action: action,
								"class": 'STForceIcon STAction_' + action,
								style: style,
								popup: menuPopup,
								disabled: disabled

							});

						} else {
							button = new MenuBarItem({
								label: i18nDetailPane['BT' + action],
								action: action,
								"class": 'STForceIcon STAction_' + action,
								style: style,
								disabled: true
							});
						}


						me.menuBar.addChild(button);

						me.actionButtons[action] = button;

						button.onClick = lang.hitch(me, 'doRecordAction', action);
					}
				}
			}

			me.layout();
		},
		enablePubDateMenu: function (dovar) {
			var me = this;

			me.pubdateMenu = new DropDownMenu({});

			me.datePicker = new DateTextBox({
				style: 'width: 160px;margin-top:10px;',
				placeholder: 'Date'
			});
			me.timePicker = new TimeTextBox({
				style: 'width: 160px; ',
				placeholder: 'Time'
			});

			me.dateItem = new MenuBarItem({
				style: 'width: 160px;float:left;'
			});
			me.timeItem = new MenuBarItem({
				style: 'width:160px; clear:both; float:left; '
			});

			me.pubdateMenu.addChild(me.dateItem, 0);
			me.pubdateMenu.addChild(me.timeItem, 1);

			me.pubdateItems['time' + dovar] = me.timeItem;
			me.pubdateItems['date' + dovar] = me.dateItem;


			domConstruct.place(me.datePicker.domNode, me.dateItem.focusNode);
			domConstruct.place(me.timePicker.domNode, me.timeItem.focusNode);

			button = new ModuleMenuItem({
				"class": "STForceIcon STAction_delete" + dovar,
				style: "display:none;clear:both;float:left;"
			});

			button.onClick = lang.hitch(
				me,
				'doExistingPubDateAction',
				'existing' + dovar,
				dovar
			);

			me.pubdateMenu.addChild(button, 2);

			me.pubdateItems['existing' + dovar] = button;

			button = new ModuleMenuItem({
				label: i18nDetailPane['BTLater' + dovar],
				"class": 'STForceIcon STAction_delay' + dovar,
				style: 'clear:both;float:left;'
			});

			button.onClick = lang.hitch(
				me,
				'doPubdateAction',
				'delay' + dovar,
				me.datePicker,
				me.timePicker,
				dovar
			);

			me.pubdateMenu.addChild(button, 3);

			me.actionButtons['delay' + dovar] = button;

			button = new ModuleMenuItem({
				label: i18nDetailPane['BTNow' + dovar],
				"class": 'STForceIcon STAction_' + dovar,
				style: 'clear:both;float:left;',
				disabled: false
			});

			if (dovar === 'Publish') {
				button.onClick = lang.hitch(me, 'doRecordAction', 'publishRecord');
			} else {
				button.onClick = lang.hitch(me, 'doRecordAction', 'hideRecord');
			}

			me.pubdateMenu.addChild(button, 4);
			me.actionButtons['now' + dovar] = button;
			me.pubdateItems['menu' + dovar] = me.pubdateMenu;

			return me.pubdateMenu;

		},
		getDateString: function (date) {
			var me = this;

			var year = date.getFullYear();
			var month = date.getMonth() + 1;
			var day = date.getDate();

			return year + '-' + (month < 10 ? '0' + month : month) + '-' + (day < 10 ? '0' + day : day);
		},
		getTimeString: function (date) {
			var me = this;

			var hours = date.getHours();
			var minutes = date.getMinutes();
			var seconds = date.getSeconds();

			return (hours < 10 ? ('0' + hours) : hours) + ':' + (minutes < 10 ? ('0' + minutes) : minutes) + ':' + (seconds < 10 ? ('0' + seconds) : seconds);
		},
		beforeRecordLoad: function (currentRecord) {
			// used for aspect
			return currentRecord;
		},
		loadRecord: function (request) {
			var me = this;

			if (!me.isFilterPane) {
				me.backend.suspendValueWatches = true;
			}

			me.form.addInitListener(function () {
				if (!me.suspendDirtyCheck && me.form.getDirtyNess()) {
					me.showCloseDialogIfDirty(function () {
						dojo.when(request, function (response) {
							if (response) {
								if (me.record && me.record.primary) {
									me.beforeRecordLoad(me.record);
								}

								if (response.items && response.items[0] && response.items[0].primary && !response.items[1] && me.backend.moduleContainer && me.backend.moduleContainer.listPane) { // hacky workaround for list deselecting the row after reload. needed so parent field can be set when creating a new childrecord after saving the parent
									me.backend.moduleContainer.listPane.lastParent = response.items[0].primary;
								}

								me.setUpActionButtons(response.actions || {});
							} else {
								me.backend.showError(response);
							}
						});

						me.form.loadRecord(request);
					}, function () {
						me.backend.hideStandBy();
						me.cancelLoadRecord();
					});
				} else {
					me.suspendDirtyCheck = false;

					// dupe
					dojo.when(request, function (response) {
						if (response) {
							if (me.record && me.record.primary) {
								me.beforeRecordLoad(me.record);
							}

							if (response.items && response.items[0] && response.items[0].primary && !response.items[1] && me.backend.moduleContainer && me.backend.moduleContainer.listPane) { // hacky workaround for list deselecting the row after reload. needed so parent field can be set when creating a new childrecord after saving the parent
								me.backend.moduleContainer.listPane.lastParent = response.items[0].primary;
							}

							me.setUpActionButtons(response.actions || {});
						} else {
							me.backend.showError(response);
						}
					});

					me.form.loadRecord(request);
				}
			});
		},
		cancelLoadRecord: function () {
			//used for aspect
		},
		newRecord: function (options) {
			var me = this;

			var request = me.STStore.get(null, options);

			dojo.when(request, function (response) {
				if (response) {
					me.setUpActionButtons(response.actions || {});
				}
			});

			me.form.newRecord(request);
		},
		resetForm: function () {
			var me = this;

			me.form.resetRecord();
		},
		doRecordAction: function (action) {
			var me = this;

			switch (action) {
				case 'saveRecord':
					me.saveRecord();
					break;
				case 'copyRecord':
					me.backend.Clipboard.copyPage(me.recordPrimary);
					break;
				default:
					me.doAction(action);
					break;
			}
		},
		doExistingPubDateAction: function (action, dovar) {
			var me = this;

			me.doAction(action, dovar);
		},
		doPubdateAction: function (action, pubdate, pubtime, dovar) {
			var me = this;

			me.pubDate = me.getDateString(dijit.byId(pubdate.id).get("value"));
			me.pubTime = me.getTimeString(dijit.byId(pubtime).get("value"));

			me.doAction(action, false, me.pubDate, me.pubTime, dovar);
		},
		doAction: function (action, doAction, pubDate, pubTime, constants, additionalPublish) {

			var me = this;
			var previousActionDef = null;
			var hasPreviousAction = false;

			me.backend.doStandBy();

			if(action == 'previewRecord'){ // popup block workaround without having to use synchronous ajax call (http://stackoverflow.com/questions/4602964/how-do-i-prevent-google-chrome-from-blocking-my-popup)
				var win = window.open('');
				window.oldOpen = window.open;
				window.open = function(url){
					win.location = url;
					window.open = oldOpen;
					win.focus();
				};
			}

			var conf = {
				data: {
					requestType: action,
					recordClass: me.classConfig.className,
					recordID: me.recordPrimary,
					doAction: doAction,
					pubDate: pubDate,
					pubTime: pubTime,
					constants: constants,
					additionalPublish: additionalPublish
				},
				error: function (response) {
					me.handleError(response, action);
				},
				success: function (response) {
					if (!me.recordPrimary && action == 'saveRecord') {
						action = 'createRecord';
					}

					me.actionSuccess(action, response);
				}
			};

			if ((action == 'publishRecord' || action == 'delayPublish') && !!me.form.getDirtyNess() && !doAction) {
				previousActionDef = me.saveRecord(true);
				hasPreviousAction = true;
			}

			if(action == 'revertRecord'){
				var dialog = new YesNoDialog({
					messageType: 'revertRecord',
					onYes: function () {
						dialog.hide();
						me.backend.STServerComm.sendAjax(conf); // FIXME: use deferred?
					},
					onNo: function () {
						dialog.hide();
						me.backend.hideStandBy();
					}
				});

				dialog.show();

				return;
			}

			dojo.when(previousActionDef, function (response) {
				if (hasPreviousAction) {
					if (response && response.success) {
						var record = response.data.items[0];

						me.recordPrimary = record.primary;
						conf.data.recordID = me.recordPrimary;
						me.backend.STServerComm.sendAjax(conf); // FIXME: use deferred?
					} else {
						me.handleError(response, action);
					}
				} else {
					me.backend.STServerComm.sendAjax(conf); // FIXME: use deferred?
				}
			});
		},
		openPreview: function (response) {
			var me = this;

			window.open(response.data.items, '_blank');
		},
		actionSuccess: function (action, response) {
			var me = this;

			if (!me.recordPrimary && action == 'saveRecord') {
				action = 'createRecord';
			}

			me.backend.hideStandBy();

			me.backend.showToaster(action);

			if (action == 'previewRecord') {
				me.openPreview(response);
			}

			return me.afterActionSuccess(response, action);
		},
		afterActionSuccess: function (response, action) {
			return action == 'previewRecord' || action == 'deleteRecord' || action == 'duplicateRecord' ? action : response.data;
		},
		handleError: function (response, action) {
			var me = this;

			me.backend.hideStandBy();

			if (response.error) {
				if (response.error == 'MissingReferencesException' || response.error == 'AffectedReferencesException') {
					var dialog = new ReferenceDialog({
						backend: me.backend,
						mainRecordClass: me.classConfig.className,
						mainRecordID: me.recordPrimary,
						response: response,
						onYes: function () {
							me.doAction(action, true, null, null, null, this.recordsSelected.join(','));
						}
					});

					dialog.show();
				} else {
					me.backend.showError(response);
				}
			} else {
				if (response.type && response.arguments) {
					me.backend.showError({
						error: response.type,
						message: response.arguments
					});
				}
			}
		},
		saveRecord: function (isChainedAction) {
			var me = this;

			me.backend.doStandBy();

			var baseQuery = {
				isIframe: 1,
				requestType: 'saveRecord',
				recordClass: me.classConfig.className
			};

			var def = me.backend.STServerComm.sendForm(me.form, baseQuery);

			def.then(function (response) {
				if (!isChainedAction) {
					if (response.success) {
						me.actionSuccess('saveRecord', response);
					} else {
						me.handleError(response);
					}
				}

			}, function (response) {
				me.handleError(response);
			});

			return def;
		},
		showReferences: function (response, action, onYes) {
			var me = this;

			var dialog = new ReferenceDialog({
				backend: me.backend,
				response: response,
				onYes: onYes,
				lastAction: action
			});

			dialog.show();
		},
		setButtonStates: function () {
			var me = this;

			me.formValid = me.form.get('state') == '';
			me.formDirty = me.form.getDirtyNess() > 0;

			for (action in me.actionButtons) {
				me.setButtonState(me.actionButtons[action]);
			}
		},
		setButtonState: function (button) {
			var me = this;

			var action = button.action;

			var formDirty = me.formDirty;
			var formValid = me.formValid;

			switch (action) {
				case 'duplicateRecord':
					button.set('disabled', !me.recordCanDuplicate(me.record));
					break;
				case 'reset':
					button.set('disabled', !formDirty || me.readOnly);
					break;
				case 'saveRecord':
					var disabled = me.readOnly || (!formValid || (formValid && !formDirty));
					button.set('disabled', disabled);
					break;
				case 'publishRecord':
					var publishOnly = !formDirty && formValid;
					if (!me.classWithPubDate()) {
						button.set('disabled', !me.recordCanPublish(formValid, formDirty, me.record));
					} else {
						button.set('disabled', false);
						if (me.record.publishDate || me.record._liveStatus === 1) {
							button.set('class', 'STForceIcon STAction_' + action + ' delayPublish');
						}
					}
					button.set('label', publishOnly ? i18nDetailPane.BTpublishOnly : i18nDetailPane.BTpublishRecord);
					break;
				case 'hideRecord':
					if (!me.classWithPubDate()) {
						button.set('disabled', !me.recordCanHide(me.record));
					} else {
						if (!me.recordCanHide(me.record) || me.record.unpublishDate) {
							button.set('class', 'STForceIcon STAction_' + action + ' delayUnpublish');
						}
					}
					break;
				case 'deleteRecord':
					button.set('disabled', !me.recordCanDelete(me.record));

					break;
				case 'revertRecord':
					var canRevert = me.recordCanRevert(me.record);
					button.set('disabled', !canRevert);
					break;
				case 'previewRecord':
					button.set('disabled', !me.recordCanPreview(me.record));
					break;
				case 'copyRecord':
					button.set('disabled', !me.recordCanCopy(me.record));
					break;
				case 'syncRecord':
					button.set('disabled', !formValid || !me.record.primary);
			}
		},
		classWithPubDate: function () {
			var me = this;

			return me.classConfig.isPubdateRecord;
		},
		hasUnpubDate: function () {
			return true;
		},
		recordCanCopy: function (record) {
			var me = this;

			return !!record.primary;
		},
		recordCanPreview: function (record) {
			var me = this;

			return record && record.primary;
		},
		recordCanDuplicate: function (record) {
			var me = this;

			return record && record.primary;
		},
		recordCanRevert: function (record) {
			var me = this;

			if (me.readOnly) {
				return false;
			}

			var langField = me.classConfig.languageField;

			if (langField) {
				return record.stati && record.stati.languages[me.backend.config.system.languages.current.id] && record.stati.languages[me.backend.config.system.languages.current.id]['status'] == 2;
			}

			return record.stati && record.stati.status != 2;
		},
		recordCanPublish: function (formValid, formDirty, record) {
			var me = this;

			if (!formValid || me.readOnly) {
				return false;
			}

			var langField = me.classConfig.languageField;

			if (langField) {
				return formDirty || (record.stati && record.stati.languages[me.backend.config.system.languages.current.id] && record.stati.languages[me.backend.config.system.languages.current.id]['status'] != 1);
			}

			return formDirty || (record.stati && record.stati.status != 1);
		},
		recordCanDelete: function (record) {
			var me = this;

			var canDelete = record && record.primary && !me.readOnly;

			return canDelete;
		},
		recordCanHide: function (record) { // function is only called if recordClass has a liveField, so we don't need to check for its existence
			var me = this;

			var classConfig = me.classConfig;

			if (!record || !record.primary || me.readOnly) {
				return false;
			}

			var langField = classConfig.languageField;

			if (langField) {
				return record.stati && record.stati.languages[me.backend.config.system.languages.current.id] && record.stati.languages[me.backend.config.system.languages.current.id]['status'];
			}

			return record.stati && record.stati.status;
		},
		showCloseDialogIfDirty: function (onYesFunc, onNoFunc) {
			var me = this;

			me.closeDialog = new YesNoDialog({
				messageType: 'unsavedChanges',
				onYes: function () {
					onYesFunc();
				},
				onNo: function () {
					if (onNoFunc) {
						onNoFunc();
					}
				}
			});

			me.closeDialog.show();
		},
		close: function (force) {
			var me = this;

			me.closingDef = new Deferred();

			me.closingDef.then(function () {
				if (me.moduleContainer && me.moduleContainer.listPane) {
					me.moduleContainer.listPane.lastParent = null;
				}

				me.destroyRecursive();
			});

			if (!force && me.form.getDirtyNess() > 0) {
				me.showCloseDialogIfDirty(function () {
					me.closingDef.resolve();
				}, function () {
					me.closingDef.cancel('cancelled');
				});
			} else {
				me.closingDef.resolve(); // [JB 12.02.2013] just resolve instantly in case we got no dirtyNess
			}

			return me.closingDef;
		},
		destroy: function () {
			var me = this;

			me.removeActionButtons();

			if (me.menuBar) {
				me.menuBar.destroyRecursive();
				delete me.menuBar;
			}

			if (me.form) {
				me.form.destroyRecursive();
				delete me.form;
			}

			if (me.formWatch) {
				me.formWatch.unwatch();
				delete me.formWatch;
			}

			delete me.STStore;
			delete me.record;
			delete me.recordPrimary;

			if (me.recordClassLabel) {
				domConstruct.destroy(me.recordClassLabel);
				delete me.recordClassLabel;
			}

			if (me.actionDef && me.actionDef.fired < 0) {
				me.actionDef.resolve();
				delete me.actionDef;
			}

			if (me.closingDef && me.closingDef.fired < 0) {
				me.closingDef.resolve();
				delete me.closingDef;
			}

			if (me.closeDialog) {
				me.closeDialog.destroyRecursive();
				delete me.closeDialog;
			}

			if (me.formContainer) {
				me.formContainer.destroyRecursive();
				delete me.formContainer;
			}

			delete me.backend;

			me.inherited(arguments);
		}
	});
});