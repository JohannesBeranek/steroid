define([
	"dojo/_base/declare",
	"dijit/layout/ContentPane",
	"dojo/dom-style",
	"dojo/_base/array",
	"dojo/dom-construct",
	"dojo/aspect",
	"dojo/_base/array",
	"dojo/Deferred",
	"dojo/on",
	"dojo/_base/lang",
	"dojo/mouse",
	"dojo/_base/event",
	"dojo/dom-geometry",
	"dojo/dom-attr",
	"dojo/DeferredList",
	"steroid/backend/mixin/_hasStandBy",
	"steroid/backend/mixin/_hasInitListeners",
	"dojo/dom-class"
], function (declare, ContentPane, domStyle, array, domConstruct, aspect, array, Deferred, on, lang, mouse, event, domGeom, domAttr, DeferredList, _hasStandBy, _hasInitListeners, domClass) {
	return declare([ContentPane, _hasStandBy, _hasInitListeners], {
		itemContainerNode: null,
		placeHolder: null,
		items: null,
		incomingValueCount: null, // needed to know when to resolve the valuesSet Deferred if multiple values are set
		placeHolderWidth: '50px',
		placeHolderHeight: '22px',
		dndManager: null,
		originalItems: null,
		itemWatches: null,
		aspects: null,
		emptySubmitNode: null,
		submitName: null,
		class: 'STDropContainer',
		disabled: false,
		readOnly: false,

		constructor: function () {
			this.items = [];
			this.itemWatches = [];
			this.aspects = [];
		},
		postCreate: function () {
			var me = this;

			me.standByNode = me.domNode;

			me.itemContainerNode = domConstruct.create('div', { class: 'STDropContainer' });

			me.containerNode.appendChild(me.itemContainerNode);

			me.emptySubmitNode = domConstruct.create('input', { type: 'hidden' });

			me.containerNode.appendChild(me.emptySubmitNode);

			if (me.dndManager && !me.isReadOnly() && !me.disabled) {
				me.dndManager.registerContainer(me);
			}

			me.inherited(arguments);
		},
		isReadOnly: function () {
			var me = this;

			return (me.fieldConf && me.fieldConf.readOnly) || me.readOnly || me.startReadOnly;
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				if (me.dndManager && readOnly) {
					me.dndManager.unregisterContainer(me);
				}

				if (me.domNode) {
					if (readOnly) {
						domClass.add(me.domNode, 'STReadOnly');
					} else {
						domClass.remove(me.domNode, 'STReadOnly');
					}
				}

				me.setItemsReadOnly(readOnly);
			});
		},
		setItemsReadOnly: function (readOnly) {
			var me = this;

			if (me.items && me.items.length) {
				for (var i = 0, item; item = me.items[i]; i++) {
					item.set('readOnly', readOnly);
				}
			}
		},
		addInitListener: function (func) {
			var me = this;

			func(me);
		},
		dropValid: function (item) {
			var me = this;

			return item && (array.indexOf(me.accept, item.type) >= 0);
		},
		getArrayDirtyNess: function () {
			var me = this;

			var itemPrimaries = me.items.length ? [] : null;

			for (var i = 0; i < me.items.length; i++) {
				itemPrimaries[i] = me.items[i].getIdentity();
			}

			var compDirty = me.compareArray(itemPrimaries, me.originalItems);

			if (compDirty > 0) {
				return compDirty;
			}

			compDirty = me.compareArray(me.originalItems, itemPrimaries);

			return compDirty;
		},
		getItemDirtyNess: function () {
			var me = this;

			var dirtyNess = 0;

			for (var i = 0; i < me.items.length; i++) {
				var itemDirtyNess = me.items[i].getDirtyNess();

				dirtyNess += itemDirtyNess;
			}

			return dirtyNess;
		},
		compareArray: function (a, b) {
			var dirtyNess = 0;

			if (a) {
				for (var i = 0; i < a.length; i++) {
					if (!b || !b[i] || (b[i] + '') !== (a[i] + '')) {
						dirtyNess++;
					}
				}
			}

			return dirtyNess;
		},
		getDirtyNess: function () {
			var me = this;

			return me.getArrayDirtyNess();
		},
		_setDisabledAttr: function (disabled) {
			var me = this;

			me.inherited(arguments);

			me.disabled = disabled;

			for (var i = 0; i < me.items.length; i++) {
				me.items[i].set('disabled', disabled);
			}
		},
		addItem: function (item, dropIndex) {
			var me = this;

			if (item.currentContainer) {
				item.beforeDomMove();
			}

			domConstruct.place(item.domNode, me.itemContainerNode, dropIndex);

			if (item.currentContainer) {
				item.afterDomMove();

				if (item.currentContainer == me) {
					me.items.splice(array.indexOf(me.items, item), 1);

					me.itemWatches[item.ownIndexInParent].unwatch();
					me.itemWatches.splice(item.ownIndexInParent, 1);

					me.aspects[item.ownIndexInParent].remove();
					me.aspects.splice(item.ownIndexInParent, 1);

					if (item.ownIndexInParent < dropIndex) { // if we're moving an item further back, we need to subtract one from index because the item is still in the dom and thus counted
						dropIndex--;
					}
				} else {
					item.currentContainer.removeDraggable(item);
				}
			}

	

			// add watches, aspects to item for value change and destroyRecursive
			me.itemWatches.splice(dropIndex, 0, item.watch('STValue', function () {
				if (!me.backend.suspendValueWatches && !me.incomingValueCount) {
					me.set('STValue', me.get('value'));
				}
			}));

			me.aspects.splice(dropIndex, 0, aspect.before(item, 'destroyRecursive', function () {
				item.isClosing = true;
				me.itemRemoved(item);
			}));

			// set currentContainer
			item.currentContainer = me;


			// remove placeholder
			if (me.placeHolder) {
				domConstruct.destroy(me.placeHolder);
				delete me.placeHolder;
			}

			// add to items array
			me.items.splice(dropIndex, 0, item);

			// FIXME: what is this here for?
			item.addValueSetListenerOnce(function (itemWithValue) {
				if (me.incomingValueCount) {
					me.incomingValueCount--;
				}

				if (!me.incomingValueCount) {
					if (!me.originalItems) {
						me.originalItems = [];

						for (var i = 0; i < me.items.length; i++) {
							if (me.isOriginalItem(me.items[i])) {
								me.originalItems[i] = me.items[i].getIdentity();
							}
						}
					}

					me.resize();
					me.updateItemIndexes();

					me.currentlySettingValue = false;
					me.valueComplete();
				}
			});

			// FIXME: why do we return item?
			return item;
		},
		isOriginalItem: function (item) {
			return !item.isNew;
		},
		getDropIndex: function (append, item) {
			var me = this;

			if (append) {
				return me.itemContainerNode.childNodes.length;
			}

			if (!me.placeHolder && item.currentContainer == me) {
				return item.ownIndexInParent;
			}

			var idx = 0;

			for (var i = 0; i < me.itemContainerNode.childNodes.length; i++) {
				if (me.itemContainerNode.childNodes[i].id == me.placeHolder.id) {
					idx = i;
				}
			}

			return idx;
		},
		drop: function (item, append) {
			var me = this;

			me.addItem(item, me.getDropIndex(append, item));
		},
		setSubmitName: function (setName) {
			var me = this;

			if (me.get('disabled')) {
				setName = false;
			}

			for (var i = 0; i < me.items.length; i++) {
				me.items[i].setSubmitName(setName);
			}

			domAttr.set(me.emptySubmitNode, 'name', me.setEmptyName(setName) ? me.submitName + '[]' : '');
		},
		setEmptyName: function (setName) {
			var me = this;

			return setName && ((me.originalItems && me.originalItems.length) && !me.items.length);
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			for (var i = 0; i < me.items.length; i++) {
				me.items[i].updateSubmitName(me.submitName); // TODO: consider RTE once we implement RTEArea
			}

			if (domAttr.get(me.emptySubmitNode, 'name')) {
				domAttr.set(me.emptySubmitNode, 'name', me.submitName + '[]');
			}
		},
		itemRemoved: function (item) {
			var me = this;

			// FIXME: why do we do this?
			if (me._beingDestroyed || (me.backend.moduleContainer.detailPane && me.backend.moduleContainer.detailPane.form && me.backend.moduleContainer.detailPane.form.isResetting) || item.isClosing) {
				var thisShouldNotBeNecessaryIThink = domConstruct.create('div');

				domConstruct.place(item.domNode, thisShouldNotBeNecessaryIThink, 0);
			}

			me.items.splice(item.ownIndexInParent, 1);

			me.itemWatches[item.ownIndexInParent].unwatch();
			me.itemWatches.splice(item.ownIndexInParent, 1);

			me.aspects[item.ownIndexInParent].remove();
			me.aspects.splice(item.ownIndexInParent, 1);

			me.updateItemIndexes();

			return item;
		},
		removeDraggable: function (item) {
			this.itemRemoved(item);
		},
		updateItemIndexes: function () {
			var me = this;

			for (var i = 0; i < me.items.length; i++) {
				for (var j = 0; j < me.itemContainerNode.childNodes.length; j++) {
					if (me.itemContainerNode.childNodes[j].id == me.items[i].id) {

						me.items[i].indexChange(j, me._beingDestroyed);
						break;
					}
				}
			}

			if (!(me._beingDestroyed || (!me.isFilterPane && me.backend.moduleContainer.detailPane && me.backend.moduleContainer.detailPane._beingDestroyed))) {
				me.updateSubmitName(me.submitName);

				me.set('STValue', me.get('value'));
			}
		},
		getItemCount: function () {
			return this.items.length;
		},
		startDrag: function (currentDraggable) {
			var me = this;

			if (me.containerNode) {
				me.dragOverHandle = on(me.containerNode, mouse.enter, lang.hitch(me, 'dragOver'));
				me.dragOutHandle = on(me.containerNode, mouse.leave, lang.hitch(me, 'dragOut'));

				if (currentDraggable.currentContainer == me) {
					me.dragOver();
				}
			}
		},
		stopDrag: function () {
			var me = this;

			if (me.dragOverHandle) {
				me.dragOverHandle.remove();
			}

			if (me.dragOutHandle) {
				me.dragOutHandle.remove();
			}
		},
		dragOver: function (e) {
			var me = this;

			me.dndManager.registerDragOver(me);

			if (e) {
				event.stop(e);
			}
		},
		dragOut: function (e) {
			var me = this;

			if (me.placeHolder) {
				domConstruct.destroy(me.placeHolder);
				delete me.placeHolder;
			}

			me.dndManager.registerDragOut(me);

			if (e) {
				event.stop(e);
			}
		},
		showPlaceHolder: function (e, currentItem, avatar) {
			var me = this;

			var mouseX = e.pageX;
			var mouseY = e.pageY;

			if (!me.items.length) { // create a single placeholder
				if (me.placeHolder) {
					return;
				} else {
					me.placeHolder = me.createPlaceholder();
				}

				me.itemContainerNode.appendChild(me.placeHolder);
			} else {
				if (me.placeHolder) {
					var marginBox = domGeom.position(me.placeHolder);

					if (marginBox.x < mouseX && (marginBox.x + marginBox.w) > mouseX && marginBox.y < mouseY && (marginBox.y + marginBox.h > mouseY)) {
						//early out if cursor is inside placeholder
						return;
					}

					domConstruct.destroy(me.placeHolder);
					delete me.placeHolder;
				}

				var nearestitem = null;
				var nearestMarginBox = null;

				var minDist = Infinity;
				var minDistItem = null;
				var minDistItemMarginBox = null;
				var minDistPoint = null; // 0=topLeft, 1=topRight, 2=bottomRight, 3=bottomLeft
				var minDistPointData = null;

				var marginBoxes = [];
				var after = false;
				var hitItem = false;

				for (var i = 0, ii = me.items.length; i < ii; i++) { // group all items by Y distance
					var current = me.items[i];
					var marginBox = domGeom.position(current.domNode);

					// early check: is mouse inside item?
					if (mouseX >= marginBox.x && mouseX <= (marginBox.x + marginBox.w) && mouseY >= marginBox.y && mouseY <= (marginBox.y + marginBox.h)) {
						minDistItem = current;

						after = mouseX >= (marginBox.x + marginBox.w / 2);
						hitItem = true;
						break;
					}

					// compute distance to all 4 points ( clockwise; we don't use arrays to be faster )
					var xd = marginBox.x - mouseX, xsd = xd * xd,
						yd = marginBox.y - mouseY, ysd = yd * yd,
						x2 = marginBox.x + marginBox.w, y2 = marginBox.y + marginBox.h,
						x2d = x2 - mouseX, x2sd = x2d * x2d,
						y2d = y2 - mouseY, y2sd = y2d * y2d;

					// top left
					var d = Math.sqrt(xsd + ysd);

					if (d < minDist) {
						minDist = d;
						minDistItem = current;
						minDistPoint = 0;
						minDistItemMarginBox = marginBox;
						minDistPointData = { x: marginBox.x, y: marginBox.y };
					}

					// top right
					d = Math.sqrt(x2sd + ysd);

					if (d < minDist) {
						minDist = d;
						minDistItem = current;
						minDistPoint = 1;
						minDistItemMarginBox = marginBox;
						minDistPointData = { x: x2, y: marginBox.y };
					}

					// bottom right
					d = Math.sqrt(x2sd + y2sd);

					if (d < minDist) {
						minDist = d;
						minDistItem = current;
						minDistPoint = 2;
						minDistItemMarginBox = marginBox;
						minDistPointData = { x: x2, y: y2 };
					}

					// bottom left
					d = Math.sqrt(xsd + y2sd);

					if (d < minDist) {
						minDist = d;
						minDistItem = current;
						minDistPoint = 3;
						minDistItemMarginBox = marginBox;
						minDistPointData = { x: marginBox.x, y: y2 };
					}

					marginBoxes.push(marginBox);
				}

				if (minDistItem === currentItem || (minDistItem.ownIndexInParent + (after ? 1 : -1) == currentItem.ownIndexInParent)) { // mouse isn't far away enough from the item we're currently dragging, so don't bother
					return;
				}

				if (hitItem) {
					// inside item -> don't do anything (but finish, so placeholder gets placed)
				}
				// left of item
				else if ((minDistPoint === 0 || minDistPoint === 3) && mouseY >= minDistItemMarginBox.y && mouseY <= ( minDistItemMarginBox.y + minDistItemMarginBox.h )) {
					// place before item
				}
				// right of item
				else if ((minDistPoint === 1 || minDistPoint === 2) && mouseY >= minDistItemMarginBox.y && mouseY <= ( minDistItemMarginBox.y + minDistItemMarginBox.h )) {
					// place after item
					after = true;
				}
				// atop item
				else if (mouseY <= minDistItemMarginBox.y) {
					if (minDistItem === me.items[0]) {
						// nothing before item -> place before item
					} else { // otherwise find correct row for item
// DUP
						var found = false;
						var first = true;

						for (var n = 0, nn = marginBoxes.length; n < nn; n++) {
							var m = marginBoxes[n];

							if (mouseY >= m.y && mouseY <= (m.y + m.h)) {
								if (first) {
									if (mouseX <= (m.x + m.w / 2)) {
										after = false;
										found = true;
										minDistItem = me.items[n];
										break; // before first - done
									} else {
										after = true;
										found = true;
										minDistItem = me.items[n];
									}

									first = false;
								} else {
									if (mouseX >= (m.x + m.w / 2)) {
										after = true;
										found = true;
										minDistItem = me.items[n];
										// no break here - a different item might be closer
									}
								}
							}
						}
// DUP END					
						if (!found) { // no row for item found, place before

						}
					}
				}
				// below item
				else if (mouseY > (minDistItemMarginBox.y + minDistItemMarginBox.h)) {
					if (minDistItem === me.items[me.items.length - 1]) {
						// nothing after item -> place after item
						after = true;
					} else { // otherwise find correct row for item
						// DUP
						var found = false;
						var first = true;

						for (var n = 0, nn = marginBoxes.length; n < nn; n++) {
							var m = marginBoxes[n];

							if (mouseY >= m.y && mouseY <= (m.y + m.h)) {
								if (first) {
									if (mouseX <= (m.x + m.w / 2)) {
										after = false;
										found = true;
										minDistItem = me.items[n];
										break; // before first - done
									} else {
										after = true;
										found = true;
										minDistItem = me.items[n];
									}

									first = false;
								} else {
									if (mouseX >= (m.x + m.w / 2)) {
										after = true;
										found = true;
										minDistItem = me.items[n];
										// no break here - a different item might be closer
									}
								}
							}
						}
// DUP END					

						if (!found) { // no row for item found, place after
							after = true;
						}
					}
				}

				if (minDistItem === currentItem || (minDistItem.ownIndexInParent + (after ? 1 : -1) == currentItem.ownIndexInParent)) {
					return;
				}

				if (!me.placeHolder)
					me.placeHolder = me.createPlaceholder();

				domConstruct.place(me.placeHolder, minDistItem.domNode, after ? 'after' : 'before');
			}
		},
		createPlaceholder: function () {
			var me = this;
// TODO: correct size
			var width = me.getPlaceHolderWidth();
			var height = me.getPlaceHolderHeight();

			return domConstruct.create('div', { style: 'height: ' + height + ';width: ' + width, class: 'STWidgetPlaceholder' });
		},
		getPlaceHolderWidth: function () {
			return this.placeHolderWidth;
		},
		getPlaceHolderHeight: function () {
			return this.placeHolderHeight;
		},
		_getValueAttr: function () {
			var me = this;

			var val = [];

			for (var i = 0; i < me.items.length; i++) {
				val.push(me.items[i].get('value'));
			}

			return val;
		},
		destroy: function () {
			var me = this;

			if (me._beingDestroyed) {
				me.inherited(arguments);
				return;
			}

			me.isDestroying = true;

			me.reset();

			me.stopDrag();

			if (me.placeHolder) {
				domConstruct.destroy(me.placeHolder);
				delete me.placeHolder;
			}

			me.dndManager.unregisterContainer(me);
			delete me.dndManager;

			domConstruct.destroy(me.itemContainerNode);
			domConstruct.destroy(me.emptySubmitNode);

			me.inherited(arguments);
		},
		reset: function (keepOriginal) {
			var me = this;

			while (me.items.length) {
				me.items[0].isClosing = true;
				me.items[0].remove();
			}

			if (!keepOriginal) {
				me.originalItems = null;
			}

			me.inherited(arguments);
		}
	});
});