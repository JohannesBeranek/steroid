define([
        "dojo/_base/declare", 
        "dojo/i18n!steroid/backend/nls/RecordClasses", 
        "dojo/i18n!steroid/backend/nls/ListPane", 
        "dijit/layout/BorderContainer", 
        "dijit/layout/ContentPane", 
        "steroid/backend/ServerComm", 
        "dojo/_base/lang", 
        "dojox/grid/EnhancedGrid", 
        "dojox/grid/LazyTreeGrid", 
        "dojox/lang/functional", 
        "dojo/data/ObjectStore", 
        "dojox/grid/LazyTreeGridStoreModel", 
        "dojo/_base/array", 
        "dojo/Deferred", 
        "dojo/_base/connect", 
        "dojox/grid/enhanced/plugins/NestedSorting", 
        "dojox/grid/enhanced/plugins/IndirectSelection", 
        "dojox/grid/enhanced/plugins/Filter", 
        "dgrid/OnDemandGrid", 
        "dgrid/Selection", 
        "dgrid/tree", 
        "dojo/dom-style", 
        "dgrid/extensions/ColumnHider", 
        "dgrid/extensions/ColumnReorder", 
        "dgrid/extensions/ColumnResizer", 
        "dgrid/extensions/DijitRegistry", 
        "dgrid/Keyboard", 
        "dojo/aspect", 
        "dijit/MenuBar",
        "dijit/MenuBarItem", 
        "dijit/form/TextBox", 
        "dojo/_base/json", 
        "steroid/backend/STStore", 
        "dojo/store/Observable", 
        "dojo/dom-construct", 
        "steroid/backend/FilterPane", 
        "dojo/dom-class", 
        "steroid/backend/mixin/_hasStandBy", 
        "put-selector/put",
        "dgrid/Grid", 
        "dijit/Tooltip", 
        "steroid/backend/ReferenceDialog", 
        "dojo/on", 
        "dojo/_base/event", 
        "dojo/query", 
        "dojo/mouse", 
        "dojo/keys", 
        "dojo/dom-attr", 
        "dijit/form/ToggleButton"
], function(declare, i18nRC, i18nListPane, BorderContainer, ContentPane, STServerComm, lang, EnhancedGrid, LazyTreeGrid, langFunc, ObjectStore, LazyTreeGridStoreModel, array, Deferred, Connect, GridNestedSorting, GridIndirectSelection, GridFilter, dGrid, dGridSelection, dGridTree, domStyle, dGridHider, dGridReorder, dGridResizer, DijitRegistry, dGridKeyboard, aspect, MenuBar, MenuBarItem, TextBox, json, STStore, ObservableStore, domConstruct, FilterPane, domClass, _hasStandBy, put, Grid, Tooltip, ReferenceDialog, on, event, query, mouse, keys, domAttr, ToggleButton) {
	return declare([BorderContainer, _hasStandBy], {

		classConfig : null,
		"class": "STListPane",
		loadInitDef : false,
		currentFilter : null,
		filterPane : null,
		filterBar : null,
		filterBox : null,
		BTCloseList : null,
		selectionBar : null,
		BTSelect : null,
		filterWatchHandles : null,
		quickSearchWatchHandle : null,
		hasMultiple : null,
		contentPane : null,
		view : null,
		menuBar : null,
		BTNewRecord : null,
		selectionChangedAspect : null,
		handleSelectAspect : null,
		keyDownAspect : null,
		quickSearchButton : null,
		BTFilter : null,
		rowTooltips : null,
		i18nExt : null,
		iconHandles : null,
		refreshHandle : null,
		fetchRecordOnSelect : true,
		lastParent : null,
		BTToggleList : null,
		hierarchic : false,
		currentlyRefreshing : false,

		constructor : function() {
			var me = this;

			me.iconHandles = [];
			me.currentFilter = {};
			me.filterWatchHandles = [];
			me.rowTooltips = {};
		},
		isHierarchic : function() {
			var me = this;

			return me.hierarchic;
		},
		postCreate : function() {
			var me = this;

			me.inherited(arguments);

			me.hierarchic = me.classConfig.isHierarchic;

			me.STStore = new ObservableStore(new STStore({
				backend : me.backend,
				classConfig : me.classConfig,
				isHierarchic : me.isHierarchic() && me.classConfig.startHierarchic
			}));

			// TODO: make do without deferred
			me.loadInitDef = new Deferred();

			if (me.hasFilters()) {
				me.initFilterBar();
			}

			me.initGrid();

			me.initMenuBar();

			if (me.isRecordSelector && me.hasMultiple) {
				me.initSelectionBar();
			}

			// FIXME: reference cleanUp
			me.standByNode = me.domNode;

			// TODO: make do without deferred, remove timeout
			dojo.when(me.loadInitDef, function() {
				setTimeout(function() {// FIXME: setTimeout race condition in case filterBox gets removed before timeout is done
					me.filterBox.focus();
				}, 50);
			});
		},
		hasFilters : function() {
			var me = this;

			return me.classConfig.filterFields && ((lang.isObject(me.classConfig.filterFields) && langFunc.keys(me.classConfig.filterFields).length) || me.classConfig.filterFields.length);
		},
		initGrid : function() {
			var me = this, fields = me.classConfig.listFields;

			var structure = [];

			var fieldCount = langFunc.keys(fields).length;
			var fieldOrder = {};

			var idx = 1;

			me.titleFieldWidths = 0;
			me.titleFieldCount = 0;

			var titleLabel = i18nRC[me.classConfig.className] && i18nRC[me.classConfig.className]['_title'] ? i18nRC[me.classConfig.className]['_title'] : i18nRC['_title'];

			structure.push({
				label : titleLabel,
				field : '_title',
				sortable : true,
				width : 400,
				hidden : false,
				unhidable : true
			});

			if (me.classConfig.hasListActions) {
				structure.push({
					label : i18nRC['_actions'],
					field : '_actions',
					sortable : false,
					width : 18 * me.classConfig.possibleListActionCount,
					hidden : false,
					unhidable : false,
					renderCell : function(object, value, node, options) {
						me.formatListActions(object, value, node, options);
					}
				});

				idx++;
			}

			for (var n in fields) {
				var nentry = fields[n];
				fieldOrder[n] = idx++;

				// TODO: more efficient solution that does not create a new function every loop (lang.hitch does the same!)
				require(["steroid/backend/datatype/list/" + nentry.dataType], (function(i, entry) {
					return function(dataType) {
						var dt = new dataType({
							classConfig : me.classConfig,
							fieldConf : entry, // FIXME: scoping
							fieldName : i, // FIXME: scoping
							backend : me.backend,
							owningRecordClass : me.classConfig.className,
							i18nExt : me.i18nExt
						});

						fieldCount--;

						structure[fieldOrder[i]] = dt.getStructure();

						if (dt.isTitleField) {
							me.titleFieldWidths += dt.getWidth();
							me.titleFieldCount++;
						}

						// executed when all dt are ready
						if (fieldCount == 0) {
							var gridPlugins = [dGrid, dGridHider, dGridReorder, dGridResizer, dGridKeyboard, DijitRegistry];

							if (!me.classConfig.listOnly) {
								gridPlugins.push(dGridSelection);
							}

							var customGrid = declare(gridPlugins, {
								noDataMessage : (me.classConfig.allowCreateInSelection || !me.createdByField) ? i18nListPane.noDataCanCreateMessage + (me.i18nExt && me.i18nExt[me.classConfig.className + '_name'] ? me.i18nExt[me.classConfig.className + '_name'] : i18nRC[me.classConfig.className + '_name'] || me.classConfig.className) : i18nListPane.noDataMessage,
								loadingMessage : i18nListPane.loadingMessage,
								insertRow : function(object, parent, beforeNode, i, options) {
									var row = this.inherited(arguments);

									var extensionClass = " delay";

									if (object.publishDate) {
										extensionClass += "Publish";
									}

									if (object.unpublishDate) {
										extensionClass += "Unpublish";
									}

									if (object.liveStatus !== null) {
										row.className += " STRecordLiveStatus_" + object.liveStatus + extensionClass;
									}

									if (object.rowClass) {
										row.className += ' ' + object.rowClass;
									}

									if (object.beingEdited) {
										row.className += " STRecordBeingEdited";

										//add row tooltips
										var tooltip = new Tooltip({
											connectId : row,
											label : i18nListPane['_beingEdited'] + object._editedBy,
											position : ['above', 'below']
										});

										me.rowTooltips[row.id] = tooltip;
									} else if (object.publishDate || object.unpublishDate) {

										var delayLabel = "";

										if (object.publishDate) {
											delayLabel += i18nListPane['_delayPublish'] + dojo.date.locale.format(new Date(object.publishDate));
										}

										if (object.unpublishDate) {
											delayLabel += ' ' + i18nListPane['_delayUnpublish'] + dojo.date.locale.format(new Date(object.unpublishDate));
										}

										var tooltip = new Tooltip({
											connectId : row,
											label : delayLabel,
											position : ['above', 'below']
										});
									}

									return row;
								},
								//remove row tooltip
								removeRow : function(rowElement, justCleanup) {
									rowElement = rowElement.element || rowElement;

									if (me.rowTooltips[rowElement.id]) {
										me.rowTooltips[rowElement.id].destroy();
										delete me.rowTooltips[rowElement.id];
									}

									this.inherited(arguments);
								}
							});

							// tree init for hierarchic structure
							if (me.isHierarchic() !== false && !me.createdByField) {
								structure[0].renderExpando = me._renderExpando;
								// TODO: might want to set up structure[0].expandOn as well ;
								// default for .expandOn: ".dgrid-expando-icon:click," + colSelector + ":dblclick," + colSelector + ":keydown"
								// where colSelector = ".dgrid-content .dgrid-column-" + column.id
								var originalRenderCell = structure[0].renderCell || Grid.defaultRenderCell;

								structure[0] = dGridTree(structure[0]);
								
								structure[0].renderCell = me._getRenderTreeCellFunc(structure[0], originalRenderCell);
								// overwrite renderCell function, because we want the data when rendering the expando
								structure[0].shouldExpand = function(row, level, previouslyExpanded) {
									return previouslyExpanded || row.data._parent === null;
								};
							}

							if (me.filterPane) {
								me.filterPane.form.addValueSetListenerOnce(function() {
									me.createGrid(customGrid, structure);
								});
							} else {
								me.createGrid(customGrid, structure);
							}
						}
					};
				})(n, nentry));

			}
		},
		doAction : function(action, recordPrimary, doAction, additionalPublish) {
			var me = this;

			if (action == 'copyRecord') {
				me.backend.Clipboard.copyPage(recordPrimary);
				return;
			}

			me.backend.doStandBy();

			var conf = {
				data : {
					requestType : action,
					recordClass : me.classConfig.className,
					recordID : recordPrimary,
					doAction : doAction,
					additionalPublish : additionalPublish,
					sync : action === 'previewRecord' ? true : false // action previewRecord needs sync to avoid popup blockers
				},
				error : function(response) {
					me.handleError(response, action, recordPrimary);
				},
				success : function(response) {
					me.actionSuccess(action, response);
				}
			};

			me.backend.STServerComm.sendAjax(conf);
		},
		actionSuccess : function(action, response) {
			var me = this;

			if(action == 'switchUser'){
				me.backend.userSwitched();
				return;
			}

			me.backend.hideStandBy();

			me.backend.showToaster(action);

			if (action == 'previewRecord') {
				me.openPreview(response);
			} else {
				me.view.refresh();
			}
		},
		openPreview : function(response) {
			var me = this;

			window.open(response.data.items, '_blank');
		},
		handleError : function(response, action, recordPrimary) {
			var me = this;

			me.backend.hideStandBy();

			if (response.error) {
				if (response.error == 'MissingReferencesException' || response.error == 'AffectedReferencesException') {
					var dialog = new ReferenceDialog({
						response : response,
						backend : me.backend,
						mainRecordClass : me.classConfig.className,
						mainRecordID : recordPrimary,
						onYes : function() {
							me.doAction(action, recordPrimary, true, this.recordsSelected.join(','));
						}
					});

					dialog.show();
				} else {
					me.backend.showError(response);
				}
			} else {
				if (response.type && response.arguments) {
					me.backend.showError({
						error : response.type,
						message : response.arguments
					});
				}
			}
		},
		formatListActions : function(object, value, node, options) {
			var me = this;

			if (!value) {
				return;
			}

			var status = null;

			if (me.classConfig.idField) {
				if (me.classConfig.liveField) {
					if (me.classConfig.languageField) {
						status = object.stati.languages[me.backend.config.system.languages.current.id].status;
						// TODO: unhardcode 'id' field of language record
					} else {
						status = object.stati.status;
					}
				}
			}

			for (var i = 0, item; item = value[i]; i++) {
				if (item == 'publishRecord' && status == 1) {
					continue;
				}

				if (item == 'hideRecord' && status == 0) {
					continue;
				}

				var icon = domConstruct.create('div', {
					"class": 'STListAction STIcon_' + item,
					'data-record-primary' : object.primary,
					'data-record-action' : item
				});

				node.appendChild(icon);
			}
		},
		endEditingWithPreviousRecord : function(rec) {
			var me = this, row;

			if (( row = me.view.row(rec.primary)) && row.data && row.element) {
				row.data.beingEdited = false;

				domClass.remove(row.element, 'STRecordBeingEdited');

				if (me.rowTooltips[row.element.id]) {
					me.rowTooltips[row.element.id].destroy();
					delete me.rowTooltips[row.element.id];
				}
			}
		},
		refreshComplete : function() {
			var me = this;

			me.removeIconHandles();

			me.currentlyRefreshing = false;
		},
		removeIconHandles : function() {
			var me = this;

			if (me.iconHandles) {
				for (var i = 0, item; item = me.iconHandles[i]; i++) {
					item.remove();
				}

				me.iconHandles = [];
			}
		},
		createGrid : function(customGrid, structure) {
			var me = this;

			me.view = new customGrid({
				STRecordClass : me.classConfig,
				columns : structure,
				cellNavigation : false,
				store : me.STStore,
				keepScrollPosition : true,
				selectionMode : (me.isRecordSelector && me.hasMultiple) ? 'extended' : 'single',
				pagingDelay : 300,
				displayHierarchic : me.isHierarchic(),
				listPane : me,
				sort : me.classConfig.defaultSort,
				query : me.query || {},
				keepSelection : true
			});
			
			// FIX FF TRANSITIONEND NOT HAPPENING
			(function() {
				var grid = me.view.columns[0].grid;
				
				var origExpand = grid.expand;
				grid.expand = function(target, expand, noTransition) {
					var ret = origExpand.call(grid, target, expand, noTransition);
					var row = target.element ? target : grid.row(target);

					var container = row.element.connected;
					var x = container.clientHeight;
					
					return ret;
				};
			})();

			me.contentPane = new ContentPane({
				region : 'center',
				style : 'padding:0;margin:0;width:100%;height:100%',
				content : me.view
			});

			me.addChild(me.contentPane);

			me.view.startup();

			// FIXME: touch
			me.iconHandles = me.view.on('.STListAction:click', function(e) {
				me.doAction(domAttr.get(e.target, 'data-record-action'), domAttr.get(e.target, 'data-record-primary'));
			});

			me.refreshHandle = me.view.on('dgrid-refresh-complete', function() {
				me.refreshComplete();
			});

			for (var i = 0; i < structure.length; i++) {
				me.view.styleColumn(i, 'width: ' + structure[i].width + (structure[i].width == 'auto' ? '' : 'px') + ';');
			}

			me.selectionChangedAspect = me.view.on('dgrid-select', function(event) {
				var rows = event.rows;

				if (rows[0]['data']['beingEdited']) {
					//TODO: notify user
				} else {
					me.selectionChanged();
				}
			});

			me.handleSelectAspect = aspect.around(me.view, '_handleSelect', lang.hitch(me, 'aspectSetNextSelectionEventKey'));
			// FIXME: touch?
			me.keyDownAspect = me.view.on('keydown', lang.hitch(me, 'setNextSelectionEventKey'));

			domStyle.set(me.view.domNode, 'height', '100%');

			me.loadInitDef.resolve();
		},

		initFilterBar : function() {
			var me = this;

			me.filterPane = new FilterPane({
				style : 'border:0;overflow:hidden;padding:0;',
				"class": 'STFilterPane',
				splitter : true,
				gutters : true,
				backend : me.backend,
				moduleContainer : me,
				classConfig : me.classConfig
			});

			me.filterPane.form.addInitListener(function() {
				for (var i in me.filterPane.form.ownFields) {
					// FIXME: race condition with setting initial value
					me.filterWatchHandles.push(me.filterPane.form.ownFields[i]._dt.watch('STValue', function(name, oldValue, newValue) {
						var obj = this;
						var fieldName = obj.fieldName;

						if (obj.fieldConf.selectableRecordClassConfig) {
							fieldName = fieldName + '.' + obj.fieldConf.selectableRecordClassConfig.fieldName + '.primary';
						} else if (obj.fieldConf.dataType == 'DTForeignReference') {
							fieldName = fieldName + '.primary';
						}

						var val = newValue;

						if (lang.isArray(val) && !val.length) {
							val = null;
						}

						// FIXME: handle all filterChanges at once at start
						me.filterChanged(fieldName, val, true);
					}));
				}
			});

			me.filterBar = new ContentPane({
				region : 'top',
				splitter : true,
				content : me.filterPane,
				style : 'display:none;height:150px;overflow:scroll',
				layoutPriority : 2,
				"class": 'STFilterBar',
				minimize : function() {
					domClass.add(this.domNode, 'minimized');
				},
				maximize : function() {
					domClass.remove(this.domNode, 'minimized');
				}
			});

			me.addChild(me.filterBar);
		},
		mayCreateNew : function() {
			var me = this;

			return (!me.isRecordSelector || me.classConfig.allowCreateInSelection) && !me.classConfig.listOnly && me.classConfig.mayCreate;
		},
		initSelectionBar : function() {
			var me = this;

			me.selectionBar = new MenuBar({
				region : 'bottom',
				"class": 'STListMenu',
				style : 'overflow: hidden;',
				splitter : false,
				layoutPriority : 1
			});

			me.BTSelect = new MenuBarItem({
				label : i18nListPane.applySelection,
				style : 'float: left',
				// FIXME: touch?
				onClick : function() {
					me.applySelection();
				}
			});

			me.selectionBar.addChild(me.BTSelect);
			me.addChild(me.selectionBar);
		},
		initMenuBar : function() {
			var me = this;

			me.menuBar = new MenuBar({
				region : 'top',
				"class": 'STListMenu',
				style : 'overflow: hidden;',
				splitter : false,
				layoutPriority : 1
			});

			if (me.mayCreateNew()) {
				me.BTNewRecord = new MenuBarItem({
					label : i18nListPane.newRecord,
					"class": 'STForceIcon STAction_new',
					style : 'float: left',
					// FIXME: touch?
					onClick : function() {
						if (!me.classConfig.isHierarchic) {
							me.view.clearSelection();
							delete me.lastParent;
						}

						var selectedRecords = me.getSelectedRecord();

						var options = {
							language : me.backend.config.system.languages.current.primary,
							parent : selectedRecords ? selectedRecords[0] : me.lastParent,
							forEditing : true
						};

						return me.newRecord(options);
					}
				});
			}

			me.BTCloseList = new MenuBarItem({
				label : i18nListPane.BTClose,
				"class": 'STForceIcon STAction_close',
				style : 'float: right',
				// FIXME: touch?
				onClick : function() {
					me.close();
				}
			});

			me.filterBox = new TextBox({
				//				style: 'width: 100px; margin: 0 auto;margin-top:3px;',
				style : 'margin-bottom: 0',
				"class": 'STQuicksearch',
				intermediateChanges : true,
				placeHolder : 'Quicksearch',
				onKeyDown : function(e) {// onKeyPressed does not get event -caught somewhere before after onKeyDown
					if (e.keyCode === keys.SPACE) {
						this.set('value', this.get('value') + ' ');
					}
				},
				// [JB] these two methods are needed to make keyboard selection(tabs, right/left arrow) work correctly without js errors
				_setSelected : function(selected) {
					if (selected) {
						this.focus();
					}
				},
				_onUnhover : function() {
				} // TODO: capture right/left arrow keypresses if we already got text
			});

			me.quickSearchButton = new MenuBarItem({
				style : 'float: left',
				filterBox : me.filterBox,
				// FIXME: touch?
				onClick : function() {
					this.filterBox.focus();
				}
			});

			domConstruct.place(me.filterBox.domNode, me.quickSearchButton.focusNode);

			me.BTRefresh = new MenuBarItem({
				label : i18nListPane.BTRefresh,
				style : 'float: left',
				"class": 'STForceIcon STAction_refresh',
				// FIXME: touch?
				onClick : function() {
					me.view.refresh();
				}
			});

			if (me.mayCreateNew()) {
				me.menuBar.addChild(me.BTNewRecord);
			}

			me.menuBar.addChild(me.BTRefresh);

			me.menuBar.addChild(me.filterBox);

			if (me.hasFilters()) {
				me.BTFilter = new MenuBarItem({
					label : i18nListPane.BTFilter,
					"class": 'STForceIcon STAction_filter',
					style : 'float: left',
					// FIXME: touch?
					onClick : function() {
						me.toggleFilterBar();
					}
				});

				me.menuBar.addChild(me.BTFilter);
			}

			if (me.isHierarchic()) {
				me.BTToggleList = new MenuBarItem({
					"class": 'STForceIcon STToggleListButton_' + (me.classConfig.startHierarchic ? 'list' : 'tree'),
					label : i18nListPane['toggleList_' + (me.classConfig.startHierarchic ? 'list' : 'tree')],
					listMode : !me.classConfig.startHierarchic,
					// FIXME: touch?
					onClick : function() {
						this.listMode = !this.listMode;
						me.hierarchic = !this.listMode;
						me.STStore.isHierarchic = !this.listMode;
						this.set('label', i18nListPane['toggleList_' + (!this.listMode ? 'list' : 'tree')]);

						if (this.listMode) {
							domClass.remove(this.domNode, 'STToggleListButton_list');
							domClass.add(this.domNode, 'STToggleListButton_tree');
						} else {
							domClass.remove(this.domNode, 'STToggleListButton_tree');
							domClass.add(this.domNode, 'STToggleListButton_list');
						}

						me.view.refresh();
					}
				});

				me.menuBar.addChild(me.BTToggleList);
			}

			me.menuBar.addChild(me.BTCloseList);

			me.addChild(me.menuBar);

			me.registerFilterEvents(me.filterBox);

			var recordClassName = me.classConfig.i18nExt ? me.classConfig.i18nExt[me.classConfig.className + '_name'] : i18nRC[me.classConfig.className + '_name'];

			me.listLabel = domConstruct.create('div', {
				innerHTML : recordClassName,
				"class": 'STListLabel'
			});

			me.menuBar.domNode.appendChild(me.listLabel);
		},
		newRecord : function(options) {
			var me = this;

			return options;
		},
		closeFilterBar : function() {
			var me = this;

			domStyle.set(me.filterBar.domNode, 'display', 'none');
		},
		openFilterBar : function() {
			var me = this;

			domStyle.set(me.filterBar.domNode, 'display', 'block');
		},
		toggleFilterBar : function() {
			var me = this;

			if (domStyle.get(me.filterBar.domNode, 'display') == 'none') {
				me.openFilterBar();
			} else {
				me.closeFilterBar();
			}

			// TODO: why do we call layout here, and not in open or close? do we even need this layout call?
			me.layout();
		},

		filterChanged : function(fieldName, value, isRecordReference) {
			var me = this;

			if (value) {// [JB 11.02.2013] null is always falsy, as is empty string
				me.currentFilter[fieldName] = {
					filterFields : (fieldName == 'quicksearch') ? me.classConfig.titleFields : [fieldName],
					filterValue : value,
					filterType : isRecordReference ? 'recordReference' : 'string'
				};
			} else if (me.currentFilter[fieldName]) {
				delete me.currentFilter[fieldName];
			} else {
				return;
			}

			var filter = [];
			var hasQuickSearchFilter = false;

			for (var i in me.currentFilter) {
				// Code to handle entered spaces in quicksearch: "a b" is handled as "a" AND "b"
				if (i == 'quicksearch') {
					hasQuickSearchFilter = true;

					var tokens = me.currentFilter.quicksearch.filterValue.split(' ');
					for (var n in tokens) {
						if (tokens[n].length) {
							var f = dojo.clone(me.currentFilter[i]);
							f.filterValue = tokens[n];
							filter.push(f);
						}
					}
				} else {
					filter.push(me.currentFilter[i]);
				}
			}

			var filterLength = hasQuickSearchFilter ? (filter.length - 1) : filter.length;

			if (me.BTFilter) {
				if (filterLength) {
					me.BTFilter.set('label', i18nListPane.BTFilter + ' (' + filterLength + ')');
				} else {
					me.BTFilter.set('label', i18nListPane.BTFilter);
				}
			}

			if (me.view) {// view already set up
				me.view.query.filter = filter;

				if (me.currentlyRefreshing) {
					me.view.on('dgrid-refresh-complete', function() {
						me.view.refresh();
					});
				} else {
					me.view.refresh();
				}
			} else if (me.query) {// view not set up yet
				me.query.filter = filter;
			} else {// FIXME: comment: when does this happen?
				me.query = {
					filter : filter
				};
			}
		},
		registerFilterEvents : function(textbox) {
			var me = this;

			me.quickSearchWatchHandle = textbox.watch('value', function(name, oldValue, newValue) {
				me.filter(newValue);
			});
		},
		filter : function(value) {
			var me = this;

			// FIXME: also clearTimeout on destroy
			if (me.filterTimeout) {
				clearTimeout(me.filterTimeout);
				// no need to delete me.filterTimeout here, will be set anew afterwards anyway
			}

			me.filterTimeout = setTimeout(function() {
				delete me.filterTimeout;
				me.filterChanged('quicksearch', value, false);
			}, 1000);
		},
		// private
		_getRenderTreeCellFunc : function(column, originalRenderCell) {
			return function(object, value, td, options) {
				// summary:
				//		Renders a cell that can be expanded, creating more rows

				var grid = column.grid, level = Number(options && options.queryLevel) + 1, mayHaveChildren = !grid.store.mayHaveChildren || grid.store.mayHaveChildren(object), parentId = options.parentId, expando, node;

				level = currentLevel = isNaN(level) ? 0 : level;
				expando = column.renderExpando(level, mayHaveChildren, grid._expanded[( parentId ? parentId + "-" : "") + grid.store.getIdentity(object)], object);
				expando.level = level;
				expando.mayHaveChildren = mayHaveChildren;

				node = originalRenderCell.call(column, object, value, td, options);
				if (node && node.nodeType) {
					put(td, expando);
					put(td, node);
				} else {
					td.insertBefore(expando, td.firstChild);
				}
			};
		},
		getSelectedRecord : function() {
			var me = this;

			var currentSelection = [];

			for (var id in me.view.selection) {
				if (me.view.selection[id]) {
					currentSelection.push(id);
				}
			}

			if (!currentSelection.length) {
				return null;
			}

			var data = [];

			for (var i in currentSelection) {
				data.push(me.view.row(currentSelection[i]).data);
			}

			return data;
		},
		rowsSelected : function() {
			var me = this;

			if (!me.hasMultiple) {
				var data = me.getSelectedRecord();

				data = data && data[0] && data[0].primary ? data[0].primary : null;

				if (!me.isRecordSelector) {
					domClass.add(me.view.row(data).element, 'STRecordBeingEdited');
				}

				if (!me.fetchRecordOnSelect) {
					me.recordSelected(data);
					return;
				}

				if (!data) {
					return;
				}

				var options = {};

				if (!me.isRecordSelector) {
					options.forEditing = true;
				}

				options = me.moduleContainer.beforeRecordSelected(options);

				me.recordSelected([me.view.store.get(data, options)]);
			}
		},
		applySelection : function() {
			var me = this;

			return me.getSelectedRecord();
		},
		recordSelected : function(record) {
			var me = this;

			return record;
		},
		// private
		_renderExpando : function(level, hasChildren, expanded, object) {// [JB 12.02.2013] added object param
			// TODO: render different icons depending on object data?

			var dir = this.grid.isRTL ? "right" : "left", cls = ".dgrid-expando-icon", node;
			if (hasChildren) {
				cls += ".ui-icon.ui-icon-triangle-1-" + ( expanded ? "se" : "e");
			}
			node = put("div" + cls + "[style=margin-" + dir + ": " + (level * (this.indentWidth || 9)) + "px; float: " + dir + "]");
			node.innerHTML = "&nbsp;";
			// for opera to space things properly
			return node;
		},
		// private
		aspectSetNextSelectionEventKey : function(originalFunc) {
			var me = this;

			return function(e, currentTarget) {
				var target;
				if (e.target)
					target = e.target;
				else if (e.srcElement)
					target = e.srcElement;
				if (target.nodeType == 3)// defeat Safari bug
					target = target.parentNode;

				if (domClass.contains(target, "dgrid-expando-icon") || domClass.contains(target, "STListAction")) {
					me.nextSelectionEventKey = null;
					e.stopPropagation();
					e.stopImmediatePropagation();
					e.preventDefault();
					// window.event.cancelBubble = true
				} else {
					me.setNextSelectionEventKey(e, currentTarget);
					originalFunc.call(me.view, e, currentTarget);
				}
			};
		},
		// private
		setNextSelectionEventKey : function(e, currentTarget) {
			var me = this;
			// FIXME: touch?
			me.nextSelectionEventKey = e.keyCode || (e.type && (e.type == 'mousedown' || e.type == 'dgrid-cellfocusin') ? 0 : null);

			if (e.keyCode == 13) {
				me.selectionChanged();
			}

		},
		selectionChanged : function() {
			var me = this;

			if (me.nextSelectionEventKey === null || me.nextSelectionEventKey === 0 || me.nextSelectionEventKey === 13) {
				delete me.nextSelectionEventKey;
				me.rowsSelected();
			}
		},
		minimize : function() {
			var me = this;

			var cols = me.view.columns;

			me.visibleCols = [];

			for (var i in cols) {
				if (cols[i].unhidable) {
					me.view.styleColumn(i, 'display:table-cell');
					continue;
				}

				if (!me.view.isColumnHidden(i)) {
					me.visibleCols.push(cols[i].field);
					me.view.styleColumn(i, 'display:none');
				}
			}

			domStyle.set(me.domNode, 'width', '200px');

			if (me.filterBar) {
				me.filterBar.minimize();
			}
		},
		maximize : function() {
			var me = this;

			var cols = me.view.columns;

			var titleField = me.classConfig.titleField;

			for (var i = 0; i < me.visibleCols.length; i++) {
				for (var j in cols) {
					if (cols[j].field == me.visibleCols[i]) {
						me.view.styleColumn(j, 'display: table-cell');
					}
				}
			}

			me.visibleCols = [];

			domStyle.set(me.domNode, 'width', '100%');

			if (me.filterBar) {
				me.filterBar.maximize();
			}
		},
		refreshAndSelect : function(record) {
			var me = this;

			me.view.refresh();

			// TODO: select after refresh
		},
		close : function() {
			// TODO: what is this function for?
		},
		domainGroupSwitched : function(domainGroup) {
			//stub
		},
		destroy : function() {
			var me = this;

			me.removeIconHandles();

			if (me._beingDestroyed) {
				me.inherited(arguments);
				return;
			}

			if (me.filterWatchHandles) {
				var filterWatchLen = me.filterWatchHandles.length;

				for (var i = 0; i < filterWatchLen; i++) {
					me.filterWatchHandles[i].unwatch();
				}
				delete me.filterWatchHandles;
			}
			delete me.STStore;

			if (me.loadInitDef.fired < 0) {
				me.loadInitDef.resolve();
			}
			delete me.loadInitDef;

			if (me.filterPane) {
				me.filterPane.destroyRecursive();
				delete me.filterPane;
			}

			if (me.filterBar) {
				me.filterBar.destroyRecursive();
				delete me.filterBar;
			}

			if (me.selectionBar) {
				me.selectionBar.destroyRecursive();
				delete me.selectionBar;
			}

			if (me.BTSelect) {
				me.BTSelect.destroyRecursive();
				delete me.BTSelect;
			}

			me.menuBar.destroyRecursive();
			delete me.menuBar;

			if (me.BTNewRecord) {
				me.BTNewRecord.destroyRecursive();
				delete me.BTNewRecord;
			}

			if (me.BTFilter) {
				me.BTFilter.destroyRecursive();
				delete me.BTFilter;
			}

			if (me.quickSearchWatchHandle) {
				me.quickSearchWatchHandle.unwatch();
				delete me.quickSearchWatchHandle;
			}

			domConstruct.destroy(me.listLabel);

			me.quickSearchButton.destroyRecursive();
			delete me.quickSearchButton;

			if (me.BTToggleList) {
				me.BTToggleList.destroyRecursive();
				delete me.BTToggleList;
			}

			me.filterBox.destroyRecursive();
			delete me.filterBox;

			me.BTCloseList.destroyRecursive();
			delete me.BTCloseList;
			delete me.loadInitDef;

			me.contentPane.destroyRecursive();
			delete me.contentPane;

			me.view.destroyRecursive();
			delete me.view;

			me.handleSelectAspect.remove();
			delete me.handleSelectAspect;

			me.keyDownAspect.remove();
			delete me.keyDownAspect;

			me.selectionChangedAspect.remove();
			delete me.selectionChangedAspect;
			delete me.query;
			delete me.currentFilter;
			delete me.filterTimeout;
			delete me.visibleCols;

			me.inherited(arguments);
		}
	});
});
