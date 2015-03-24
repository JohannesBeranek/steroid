define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTDateTime",
	"dijit/form/MappedTextBox",
	"dijit/form/DateTextBox",
	"dijit/form/TimeTextBox",
	"dojo/dom-style",
	"dojo/dom-attr"
], function (declare, _DTFormFieldMixin, _DTDateTime, MappedTextBox, DateTextBox, TimeTextBox, domStyle, domAttr) {

	return declare([MappedTextBox, _DTFormFieldMixin, _DTDateTime], {

		datePicker: null,
		timePicker: null,
		"class": 'STDateTimePicker',
		dateWatch: null,
		timeWatch: null,

		constructor: function () {
			this.datePicker = null;
			this.timePicker = null;
			this.dateWatch = null;
			this.timeWatch = null;
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.datePicker = new DateTextBox({});
			me.datePicker.startup();
			me.domNode.appendChild(me.datePicker.domNode);

			me.timePicker = new TimeTextBox({});
			me.timePicker.startup();
			me.domNode.appendChild(me.timePicker.domNode);

			me.addValueSetListener(function () {
				me.dateWatch = me.datePicker.watch('value', function (name, oldValue, newValue) {
					me.valueChanged();
				});

				me.timeWatch = me.timePicker.watch('value', function (name, oldValue, newValue) {
					me.valueChanged();
				});
			});
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			if (me.isReadOnly()) {
				readOnly = true;
			}

			me.inherited(arguments);

			me.addInitListener(function () {
				if (me.datePicker) {
					me.datePicker.set('readOnly', readOnly);
				}

				if (me.timePicker) {
					me.timePicker.set('readOnly', readOnly);
				}
			});
		},
		valueChanged: function () {
			var me = this;

			var date = me.datePicker.get('value');
			var time = me.timePicker.get('value');

			if (date && time) {
				var dateString = me.getDateString(date);
				var timeString = me.getTimeString(time);

				me.set('value', dateString + ' ' + timeString);
			}
		},
		destroy: function () {
			var me = this;

			me.dateWatch.unwatch();
			me.timeWatch.unwatch();

			me.datePicker.destroyRecursive();
			me.timePicker.destroyRecursive();

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			domAttr.set(me.focusNode, 'type', 'hidden');

			me.initComplete();
		},
		_setValueAttr: function (value) {
			var me = this;

			var date = new Date(value ? value.replace(/\-/g, '\/') : null); // need to convert to YYYY/MM/DD or firefox and IE won't recognize as valid date

			if (value && date != 'Invalid Date') {
				value = value.substr(0, value.length - 2) + '00';
				me.setPickerValues(date);
			}

			me.inherited(arguments, [value]);
		},
		getDateString: function (date) {
			var me = this;

			var year = date.getFullYear();
			var month = date.getMonth() + 1;
			var day = date.getDate();

			return year + '-' + (month < 10 ? '0' + month : month) + '-' + (day < 10 ? '0' + day : day);
		},
		getTimeString: function (date) {
			var me = this;

			var hours = date.getHours();
			var minutes = date.getMinutes();
			var seconds = date.getSeconds();

			return (hours < 10 ? ('0' + hours) : hours) + ':' + (minutes < 10 ? ('0' + minutes) : minutes) + ':' + (seconds < 10 ? ('0' + seconds) : seconds);
		},
		setPickerValues: function (date) {
			var me = this;

			var dateString = me.getDateString(date);
			var timeString = 'T' + me.getTimeString(date);

			me.datePicker.set('value', dateString);
			me.timePicker.set('value', timeString);
		},
		reset: function () {
			var me = this;

			me.datePicker.set('value', null);
			me.timePicker.set('value', null);

			me.inherited(arguments);
		}
	});
});