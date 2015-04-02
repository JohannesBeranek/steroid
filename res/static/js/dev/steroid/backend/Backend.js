define([
	//misc
	"dijit/_WidgetBase",
	"dojo/_base/declare",

	//dijit layout
	"dijit/TitlePane",
	"dijit/layout/ContentPane",
	"dijit/layout/BorderContainer",

	// dijit menu
	"dijit/MenuBar",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"dijit/MenuBarItem",
	"dijit/layout/StackContainer",

	// dojox
	"steroid/backend/User",
	"steroid/backend/DomainGroupSelector",
	"steroid/backend/LanguageSelector",
	"steroid/backend/MenuTime",
	"steroid/backend/ServerComm",
	"steroid/backend/ModuleMenuItem",
	"steroid/backend/WizardMenuItem",
	"dojo/i18n!steroid/backend/nls/Backend",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dojo/hash",
	"steroid/backend/DetailPane",
	"dojox/data/JsonRestStore",
	"steroid/backend/STStore",
	"dojo/store/Observable",
	"dijit/Dialog",
	"dojox/lang/functional/object",
	"dijit/layout/AccordionContainer",
	"dojo/window",
	"dojo/aspect",
	"dojo/_base/lang",
	"dojo/io-query",
	"dojo/_base/connect",
	"dojo/dom-construct",
	"dojo/dom-class",
	"dijit/form/Button",
	"dojo/_base/array",
	"steroid/backend/ErrorDialog",
	"steroid/backend/ReferenceDialog",
	"steroid/backend/ModuleContainer",
	"steroid/backend/mixin/_ModuleContainerList",
	"dojo/dom-style",
	"steroid/backend/mixin/_hasStandBy",
	"steroid/backend/WelcomeScreen",
	"steroid/backend/Toaster",
	"dojo/i18n!steroid/backend/nls/Toaster",
	"dojo/_base/window",
	"dijit/registry",
	"steroid/backend/mixin/_hasInitListeners",
	"steroid/backend/dnd/Clipboard",
	"steroid/backend/dnd/DndManager",
	"steroid/backend/stats/stats"
], function (_WidgetBase, declare, TitlePane, ContentPane, BorderContainer, MenuBar, DropDownMenu, PopupMenuBarItem, MenuBarItem, StackContainer, STUser, STDomainGroupSelector, STLanguageSelector, STMenuTime, STServerComm, ModuleMenuItem, WizardMenuItem, i18n, i18nRC, i18nErr, hash, DetailPane, JsonRestStore, STStore, ObservableStore, Dialog, langFuncObj, AccordionContainer, win, aspect, lang, ioQuery, connect, domConstruct, domClass, Button, array, ErrorDialog, ReferenceDialog, ModuleContainer, _ModuleContainerList, domStyle, _hasStandBy, WelcomeScreen, Toaster, i18nToaster, baseWindow, registry, _hasInitListeners, Clipboard, DndManager, STStats) {
	return declare([ _WidgetBase, _hasStandBy, _hasInitListeners], {
		debugMode: false,
		moduleContainer: null,
		toaster: null,
		checkMessages: true,
		messageTimer: null,
		messageInterval: 30,
		suspendValueWatches: false,
		moduleContainerCloseAspect: null,
		contentTypeMenus: null,
		wizardTypeMenu: null,
		nls: {},
		BTDebug: null,
		Clipboard: null,
		dndManager: null,
		BTStats: null,
		statisticsModule: null,
		overlay: null,
		standBy: false,

		// microtime stuff used for profiling
		mt: function () {
			return (new Date().getTime() / 1000);
		},
		postCreate: function () {
			var me = this;

			var loader = document.getElementById('spinningSquaresG');

			if (loader) {
				domConstruct.destroy(loader);
			}

			me.dialogs = [];

			me.init();

			me.addInitListener(function () {
				me.createUI();
			});
		},
		init: function () {
			var me = this;

			me.contentPanes = [];
			me.listPanes = [];
			me.detailPanes = [];
			me.currentRecordClassOpen = null;
			me.currentRecordOpen = null;

			me.domNode = dojo.byId('uiContainer');

			me.STServerComm = new STServerComm({ backend: me });

			window.onbeforeunload = function (e) {
				var e = e || window.event;

				// For IE and Firefox
				if (e) {
					e.returnValue = '';
				}

				me.endEditing(true);

				// For Chrome and Safari
				return null;
			};

			me.collectNLS();
		},
		collectNLS: function () {
			var me = this;

			var count = 0;

			for (var type in me.config.recordClasses) {
				for (var i in me.config.recordClasses[type]) {
					if (me.config.recordClasses[type][i].classLocation) {
						count++;
					}
				}
			}

			if (me.config.wizards) {
				count += me.config.wizards.length; // wizards are always /ext classes
			}

			if(count == 0){
				me.initComplete();
				return;
			}

			//FIXME: remove duplicate code
			for (var type in me.config.recordClasses) {
				for (var i in me.config.recordClasses[type]) {
					var item = me.config.recordClasses[type][i];

					if (item.classLocation) {
						var nlsPath = "dojo/i18n!" + item.classLocation + '/res/static/js/nls/' + item.className;

						require([nlsPath], (function (item) {
							return function (i18nExt) {
								item.i18nExt = i18nExt;

								count--;

								if (!count) {
									me.initComplete();
								}
							};
						})(item));
					}
				}
			}

			if (me.config.wizards) {
				for (var i = 0, item; item = me.config.wizards[i]; i++) {
					var nlsPath = "dojo/i18n!" + item.classLocation + '/res/static/js/nls/' + item.className;

					require([nlsPath], (function (item) {
						return function (i18nExt) {
							item.i18nExt = i18nExt;

							count--;

							if (!count) {
								me.initComplete();
							}
						};
					})(item));
				}
			}
		},
		createWelcomeScreen: function () {
			var me = this;

			me.welcomeScreenContainer = new WelcomeScreen({
				style: 'width: 100%;height: 100%;overflow:hidden;padding:0;padding-top:50px;position:absolute;top:0;left:0;right:0;bottom:0;',
				gutters: false,
				'class': 'STWelcome',
				backend: me
			});

			me.welcomeScreenContainer.placeAt(me.domNode);

			me.welcomeScreenContainer.startup();
		},
		createUI: function () {
			var me = this;

			me.dndManager = new DndManager({});

			me.STViewPort = new BorderContainer({
				style: 'width: 100%;height: 50px;overflow:hidden;padding:0;position:relative;',
				gutters: false
			});

			me.standByNode = me.STViewPort.domNode;

			me.STMenuBar = new MenuBar({
				region: 'top',
				style: 'overflow:hidden',
				splitter: false
			});

			me.addLogoutButton();

			me.addMenuTime();

			me.addMenuUser();

			if (me.config.User.config.permission == '__dev__') {
				me.BTDebug = new MenuBarItem({
					label: 'Debug: Off',
					"class": 'STForceIcon',
					style: 'float:right;',
					onClick: function () {
						me.debugMode = !me.debugMode;

						this.set('label', 'Debug: ' + (me.debugMode ? 'On' : 'Off'));
					}
				});

				me.STMenuBar.addChild(me.BTDebug);

				me.BTStats = new MenuBarItem({
					label: i18n.BTStats.label,
					"class": 'STForceIcon STModule_stats',
					style: 'float:left;',
					onClick: function () {
						me.openStatistics();
					}
				});

				me.STMenuBar.addChild(me.BTStats);
			}

			me.setMenuBarItemsByUserConf();

			me.createWelcomeScreen();

			me.createClipboard();

			me.STMenuBar.startup();

			me.STViewPort.addChild(me.STMenuBar);
			me.STViewPort.placeAt(me.domNode);
			me.STViewPort.startup();

			me.pollMessage();
		},
		openStatistics: function () {
			var me = this;

			me.removeStatistics();

			if (me.moduleContainer) {
				var closingDef = me.moduleContainer.close();

				dojo.when(closingDef, function () {
					me._openStatistics();
				});
			} else {
				me._openStatistics();
			}

		},
		_openStatistics: function () {
			var me = this;

			me.statisticsModule = new STStats({
				"class": 'STStats',
				region: 'center',
				backend: me
			});

			domStyle.set(me.welcomeScreenContainer.domNode, 'display', 'none');
			domStyle.set(me.STViewPort.domNode, 'height', '30px');

			me.statisticsModule.placeAt(me.domNode);

			me.statisticsModule.startup();
		},
		removeStatistics: function () {
			var me = this;

			if (me.statisticsModule) {
				me.statisticsModule.destroyRecursive();
				delete me.statisticsModule;

				domStyle.set(me.welcomeScreenContainer.domNode, 'display', 'block');
			}
		},
		createClipboard: function () {
			var me = this;

			me.Clipboard = new Clipboard({
				backend: me
			});

			me.STMenuBar.addChild(me.Clipboard);
		},
		pollMessage: function (date) {
			var me = this;

			if (!date) {
				date = new Date();
			}

			if (me.checkMessages) {
				if (!me.STServerComm.loading) {
					var hours = date.getHours();
					var minutes = date.getMinutes();
					var seconds = date.getSeconds() - 1;

					var now = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + ' ' + (hours < 10 ? "0" + hours : hours) + ':' + (minutes < 10 ? "0" + minutes : minutes) + ':' + (seconds < 10 ? "0" + seconds : seconds);

					var conf = {
						error: function () {
						},
						success: lang.hitch(me, 'messagesReceived'),
						data: {
							requestType: 'getMessages',
							time: now
						}
					};

					if (me.moduleContainer && me.moduleContainer.detailPane && me.moduleContainer.detailPane && me.moduleContainer.detailPane.form && me.moduleContainer.detailPane.form.record) {
						conf.data.editing = me.moduleContainer.detailPane.form.record.primary;
						conf.data.editingClass = me.moduleContainer.detailPane.form.ownClassConfig.className;

						if (me.moduleContainer.detailPane.form.record.parent) {
							conf.data.editingParent = me.moduleContainer.detailPane.form.record.parent.primary;
						}
					}

					me.STServerComm.sendAjax(conf);
				}

				var date = new Date();

				if (me.messageTimer) {
					clearTimeout(me.messageTimer);
					delete me.messageTimer;
				}

				me.messageTimer = setTimeout(function () {
					me.pollMessage(date);
				}, me.messageInterval * 1000);
			}
		},
		messagesReceived: function (response) {
			var me = this;

			if (response && response.data && response.data.length) {
				for (var i = 0; i < response.data.length; i++) {
					var item = response.data[i];

					var type = item.alert ? 'warning' : 'message';

					var message = item.creator + ': <br/><br/>' + item.text;

					me.showToaster(type, message, true);
				}
			}
		},
		getClassConfigFromClassName: function (recordClassName) {
			var me = this;

			var classConfig = false;

			for (type in me.config.recordClasses) {
				for (var i = 0; i < me.config.recordClasses[type].length; i++) {
					if (me.config.recordClasses[type][i].className == recordClassName) {
						return me.config.recordClasses[type][i];
					}
				}
			}

			return null;
		},
		setMenuBarItemsByUserConf: function () {
			var me = this;

			me.addDomainGroupSelector();

			me.addLanguageSelector();

			me.createModuleMenu();
			me.createWizardMenu();
		},
		addDomainGroupSelector: function () {
			var me = this;

			if (me.STDomainGroupSelector) {
				me.STDomainGroupSelector.destroyRecursive();
				delete me.STDomainGroupSelector;
			}

			if (me.config.system.domainGroups.available.length) {
				me.STDomainGroupSelector = new STDomainGroupSelector({
					backend: me,
					menuBar: me.STMenuBar
				});

				me.STDomainGroupSelector.startup();
			}
		},
		addLanguageSelector: function () {
			var me = this;

			if (me.STLanguageSelector) {
				me.STLanguageSelector.destroyRecursive();
			}

			if (me.config.system.languages.available.length) {
				me.STLanguageSelector = new STLanguageSelector({
					backend: me,
					menuBar: me.STMenuBar
				});
			}
		},
		addMenuUser: function () {
			var me = this;

			me.RCUser = new STUser({ backend: me });

			me.STMenuBar.addChild(me.RCUser.BTUser);
		},
		addMenuTime: function () {
			var me = this;

			me.STMenuTime = new STMenuTime({ backend: me });

			me.STMenuBar.addChild(me.STMenuTime.BTTime);
		},
		hideDialog: function (dialog) {
			var me = this;

			me.dialogs.splice(array.indexOf(me.dialogs, dialog), 1);

			dialog.destroyRecursive();
		},
		addLogoutButton: function () {
			var me = this;

			me.STBTLogout = new MenuBarItem({
				label: i18n.logout,
				style: 'float:right',
				"class": 'STForceIcon STIconLogout',
				onClick: function () {
					var conf = {
						data: {
							requestType: 'logout',
							logout: 1
						},
						error: function (response) {
							me.showError(response);
						},
						success: function (response) {
							window.location.href = me.STServerComm.interfaceUrl;
						}
					};

					if (me.moduleContainer) {
						var closingDef = me.moduleContainer.close();

						dojo.when(closingDef, function () {
							me.STServerComm.sendAjax(conf);
						});
					} else {
						me.STServerComm.sendAjax(conf);
					}
				}
			});

			me.STMenuBar.addChild(me.STBTLogout);
		},
		showModule: function (recordClass) {
			return (recordClass.mayWrite || recordClass.listOnly) && recordClass.hasPrimaryField && !recordClass.isDependency;
		},
		showModuleGroup: function (type) {
			return !(type == 'widget' || type == 'system');
		},
		createWizardMenu: function () {
			var me = this;

			if (me.wizardTypeMenu) {
				me.STMenuBar.removeChild(me.wizardTypeMenu);
			}

			if (!me.config.wizards || !me.config.wizards.length) {
				return;
			}

			var wizardMenu = new DropDownMenu({});

			me.wizardTypeMenu = new PopupMenuBarItem({
				label: i18nRC.type_wizard,
				popup: wizardMenu,
				"class": 'STForceIcon STModule_wizard'
			});

			for (var i = 0, item; item = me.config.wizards[i]; i++) {
				var menuItem = new WizardMenuItem({
					label: item.i18nExt[item.className + '_name'],
					wizardConfig: item,
					backend: me
				});

				wizardMenu.addChild(menuItem);
			}

			me.STMenuBar.addChild(me.wizardTypeMenu);
		},
		createModuleMenu: function () {
			var me = this;

			if (me.contentTypeMenus) {
				for (type in me.contentTypeMenus) {
					me.STMenuBar.removeChild(me.contentTypeMenus[type]);
					me.contentTypeMenus[type].popup.destroyRecursive();
					me.contentTypeMenus[type].destroyRecursive();
				}
			}

			me.STMenuBar.selected = null; //fixes menuBar to stop working after domainGroup switch

			me.contentTypeMenus = {};

			var keys = langFuncObj.keys(me.config.recordClasses);

			keys.sort();

			for (var j = 0; j < keys.length; j++) {

				var type = keys[j];

				var recordClasses = me.config.recordClasses[type];
				var hasWritable = false;

				for (var i = 0, ilen = recordClasses.length; i < ilen; i++) {
					if (me.showModule(recordClasses[i])) {
						hasWritable = true;
						break;
					}
				}

				if (!me.showModuleGroup(type) || !hasWritable) {
					continue;
				}

				me.contentTypeMenus[type] = {};

				var typeMenu = new DropDownMenu({});

				var labels = [];

				for (var i = 0, ilen = recordClasses.length; i < ilen; i++) {
					var recordClass = recordClasses[i], currentLabel;

					if (!me.showModule(recordClass)) {
						continue;
					}

					var i18n = recordClass.i18nExt || i18nRC;

					var currentLabel = i18n[recordClass.className + '_name'] || recordClass.className;

					for (var pos = 0, nlen = labels.length; pos < nlen; pos++) {
						if (labels[pos] > currentLabel) {
							break;
						}
					}

					labels.splice(pos, 0, currentLabel);

					var item = new ModuleMenuItem({
						label: currentLabel,
						classConfig: recordClass,
						backend: me
					});

					typeMenu.addChild(item, pos);
				}

				var typeMenuItem = new PopupMenuBarItem({
					label: i18nRC['type_' + type],
					popup: typeMenu,
					"class": 'STForceIcon STModule_' + type
				});

				me.contentTypeMenus[type] = typeMenuItem;

				me.STMenuBar.addChild(typeMenuItem);
			}
		},
		removeModuleContainer: function (moduleContainer) {
			var me = this;

			me.STViewPort.removeChild(moduleContainer);

			domStyle.set(me.STViewPort.domNode, 'height', '30px');

			me.STViewPort.resize();

			delete me.moduleContainer;
		},
		openModule: function (classConfig, recordID) {
			var me = this;

			me.removeStatistics();

			if (me.moduleContainer) {
				dojo.when(me.moduleContainer.close(), function () {
					delete me.moduleContainer;
					me.openModule(classConfig, recordID);
				});

				return; // try again once deferreds are resolved
			}

			var mixins = [ModuleContainer];

			if (classConfig.listFields) {
				mixins.push(_ModuleContainerList);
			}

			var customContainer = declare(mixins, {
				style: 'width: 100%;height: 100%;overflow:hidden;padding:0;background-color:white;',
				region: 'center',
				classConfig: classConfig,
				backend: me,
				i18nExt: classConfig.i18nExt
			});

			me.moduleContainer = new customContainer({});

			me.moduleContainerCloseAspect = aspect.after(me.moduleContainer, 'hasClosed', function () {
				me.STViewPort.removeChild(me.moduleContainer);
				domStyle.set(me.STViewPort.domNode, 'height', '50px');
			});

			domStyle.set(me.STViewPort.domNode, 'height', '100%');

			me.STViewPort.addChild(me.moduleContainer);

			me.STViewPort.resize();
		},
		switchDomainGroup: function (domainGroup) {
			var me = this;

			me.doStandBy();

			var recordID = domainGroup || me.config.system.domainGroups.available[0].primary;

			var conf = {
				data: {
					requestType: 'selectDomainGroup',
					recordID: recordID
				},
				success: lang.hitch(me, 'domainGroupSwitched'),
				error: lang.hitch(me, 'showError')
			};

			me.STServerComm.sendAjax(conf);
		},
		switchLanguage: function (language) {
			var me = this;

			me.doStandBy();

			var recordID = language || me.backend.config.system.languages.available[0].primary;

			var conf = {
				data: {
					requestType: 'selectLanguage',
					recordID: recordID
				},
				success: lang.hitch(me, 'languageSwitched'),
				error: lang.hitch(me, 'showError')
			};

			me.STServerComm.sendAjax(conf);
		},
		userSwitched: function(){
			window.location.reload();
		},
		languageSwitched: function (response) {
			var me = this;

			me.setConf(response.data);

			me.setMenuBarItemsByUserConf();

			if (me.moduleContainer) {
				if (!me.getClassConfigFromClassName(me.moduleContainer.classConfig.className)) {
					me.moduleContainer.close();
				} else {
					me.moduleContainer.languageSwitched();
				}
			}

			me.hideStandBy();
		},
		domainGroupSwitched: function (response) {
			var me = this;

			me.clearInitListeners();

			me.setConf(response.data);

			me.addInitListener(function () {
				me.setMenuBarItemsByUserConf();

				if (me.moduleContainer) {
					if (!me.getClassConfigFromClassName(me.moduleContainer.classConfig.className)) {
						me.moduleContainer.close(true);
					} else {
						me.moduleContainer.domainGroupSwitched(response.data.system.domainGroups.current);
					}
				}

				me.hideStandBy();
			});

			me.collectNLS();

			me.welcomeScreenContainer.domainGroupSwitched(me.config.system.domainGroups.current);
		},
		setConf: function (config) {
			var me = this;

			if (me.config && me.config.User) {
				config.User = me.config.User;
			}

			me.config = config;

			me.STServerComm.setConf(config);
		},
		showError: function (response) {
			var me = this;

			me.hideStandBy();

			var dialog = new ErrorDialog({
				response: response,
				backend: me,
				onClose: function (errorType) {
					switch (errorType) {
//						case 'AccessDeniedException':
//							if (me.moduleContainer) {
//								me.moduleContainer.close(true);
//							}
//							break;
						case 'RecordIsLockedException':
							me.moduleContainer.recordIsLockedException();
							break;
					}
				}
			});

			dialog.show();

			dialog.resize(); // need to call this again, otherwise the dialog may not always be centered on screen
		},
		storeQueryError: function (response) {
			var me = this;

			me.showError(response);
		},
		storeGetError: function (response) {
			var me = this;

			if (me.moduleContainer) {
				me.moduleContainer.storeGetError();
			}

			me.showError(response);
		},
		showToaster: function (type, message, stay) {
			var me = this;

			if (!me.toaster) {
				var toasterNode = domConstruct.create('div', { "class": 'STToaster', id: 'STToaster' });

				baseWindow.body().appendChild(toasterNode);

				me.toaster = new Toaster({
					id: 'STToaster',
					duration: 0
				}, toasterNode);
			}

			var message = message || i18nToaster[type];

			switch (type) {
				case 'saveRecord':
				case 'publishRecord':
				case 'deleteRecord':
				case 'hideRecord':
				case 'revertRecord':
				case 'copyRecord':
					type = 'message';
					break;
			}

			if (stay) {
				message = message + '<br/>(' + i18nToaster.clickToHide + ')';
			}

			me.toaster.setContent(message, type, stay ? 0 : 2000);

			me.toaster.show();
		},
		endEditing: function (sync) {
			var me = this;

			if (me.moduleContainer) {
				me.moduleContainer.endEditing(sync);
			}
		},
		doStandBy: function (caller) {
			var me = this;

			if (!me.overlay) {
				me.overlay = domConstruct.toDom('<div class="STOverlay"><div id="spinningSquaresG"><div id="spinningSquaresG_1" class="spinningSquaresG"></div><div id="spinningSquaresG_2" class="spinningSquaresG"></div><div id="spinningSquaresG_3" class="spinningSquaresG"></div><div id="spinningSquaresG_4" class="spinningSquaresG"></div><div id="spinningSquaresG_5" class="spinningSquaresG"></div><div id="spinningSquaresG_6" class="spinningSquaresG"></div><div id="spinningSquaresG_7" class="spinningSquaresG"></div><div id="spinningSquaresG_8" class="spinningSquaresG"></div></div></div>');
				document.body.appendChild(me.overlay);
			}

			if (!me.standBy) {
				domClass.replace(me.overlay, 'visible', 'hidden');
				me.standBy = true;
			}
		},
		hideStandBy: function (caller) {
			var me = this;

			if (me.standBy) {
				domClass.replace(me.overlay, 'hidden', 'visible');
				me.standBy = false;
			}
		},
		destroy: function () {
			var me = this;

			if (me.overlay) {
				domConstruct.destroy(me.overlay);
				delete me.overlay;
			}

			me.removeStatistics();

			me.clipboard.destroyRecursive();
			delete me.clipboard;

			me.STServerComm.destroyRecursive();
			delete me.STServerComm;

			me.welcomeScreenContainer.destroyRecursive();
			delete me.welcomeScreenContainer;

			me.STViewPort.destroyRecursive();
			delete me.STViewPort;

			me.STMenuBar.destroyRecursive();
			delete me.STMenuBar;

			delete me.config;

			delete me.messageTimer;

			if (me.STDomainGroupSelector) {
				me.STDomainGroupSelector.destroyRecursive();
				delete me.STDomainGroupSelector;
			}

			if (me.STLanguageSelector) {
				me.STLanguageSelector.destroyRecursive();
				delete me.STLanguageSelector;
			}

			me.RCUser.destroyRecursive();
			delete me.RCUser;

			me.STMenuTime.destroyRecursive();
			delete me.STMenuTime;

			var dialogLen = me.dialogs.length;

			for (var i = 0; i < dialogLen; i++) {
				me.dialogs[i].destroyRecursive();
			}

			me.STBTLogout.destroyRecursive();
			delete me.STBTLogout;

			delete me.contentTypeMenus;

			if (me.moduleContainer) {
				me.moduleContainer.destroyRecursive();
				delete me.moduleContainer;
			}

			if (me.BTStats) {
				me.BTStats.destroyRecursive();
				delete me.BTStats;
			}

			me.moduleContainerCloseAspect.remove();
			delete me.moduleContainerCloseAspect;

			if (me.BTDebug) {
				me.BTDebug.destroyRecursive();
				delete me.BTDebug;
			}

			me.Clipboard.destroyRecursive();
			delete me.Clipboard;

			me.dndManager.destroy();

			if (me.toaster) {
				me.toaster.destroyRecursive();
				delete me.toaster;
			}

			delete me.dialogs;

			me.inherited(arguments);
		}
	});
});