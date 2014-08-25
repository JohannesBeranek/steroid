define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dijit/DropDownMenu",
	"dijit/form/DropDownButton",
	"dijit/MenuItem",
	"dojo/i18n!steroid/backend/nls/Languages",
	"dojo/io-query"
], function (declare, _WidgetBase, DropDownMenu, DropDownButton, MenuItem, i18n, query) {
	return declare([_WidgetBase], {

		STServerComm: null,
		langMenu: null,
		BTSwitch: null,
		parentContainer: null,

		constructor: function(){
			var me = this;

			me.STServerComm = null;
			me.langMenu = null;
			me.BTSwitch = null;
			me.parentContainer = null;
		},
		postCreate:function () {

			var me = this;

			me.STServerComm = me.backend.STServerComm;

			me.langMenu = new DropDownMenu({
				style:'display: none;'
			});

			me.BTSwitch = new DropDownButton({
				label: i18n[me.backend.BELanguages.current + '_short'],
				dropDown: me.langMenu,
				style: me.style
			});

			for(var i = 0; i < me.backend.config.interface.languages.available.length; i++){
				me.langMenu.addChild(new MenuItem({
					label: i18n[me.backend.config.interface.languages.available[i] + '_short'],
					lang:me.backend.config.interface.languages.available[i],
					onClick: function(){
						me.backend.STServerComm.switchBELang(this.lang);
					}
				}));
			}

			me.parentContainer.appendChild(me.BTSwitch.domNode);
		},
		destroy: function(){
			var me = this;

			me.STServerComm.destroyRecursive();
			delete me.STServerComm;

			me.langMenu.destroyRecursive();
			delete me.langMenu;

			me.BTSwitch.destroyRecursive();
			delete me.BTSwitch;

			delete me.parentContainer;

			me.inherited(arguments);
		}
	});
});