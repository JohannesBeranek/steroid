define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTFile",
	"dijit/form/ValidationTextBox",
	"dojo/_base/lang",
	"dojo/dom-construct",
	"dojo/io-query",
	"dojo/on",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _DTFormFieldMixin, _DTFile, ValidationTextBox, lang, domConstruct, query, on, i18nRC) {

	return declare([ValidationTextBox, _DTFormFieldMixin, _DTFile], {
		origImgSrc: null,
		containerNode: null,
		isImage: false,

		postMixInProperties: function () {
			var me = this;

			me.type = 'hidden';
			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.containerNode = domConstruct.create('div', { "class": 'STFileFieldContainer' });

			domConstruct.place(me.containerNode, me.domNode, 'after');

			me._initValueNode();

			me.initComplete();
		},
		_initValueNode: function () {
			var me = this;

			if (me.readOnly) {
				return;
			}

			me.valueNode = domConstruct.create('input', { type: 'file', 'class': 'DTFileInput' });

			domConstruct.place(me.valueNode, me.containerNode);

			me._listener = on(me.valueNode, "change", function (evt) {
				me._matchValue(evt);
			});
			me._keyListener = on(me.valueNode, "keyup", function (evt) {
				me._matchValue(evt);
			});
		},
		reset: function () {
			var me = this;

			if (typeof me.valueNode != 'undefined') {
				me._listener.remove();
				me._keyListener.remove();

				domConstruct.destroy(me.valueNode);
			}

			me._initValueNode();

			me.inherited(arguments);
		},
		_matchValue: function (event) {
			// summary: set the content of the upper input based on the semi-hidden file input
			var me = this;

			if (me.valueNode.value) {
				me.set('value', me.valueNode.value);
			}

			if (typeof event != 'undefined' && typeof event.target != 'undefined' && typeof event.target.files != 'undefined' && typeof FileReader != 'undefined') {
				var files = event.target.files;

				if (files.length === 1) {
					var file = files[0];

					if (file.type === 'image/png' || file.type === 'image/jpeg' || file.type === 'image/gif') {
						var reader = new FileReader();

						reader.onload = (function (f, that) {
							return function (e) {
								that._ensureDisplayNode();
								that.displayNode.src = e.target.result;
								that.set('isImage', true);
							};
						})(file, me);

						reader.readAsDataURL(file);
					}
				} else { // remove current preview or reset it to previous one
					if (me.origImgSrc) {
						me._ensureDisplayNode();

						me.displayNode.src = me.origImgSrc;

						me.set('isImage', true);
					} else if (typeof me.displayNode != 'undefined') {
						domConstruct.destroy(me.displayNode);

						delete me.displayNode;

						me.set('isImage', false);
					}
				}
			}
		},
		validator: function (value) {
			return !!value;
		},
		isValid: function () {
			return !!this.STValue;
		},
		_setValueAttr: function (value) {
			var me = this;

			if (value && lang.isObject(value)) {
				var downloadUrl = me.backend.config["interface"].basePath + '?' + query.objectToQuery(dojo.mixin({ requestType: 'download', file: value.primary }, me.backend.config["interface"].ajaxQuery));

				me._ensureDownloadNode();

				if (typeof value.cached != 'undefined') { // image with preview
					me._ensureDisplayNode();

					me.displayNode.src = me.origImgSrc = value.cached;

					me.set('isImage', true);
				} else {
					me._removeDisplayNode();
					me.set('isImage', false);
				}


				me.downloadNode.href = encodeURI(downloadUrl);
				me.downloadNode.innerHTML = value.name;
			} else {
				me._removeDisplayNode();
				me._removeDownloadNode();

				me.set('isImage', false);
			}

			me.set('STValue', me.STSetValue(value));

			me.inherited(arguments);
		},
		_ensureDownloadNode: function () {
			var me = this;

			if (typeof me.downloadNode == 'undefined') {
				me.downloadNode = domConstruct.place('<a href="" class="STFileNameDownloadLink" target="_blank"></a>', me.containerNode);
			}
		},
		_removeDownloadNode: function () {
			var me = this;

			if (typeof me.downloadNode != 'undefined') {
				domConstruct.destroy(me.downloadNode);

				delete me.downloadNode;
			}
		},
		_ensureDisplayNode: function () {
			var me = this;

			if (typeof me.displayNode == 'undefined') {
				me.displayNode = domConstruct.place('<img class="STFileName" src="" />', me.containerNode);

				if (typeof me.downloadNode != 'undefined') {
					domConstruct.place(me.downloadNode, me.displayNode, 'after'); // make sure downloadNode is always the last
				}
			}
		},
		_removeDisplayNode: function () {
			var me = this;

			if (typeof me.displayNode != 'undefined') {
				domConstruct.destroy(me.displayNode);

				delete me.displayNode;
			}
		},
		_getStateAttr: function () {
			var me = this;

			if (me.STValue) {
				return '';
			}

			return me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			domConstruct.destroy(me.valueNode);

			me._removeDisplayNode();
			me._removeDownloadNode();

			if (me._listener) {
				me.disconnect(me._listener);
			}

			if (me._keyListener) {
				me.disconnect(me._keyListener);
			}

			domConstruct.destroy(me.containerNode);

			me.inherited(arguments);
		}
	});
});