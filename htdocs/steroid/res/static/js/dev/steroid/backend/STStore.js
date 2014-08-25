// FIXME: this uses deprecated dojo/_base/Deferred instead of new dojo/Deferred (won't work by just switching out)
define(["dojo/json", "dojo/_base/declare", "dojo/store/util/QueryResults", "dojo/_base/Deferred", "dojo/_base/json", "dojo/store/JsonRest", "dojo/_base/lang"
], function (JSON, declare, QueryResults, Deferred, json, JsonRest, lang) {
	//  module:
	//    dojo/store/JsonRest
	//  summary:
	//    The module defines a JSON/REST based object store

	return declare("steroid.backend.STStore", [JsonRest], {
		backend: null,
		// summary:
		//		This is a basic store for RESTful communicating with a server through JSON
		//		formatted data. It implements dojo.store.api.Store.

		constructor: function (/*dojo.store.JsonRest*/ options) {
			// summary:
			//		This is a basic store for RESTful communicating with a server through JSON
			//		formatted data.
			// options:
			//		This provides any configuration information that will be mixed into the store

			this.backend = null;
			declare.safeMixin(this, options);
		},
		// target: String
		//		The target base URL to use for all requests to the server. This string will be
		// 	prepended to the id to generate the URL (relative or absolute) for requests
		// 	sent to the server
		target: "",
		// idProperty: String
		//		Indicates the property to use as the identity property. The values of this
		//		property should be unique.
		idProperty: "primary",
		// sortParam: String
		// 		The query parameter to used for holding sort information. If this is omitted, than
		//		the sort information is included in a functional query token to avoid colliding
		// 		with the set of name/value pairs.

		requestingRecordClass: null, // need to store these as grid will overwrite query when getting children of a parent
		requestFieldName: null,
		mainRecordClass: null,

		get: function (id, options) {
			var me = this;

			var results = new Deferred();

			var conf = {
				data: me.createQuery({}, options, id || 'new'),
				error: lang.hitch(me.backend, 'storeGetError'),
				success: function (response) {
					if (response && response.success) {
						if (!results.doCancel) {
							results.resolve(response.data);
						}
					} else {
						me.backend.storeGetError(response);
					}
				}
			};

			var def = me.backend.STServerComm.sendAjax(conf);

			return results;
		},
		// accepts: String
		//		Defines the Accept header to use on HTTP requests
		accepts: "application/javascript, application/json",
		getIdentity: function (object) {
			// summary:
			//		Returns an object's identity
			// object: Object
			//		The object to get the identity from
			//	returns: Number
			return object[this.idProperty];
		},
		put: function (object, options) {

		},
		add: function (object, options) {

		},
		remove: function (id) {

		},
		query: function (query, options) {
			var me = this;

			var conf = {
				data: me.createQuery(query, options),
				error: lang.hitch(me.backend, 'storeQueryError')
			};

			var results = new Deferred();

			var def = me.backend.STServerComm.sendAjax(conf);

			// FIXME: error/cancel handling
			results.total = def.then(function (response) {
				var range;

				if (response && response.data) {
					range = response.data.total;
				} else {
					range = 0;
				}

				return range && (range = range.match(/\/(.*)/)) && +range[1];
			});

			// FIXME: comment what this is for
			def.then(function (response) {
				if (results.fired < 1 && response && response.data) {
					results.resolve(response.data.items);
				}
			});

			return QueryResults(results);
		},
		createQuery: function (query, options, id) {
			var me = this;

			if (id) {
				query.requestType = 'getRecord';
				query.recordID = id;
			} else {
				query.requestType = 'getList';
			}

			query.recordClass = options && options.recordClass || me.classConfig.className;

			query.displayHierarchic = me.isHierarchic;

			if (options) {
				query.limitStart = options.start;
				query.limitCount = options.count;

				query.sort = json.toJson(options.sort);

				if (options.requestingRecordClass) {
					query.requestingRecordClass = options.requestingRecordClass;
				}

				if (options.language) {
					query.language = options.language;
				}

				if (options.parent) {
					query.parent = options.parent.primary || options.parent;
				}

				if (typeof options.hierarchic !== 'undefined') {
					query.displayHierarchic = options.hierarchic;
				}

				if (options.forEditing) {
					query.forEditing = true;
				}

				if (options.previousEditedRecordClass && options.previousEditedRecordID) {
					query.previousEditedRecordClass = options.previousEditedRecordClass;
					query.previousEditedRecordID = options.previousEditedRecordID;
				}
			}

			query.contentLanguage = me.backend.config.system.languages.current.primary;
			query.contentDomainGroup = me.backend.config.system.domainGroups.current.primary;

			if (query._title) { // used by FilteringSelect -> convert to filter
				if (query._title != '') {
					if (!query.filter) {
						query.filter = [];
					}

					var filterValue = (query._title.toString ? query._title.toString() : query._title); // normalize value to string

					query.filter.push({
						filterFields: me.classConfig.titleFields,
						filterValue: filterValue,
						filterType: 'wildcard'
					});
				}
			}

			if (query.filter && lang.isArray(query.filter) && query.filter.length) {
				query.filter = json.toJson(query.filter);
			}

			if (!query.exclude) {
				delete query.exclude;
			}

			if (query.requestingRecordClass && !me.requestingRecordClass) {
				me.requestingRecordClass = query.requestingRecordClass;
				me.mainRecordClass = query.mainRecordClass;
				me.requestFieldName = query.requestFieldName;
			}

			if (me.requestingRecordClass && !query.requestingRecordClass) {
				query.requestingRecordClass = me.requestingRecordClass;
				query.requestFieldName = me.requestFieldName;
				query.mainRecordClass = me.mainRecordClass;
			}

			return query;
		},
		mayHaveChildren: function (object) {
			return object.children && object.children !== false;
		},
		getChildren: function (object, options) {
			return this.query(this.createQuery({ parent: object[this.idProperty]}));
		},
		getRoot: function (onItem, onError) {
		},
		getLabel: function (object) {
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			me.inherited(arguments);
		}
	});

});