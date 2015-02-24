define({ root: ({
	generic: {
		title: 'Error',
		message: 'I cannot do that, Dave'
	},
	confirm: {
		title: 'Please confirm',
		message: 'Are you sure you want to delete this record? This action cannot be undone!'
	},
	unexpected_token: {
		title: 'Unexpected token',
		message: 'I did not expect such a token to be taken'
	},
	UnknownRequestException: {
		title: 'You have been logged out',
		message: 'Unknown request. You have been logged out.'
	},
	LoginFailException: {
		title: 'Login incorrect'
	},
	WarningException: {
		title: 'Warning',
		message: "This action is not allowed"
	},
	MissingTemplateException: {
		title: 'Missing template',
		message: "There is no page template defined for this type of record"
	},
	LogoutFailException: {
		title: 'Error',
		message: "I'm sorry, but I can't let you go"
	},
	Exception: {
		title: 'Exceptional error',
		message: 'Whoopsie, you managed to blow up the internets. Good job'
	},
	CannotCopyRecordException: {
		title: 'Reference not published',
		message: 'Cannot publish "$record" because the following $field is not published: "$targetRecord"'
	},
	NoChangeException: {
		title: 'No change',
		message: "You were trying to do something, but it didn't happen"
	},
	MissingReferencesException: {
		title: 'Other records that must (greyed out), or can (selectable) also be published'
	},
	AffectedReferencesException: {
		title: 'Other records that also must be hidden'
	},
	AccessDeniedException: {
		title: 'Access denied',
		message: 'You are not allowed to access $rc "$record" in current subweb'
	},
	ActionDeniedException: {
		title: 'Action denied',
		message: 'You lack permissions for action "$action" on $rcs'
	},
	RecordActionDeniedException: {
		title: 'Record action denied',
		message: 'Action "$action" is disabled on $rcs'
	},
	UrlParseException: {
		title: 'Invalid Url',
		message: '$rc "$record" could not be recognized. Please ensure to enter a correct url. Urls to specific content (e.g. a single post on Facebook) may not be allowed'
	},
	NoSyncKeyRecordException: {
		title: 'Need key',
		message: 'Synchronisation failed, because the content is restricted. Please create an API Key or make the content publicly available'
	},
	InvalidValueForFieldException: {
		title: 'Invalid value',
		message: 'Invalid value for field "$field"'
	},
	ParentOfItselfException: {
		title: 'Invalid parent',
		message: 'Entry cannot be a parent of itself'
	},
	NoParentPageException: {
		title: 'No parent page',
		message: 'No parent page for "$record" found'
	},
	NoRootPageException: {
		title: 'No root page',
		message: 'Subweb "$targetRecord" has no root page!'
	},
	RootPageExistsException: {
		title: 'No parent page',
		message: 'Please choose a parent page for "$record"'
	},
	RecordIsLockedException: {
		title: 'Content locked, being edited by',
		message: 'This content is currently being edited by '
	},
	RecordDoesNotExistException: {
		title: 'Record does not exist',
		message: "The $rc doesn't exist anymore"
	},
	TargetDoesNotExistException: {
		title: 'Target does not exist',
		message: "The $field you've selected doesn't exist any more"
	},
	RecordLimitedToDomainGroupException: {
		title: 'Record has limited access',
		message: 'Usage of $rc "$record" restricted to subweb "$targetRecord"'
	},
	InvalidPubdateException: {
		title: 'PubDate Error',
		message: 'There is a problem with your current pubDates'
	},
	unsavedChanges: {
		title: 'Unsaved changes',
		message: 'There are unsaved changes, do you really want to continue?'
	},
	revertRecord: {
		title: 'Revert to live',
		message: 'Any changes you have made will be overwritten by the live version. Continue?'
	},
	affected: {
		hide: 'References that will be hidden: ',
		'delete': 'References that will be deleted: ',
		publish: 'References what will be published: ',
		revert: 'References that will be reverted: ',
		confirm: 'Are you sure you want to delete this record? This action cannot be undone!'
	},
	domainGroupModified: {
		title: 'Domain group changed',
		message: 'You have created or modified a domain group. Would you like to switch to it?'
	},
	DomainTakenException: {
		title: 'Domain taken',
		message: 'This domain is already taken by $rc $record.'
	},
	BtnOK: 'OK',
	BtnMore: 'Details',
	BtnYes: 'Yes',
	BtnNo: 'No',
	BtnPublish: 'Publish selected',
	BtnCancel: 'Cancel',
	simpleErrorPaneTitle: 'Info',
	detailErrorPaneTitle: 'Details',
	required: '(required)',
	optional: '(optional)'

}),
	'de': true
});