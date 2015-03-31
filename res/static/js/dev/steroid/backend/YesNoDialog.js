define([
	"dojo/_base/declare",
	"dijit/Dialog",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dijit/form/Button",
	"dojo/dom-construct"
], function (declare, Dialog, i18nErr, Button, domConstruct) {
	return declare([Dialog], {
		onYes: null,
		onNo: null,

		postMixInProperties: function(){
			var me = this;

			me.title = i18nErr[me.messageType] ? i18nErr[me.messageType].title : i18nErr.generic.title;
			me.message = i18nErr[me.messageType] ? i18nErr[me.messageType].message : i18nErr.generic.message;

			me.style = 'min-width:200px;';

			me.inherited(arguments);
		},
		postCreate: function(){
			var me = this;

			me.contentArea = domConstruct.create('div', { "class": 'dijitDialogPaneContentArea', innerHTML: me.message }, me.containerNode);
			me.actionBar = domConstruct.create('div', { "class": 'dijitDialogPaneActionBar' }, me.containerNode);

			me.BtnYes = new Button({
				label: i18nErr.BtnYes,
				onClick: function(){
					if(me.onYes){
						me.onYes();
					}

					if(!me._beingDestroyed){
						me.hide();
					}
				}
			});

			me.BtnYes.placeAt(me.actionBar);

			me.BtnNo = new Button({
				label:i18nErr.BtnNo,
				onClick:function () {
					if(me.onNo){
						me.onNo();
					}

					if (!me._beingDestroyed) {
						me.hide();
					}
				}
			});

			me.BtnNo.placeAt(me.actionBar);

			me.inherited(arguments);
		},
		destroy: function(){
			var me = this;

			domConstruct.destroy(me.contentArea);
			domConstruct.destroy(me.actionBar);

			me.BtnYes.destroyRecursive();
			delete me.BtnYes;

			me.BtnNo.destroyRecursive();
			delete me.BtnNo;

			me.inherited(arguments);
		}
	});
});