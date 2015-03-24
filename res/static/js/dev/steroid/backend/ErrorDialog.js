define([
	"dojo/_base/declare",
	"steroid/backend/Dialog",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/DetailPane",
	"dijit/form/Button",
	"dojo/dom-construct",
	"steroid/backend/Localizor"
], function (declare, Dialog, i18nErr, i18nRC, i18nDP, Button, domConstruct, Localizor) {
	return declare([Dialog], {
		response: null,
		onClose: null,
		style: 'min-width: 200px;',
		contentArea: null,
		actionBar: null,
		BtnOK: null,
		BtnMore: null,
		backend: null,
		

		constructor: function () {
			this.response = {};

			this.inherited(arguments);
		},
		postCreate: function () {
			var me = this, message = '';

			if(i18nErr[me.response.error] && i18nErr[me.response.error].message){
				message = i18nErr[me.response.error].message;

				if(me.response.data){
					switch(me.response.error){
						case 'AffectedReferencesException':

						break;
						default:
							for(var key in me.response.data){
								var val = me.response.data[key];

								switch(key){
									case 'rc':
										var className = i18nRC[val + '_name'] || null;

										if(!className){
											var classConfig = me.backend.getClassConfigFromClassName(val);

											if(classConfig.i18nExt){
												className = classConfig.i18nExt[val + '_name'] || val;
											}
										}

										val = className || val;
									break;
									case 'field':
										var classConfig = me.backend.getClassConfigFromClassName(me.response.data.rc);								
										var fieldConf = classConfig.formFields[val];

										var localizor = new Localizor({
											backend: me.backend
										});

										var fieldLabel = localizor.getFieldLabel(fieldConf, classConfig.className, val, classConfig.i18nExt);

										val = fieldLabel || val;
									break;
									case 'action':
										val = i18nDP['BT' + val + 'Record'];
									break;
								}

								message = message.replace(('$' + key), val);
							}
						break;
					}
				}
			} else {
				message = me.response.message;
			}

			me.contentArea = domConstruct.create('div', { "class": 'dijitDialogPaneContentArea', innerHTML: message }, me.containerNode);
			me.actionBar = domConstruct.create('div', { "class": 'dijitDialogPaneActionBar' }, me.containerNode);

			me.BtnOK = new Button({
				label: i18nErr.BtnOK,
				onClick: function () {
					if (me.onClose) {
						me.onClose(me.response.error);
					}

					me.hide();
				}
			});

			me.BtnOK.placeAt(me.actionBar);

//			domConstruct.create('div', { style: 'margin-top: 20px;', innerHTML: me.errorText }, me.contentArea);

			me.set('title', i18nErr[me.response.error] ? i18nErr[me.response.error].title : i18nErr.generic.title);

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			domConstruct.destroy(me.contentArea);
			delete me.contentArea;

			domConstruct.destroy(me.actionBar);
			delete me.actionBar;

			me.BtnOK.destroyRecursive();
			delete me.BtnOK;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});