define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dijit/MenuBarItem",
	"dojo/i18n!steroid/backend/nls/User",
	"dojo/i18n!steroid/backend/nls/Languages",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"dijit/MenuItem",
	"./FullScreenHandler"
], function (declare, _WidgetBase, MenuBarItem, i18n, i18nLang, DropDownMenu, PopupMenuBarItem, MenuItem, FullScreenHandler) {
	return declare([_WidgetBase], {
		postCreate: function () {
			var me = this,
				userName = me.backend.config.User.values.username,
				availableLanguages = me.backend.config["interface"].languages.available,
				availableThemes = me.backend.config["interface"].themes.available;

			if (me.backend.config.User.values.firstname && me.backend.config.User.values.lastname) {
				userName = me.backend.config.User.values.firstname + ' ' + me.backend.config.User.values.lastname;
			}

			me.userMenu = new DropDownMenu({});

			me.BTUser = new PopupMenuBarItem({
				label: userName,
				"class": 'STForceIcon STIconUser' + (me.backend.config.User.isSwitched ? ' isSwitched' : ''),
				style: 'float:right;',
				popup: me.userMenu
			});


			me.languageMenu = new DropDownMenu({});

			me.languageMenuItem = new PopupMenuBarItem({
				label: i18nLang.language,
				popup: me.languageMenu,
				"class": 'STUserMenu_language'
			});

			me.userMenu.addChild(me.languageMenuItem);


			me.themeMenu = new DropDownMenu({});

			me.themeMenuItem = new PopupMenuBarItem({
				label: i18n.theme,
				popup: me.themeMenu,
				"class": 'STUserMenu_theme'
			});

			me.userMenu.addChild(me.themeMenuItem);


			// fullscreen support
			// TODO: move to some generic place, so it can be used not only for the whole backend, but for other things as well (page editing for example, rte)

			if (FullScreenHandler.isAvailable) {
				me.userMenu.addChild(new MenuBarItem({
					label: 'Fullscreen On/Off',
					"class": 'STUserMenu_fullscreen',
					onClick: function () {
						FullScreenHandler.toggle(document.body);
					}
				}));
			}

			// fullscreen support - end

			for (var i = 0; i < availableLanguages.length; i++) {
				var language = availableLanguages[i];

				var item = new MenuItem({
					label: i18nLang[language + '_long'],
					language: language,
					backend: me.backend, // TODO: don't need this?
					onClick: function () {
						var res = me.backend.STServerComm.switchBELangAjax(this.language);

						res.then(function (response) {
							me.backend.STServerComm.reloadBackend();
						});
					}
				});

				me.languageMenu.addChild(item);
			}

			// themes
			for (var i = 0; i < availableThemes.length; i++) {
				var theme = availableThemes[i];

				var item = new MenuItem({
					label: theme.label,
					theme: theme,
					onClick: function () {
						me.setTheme(this.theme);

						// TODO: persist theme
						me.backend.STServerComm.sendAjax({
							data: {
								requestType: 'changeBETheme',
								beTheme: this.theme.id
							}
						});
					}
				});


				me.themeMenu.addChild(item);

			}

			// apply current theme after login
			if (me.backend.config["interface"].themes.current) {
				me.setTheme(me.backend.config["interface"].themes.current);
			}

			me.userMenu.addChild(new MenuBarItem({
				label: i18n.editProfile,
				"class": 'STUserMenu_editProfile',
				onClick: function () {
					me.loadProfileEdit();
				}
			}));

			if(me.backend.config.User.isSwitched){
				me.userMenu.addChild(new MenuBarItem({
					label: i18n.unSwitchUser,
					"class": 'STUserMenu_unSwitch',
					onClick: function () {
						me.unSwitchUser();
					}
				}));
			}
		},
		setTheme: function (theme) {
			var linkElement = document.getElementById('stylesheet-theme');

			var newLinkElement = document.createElement('link');
			newLinkElement.setAttribute('rel', 'stylesheet');
			newLinkElement.setAttribute('href', theme.stylesheet);

			var linkParent = linkElement.parentNode;
			linkParent.insertBefore(newLinkElement, linkElement);
			linkParent.removeChild(linkElement);

			newLinkElement.setAttribute('id', 'stylesheet-theme');

			document.body.setAttribute('class', theme.name);

			var linkElementOverridePrev = document.getElementById('stylesheet-override');

			if (linkElementOverridePrev) {
				linkElementOverridePrev.parentNode.removeChild(linkElementOverridePrev);
			}

			if (typeof theme['stylesheet-override'] !== 'undefined') {
				var linkElementOverride = document.createElement('link');
				linkElementOverride.setAttribute('rel', 'stylesheet');
				linkElementOverride.setAttribute('href', theme['stylesheet-override']);

				var linkElementOverridePost = document.getElementById('stylesheet-override-post');

				if(linkElementOverridePost){
					linkElementOverridePost.parentNode.insertBefore(linkElementOverride, linkElementOverridePost);
				}
			}
		},
		unSwitchUser: function () {
			var me = this;

			var conf = {
				data: {
					requestType: 'unSwitchUser'
				},
				success: function (response) {
					window.location.reload();
				},
				error: function (response) {
					me.backend.showError(response);
				}
			};

			me.backend.STServerComm.sendAjax(conf);
		},
		loadProfileEdit: function () {
			var me = this;

			var conf = {
				data: {
					requestType: 'getProfilePage',
					sync: true
				},
				success: function (response) {
					window.open(response.data.url, '_blank');
				},
				error: function (response) {
					me.backend.showError(response);
				}
			};

			me.backend.STServerComm.sendAjax(conf);
		},
		destroy: function () {
			var me = this;

			me.userMenu.destroyRecursive();
			delete me.userMenu;

			me.BTUser.destroyRecursive();
			delete me.BTUser;

			me.languageMenu.destroyRecursive();
			delete me.languageMenu;

			me.languageMenuItem.destroyRecursive();
			delete me.languageMenuItem;

			me.inherited(arguments);
		}
	});
});