define({
	_title: 'Titel',
	_actions: 'Aktionen',
	multipleSelection: ' (Mehrfachauswahl möglich)',
	singleSelection: ' (bitte auswählen)',
	genericInvalid: 'Ungültiger Wert',
	type_admin: 'Admin',
	type_content: 'Inhalte',
	type_config: 'Konfiguration',
	type_system: 'System',
	type_widget: 'Widget',
	type_ext_content: 'Externe Inhalte',
	type_util: 'Werkzeuge',
	type_wizard: 'Assistenten',
	RCFile_name: 'Datei',
	RCChangeLog_name: 'Changelog',
	RCDomainGroup_name: 'Subweb',
	RCPage_name: 'Seite',
	RCGFXJob_name: 'GFX Job',
	RCDefaultMenu_name: 'Standardmenü',
	RCDefaultParentPage_name: 'Standardelternseite',
	RCElementInArea_name: 'Element in Bereich',
	RCBackendPreferenceUser_name: 'Backend User Einstellungen',
	RCDomain_name: 'Domain',
	RCFileCategory_name: 'Dateikategorie',
	RCTemplateArea_name: 'Templatebereich',
	RCUrl_name: 'Url',
	RCUrlHandler_name: 'Url handler',
	RCPermissionEntity_name: 'Rechte-Entität',
	RCPreviewSecret_name: 'Vorschau-Schlüssel',
	RCPageArea_name: 'Seitenbereich',
	RCEdit_name: 'Inhaltsbearbeitung',
	RCFileType_name: 'Dateityp',
	RCArchive_name: 'Archiv',
	RCMessageBox_name: 'Nachrichten',
	RCLog_name: 'Log',
	RCPermission_name: 'Benutzergruppen',
	RCDomainGroupLanguagePermissionUser_name: 'Rechte in Subweb',
	RCTemplate_name: 'Template',
	RCResJob_name: 'Res Job',
	RCUrlRewrite_name: 'Url Überschreibung',
	RCPageUrl_name: 'Page Url',
	RCUser_name: 'Benutzer/Ersteller',
	RCFrontendLocalization_name: 'Frontend Übersetzungen',
	RCLanguage_name: 'Sprache',
	RCPubDateEntries_name: 'PubDate Einträge',
	RCPubDateEntries: {
		pubDateMail: 'PubDate Cronjob Fehler - E-Mail'
	},
	generic: {
		_messageBox: {
			syncFail: {
				title: 'Synchronisation failed',
				text: 'Synchronisation von $rc "$url" im Subweb $domainGroup ist $syncFailsx fehlgeschlagen. Bitte überprüfen sie die Url'
			},
			recordDeleted: {
				title: 'Inhalte gelöscht',
				text: '$recordClass "$recordTitle" im Subweb $domainGroup wurde gelöscht. Dies hatte zur Folge, dass diese Inhalte aus Ihrem Subweb ($targetDomainGroup) ebenfalls gelöscht wurden: <br/>'
			},
			delayedActionFail: {
				title: 'Verzögerte Aktion fehlgeschlagen',
				text: 'Aktion $action für $recordClass "$recordTitle" in Subweb $domainGroup konnte nicht durchgeführt werden'
			}
		},
		chart_liveStatus: {
			0: 'Unveröffentlicht',
			1: 'Veröffentlicht',
			2: 'Modifiziert',
		},
		widget_description: 'Ein Widget',
		fs_main: 'Allgemein',
		primary: 'Primär',
		id: 'ID',
		live: 'Live',
		autoSync: 'Automatisch synchronisieren',
		autoSyncInterval: 'Intervall für automatische Synchronisation',
		title: 'Titel',
		creator: 'Ersteller',
		mtime: 'Modifiziert',
		ctime: 'Erstellt',
		parent: 'Kind von',
		columns: 'Spalten',
		area: 'Bereich',
		sorting: 'Sortierung',
		key: 'Schlüssel',
		class: 'Klasse',
		customUrl: 'Benutzerdefinierte URL',
		text: 'Text',
		link: 'Link',
		hidden: 'Versteckt',
		secret: 'Geheim',
		lastSync: 'Letzte erfolgreiche Synchronisation',
		lastSyncTry: 'Letzte versuchte Synchronisation',
		lastErrorCount: 'Fehler seit letzter versuchter Synchronisation',
		allDay: 'Ganzer Tag',
		firstname: 'Vorname',
		lastname: 'Nachname',
		gender: 'Geschlecht (m/f)',
		filename: 'Dateiname',
		template: 'Template',
		description: 'Beschreibung',
		url: 'Url',
		useStaging: 'Staging verwenden',
		domain: 'Domain',
		mimeCategory: 'MIME Kategorie',
		excludeFromSearch: 'Von Suche ausschließen',
		mimeType: 'MIME Typ',
		username: 'Benutzername',
		returnCode: 'Antwortcode',
		className: 'Klassenname',
		uid: 'UID',
		lastModified: 'Zuletzt geändert',
		dstart: 'Startdatum',
		tstart: 'Startzeit',
		dend: 'Enddatum',
		tend: 'Endzeit',
		fullname: 'Voller Name',
		value: 'Wert',
		source: 'Quelle',
		formatted: 'Formattiert',
		apiKey: 'API key',
		lastUse: 'Letzte Verwendung',
		hash: 'Hash',
		recordClass: 'Record Klasse',
		is_backendAllowed: 'Backend Zugriff',
		externalUrl: 'External url',
		pubDate: 'Veröffentlichungsdatum',
		pubTime: 'Veröffentlichungszeit',
		zip: 'PLZ',
		image: 'Bild',
		pubStart: 'Veröffentlichen',
		pubEnd: 'Verstecken',
		fs_pubdates: 'Verzögerte Veröffentlichung'
	},
	widgets: {
		type_list_name: 'Listen',
		type_teaser_name: 'Teaser',
		type_crm_name: 'CRM',
		type_general_name: 'Allgemein',
		type_media_name: 'Medien',
		type_event_name: 'Events',
		type_person_name: 'Personen',
		type_external_name: 'Extern',
		copy: 'Kopieren',
		close: 'Löschen',
		hide: 'Verstecken',
		publish: 'Veröffentlichen',
		insert: 'Einfügen'
	},
	RCArchive: {
		type: 'Typ',
		type_values: {
			useDate: 'Nach Datum',
			useAge: 'Nach Alter'
		},
		date: 'Datum',
		age: 'Alter in Monaten'
	},
	RCFrontendLocalization: {
		variables: 'Variablen'
	},
	RCPage: {
		parent: 'Elternseite',
		'page:RCPageArea': 'Seitenbereich',
		pageType: 'Seitentyp',
		forwardTo: 'Weiterleiten an andere Seite',
		'page:RCPageUrl': 'URLs',
		'page:RCPageUrl.url.url': 'URLs',
		'page:RCMenuItem': 'Menübearbeitung (eingeschränkt)',
		robots: 'Suchmaschinenhinweis ("robots")',
		robots_values: {
			noindex: 'Seite nicht anzeigen (noindex)',
			nofollow: 'Links auf der Seite nicht anzeigen (nofollow)',
			none: 'Seite und Links auf der Seite nicht anzeigen (none)'
		},
		menu: 'Menübearbeitung (eingeschränkt)',
		fs_url: 'URLs',
		fs_search: 'Suche',
		fs_pageContent: 'Seiteninhalt',
		image: 'Headerbild',
		description: 'Beschreibung (meta)'
	},
	RCUser: {
		_title: 'Name',
		username: 'CRM ID'
	},
	RCUrl: {
		url: 'Url',
		returnCode: 'Antwortcode',
		primary: 'Primär',
		secondary: 'Sekundär',
		currentLive: 'Aktuelle Live URL'
	},
	RCFile: {
		'file:RCFileUrl': 'Url',
		downloadFilename: 'Dateiname für Download',
		copyright: 'Copyright',
		comment: 'Kommentar (nur intern)',
		alt: 'Alt Text',
		lockToDomainGroup: 'Darf nicht weiterverwendet werden (und wird anderen Subwebs auch nicht angezeigt)',
		setFocusButtonLabel: 'Fokuspunkt setzen',
		removeFocusButtonLabel: 'Fokuspunkt entfernen',
		formats: {
			original: 'Original Seitenverhältnis',
			ratio_16x9: '16:9',
			ratio_4x3: '4:3',
			ratio_1x1: 'Quadratisch'
		},
		enablePreview: 'Vorschau aktivieren',
		dummyLabel: 'In das Bild klicken um Fokuspunkt zu setzen',
		renderConfig: 'Rendering Konfiguration'
	},
	RCMessageBox: {
		sendToAll: 'Nachricht an alle senden',
		alert: 'Wichtig',
		user: 'Nachricht nur an einen User',
		domainGroup: 'An alle User dieses Subwebs senden'
	},
	RCLanguage: {
		iso639: 'ISO-639 code',
		locale: 'Locale',
		isDefault: 'Standardsprache'
	},
	RCTemplate: {
		isStartPageTemplate: 'Template für Startseite',
		widths: 'Breiten (kommagetrennt)'
	},
	RCDomain: {
		disableTracking: 'Tracking deaktivieren',
		noSSL: 'SSL Verschlüsselung deaktivieren',
		returnCode_values: {
			200: 'Primär',
			418: 'Alias'
		},
		redirectToUrl: 'Weiterleiten auf URL',
		redirectToPage: 'Weiterleiten auf Seite'
	},
	RCDomainGroup: {
		favicon: 'Favicon',
		notFoundPage: '404 Seite',
		mayChangeTopicNodeHeadlines: 'Darf Überschriften auf Knotenseiten verändern'
	},
	RCPermissionEntity: {
		mayWrite: 'Schreibrechte',
		restrictToOwn: 'Auf eigene beschränken',
		mayPublish: 'Darf veröffentlichen',
		mayHide: 'Darf verstecken',
		mayDelete: 'Darf löschen',
		mayCreate: 'Darf erstellen'
	},
	RCDomainGroupLanguagePermissionUser: {
		'permission:RCPermissionPerPage': 'Ab dieser Seite (leer = "Alle")',
		_title: 'Benutzer'
	},
	RCFieldPermission: {
		readOnlyFields: 'Folgende Felder auf Leserechte beschränken:'
	},

//	WIDGETS

	closeButtonLabel: 'x',
	hideButtonLabelHidden: '&curren;',
	hideButtonLabel: '_',
	isHidden: 'Versteckt',
	RCArea_name: 'Bereich',
	RCArea: {
		widget_description: 'Ein Bereich der weitere Bereiche oder Widgets beinhalten kann'
	},

//	MENU BUILDER

	RCMenu_name: 'Menü',
	RCMenu: {
		root: 'Aus Unterseiten von folgender Seite generieren',
		foreignDomainGroupMenuItemReference: 'Andere Subwebs'
	},
	RCMenuItem_name: 'Menüpunkt',
	RCMenuItem: {
		_description: 'In das Menü ziehen um einen neuen Menüpunkt hinzuzufügen',
		icon: 'Icon',
		url: 'Externe Url',
		showInMenu: 'Im Menü anzeigen',
		subItemsFromPage: 'Unterseiten der ausgewählten Seite anzeigen',
		pagesFromRecordClass: 'Untermenü aus folgenden Inhaltstypen generieren'
	}
});
