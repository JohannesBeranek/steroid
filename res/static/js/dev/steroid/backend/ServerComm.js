define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dojo/request",
	"dijit/Dialog",
	"dojo/i18n!steroid/backend/nls/ServerComm",
	"dojo/io-query",
	"dojo/_base/lang",
	"dojox/lang/functional",
	"dojo/request/iframe",
	"dojo/dom-form",
	"dojo/json"
], function (declare, _WidgetBase, request, Dialog, i18n, query, lang, langFunc, requestIframe, domForm, JSON) {
	return declare([_WidgetBase], {
		backend: null,
		loading: false,

		constructor: function () {
			this.backend = null;
		},
		postMixInProperties: function () {
			this.inherited(arguments);

			var me = this;
			
			me.setConf(me.backend.config);
			
			var handleError = (function() {
				var sc = me;
								
				return function () {
					// TODO: add stack trace (use stacktrace.js!)
					sc.sendAjax({ data: { requestType: 'log', msg: JSON.stringify(arguments) } });
				};
			})();


			window.onerror = function (msg, url, line, col, errorObject) {
				handleError.apply(this, arguments);
			};

			if (window.console) {
				var oldErr = window.console.error;
				window.console.error = function () {
					try {
						oldErr.apply(window.console, arguments);
					} catch(e) {}
					
					handleError.apply(this, arguments);
				};
			}
		},
		setConf: function (config) {
			var me = this;

			me.interfaceUrl = config['interface'].basePath;
			me.ajaxQuery = config['interface'].ajaxQuery;
		},
		switchBELangAjax: function (language) {
			var me = this;

			var conf = {
				data: {
					requestType: 'changeBELang',
					beLang: language
				}
			};

			return me.sendAjax(conf);
		},
		endEditing: function (recordClassName, record, sync) {
			var me = this;

			if (record && record.primary && record.primary !== 'new') {
				var conf = {
					data: {
						requestType: 'endEditing',
						recordClass: recordClassName,
						recordID: record.primary,
						sync: sync || false
					}
				};

				me.sendAjax(conf);
			}
		},
		reloadBackend: function () {
			var me = this;

			window.location.href = me.interfaceUrl;
		},
		switchBELang: function (language) {
			var me = this;

			var currentQueryObj = query.queryToObject(window.location.search.slice(1));

			currentQueryObj.beLang = language;

			window.location.href = me.interfaceUrl + '?' + query.objectToQuery(currentQueryObj) + window.location.hash;
		},
		sendAjax: function (conf) {
			var me = this;

			me.loading = true;

			var errorHandler = conf.error ? conf.error : lang.hitch(this, 'error');
			var successHandler = conf.success ? conf.success : lang.hitch(this, 'success');

			var url = me.interfaceUrl + '?' + query.objectToQuery(me.ajaxQuery);

			var options = { handleAs: 'json' }; // FIXME: timeout param

			if (conf.data) {
				options.data = conf.data;
				options.sync = conf.data.sync || false;
			}

			options.timeout = 1000 * 60 * 10;

			var def = request.post(url, options).then(function (response) {
				me.loading = false;

				if (response.success) {
					successHandler(response);
				} else {
					errorHandler(response);
				}

				return response;
			}, function (response) {
				me.loading = false;

				errorHandler(response);

				return response;
			}); // .then returns deferred


			return def;
		},
		sendForm: function (form, baseQuery) {
			var me = this;

			var def = requestIframe(me.interfaceUrl, {
				query: dojo.mixin(baseQuery, me.ajaxQuery),
				handleAs: 'json',
				form: form.domNode // TODO: preventCache, timeout params
			});

			return def;
		},
		success: function (response) {
			// anything todo?
		},
		error: function (response) {
			var me = this;

			if (me.backend) {
				me.backend.showError(response);
			}
		},
		destroyRecursive: function () {
			this.destroy();
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});