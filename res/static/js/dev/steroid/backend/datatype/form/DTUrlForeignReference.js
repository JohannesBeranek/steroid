define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/dnd/DropContainer",
	"dojo/dom-construct",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"steroid/backend/dnd/PageUrlJoinRecord",
	"dojox/lang/functional"
], function (declare, _DTFormFieldMixin, DropContainer, domConstruct, i18nRC, i18nErr, PageUrlJoinRecord, langFunc) {

	return declare([_DTFormFieldMixin, DropContainer], {
		urlRecords: null,
		RCPageUrlConfig: null,
		RCUrlConfig: null,
		valueWatches: null,
		displayNode: null,

		constructor: function () {
			var me = this;

			me.urlRecords = [];
			me.valueWatches = [];
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			me.RCPageUrlConfig = me.backend.getClassConfigFromClassName('RCPageUrl');
			me.RCUrlConfig = me.backend.getClassConfigFromClassName('RCUrl');
		},
		postCreate: function () {
			var me = this;

			me.displayNode = domConstruct.create('div', { "class": 'STDisplayTextNode' });

			me.domNode.appendChild(me.displayNode);

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		},
		_setValueAttr: function (value) {
			var me = this;

			if (value && value.urls) {
				me.incomingValueCount = langFunc.keys(value.urls).length;

				for (var i in value.urls) {
					var urlRecord = new PageUrlJoinRecord({
						toggleable: true,
						backend: me.backend,
						ownClassConfig: me.RCPageUrlConfig,
						inlineClassConfig: me.RCUrlConfig,
						inlineSubstitutionFieldName: me.fieldConf.selectableRecordClassConfig.fieldName,
						submitName: me.submitName,
						owningRecordClass: me.owningRecordClass,
						mainClassConfig: me.mainClassConfig,
						readOnly: false,
						onlyTitleEditable: false
					});

					urlRecord.startup();

					me.addItem(urlRecord, me.items.length);

					urlRecord.set('value', value.urls[i]);

					me.valueWatches.push(urlRecord.watch('STValue', function (name, oldValue, newValue) {
						me.valueChange();
					}));
				}
			} else {
				me.valueComplete();
			}

			var text = '';

			if (value && value.primary) {
				switch (value.primary) {
					case 'NO_DOMAIN':
						text = i18nErr.url.no_domain;
						break;
					case 'NO_LIVE':
						text = i18nErr.url.no_live;
						break;
					default:
						text = '<a href="' + value.primary + '" target="_blank">' + value.primary + '</a>';
						break;
				}

				me.displayNode.innerHTML = i18nRC.RCUrl.currentLive + ': ' + text;
			}
		},
		destroy: function () {
			var me = this;

			for (var i = 0, ilen = me.valueWatches.length; i < ilen; i++) {
				me.valueWatches[i].unwatch();
			}

			me.valueWatches = [];

			domConstruct.destroy(me.displayNode);
			domConstruct.destroy(me.displayNodeLabel);

			me.inherited(arguments);
		},
		valueChange: function () {
			var me = this;

			me.set('STValue', me.STSetValue(me.get('value')));
		},
		getDirtyNess: function () {
			var me = this;

			return me.getArrayDirtyNess() + me.getItemDirtyNess();
		}
	});
});