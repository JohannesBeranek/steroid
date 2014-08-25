define([
	"dojo/_base/declare",
	"dijit/layout/BorderContainer",
	"dojox/charting/Chart",
	"steroid/backend/stats/themes/general",
	"dojox/charting/plot2d/Pie",
	"dojo/dom-style",
	"dojo/dom-construct",
	"dojo/dom-attr",
	"dojox/charting/widget/Legend",
	"dijit/layout/ContentPane",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/DetailPane",
	"dojo/i18n!steroid/backend/nls/stats",
	"dojo/on",
	"dojox/lang/functional",
	"dijit/MenuBar",
	"dijit/MenuBarItem"
], function (declare, BorderContainer, Chart, ThemeGeneral, Pie, domStyle, domConstruct, domAttr, Legend, ContentPane, i18nRC, i18nDetailPane, i18nStats, on, langFunc, MenuBar, MenuBarItem) {
	return declare([BorderContainer], {
		charts: {},
		chartContainer: null,
		menuContainer: null,
		classContainer: null,
		style: 'width: 100%;height:100%;',
		menuClickHandlers: [],
		textNode: null,
		menuBar: null,

		startup: function () {
			var me = this;

			me.menuBar = new MenuBar({
				region: 'top'
			});

			me.menuBar.addChild(new MenuBarItem({
				label: i18nDetailPane.BTClose,
				class: 'STForceIcon STAction_close',
				style: 'float: right',
				onClick: function () {
					me.backend.removeStatistics();
				}
			}));

			me.addChild(me.menuBar);

			me.chartContainer = new ContentPane({
				id: 'ST_ChartPane',
				region: 'center',
				style: 'height: 99% !important;width: 50%;'
			});

			me.addChild(me.chartContainer);

			me.createMenu();

			me.classContainer = new ContentPane({
				id: 'ST_ChartClassPane',
				region: 'right',
				style: 'width: 300px;'
			});

			me.addChild(me.classContainer);

			var chart = me.createChart('general');

			chart.setTheme(ThemeGeneral);

			chart.addPlot('default', {
				type: Pie,
				radius: 200,
				fontColor: 'black',
				labelOffset: -40
			});

			me.inherited(arguments);

			me.backend.STServerComm.sendAjax({
				error: function (response) {
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success && response.data && response.data.recordNumbers) {
						me.showData(response.data.recordNumbers);
					} else {
						me.backend.showError(response);
					}
				},
				data: {
					requestType: 'stats',
					statType: 'general'
				}
			});
		},
		createChart: function (chartType, classConfig) {
			var me = this;

			if(!(me.chartContainer && me.chartContainer.containerNode)){
				return;
			}

			if (me.textNode) {
				domConstruct.destroy(me.textNode);

				delete me.textNode;
			}

			var title = '';

			if (classConfig && classConfig.i18nExt && classConfig.i18nExt[classConfig.className] && classConfig.i18nExt[classConfig.className]['chart_' + chartType + '_title']) {
				title = classConfig.i18nExt[classConfig.className]['chart_' + chartType + '_title'];
			} else if (i18nRC.generic['chart_' + chartType + '_title']) {
				title = i18nRC.generic['chart_' + chartType + '_title'];
			} else if (i18nStats[chartType]) {
				title = i18nStats[chartType].title;
			} else {
				title = chartType;
			}

			if (chartType !== 'general') {
				if (classConfig.i18nExt && classConfig.i18nExt[classConfig.className + '_name']) {
					title += ': ' + classConfig.i18nExt[classConfig.className + '_name'];
				} else if (i18nRC[classConfig.className + '_name']) {
					title += ': ' + i18nRC[classConfig.className + '_name'];
				}
			}

			var container = domConstruct.create('div', {style: 'height: 500px;'});

			me.chartContainer.containerNode.appendChild(container);

			var chart = new Chart(container, {
				title: title,
				titlePos: 'top',
				titleGap: 30
			});

			me.charts[chartType] = chart;

			return chart;
		},
		createMenu: function () {
			var me = this;

			me.menuContainer = new ContentPane({
				id: 'ST_ChartMenuPane',
				region: 'left',
				style: 'width: 300px;'
			});

			me.addChild(me.menuContainer);

			var recordClasses = [];

			var addRecordClass = function (item) {
				var title = '';

				if (item.i18nExt) {
					title = item.i18nExt[item.className + '_name'];
				} else if (i18nRC[item.className + '_name']) {
					title = i18nRC[item.className + '_name'];
				} else {
					title = item.className;
				}

				recordClasses.push({
					title: title,
					conf: item
				});
			};

			for (var i = 0, item; item = me.backend.config.recordClasses.content[i]; i++) {
				addRecordClass(item);
			}

			for (var i = 0, item; item = me.backend.config.recordClasses.ext_content[i]; i++) {
				addRecordClass(item);
			}

			recordClasses = recordClasses.sort(function (a, b) {
				return a.title > b.title ? 1 : -1;
			});

			for (var i = 0, item; item = recordClasses[i]; i++) {
				me.createMenuLink(item);
			}
		},
		createMenuLink: function (classConf) {
			var me = this;

			var link = domConstruct.create('a', {innerHTML: classConf.title, href: '', rel: classConf.conf.className, style: 'display:block;margin-bottom:4px;'});

			me.menuContainer.domNode.appendChild(link);

			me.menuClickHandlers.push(on(link, 'click', function (e) {
				me.loadClassStatistics(e.target.rel);

				e.preventDefault();
				return false;
			}));
		},
		loadClassStatistics: function (className) {
			var me = this;

			me.backend.STServerComm.sendAjax({
				error: function (response) {
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success) {
						me.showClassData(response, className);
					} else {
						me.backend.showError(response);
					}
				},
				data: {
					requestType: 'stats',
					statType: 'class',
					statClass: className
				}
			});
		},
		removeAllCharts: function () {
			var me = this;

			if (me.charts) {
				for (var chart in me.charts) {
					me.charts[chart].removeSeries('Main');
					me.charts[chart].removePlot('default');
					me.charts[chart].destroy();
				}
			}

			if(me.chartContainer && me.chartContainer.containerNode){
				domConstruct.empty(me.chartContainer.containerNode);
			}

			me.charts = {};
		},
		createTextNode: function (text) {
			var me = this;

			if (!(me.chartContainer && me.chartContainer.containerNode)) {
				return;
			}

			me.removeAllCharts();

			if (me.textNode) {
				domConstruct.destroy(me.textNode);

				delete me.textNode;
			}

			me.textNode = domConstruct.create('p', { innerHTML: i18nStats[text] });

			me.chartContainer.containerNode.appendChild(me.textNode);
		},
		setupClassChart: function (chart, chartType, chartConfig, classConfig) {
			var me = this;

			if(!chart){
				return;
			}

			chart.setTheme(chartConfig.config.theme || ThemeGeneral);

			delete chartConfig.config.theme;

			if (!langFunc.keys(chartConfig.config).length) {
				delete chartConfig.config;
			}

			var config = chartConfig.config || {
				type: Pie,
				radius: 200,
				fontColor: 'black',
				labelOffset: -40
			};

			chart.addPlot('default', config);

			var data = [];

			for (var i = 0, ilen = chartConfig.data.length; i < ilen; i++) {
				var text = '';

				if (classConfig.i18nExt && classConfig.i18nExt[classConfig.className] && classConfig.i18nExt[classConfig.className]['chart_' + chartType]) {
					text = classConfig.i18nExt[classConfig.className]['chart_' + chartType][i] + ': ' + chartConfig.data[i];
				} else if (i18nRC[classConfig.className] && i18nRC[classConfig.className]['chart_' + chartType]) {
					text = i18nRC[classConfig.className]['chart_' + chartType][i] + ': ' + chartConfig.data[i];
				} else if (i18nRC.generic['chart_' + chartType]) {
					text = i18nRC.generic['chart_' + chartType][i] + ': ' + chartConfig.data[i];
				} else {
					text = chartConfig.data[i];
				}

				data.push({
					x: i + 1,
					y: chartConfig.data[i],
					text: text
				});
			}

			chart.addSeries('Main', data);

			chart.render();
		},
		showClassData: function (response, className) {
			var me = this;

			me.removeAllCharts();

			if (!langFunc.keys(response.data).length) { // nothing to show yet
				me.createTextNode('noData');

				return;
			}

			for (var chartType in response.data) {
				var classConfig = me.backend.getClassConfigFromClassName(className);
				var chart = me.createChart(chartType, classConfig);
				var requires = [];

				if (response.data[chartType].config.theme) {
					requires.push(response.data[chartType].config.theme);
				}

				if (response.data[chartType].config.type) {
					requires.push(response.data[chartType].config.type);
				}

				if (requires.length) {
					require(requires, (function (chart, chartType, data, classConfig) {
						return function (resA, resB) {
							if (data.config.theme) {
								data.config.theme = resA;
							}

							if (data.config.type) {
								data.config.type = data.config.theme ? resB : resA;
							}

							me.setupClassChart(chart, chartType, data, classConfig);
						};
					})(chart, chartType, response.data[chartType], classConfig));
				} else {
					me.setupClassChart(chart, chartType, response.data[chartType], classConfig);
				}
			}
		},
		showData: function (data) {
			var me = this;

			if(!me.charts['general']){
				return;
			}

			for (var i = 0, item; item = data[i]; i++) {
				if (item.text === 'others') {
					item.text = i18nStats.records_other;
				} else {
					var classConf = me.backend.getClassConfigFromClassName(item.text);

					if (classConf.i18nExt) {
						item.text = classConf.i18nExt[item.text + '_name'];
					} else if (i18nRC[item.text + '_name']) {
						item.text = i18nRC[item.text + '_name'];
					}
				}

				item.text += ': ' + item.y;
			}

			me.charts['general'].addSeries('Main', data);

			me.charts['general'].render();
		},
		destroy: function () {
			var me = this;

			me.removeAllCharts();

			me.menuBar.destroyRecursive();
			delete me.menuBar;

			if (me.textNode) {
				domConstruct.destroy(me.textNode);

				delete me.textNode;
			}

			if (me.menuClickHandlers && me.menuClickHandlers.length) {
				for (var i = 0, item; item = me.menuClickHandlers[i]; i++) {
					item.remove();
				}
			}

			delete me.menuClickHandlers;
			delete me.menuContainer;
			delete me.chartContainer;
			delete me.classContainer;

			me.inherited(arguments);
		}
	});
});