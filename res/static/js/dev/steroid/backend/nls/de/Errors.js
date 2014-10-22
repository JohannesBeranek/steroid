define({
	generic: {
		title: 'Fehler',
		message: 'Das kann ich nicht tun, Dave'
	},
	confirm: {
		title: 'Bitte bestätigen',
		message: 'Wollen Sie diesen Eintrag wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden!'
	},
	unexpected_token: {
		title: 'Unbekanntes Zeichen',
		message: 'Ups, da ist was schief gegangen'
	},
	UnknownRequestException: {
		title: 'Sie wurden ausgeloggt',
		message: 'Unbekannte Anfrage. Sie wurden ausgeloggt.'
	},
	LoginFailException: {
		title: 'Login falsch'
	},
	MissingTemplateException: {
		title: 'Template fehlt',
		message: "Speichern fehlgeschlagen, weil für $rc kein Template gefunden werden konnte"
	},
	WarningException: {
		title: 'Warnung',
		message: "Diese Aktion ist nicht erlaubt"
	},
	LogoutFailException: {
		title: 'Fehler',
		message: "Tut mir leid, Sie müssen noch hier bleiben"
	},
	Exception: {
		title: 'Ausnahmefehler',
		message: 'Ausnahmsweise ist eine Ausnahme aufgetreten'
	},
	CannotCopyRecordException: {
		title: 'Referenz nicht veröffentlicht',
		message: 'Die Veröffentlichung von $rc "$record" ist fehlgeschlagen, da der/die verlinkte $field noch nicht veröffentlich wurde: "$targetRecord"'
	},
	NoChangeException: {
		title: 'Nichts passiert',
		message: "Sie wollten etwas tun das nicht passiert ist. Und jetzt?"
	},
	MissingReferencesException: {
		title: 'Andere Einträge die ebenfalls veröffentlich werden müssen (ausgegraut), bzw können (auswählbar)'
	},
	AffectedReferencesException: {
		title: 'Die folgenden Einträge müssen ebenfalls versteckt werden'
	},
	AccessDeniedException: {
		title: 'Zugriff verweigert',
		message: 'Sie haben im aktuellen Subweb keine Zugriffsberechtigung auf den Inhaltstyp "$rc"'
	},
	ActionDeniedException: {
		title: 'Aktion verweigert',
		message: 'Sie haben nicht die erforderlichen Berechtigungen um diesen Eintrag zu $action'
	},
	RecordActionDeniedException: {
		title: 'Aktion verweigert',
		message: 'Aktion "$action" ist für diesen Eintrag deaktiviert'
	},
	UrlParseException: {
		title: 'Ungültige Url',
		message: '$rc "$record" konnte nicht erkannt werden. Bitte achten Sie darauf, eine gültige url anzugeben. Urls auf spezifische Inhalte (z.B. einzelne Facebook Posts) sind möglicherweise nicht erlaubt'
	},
	NoSyncKeyRecordException: {
		title: 'API Key benötigt',
		message: 'Synchronisaton fehlgeschlagen, da der Inhalt geschützt ist. Bitte legen Sie entweder einen API Key an, oder machen Sie den Inhalt öffentlich zugängig'
	},
	InvalidValueForFieldException: {
		title: 'Ungültiger Wert',
		message: 'Ungültiger Wert für Feld "$field"'
	},
	ParentOfItselfException: {
		title: 'Ungültiger Wert',
		message: 'Eintrag kann nicht Kind von sich selbst sein'
	},
	NoParentPageException: {
		title: 'Keine Elternseite',
		message: 'Es konnte keine Elternseite für "$record" gefunden werden'
	},
	NoRootPageException: {
		title: 'Keine Startseite',
		message: 'Subweb "$targetRecord" hat keine Startseite!'
	},
	RootPageExistsException: {
		title: 'Keine Elternseite',
		message: 'Bitte wählen Sie eine Elternseite für "$record"'
	},
	RecordIsLockedException: {
		title: 'Inhalt gesperrt, wird gerade bearbeitet von',
		message: 'Dieser Inhalt wird gerade bearbeitet von '
	},
	RecordDoesNotExistException: {
		title: 'Inhalt existiert nicht',
		message: '$rc wurde entfernt.'
	},
	TargetDoesNotExistException: {
		title: 'Ziel existiert nicht',
		message: 'Der/die ausgewählte $field wurde entfernt'
	},
	RecordLimitedToDomainGroupException: {
		title: 'Eintrag hat eingeschränkten Zugriff',
		message: '$rc "$record" ist auf die Verwendung im Subweb "$targetRecord" beschränkt'
	},
	InvalidPubdateException: {
		title: 'PubDate Fehler',
		message: 'Es gibt ein Problem mit den derzeitigen pubDates'
	},
	unsavedChanges: {
		title: 'Ungespeicherte Änderungen',
		message: 'Ihre Änderungen wurden noch nicht gespeichert. Möchten Sie trotzdem schließen?'
	},
	affected: {
		hide: 'References that will be hidden: ',
		'delete': 'References that will be deleted: ',
		publish: 'References what will be published: ',
		revert: 'References that will be reverted: ',
		confirm: 'Wollen Sie diesen Eintrag wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden!'
	},
	domainGroupModified: {
		title: 'Subwebänderung',
		message: 'Sie haben ein Subweb erstellt oder verändert. Möchten Sie in dieses wechseln?'
	},
	DomainTakenException: {
		title: 'Domain vergeben',
		message: 'Diese Domain ist bereits bei $rc $record in Verwendung.'
	},
	BtnOK: 'OK',
	BtnMore: 'Details',
	BtnYes: 'Ja',
	BtnNo: 'Nein',
	BtnPublish: 'Ausgewählte veröffentlichen',
	BtnCancel: 'Abbrechen',
	simpleErrorPaneTitle: 'Info',
	detailErrorPaneTitle: 'Details',
	required: '(benötigt)',
	optional: '(optional)'
});