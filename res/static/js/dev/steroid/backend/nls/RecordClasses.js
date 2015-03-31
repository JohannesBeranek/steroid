define({ root: ({
	_title: 'Title',
	_actions: 'Actions',
	multipleSelection: ' (multiple choice)',
	singleSelection: ' (please choose)',
	genericInvalid: 'Invalid value',
	type_dev: 'Dev',
	type_admin: 'Admin',
	type_content: 'Content',
	type_config: 'Config',
	type_system: 'System',
	type_ext_content: 'External content',
	type_widget: 'Widget',
	type_util: 'Utility',
	type_wizard: 'Wizard',
	widget_reference_warning: 'Widget is referenced in:',
	RCFile_name: 'File',
	RCChangeLog_name: 'Changelog',
	RCDomainGroup_name: 'Subweb',
	RCPage_name: 'Page',
	RCElementInArea_name: 'Element in Area',
	RCBackendPreferenceUser_name: 'Backend User Preference',
	RCDomain_name: 'Domain',
	RCFileCategory_name: 'File category',
	RCTemplateArea_name: 'Template area',
	RCUrl_name: 'Url',
	RCUrlHandler_name: 'Url handler',
	RCPermissionEntity_name: 'Permission Entity',
	RCPreviewSecret_name: 'Preview Secret',
	RCPageArea_name: 'Page area',
	RCEdit_name: 'Content Edit',
	RCFileType_name: 'File Type',
	RCDefaultMenu_name: 'Default menu',
	RCMessageBox_name: 'Message Box',
	RCGFXJob_name: 'GFX Job',
	RCResJob_name: 'Res job',
	RCArchive_name: 'Archive',
	RCUrlRewrite_name: 'Url rewrite',
	RCPageUrl_name: 'Page url',
	RCLog_name: 'Log',
	RCPermissionPermissionEntity_name: 'Permissions',
	RCPermission_name: 'User group',
	RCDomainGroupLanguagePermissionUser_name: 'Permission in subweb',
	RCTemplate_name: 'Template',
	RCUser_name: 'User/Creator',
	RCDefaultParentPage_name: 'Default parent page',
	RCFrontendLocalization_name: 'Frontend localization',
	RCLanguage_name: 'Language',
	RCPubDateEntries_name: 'PubDate Entries',
	RCPubDateEntries: {
		pubStart: 'Publish',
		pubEnd: 'Hide',
		fs_pubdates: 'Delay Publishing',
		pubDateMail: 'PubDate Cronjob Error - E-Mail'
	},
	generic: {
		_messageBox: {
			syncFail: {
				title: 'Synchronisation failed',
				text: 'Synchronisation of $rc "$url" in subweb $domainGroup failed $syncFails times. Please check the url'
			},
			recordDeleted: {
				title: 'Content was deleted',
				text: '$recordClass "$recordTitle" in subweb $domainGroup was deleted. This caused the following contents in your subweb ($targetDomainGroup) to get deleted aswell:<br/>'
			},
			delayedActionFail: {
				title: 'Delayed action failed',
				text: '$recordClass "$recordTitle" in subweb $domainGroup could not be $actioned'
			}
		},
		chart_liveStatus: {
			0: 'Hidden',
			1: 'Published',
			2: 'Modified'
		},
		widget_description: 'A widget',
		fs_main: 'General',
		primary: 'Primary',
		id: 'ID',
		live: 'Live',
		language: 'Language',
		title: 'Title',
		creator: 'Creator',
		mtime: 'Modified',
		ctime: 'Created',
		parent: 'Parent',
		fixed: 'Fixed',
		columns: 'Columns',
		area: 'Area',
		apiKey: 'API key',
		sorting: 'Sorting',
		key: 'Key',
		"class": 'Class',
		customUrl: 'Custom URL',
		text: 'Text',
		link: 'Link',
		video: 'Video',
		hidden: 'Hidden',
		secret: 'Secret',
		lastSync: 'Latest successful synchronization',
		lastSyncTry: 'Latest attempted synchronization',
		lastErrorCount: 'Errors since last try',
		allDay: 'Whole day',
		firstname: 'First name',
		lastname: 'Last name',
		gender: 'Gender (m/f)',
		filename: 'Filename',
		template: 'Template',
		description: 'Description',
		url: 'Url',
		useStaging: 'Use staging',
		domain: 'Domain',
		mimeCategory: 'MIME category',
		mimeType: 'MIME type',
		username: 'Username',
		returnCode: 'Return code',
		className: 'Class name',
		uid: 'UID',
		lastModified: 'Last modified',
		dstart: 'Start date',
		tstart: 'Start time',
		dend: 'End date',
		tend: 'End time',
		fullname: 'Full name',
		value: 'Value',
		lastUse: 'Last use',
		hash: 'Hash',
		recordClass: 'Recordclass',
		autoSyncInterval: 'Auto sync interval',
		autoSync: 'Auto sync',
		is_backendAllowed: 'Backend access',
		'page:RCPageUrl': 'Url',
		externalUrl: 'External url',
		pubDate: 'Publishing date',
		pubTime: 'Publishing time',
		widget_description: 'A widget',
		zip: 'ZIP code',
		image: 'Image',
		pubStart: 'Publish',
		pubEnd: 'Hide',
		fs_pubdates: 'Delay Publishing'
	},
	widgets: {
		type_list_name: 'List',
		type_crm_name: 'CRM',
		type_media_name: 'Media',
		type_general_name: 'General',
		type_teaser_name: 'Teaser',
		type_event_name: 'Event',
		type_person_name: 'Person',
		type_external_name: 'External',
		copy: 'Copy',
		close: 'Remove',
		hide: 'Hide',
		publish: 'Publish',
		insert: 'Insert'
	},
	RCArchive: {
		type: 'Type',
		type_values: {
			useDate: 'By date',
			useAge: 'By age'
		},
		date: 'Date',
		age: 'Age in months'
	},
	RCChangeLog: {
		alert: 'Alert'
	},
	RCFrontendLocalization: {
		variables: 'Variables'
	},
	RCPage: {
		page_RCPageArea: 'Page area',
		pageType: 'Page type',
		forwardTo: 'Forward to different page',
		'page:RCPageUrl': 'URLs',
		'page:RCPageUrl.url.url': 'URLs',
		'page:RCMenuItem': 'Menu editing (restricted)',
		robots: 'Search engine directive ("robots")',
		robots_values: {
			noindex: 'Do not show page (noindex)',
			nofollow: 'Do not show links on this page (nofollow)',
			none: 'Do not show page and links on this page (none)'
		},
		menu: 'Edit menus (limited)',
		fs_url: 'URLs',
		fs_search: 'Search',
		fs_pageContent: 'Page content',
		image: 'Header image',
		description: 'Description (meta)'
	},
	RCUser: {
		_title: 'Name',
		username: 'Username or CRM ID',
		gender_values: {
			m: 'Male',
			f: 'Female'
		}
	},
	RCUrl: {
		url: 'Url',
		returnCode: 'Returncode',
		primary: 'Primary',
		secondary: 'Secondary',
		currentLive: 'Current live URL'
	},
	RCFile: {
		file_RCFileUrl: 'Url',
		downloadFilename: 'Filename for download',
		copyright: 'Copyright',
		comment: 'Comment (internally)',
		alt: 'Alt text',
		lockToDomainGroup: 'May not be used elsewhere (and will not show up for other subwebs either)',
		renderConfig: 'Render config'
	},
	RCMessageBox: {
		sendToAll: 'Send message to everyone',
		alert: 'Alert',
		user: 'Send to a single user',
		domainGroup: 'Send to all users of subweb'
	},
	RCDomain: {
		disableTracking: 'Disable tracking',
		returnCode_values: {
			200: 'Primary',
			418: 'Alias'
		},
		noSSL: 'Disable SSL encryption',
		redirectToUrl: 'Redirect to URL',
		redirectToPage: 'Redirect to Page'
	},
	RCLanguage: {
		iso639: 'ISO-639 code',
		locale: 'Locale',
		isDefault: 'Is default language'
	},
	RCTemplate: {
		isStartPageTemplate: 'Start page template',
		widths: 'Widths (comma separated)'
	},
	RCDomainGroup: {
		parent: 'Parent subweb',
		favicon: 'Favicon',
		notFoundPage: '404 page'
	},
	RCPermissionEntity: {
		mayWrite: 'Write permission',
		restrictToOwn: 'Restrict to own entries',
		mayPublish: 'May publish',
		mayHide: 'May hide',
		mayDelete: 'May delete',
		mayCreate: 'May create'
	},
	RCDomainGroupLanguagePermissionUser: {
		'permission:RCPermissionPerPage': 'Start at page(s) (empty = "all")',
		_title: 'User'
	},
	RCFieldPermission_name: 'Field permissions',
	RCFieldPermission: {
		readOnlyFields: 'Read-only fields'
	},

	// WIDGETS

	closeButtonLabel: 'x',
	hideButtonLabelHidden: '[  ]',
	hideButtonLabel: '_',
	isHidden: 'Hidden',
	RCArea_name: 'Area',
	RCArea: {
		widget_description: 'An area that can hold other areas and widgets'
	},

//	MENU BUILDER

	RCMenu_name: 'Menu',
	RCMenu: {
		root: 'Root page',
		defaultMenu: 'Default menu',
		'menu:RCMenuItem': 'Menu items',
		foreignDomainGroupMenuItemReference: 'Other subwebs'
	},
	RCMenuItem_name: 'Menu item',
	RCMenuItem: {
		_description: 'Drag this into the menu to create a new custom menu item',
		icon: 'Icon (only in first level of main menu)',
		url: 'External url',
		showInMenu: 'Show in menu',
		subItemsFromPage: 'Display child pages in menu',
		pagesFromRecordClass: 'Display other page types as children',
		alignRight: 'Align right'
	}
}),
	'de': true
});
