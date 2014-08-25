define([
	"dojo/_base/declare"
], function (declare) {

	return declare([], {

		initListeners: null,
		initListenersOnce: null,
		valueSetListeners: null,
		valueSetListenersOnce: null,
		initialized: false,
		valueSet: false,
		valueSetListenerCount: 0,

		constructor: function () {
			this.initListeners = [];
			this.valueSetListeners = {};
			this.valueSetListenersOnce = [];
		},
		addInitListener: function (func) {
			var me = this;

			if (!func) {
				return;
			}

			if (me.initialized) {
				func(me);
			} else {
				me.initListeners.push(func);
			}
		},
		clearInitListeners: function (andExecute) {
			var me = this;

			if (andExecute && me.initListeners.length) {
				for (var i = 0, item; item = me.initListeners[i]; i++) {
					item(me);
				}
			}

			me.initListeners = [];

			me.initialized = false;
		},
		initComplete: function () {
			var me = this;

			if (me.initListeners.length) {
				for (var i = 0, item; item = me.initListeners[i]; i++) {
					item(me);
				}
			}

			me.initialized = true;
		},
		addValueSetListener: function (func) {
			var me = this;

			me.valueSetListenerCount++;
			me.valueSetListeners[me.valueSetListenerCount] = func;

			if (me.valueSet) {
				func(me);
			}

			return me.valueSetListenerCount;
		},
		removeValueSetListener: function (key) {
			var me = this;

			if (typeof me.valueSetListeners[key] !== 'undefined') {
				delete me.valueSetListeners[key];
				return true;
			}

			return false;
		},
		addValueSetListenerOnce: function (func) {
			var me = this;

			if (me.valueSet) {
				func(me);
			} else {
				me.valueSetListenersOnce.push(func);
			}
		},
		valueComplete: function () {
			var me = this;

			for (var i in me.valueSetListeners) {
				me.valueSetListeners[i](me);
			}

			/* if (me.valueSetListenersOnce.length) {
				for (var i = 0, item; item = me.valueSetListenersOnce[i]; i++) {
					item(me);
				}
			} 

			me.valueSetListenersOnce = [];
			*/

			// JB 21.01.2014 In contrast to a for loop, this avoids infinite loops when a valueSetListenersOnce entry calls set again 
			while(me.valueSetListenersOnce.length) {
				var item = me.valueSetListenersOnce.shift();
				item(me);
			}

			me.valueSet = true;
		},
		reset: function () {
			var me = this;

			me.valueSet = false;
		},
		destroy: function () {
			var me = this;

			me.initListeners = [];
			me.valueSetListeners = {};

			me.inherited(arguments);
		}
	});
});
