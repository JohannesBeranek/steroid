define([
	"dojo/_base/declare",
	"steroid/backend/mixin/ResizeHandle",
	"dojo/dom-style",
	"dojo/Stateful",
	"dojo/_base/lang",
	"dojo/Deferred",
	"dojo/_base/array",
	"dojo/dom-geometry",
	"dojo/dom-class"
], function (declare, ResizeHandle, domStyle, Stateful, lang, Deferred, array, domGeom, domClass) {

	return declare([Stateful], {

		resizeNode:'domNode',
		sizeableHoriz: false,
		sizeableVert: false,
		resizeHandle: null,
		"class": 'STResizeable',
		parentDimensions: null,
		ownDimensions: null,
		currentDimensions: null,
		resizeHandleVisible: true,
		minDimensions: null,
		ownDimensionsSet: null,
		parentContainerPadding: 0,
		pixelBased: true,

		constructor: function() {
			var me = this;
			
			me.parentDimensions = {};
			me.ownDimensions = {};
			me.currentDimensions = {};
			me.minDimensions = {
				w: 100,
				h: 100
			};
			me.ownDimensionsSet = new Deferred();
		},
		postCreate: function(){
			var me = this;

			me.inherited(arguments);

			domClass.add(me[me.resizeNode], 'STResizeable');

			me.pixelBased = me.parentWidget.pixelBased;

			me.setupResizeHandle();
		},
		setupResizeHandle: function(){
			var me = this;

			me.createResizeHandle();
		},
		createResizeHandle: function(){
			var me = this;

			me.resizeHandle = new ResizeHandle({
				targetContainer:me[me.resizeNode],
				animateSizing:false,
				pixelBased: me.pixelBased,
				resizeAxis: (me.sizeableHoriz ? 'x' : '') + (me.sizeableVert ? 'y' : ''),
				owner:me,
				style:me.resizeHandleVisible ? 'display: block' : 'display: none'
			}).placeAt(me[me.resizeNode]);
		},
		isValidDimension: function(dim) {
			// FIXME: why should this ever be an array?
			var val = lang.isArray(dim) && dim.length ? dim[0] : dim;

			return !isNaN(parseInt(val, 10));
		},
		unifyDimensions: function(dim, current) {
			var me = this;

			if (dim && (me.isValidDimension(dim.w) || me.isValidDimension(dim.h) )) {
				var newVal = {};
	
				newVal.w = dim.w || current.w;
				newVal.h = dim.h || current.h;
	 
				return newVal;
			}
			
			return false;
		},
		setParentDimensions: function(dim) {
			var me = this, newVal;

			if (newVal = me.unifyDimensions(dim, me.parentDimensions)) {			
				me.parentDimensions = newVal;

				me.parentDimensionsChanged();
			} // FIXME: under which circumstances should dim be invalid? shouldn't those cases be handled?
		},
		_setOwnDimensionsAttr: function(dim){
			var me = this, newVal;

			if (newVal = me.unifyDimensions(dim, me.ownDimensions)) {
				if (me.sizeableHoriz) {
					me.doResize('w');
				}
	
				if (me.sizeableVert) {
					me.doResize('h');
				}
	
				if (!me.ownDimensionsSet.isResolved()) {
					me.ownDimensionsSet.resolve(newVal);
				}
	
				me.set('ownWidth', newVal.w);
	
				me.inherited(arguments, [newVal]);
			}
		},
		setCurrentDimensions: function(dim) {
			var me = this;

			if (dim && (me.isValidDimension(dim.w) || me.isValidDimension(dim.h))) {
				me.currentDimensions = { w: dim.w || me.ownDimensions.w, h: dim.h || me.ownDimensions.h };
	
				me.currentDimensionsChanged();
			}
		},
		parentDimensionsChanged: function() {
			var me = this;

			var newCurrentDimensions = {};

			if (me.sizeableHoriz) {
				newCurrentDimensions.w = Math.max(me.checkConstraints(me.currentDimensions.w, me.parentDimensions.w, true), me.minDimensions.w);
			}

			if (me.sizeableVert) {
				newCurrentDimensions.h = Math.max(me.checkConstraints(me.currentDimensions.h, me.parentDimensions.h, true), me.minDimensions.h);
			}

			me.setCurrentDimensions(newCurrentDimensions);
		},
		currentDimensionsChanged: function(){
			var me = this;

			var newOwnDimensions = {};

			if (me.sizeableHoriz) {
				newOwnDimensions.w = me.recalculateOwnDimensions('w');
			}

			if (me.sizeableVert) {
				newOwnDimensions.h = me.recalculateOwnDimensions('h');
			}

			me.set('ownDimensions', newOwnDimensions);
		},
		doResize: function(dim){
			var me = this;

			var currentDim = me.currentDimensions[dim];

			var max = me.getMax(dim, me.parentDimensions);

			var dimensionName = dim === 'w' ? 'width' : 'height';

			domStyle.set(me[me.resizeNode], dimensionName, '');

			var valueString = (currentDim >= max) ? me.getStringForCurrentEqualsMax(me.mutateDomDimension(dim, currentDim)) : me.getStringForCurrentLessThanMax(me.mutateDomDimension(dim, currentDim), me.parentWidget.currentDimensions[dim]);

			if (valueString !== null) {
				domStyle.set(me[me.resizeNode], dimensionName, valueString);
			}
		},
		mutateDomDimension: function(dim, value){
			var me = this;

			return value;
		},
		getStringForCurrentEqualsMax: function(value){
			return '100%';
		},
		getStringForCurrentLessThanMax: function(value, parentDimension){
			var me = this;

			if(me.pixelBased){
				return value + 'px';
			} else {
				return Math.floor((value/parentDimension)*100) + '%';
			}
		},
		recalculateOwnDimensions: function(dim) { // set the new possible own dimensions (mostly for children) based on the current dimension
			var me = this;

			if(me.parentDimensions[dim]){
				var dims = [];

				for (var i = 0; i < me.parentDimensions[dim].length; i++) {
					if (me.parentDimensions[dim][i] <= me.currentDimensions[dim]) {
						dims.push(lang.clone(me.parentDimensions[dim][i]));
					}
				}
			} else {
				dims = [me.currentDimensions[dim]];
			}

			return dims;
		},
		getMax: function(dim, dimensions){
			var me = this;

			if (!dimensions || !dimensions[dim] || dimensions[dim] < me.minDimensions[dim]) {
				return me.minDimensions[dim];
			}

			return Math.max.apply(Math, dimensions[dim]);
		},
		checkConstraints:function (newValue, constraints, noConversion) {
			var me = this;

			if(!newValue){
				return constraints && constraints[0] ? constraints[0] : 0;
			}

			if (!constraints) {
				return newValue;
			}

			function closest(num, arr) {
				var curr = arr[0];
				var diff = Math.abs(num - curr);
				for (var val = 0; val < arr.length; val++) {
					var newdiff = Math.abs(num - arr[val]);
					if (newdiff < diff) {
						diff = newdiff;
						curr = arr[val];
					}
				}

				return curr;
			}

			return closest(newValue, constraints);
		},
		destroy: function(){
			var me = this;

			if(!me.ownDimensionsSet.isResolved()){
				me.ownDimensionsSet.resolve();
				delete me.ownDimensionsSet;
			}

			if(me.resizeHandle){
				me.resizeHandle.destroyRecursive();
			}

			delete me.resizeHandle;

			me.inherited(arguments);
		}
	});
});
