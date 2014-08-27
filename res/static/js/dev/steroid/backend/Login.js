define([
	"dojo/_base/declare",
	"dijit/form/Form",
	"dijit/form/ValidationTextBox",
	"dojox/form/BusyButton",
	"dojox/layout/TableContainer",
	"steroid/backend/ServerComm",
	"steroid/backend/Dialog",
	"dojo/i18n!steroid/backend/nls/login",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dojo/dom-style",
	"steroid/backend/LanguageSwitch",
	"dijit/Tooltip",
	"dojo/_base/lang",
	"dojo/_base/kernel",
	"dojo/io-query",
	"dojo/dom-construct"
], function (declare, Form, TextBox, Button, TableContainer, STServerComm, Dialog, i18n, i18nErr, domStyle, STLanguageSwitch, Tooltip, lang, kernel, query, domConstruct) {
	return declare(Dialog, {
		config: null,

		autofocus: true,

		tableContainer: null,

		userField: null,
		passField: null,
		formStateWatch: null,

		langSwitch: null,

		form: null,

		contentNode: null,
		closable: false,

		preamble: function () {
			this.inherited(arguments);
			this.title = i18n.title;
		},

		postCreate: function () {
			this.inherited(arguments);

			var me = this;

			me.BELanguages = me.config['interface'].languages;

			me.init();

			me.createUI();
		},
		createUI: function () {
			var me = this;

			me.tableContainer = new TableContainer({
				orientation: 'vert'
			});

			me.form.containerNode.appendChild(me.tableContainer.domNode);

			me.userField = new TextBox({
				name: 'username', // TODO: softcode
				type: 'text',
				required: true,
				trim: true,
				placeHolder: "user.name@domain.com",
				invalidMessage: i18n.invalidUsername,
				label: i18n.labelUsername
			}).placeAt(me.tableContainer);


			me.passField = new TextBox({
				name: 'password', // TODO: softcode
				type: 'password',
				required: true,
				invalidMessage: i18n.invalidPassword,
				trim: true,
				placeHolder: '1234',
				label: i18n.labelPassword
			}).placeAt(me.tableContainer);

			me.form.submitButton = new Button({
				label: i18n.labelLogin,
				type: 'submit',
				style: 'float: right;',
				spanLabel: true,
				disabled: true,
				busyLabel: i18n.labelLoginBusy,
				onClick: function (e) {
					return me.form.submit();
				}
			}).placeAt(me.tableContainer);

			me.formStateWatch = me.form.watch('state', function (property, oldValue, newValue) {
				me.form.submitButton.set('disabled', newValue !== '' || me.form.submitButton.isBusy);
			});

			me.tableContainer.startup(); // layout - tableContainer implementation calls startup on all not yet started child widgets!

			me.contentNode = domConstruct.create("div");
			me.contentNode.appendChild(me.form.domNode);

			me.set('content', me.contentNode);

			me.createLangSwitch();
		},
		switchLanguage: function (language) {
			var me = this;

			me.STServerComm.switchBELang(language);
		},
		createLangSwitch: function () {
			var me = this;

			me.langSwitch = new STLanguageSwitch({
				style: 'float:right;display:table-cell',
				backend: me,
				parentContainer: me.titleBar
			});

			domStyle.set(me.titleBar, {
				overflow: 'hidden',
				display: 'table',
				width: '100%'
			});
			domStyle.set(me.titleNode, {
				display: 'table-cell',
				verticalAlign: 'middle'
			});
		},
		init: function () {
			var me = this;

			me.STServerComm = new STServerComm({ backend: me });

			me.form = new Form({
				encType: 'multipart/form-data',
				action: '',
				method: 'post',
				onSubmit: function (event) { // TODO: dojox.busyButton sets busy _after_ onSubmit, which in theory could give us a race condition
					if (me.form.validate()) {
						me.form.submitButton.makeBusy(); // needed because of people pressing return key ; this also makes submitButton value unavailable to form.getValues()

						// me.resize(); //  does not fix dialog box size

						var data = me.form.getValues();

						var currentQueryObj = query.queryToObject(window.location.search.slice(1));

						if (currentQueryObj.beLang) {
							data.beLang = currentQueryObj.beLang;
						}

						data.requestType = 'login';
						data.login = me.config.login.class; // FIXME: move out of core!


						var conf = {
							data: data,
							success: lang.hitch(me, 'loginSuccess'),
							error: lang.hitch(me, 'loginFail')
						};

						me.STServerComm.sendAjax(conf);
					}

					if (event) {
						event.preventDefault();
					}

					return false;

				}
			});
		},
		loginSuccess: function (response) {
			var me = this;

			if (response.data.interface.languages.current == kernel.locale) {
				require(["steroid/backend/Backend"], function (Backend) {
//					if (window.Backend) {
//						// FIXME: why is this necessary?
					// FIXME: why doesn't this work?
//						return;
//					}

					window.Backend = new Backend({ config: response.data });
				});

				dijit.hideTooltip(me.form.submitButton.domNode);
				me.destroyRecursive();
			} else {
				window.location.reload();
			}
		},
		loginFail: function (response) {
			var me = this;

			var err = i18nErr[response.error] ? i18nErr[response.error].title : i18nErr.generic.title;

			me.form.submitButton.cancel();

			dijit.showTooltip('<span class="errorMessageInline">' + err + '</span>', me.form.submitButton.domNode);
		},
		destroy: function () {
			var me = this;

			me.tableContainer.destroyRecursive();
			delete me.tableContainer;

			me.userField.destroyRecursive();
			delete me.userField;

			me.passField.destroyRecursive();
			delete me.passField;

			me.formStateWatch.unwatch();
			delete me.formStateWatch;

			me.STServerComm.destroyRecursive();
			delete me.STServerComm;

			me.langSwitch.destroyRecursive();
			delete me.langSwitch;

			me.form.submitButton.destroyRecursive();
			delete me.form.submitButton;

			me.form.destroyRecursive();
			delete me.form;

			domConstruct.destroy(me.contentNode);
			delete me.contentNode;

			me.inherited(arguments);
		}
	});
});