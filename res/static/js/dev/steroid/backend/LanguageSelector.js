define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dijit/MenuBarItem",
	"dojo/i18n!steroid/backend/nls/LanguageSelector",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"steroid/backend/LanguageMenuItem"
], function(declare, _WidgetBase, MenuBarItem, i18n, DropDownMenu, PopupMenuBarItem, LanguageMenuItem){
	return declare([_WidgetBase], {
		languageMenu: null,
		languageMenuItem: null,
		menuBar: null,

		constructor: function(){
			this.languageMenu = null;
			this.languageMenuItem = null;
			this.menuBar = null;
		},
		postCreate: function(){
			var me = this;

			var availableLanguages = me.backend.config.system.languages.available;

			me.languageMenu = new DropDownMenu({});

			me.languageMenuItem = new PopupMenuBarItem({
				label:me.backend.config.system.languages.current.title,
				popup:me.languageMenu,
				style:'float:right;'
			});

			for (var i = 0; i < availableLanguages.length; i++) {

				var language = availableLanguages[i];

				var item = new LanguageMenuItem({
					label:language.title,
					STLanguage:language,
					backend:me.backend
				});

				me.languageMenu.addChild(item);
			}

			me.menuBar.addChild(me.languageMenuItem);
		},
		destroy: function(){
			var me = this;

			me.languageMenu.destroyRecursive();
			delete me.languageMenu;

			me.languageMenuItem.destroyRecursive();
			delete me.languageMenuItem;

			delete me.menuBar;

			me.inherited(arguments);
		}
	});
});