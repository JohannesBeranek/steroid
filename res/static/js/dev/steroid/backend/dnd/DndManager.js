define([
	"dojo/_base/declare",
	"dojo/on",
	"dojo/_base/lang",
	"dijit/TitlePane",
	"dojo/_base/window",
	"dojo/dom-style",
	"dojo/dom-geometry",
	"dojo/_base/event",
	"dojo/dom-class",
	"dojo/_base/array"
], function (declare, on, lang, TitlePane, win, domStyle, domGeom, event, domClass, array) {
	return declare([], {
		dropContainers: [],
		currentDraggable: null,
		currentOverContainer: null,
		lastMouseDownCoords: null,
		delay: 30,
		avatar: null,
		avatarOffsetX: 5,
		avatarOffsetY: 5,

		mouseMoveListener: null,
		mouseUpListener: null,

		reset: function () {
			var me = this;

			me.currentDraggable = null;
			me.currentOverContainer = null;
			me.lastMouseDownCoords = null;
			me.avatar = null;
		},
		registerContainer: function (container) {
			var me = this;

			if (array.indexOf(me.dropContainers, container) < 0) {
				me.dropContainers.push(container);
			}
		},
		unregisterContainer: function (container) {
			var me = this;

			var idx = array.indexOf(me.dropContainers, container);

			if (idx > -1) {
				me.dropContainers.splice(idx, 1);
			}
		},
		registerMoveUpHandler: function() {
			var me = this;

			if (me.mouseMoveListener === null) {
				me.mouseMoveListener = on(dojo.doc, 'mousemove', lang.hitch(me, 'mouseMove'));
			}

			if (me.mouseUpListener === null) {
				me.mouseUpListener = on(dojo.doc, 'mouseup', lang.hitch(me, 'mouseUp'));
			}
		},
		unregisterMoveUpHandler: function() {
			var me = this;

			if (me.mouseMoveListener !== null) {
				me.mouseMoveListener.remove();
				me.mouseMoveListener = null;
			}

			if (me.mouseUpListener !== null) {
				me.mouseUpListener.remove();
				me.mouseUpListener = null;
			}
		},
		draggableMouseDown: function (e, draggable) {
			var me = this;

			me.currentDraggable = draggable;

			me.lastMouseDownCoords = {
				l: e.offsetX || e.layerX,
				t: e.offsetY || e.layerY
			};

			me.registerMoveUpHandler();

			event.stop(e);
		},
		mouseMove: function (e) {
			var me = this;

			var offsetX = e.offsetX || e.layerX;
			var offsetY = e.offsetY || e.layerY;

			if (me.avatar) {
				me.moveAvatar(e);
			} else if (me.currentDraggable) {
				if (offsetX < me.lastMouseDownCoords.l || offsetX > me.lastMouseDownCoords.l || offsetY < me.lastMouseDownCoords.t || offsetY > me.lastMouseDownCoords.t) {
					me.currentDraggable.isDragging = true;
					me.createAvatar(e);
					me.startDrag();
				}
			}

			// event.stop(e);
		},
		mouseUp: function (e) {
			var me = this;

			me.unregisterMoveUpHandler();

			if (me.currentDraggable) {
				if (me.avatar) {
					me.avatar.destroyRecursive();
					delete me.avatar;
				}

				if (me.currentOverContainer && me.currentOverContainer.dropValid(me.currentDraggable)) {

					me.currentOverContainer.drop(me.currentDraggable);

					delete me.currentOverContainer;
				}

				me.currentDraggable.isDragging = false;
				me.lastMouseDownCoords = null;
				delete me.currentDraggable;

				me.stopDrag();
			}
		},
		startDrag: function () {
			var me = this;

			for (var i = 0; i < me.dropContainers.length; i++) {
				me.dropContainers[i].startDrag(me.currentDraggable);
			}
		},
		stopDrag: function () {
			var me = this;

			for (var i = 0; i < me.dropContainers.length; i++) {
				me.dropContainers[i].stopDrag();
			}
		},
		createAvatar: function (e) {
			var me = this;

			me.avatar = new TitlePane({
				title: me.currentDraggable.title,
				open: false,
				style: 'position: absolute;opacity: 0.5;z-index:10000',
				toggleable: false,
				closeable: false,
				lastValid: null,
				isValid: function (isValid) {
					var me = this;

					if (isValid != me.lastValid) {
						domClass.remove(me.domNode, me.lastValid ? 'dropValid' : 'dropInvalid');
						me.lastValid = isValid;
						domClass.add(me.domNode, isValid ? 'dropValid' : 'dropInvalid');
					}
				}
			}).placeAt(win.body());

			me.avatar.startup();

			me.moveAvatar(e);
		},
		moveAvatar: function (e) {
			var me = this;

			domStyle.set(me.avatar.domNode, 'left', (e.pageX + me.avatarOffsetX) + 'px');
			domStyle.set(me.avatar.domNode, 'top', (e.pageY + me.avatarOffsetY) + 'px');

			if (me.currentOverContainer && me.currentOverContainer.dropValid(me.currentDraggable)) {
				me.avatar.isValid(true);
				me.currentOverContainer.showPlaceHolder(e, me.currentDraggable, me.avatar);
			} else {
				me.avatar.isValid(false);
			}
		},
		registerDragOver: function (dropContainer) {
			var me = this;

			if (me.currentOverContainer) {
				me.currentOverContainer.dragOut();
			}

			me.currentOverContainer = dropContainer;
		},
		registerDragOut: function (dropContainer) {
			var me = this;

			delete me.currentOverContainer;
		},
		destroyRecursive: function () {
			this.destroy();
		},
		destroy: function () {
			var me = this;

			delete me.currentDraggable;

			if (me.avatar) {
				me.avatar.destroyRecursive();

				delete me.avatar;
			}

			delete me.lastMouseDownCoords;
			delete me.currentOverContainer;
			delete me.dropContainers;

			me.unregisterMoveUpHandler();

			me.inherited(arguments);
		}
	});
});