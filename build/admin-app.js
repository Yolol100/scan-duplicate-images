( function () {
	'use strict';

	if ( ! window.mediaInsightSettings || ! window.wp || ! window.wp.element || ! window.wp.components || ! window.wp.apiFetch ) {
		return;
	}

	var settings = window.mediaInsightSettings;
	var wp = window.wp;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var createRoot = wp.element.createRoot;
	var render = wp.element.render;
	var apiFetch = wp.apiFetch;
	var components = wp.components;
	var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( text ) { return text; };
	var sprintf = wp.i18n && wp.i18n.sprintf ? wp.i18n.sprintf : function ( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var sequentialIndex = 0;

		return String( text ).replace( /%(\d+\$)?s/g, function ( match, position ) {
			var index = position ? parseInt( position, 10 ) - 1 : sequentialIndex++;
			return typeof args[ index ] === 'undefined' ? match : args[ index ];
		} );
	};
	var speak = wp.a11y && wp.a11y.speak ? wp.a11y.speak : function () {};
	var maxScanLimit = parseInt( settings.maxScanLimit, 10 );

	maxScanLimit = isFinite( maxScanLimit ) && maxScanLimit > 0 ? maxScanLimit : 50000;

	var Card = components.Card || function ( props ) {
		var cardProps = Object.assign( {}, props, { className: props.className ? props.className + ' components-card' : 'components-card' } );
		return el( 'div', cardProps, props.children );
	};
	var CardBody = components.CardBody || function ( props ) {
		var bodyProps = Object.assign( {}, props, { className: props.className ? props.className + ' components-card__body' : 'components-card__body' } );
		return el( 'div', bodyProps, props.children );
	};
	var CardHeader = components.CardHeader || function ( props ) {
		var headerProps = Object.assign( {}, props, { className: props.className ? props.className + ' components-card__header' : 'components-card__header' } );
		return el( 'div', headerProps, props.children );
	};
	var Button = components.Button || function ( props ) {
		var buttonProps = Object.assign( {}, props );
		var children = props.children;

		delete buttonProps.children;
		delete buttonProps.isBusy;
		delete buttonProps.isSmall;
		delete buttonProps.variant;

		if ( props.label && ! buttonProps['aria-label'] ) {
			buttonProps['aria-label'] = props.label;
		}

		delete buttonProps.label;

		if ( props.href ) {
			buttonProps.className = buttonProps.className ? buttonProps.className + ' components-button' : 'components-button';
			if ( props.disabled ) {
				delete buttonProps.href;
				buttonProps['aria-disabled'] = 'true';
			}
			delete buttonProps.disabled;
			return el( 'a', buttonProps, children );
		}

		buttonProps.type = buttonProps.type || 'button';
		return el( 'button', buttonProps, children );
	};
	var ButtonGroup = components.ButtonGroup || function ( props ) {
		return el( 'div', { className: props.className ? props.className + ' components-button-group' : 'components-button-group' }, props.children );
	};
	var Notice = components.Notice || function ( props ) {
		return el( 'div', { className: props.className || 'notice notice-' + ( props.status || 'info' ) }, props.children );
	};
	var Spinner = components.Spinner || function () {
		return el( 'span', { className: 'spinner is-active' } );
	};
	var TextControl = components.TextControl || function ( props ) {
		return el( 'label', { className: 'media-insight-text-control-fallback' },
			el( 'span', {}, props.label ),
			el( 'input', { type: props.type || 'text', min: props.min, max: props.max, value: props.value, onChange: function ( event ) { props.onChange( event.target.value ); } } ),
			props.help ? el( 'span', { className: 'description' }, props.help ) : null
		);
	};

	function Panel( props ) {
		return el( Card, { className: 'media-insight-panel ' + ( props.className || '' ) },
			el( HeaderSlot, { className: 'media-insight-panel-header' },
				el( 'div', { className: 'media-insight-panel-heading' },
					el( 'h2', {}, props.title ),
					props.description ? el( 'p', { className: 'media-insight-muted' }, props.description ) : null
				),
				props.actions ? el( 'div', { className: 'media-insight-panel-actions' }, props.actions ) : null
			),
			el( CardBody, {}, props.children )
		);
	}

	function Chip( props ) {
		var className = 'media-insight-chip' + ( props.className ? ' ' + props.className : '' );

		return el( 'span', { className: className }, props.children );
	}

	if ( apiFetch.createNonceMiddleware ) {
		apiFetch.use( apiFetch.createNonceMiddleware( settings.restNonce ) );
	}

	function restPath( path ) {
		return '/' + settings.restNamespace + path;
	}

	function clampPercent( value ) {
		return Math.max( 0, Math.min( 100, Math.round( value ) ) );
	}

	function percent( status ) {
		if ( ! status ) {
			return 0;
		}

		if ( typeof status.progress === 'number' ) {
			return clampPercent( status.progress );
		}

		if ( status.status === 'complete' ) {
			return 100;
		}

		if ( ! status.total ) {
			return 0;
		}

		return clampPercent( ( status.processed / status.total ) * 100 );
	}

	function parseLimit( value ) {
		var parsed = parseInt( value, 10 );

		if ( ! isFinite( parsed ) || parsed < 0 ) {
			return 0;
		}

		return parsed > maxScanLimit ? maxScanLimit : parsed;
	}

	function getDefaultLimit() {
		var defaultLimit = parseLimit( settings.defaultScanLimit || 500 );
		return String( defaultLimit || 500 );
	}

	function formatNumber( value ) {
		var number = parseInt( value, 10 );
		var locale = document.documentElement && document.documentElement.lang ? document.documentElement.lang : undefined;

		number = isFinite( number ) ? number : 0;

		try {
			return window.Intl && window.Intl.NumberFormat ? new window.Intl.NumberFormat( locale ).format( number ) : String( number );
		} catch ( error ) {
			return String( number );
		}
	}

	function isTerminalStatus( status ) {
		return status && [ 'complete', 'failed', 'cancelled' ].indexOf( status.status ) !== -1;
	}

	function isRunningStatus( status ) {
		return status && [ 'queued', 'running' ].indexOf( status.status ) !== -1;
	}

	function getActiveScanStorageKey() {
		return 'mediaInsightActiveScan:' + ( settings.restNamespace || 'media-insight/v2' );
	}

	function getSafeLocalStorage() {
		try {
			return window.localStorage || null;
		} catch ( error ) {
			void error;
			return null;
		}
	}

	function rememberActiveScan( scanId ) {
		var storage = getSafeLocalStorage();

		if ( ! scanId || ! storage ) {
			return;
		}

		try {
			storage.setItem( getActiveScanStorageKey(), String( scanId ) );
		} catch ( error ) {
			void error;
		}
	}

	function forgetActiveScan() {
		var storage = getSafeLocalStorage();

		if ( ! storage ) {
			return;
		}

		try {
			storage.removeItem( getActiveScanStorageKey() );
		} catch ( error ) {
			void error;
		}
	}

	function getRememberedActiveScan() {
		var storage = getSafeLocalStorage();

		if ( ! storage ) {
			return '';
		}

		try {
			return storage.getItem( getActiveScanStorageKey() ) || '';
		} catch ( error ) {
			void error;
			return '';
		}
	}

	function safeSpeak( message ) {
		try {
			speak( message );
		} catch ( error ) {
			void error;
		}
	}

	function statusLabel( status ) {
		if ( ! status || ! status.status ) {
			return __( 'Ready', 'media-insight' );
		}

		if ( status.status === 'queued' ) {
			return __( 'Queued', 'media-insight' );
		}

		if ( status.status === 'running' ) {
			return __( 'Running', 'media-insight' );
		}

		if ( status.status === 'complete' ) {
			return __( 'Complete', 'media-insight' );
		}

		if ( status.status === 'cancelled' ) {
			return __( 'Cancelled', 'media-insight' );
		}

		if ( status.status === 'failed' ) {
			return __( 'Failed', 'media-insight' );
		}

		return status.status;
	}

	function statusClass( status ) {
		if ( ! status || ! status.status ) {
			return 'is-ready';
		}

		return 'is-' + status.status;
	}

	function statusDescription( status ) {
		if ( status && status.message ) {
			return status.message;
		}

		if ( ! status || ! status.status ) {
			return __( 'Start with the standard limit, review the first report, then run a full scan when needed.', 'media-insight' );
		}

		if ( status.status === 'complete' ) {
			return settings.i18n.complete || __( 'Scan complete.', 'media-insight' );
		}

		if ( status.status === 'cancelled' ) {
			return settings.i18n.cancelled || __( 'Scan cancelled.', 'media-insight' );
		}

		if ( status.status === 'failed' ) {
			return settings.i18n.failed || __( 'The scan failed. Please try again or reduce the scan limit.', 'media-insight' );
		}

		return settings.i18n.running || __( 'Scanning media usage...', 'media-insight' );
	}

	function sourceLabel( source ) {
		if ( source === 'acf_page_image' ) {
			return __( 'ACF image', 'media-insight' );
		}

		if ( source === 'acf_page_gallery' ) {
			return __( 'ACF gallery', 'media-insight' );
		}

		return __( 'Featured image', 'media-insight' );
	}

	function postTypeLabel( postType ) {
		if ( postType === 'page' ) {
			return __( 'Page', 'media-insight' );
		}

		if ( postType === 'post' ) {
			return __( 'Post', 'media-insight' );
		}

		return postType || __( 'Content', 'media-insight' );
	}

	function summarizeSources( usages ) {
		var counts = {};
		( usages || [] ).forEach( function ( usage ) {
			var key = usage.source || 'featured_image';
			counts[ key ] = counts[ key ] ? counts[ key ] + 1 : 1;
		} );

		return Object.keys( counts ).map( function ( key ) {
			return {
				key: key,
				label: sourceLabel( key ),
				count: counts[ key ]
			};
		} );
	}

	function HeaderSlot( props ) {
		return el( CardHeader, { className: props.className }, props.children );
	}

	function StatusBadge( props ) {
		return el( 'span', { className: 'media-insight-status-badge ' + statusClass( props.status ) }, props.children || statusLabel( props.status ) );
	}

	function ScopeList() {
		var items = [
			__( 'Pages: featured images', 'media-insight' ),
			__( 'Pages: ACF image and gallery fields', 'media-insight' ),
			__( 'Posts: featured images', 'media-insight' )
		];

		return el( 'div', { className: 'media-insight-scope-card' },
			el( 'ul', { className: 'media-insight-scope-list', role: 'list' }, items.map( function ( item ) {
				return el( 'li', { key: item },
					el( 'span', { className: 'media-insight-scope-icon', 'aria-hidden': 'true' } ),
					el( 'span', {}, item )
				);
			} ) )
		);
	}

	function ScanPresetButtons( props ) {
		var currentLimit = parseLimit( props.limit );
		var presets = [
			{
				label: __( 'Quick', 'media-insight' ),
				value: 100,
				help: __( '100 items', 'media-insight' )
			},
			{
				label: __( 'Standard', 'media-insight' ),
				value: 500,
				help: __( '500 items', 'media-insight' )
			},
			{
				label: __( 'Full site', 'media-insight' ),
				value: 0,
				help: __( 'All supported content', 'media-insight' )
			}
		];

		return el( ButtonGroup, { className: 'media-insight-preset-group' }, presets.map( function ( preset ) {
			var isSelected = currentLimit === preset.value;
			var isDisabled = props.disabled || ( preset.value > 0 && preset.value > maxScanLimit );

			return el( Button, {
				key: preset.label,
				variant: 'secondary',
				className: isSelected ? 'media-insight-preset is-selected' : 'media-insight-preset',
				disabled: isDisabled,
				'aria-pressed': isSelected ? 'true' : 'false',
				onClick: function () {
					props.onChange( String( preset.value ) );
				}
			},
				el( 'span', { className: 'media-insight-preset__header' },
					el( 'span', { className: 'media-insight-preset__label' }, preset.label ),
					isSelected ? el( 'span', { className: 'media-insight-preset__check', 'aria-hidden': 'true' } ) : null
				),
				el( 'span', { className: 'media-insight-preset__help' }, preset.help )
			);
		} ) );
	}

	function InlineStatus( props ) {
		var status = props.status;
		var progress = percent( status );
		var running = isRunningStatus( status );

		if ( ! status || ! status.status ) {
			return el( 'div', { className: 'media-insight-inline-status is-ready', 'aria-live': 'polite' },
				el( 'div', { className: 'media-insight-status-content' },
					el( 'span', { className: 'media-insight-ready-icon', 'aria-hidden': 'true' } ),
					el( 'div', {},
						el( 'div', { className: 'media-insight-inline-status-header' },
							el( 'strong', {}, __( 'Ready for a read-only scan', 'media-insight' ) )
						),
						el( 'p', { className: 'media-insight-status-message' }, statusDescription( status ) )
					)
				),
				el( StatusBadge, { status: status } )
			);
		}

		return el( 'div', { className: 'media-insight-inline-status ' + statusClass( status ), 'aria-live': 'polite' },
			el( 'div', { className: 'media-insight-inline-status-header' },
				el( 'div', { className: 'media-insight-status-heading-wrap' },
					running ? el( Spinner, {} ) : null,
					el( 'strong', {}, statusLabel( status ) )
				),
				el( StatusBadge, { status: status } )
			),
			el( 'p', { className: 'media-insight-status-message' }, statusDescription( status ) ),
			( running || status.status === 'complete' ) ? el( 'div', { className: 'media-insight-progress-wrap' },
				el( 'div', { className: 'media-insight-progress-track', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': progress, 'aria-label': __( 'Scan progress', 'media-insight' ) },
					el( 'span', { className: 'media-insight-progress-bar', style: { width: progress + '%' } } )
				),
				el( 'div', { className: 'media-insight-progress-meta' },
					el( 'span', {}, String( progress ) + '%' ),
					el( 'span', {}, formatNumber( status.processed || 0 ) + ' / ' + formatNumber( status.total || 0 ) )
				)
			) : null
		);
	}

	function Stat( props ) {
		return el( Card, { className: 'media-insight-stat-card media-insight-stat-card--' + ( props.type || 'default' ) },
			el( CardBody, {},
				el( 'div', { className: 'media-insight-stat-content' },
					el( 'strong', {}, formatNumber( props.value || 0 ) ),
					el( 'span', {}, props.label )
				)
			)
		);
	}

	function ScanSetup( props ) {
		var running = props.running;
		var limit = parseLimit( props.limit );
		var limitSummary = 0 === limit ? __( 'Full site scan', 'media-insight' ) : sprintf( __( '%s items maximum', 'media-insight' ), formatNumber( limit ) );

		return el( Card, { className: 'media-insight-panel media-insight-scan-panel' },
			el( CardBody, {},
				el( 'div', { className: 'media-insight-setup-grid media-insight-setup-grid--single' },
					el( 'div', { className: 'media-insight-setup-primary' },
						el( 'div', { className: 'media-insight-field-group' },
							el( 'label', { className: 'media-insight-field-label' }, __( 'Scan size', 'media-insight' ) ),
							el( ScanPresetButtons, { limit: props.limit, disabled: running, onChange: props.onLimitChange } ),
							el( 'p', { className: 'media-insight-help-text' }, limitSummary )
					),
						el( 'div', { className: 'media-insight-advanced-limit' },
							el( TextControl, {
							label: __( 'Manual limit', 'media-insight' ),
							help: sprintf( __( 'Use 0 for all supported content. Maximum: %s.', 'media-insight' ), formatNumber( maxScanLimit ) ),
							type: 'number',
							min: 0,
							max: maxScanLimit,
							value: props.limit,
							onChange: function ( value ) {
								props.onLimitChange( String( parseLimit( value ) ) );
							}
						} )
					),
						el( 'div', { className: 'media-insight-scan-footer' },
							el( 'div', { className: 'media-insight-actions' },
								el( Button, { variant: 'primary', isBusy: running || props.busy, disabled: props.startDisabled, onClick: props.onStart }, running || props.busy ? __( 'Scanning...', 'media-insight' ) : __( 'Start scan', 'media-insight' ) ),
								running ? el( Button, { variant: 'secondary', onClick: props.onCancel }, __( 'Cancel scan', 'media-insight' ) ) : null
							),
							el( InlineStatus, { status: props.status } )
						)
				)
			)
		)
		);
	}

	function EmptyResults() {
		return el( Card, { className: 'media-insight-result-card media-insight-empty-state' },
			el( CardBody, {},
				el( 'div', { className: 'media-insight-empty-icon', 'aria-hidden': 'true' } ),
				el( 'strong', {}, __( 'No repeated media found', 'media-insight' ) ),
				el( 'p', {}, __( 'The latest complete scan did not find repeated featured images or supported ACF image fields.', 'media-insight' ) )
			)
		);
	}

	function NoReport() {
		return el( Panel, {
			title: __( 'Results', 'media-insight' ),
			description: __( 'Results will appear here after a complete scan.', 'media-insight' ),
			className: 'media-insight-results-panel is-empty-report'
		},
			el( Card, { className: 'media-insight-empty-report-card' },
				el( CardBody, {},
					el( 'div', { className: 'media-insight-empty-report-copy' },
						el( 'strong', {}, __( 'No completed scan yet', 'media-insight' ) ),
						el( 'p', {}, __( 'Run a scan to find repeated media usage and export a clean client-ready report.', 'media-insight' ) )
					),
					el( 'div', { className: 'media-insight-empty-report-list', 'aria-label': __( 'Report includes', 'media-insight' ) },
						el( Chip, {}, __( 'Featured images', 'media-insight' ) ),
						el( Chip, {}, __( 'ACF image fields', 'media-insight' ) ),
						el( Chip, {}, __( 'CSV export', 'media-insight' ) )
					)
				)
			)
		);
	}

	function ResultPreview( props ) {
		var image = props.image;
		var src = image.thumbnail_url || image.url || '';
		var alt = image.alt_text || image.filename || __( 'Image preview', 'media-insight' );

		if ( ! src ) {
			return el( 'div', { className: 'media-insight-result-preview is-empty', 'aria-hidden': 'true' }, 'IMG' );
		}

		return el( 'a', { className: 'media-insight-result-preview', href: image.url || src, target: '_blank', rel: 'noopener noreferrer', 'aria-label': sprintf( __( 'Open image %s', 'media-insight' ), alt ) },
			el( 'img', { src: src, alt: alt, loading: 'lazy' } )
		);
	}

	function ResultActions( props ) {
		var image = props.image;

		return el( 'div', { className: 'media-insight-result-actions' },
			image.url ? el( Button, { variant: 'secondary', isSmall: true, href: image.url, target: '_blank', rel: 'noopener noreferrer' }, __( 'Open image', 'media-insight' ) ) : null,
			image.media_edit_url ? el( Button, { variant: 'secondary', isSmall: true, href: image.media_edit_url }, __( 'Edit media', 'media-insight' ) ) : null
		);
	}

	function UsageList( props ) {
		var usages = props.usages || [];

		return el( 'div', { className: 'media-insight-usage-table-wrap' },
			el( 'div', { className: 'media-insight-usage-table', role: 'table', 'aria-label': __( 'Usage locations', 'media-insight' ) },
				el( 'div', { className: 'media-insight-usage-row media-insight-usage-row--head', role: 'row' },
					el( 'span', { role: 'columnheader' }, __( 'Content', 'media-insight' ) ),
					el( 'span', { role: 'columnheader' }, __( 'Source', 'media-insight' ) ),
					el( 'span', { role: 'columnheader' }, __( 'Action', 'media-insight' ) )
				),
				usages.map( function ( usage, index ) {
					var title = usage.post_title || __( 'Untitled', 'media-insight' );

					return el( 'div', { className: 'media-insight-usage-row', role: 'row', key: index },
						el( 'div', { className: 'media-insight-usage-main', role: 'cell' },
							usage.edit_url ? el( 'a', { href: usage.edit_url, title: title }, title ) : el( 'span', { title: title }, title ),
							el( 'span', { className: 'media-insight-usage-context' }, postTypeLabel( usage.post_type ) )
						),
						el( 'div', { className: 'media-insight-usage-meta', role: 'cell' },
							el( Chip, {}, sourceLabel( usage.source ) )
						),
						el( 'div', { className: 'media-insight-usage-action', role: 'cell' },
							usage.edit_url ? el( Button, { variant: 'tertiary', isSmall: true, href: usage.edit_url }, __( 'View post', 'media-insight' ) ) : null
						)
					);
				} )
			)
		);
	}

	function ResultCard( props ) {
		var image = props.image;
		var usages = image.usages || [];
		var sourceSummary = summarizeSources( usages );
		var filename = image.filename || image.key || __( 'Unknown image', 'media-insight' );

		return el( Card, { className: 'media-insight-result-card', key: image.key },
			el( CardBody, {},
				el( 'div', { className: 'media-insight-result-head' },
					el( ResultPreview, { image: image } ),
					el( 'div', { className: 'media-insight-result-content' },
						el( 'div', { className: 'media-insight-result-title-row' },
							el( 'div', { className: 'media-insight-result-title-wrap' },
								el( 'h3', { title: filename }, image.url ? el( 'a', { href: image.url, target: '_blank', rel: 'noopener noreferrer' }, filename ) : filename ),
								el( 'p', { className: 'media-insight-muted' }, sprintf( __( '%1$s uses · %2$s references', 'media-insight' ), formatNumber( image.unique_post_count || 0 ), formatNumber( image.usage_count || 0 ) ) )
							),
							el( Chip, { className: 'is-warning media-insight-repeated-chip' }, __( 'Repeated', 'media-insight' ) )
						),
						el( 'div', { className: 'media-insight-result-meta-row' },
							el( 'div', { className: 'media-insight-source-summary' }, sourceSummary.map( function ( item ) {
								return el( Chip, { key: item.key }, item.label + ' ' + formatNumber( item.count ) );
							} ) ),
							el( ResultActions, { image: image } )
						)
					)
				),
				el( UsageList, { usages: usages } )
			)
		);
	}

	function Results( props ) {
		var report = props.report;
		var status = props.status;
		var canExport = report && status && status.status === 'complete' && props.scanId;

		if ( ! report ) {
			return el( NoReport, {} );
		}

		var stats = report.stats || {};
		var duplicates = report.duplicates || [];
		var exportUrl = canExport ? settings.adminPageUrl + '&media_insight_export_csv=1&scan_id=' + encodeURIComponent( props.scanId ) + '&_wpnonce=' + encodeURIComponent( settings.exportNonce ) : '';
		var exportAction = canExport ? el( Button, {
			variant: 'primary',
			href: exportUrl,
			className: 'media-insight-export-button'
		}, settings.i18n.exportCsv || __( 'Export CSV', 'media-insight' ) ) : null;

		return el( Panel, {
			title: __( 'Results', 'media-insight' ),
			description: __( 'Repeated media usage found by the latest complete scan.', 'media-insight' ),
			actions: exportAction
		},
			el( 'div', { className: 'media-insight-stat-grid' },
				el( Stat, { type: 'scanned', label: __( 'Scanned items', 'media-insight' ), value: stats.scanned_items } ),
				el( Stat, { type: 'images', label: __( 'Images found', 'media-insight' ), value: stats.unique_images } ),
				el( Stat, { type: 'repeated', label: __( 'Repeated images', 'media-insight' ), value: stats.repeated_images } )
			),
			stats.acf_enabled ? null : el( Notice, { status: 'warning', isDismissible: false, className: 'media-insight-notice' }, __( 'ACF is not active, so only featured images were scanned.', 'media-insight' ) ),
			duplicates.length ? el( 'div', { className: 'media-insight-results-list' }, duplicates.map( function ( image ) {
				return el( ResultCard, { key: image.key, image: image } );
			} ) ) : el( EmptyResults, {} )
		);
	}

	function App() {
		var limitState = useState( getDefaultLimit() );
		var limit = limitState[0];
		var setLimit = limitState[1];
		var statusState = useState( null );
		var status = statusState[0];
		var setStatus = statusState[1];
		var reportState = useState( null );
		var report = reportState[0];
		var setReport = reportState[1];
		var busyState = useState( false );
		var busy = busyState[0];
		var setBusy = busyState[1];
		var startRequestRef = useRef ? useRef( false ) : { current: false };
		var errorState = useState( '' );
		var error = errorState[0];
		var setError = errorState[1];

		useEffect( function () {
			var rememberedScanId = getRememberedActiveScan();

			if ( ! rememberedScanId ) {
				return;
			}

			setBusy( true );

			apiFetch( {
				path: restPath( '/scans/' + encodeURIComponent( rememberedScanId ) ),
				method: 'GET'
			} ).then( function ( response ) {
				updateFromResponse( response );
			} ).catch( function () {
				forgetActiveScan();
				setBusy( false );
			} );
		}, [] );

		function updateFromResponse( response ) {
			setStatus( response );

			if ( response && response.scan_id && isRunningStatus( response ) ) {
				rememberActiveScan( response.scan_id );
			} else if ( response && isTerminalStatus( response ) ) {
				forgetActiveScan();
			}

			if ( response && response.report && response.status === 'complete' ) {
				setReport( response.report );
			} else if ( ! response || response.status !== 'complete' ) {
				setReport( null );
			}
		}

		function handleLimitChange( value ) {
			setLimit( String( parseLimit( value ) ) );
		}

		function startScan() {
			if ( startRequestRef.current || busy || isRunningStatus( status ) ) {
				return;
			}

			startRequestRef.current = true;
			setBusy( true );
			setError( '' );
			setReport( null );
			safeSpeak( settings.i18n.queued || __( 'Scan queued.', 'media-insight' ) );

			apiFetch( {
				path: restPath( '/scans' ),
				method: 'POST',
				data: { limit: parseLimit( limit ) }
			} ).then( function ( response ) {
				startRequestRef.current = false;
				updateFromResponse( response );
			} ).catch( function ( apiError ) {
				startRequestRef.current = false;
				setBusy( false );
				setError( apiError && apiError.message ? apiError.message : settings.i18n.failed );
			} );
		}

		function cancelScan() {
			if ( ! status || ! status.scan_id ) {
				return;
			}

			apiFetch( {
				path: restPath( '/scans/' + encodeURIComponent( status.scan_id ) + '/cancel' ),
				method: 'POST'
			} ).then( function ( response ) {
				setBusy( false );
				updateFromResponse( response );
				safeSpeak( settings.i18n.cancelled || __( 'Scan cancelled.', 'media-insight' ) );
			} ).catch( function ( apiError ) {
				setBusy( false );
				setError( apiError && apiError.message ? apiError.message : settings.i18n.failed );
			} );
		}

		useEffect( function () {
			if ( ! status || ! status.scan_id || isTerminalStatus( status ) ) {
				if ( isTerminalStatus( status ) ) {
					setBusy( false );
				}
				if ( status && status.status === 'complete' ) {
					safeSpeak( settings.i18n.complete || __( 'Scan complete.', 'media-insight' ) );
				}
				return;
			}

			var timer = window.setTimeout( function () {
				apiFetch( {
					path: restPath( '/scans/' + encodeURIComponent( status.scan_id ) + '/process' ),
					method: 'POST'
				} ).then( function ( response ) {
					updateFromResponse( response );
				} ).catch( function ( apiError ) {
					setBusy( false );
					setError( apiError && apiError.message ? apiError.message : settings.i18n.failed );

					apiFetch( {
						path: restPath( '/scans/' + encodeURIComponent( status.scan_id ) ),
						method: 'GET'
					} ).then( function ( refreshResponse ) {
						updateFromResponse( refreshResponse );
					} ).catch( function () {
						setStatus( Object.assign( {}, status, {
							status: 'failed',
							message: apiError && apiError.message ? apiError.message : settings.i18n.failed
						} ) );
					} );
				} );
			}, 650 );

			return function () {
				window.clearTimeout( timer );
			};
		}, [ status ] );

		var running = isRunningStatus( status );
		var startDisabled = busy || running;

		return el( 'div', { className: 'media-insight-shell' },
			el( 'h1', { className: 'screen-reader-text' }, __( 'Media Insight', 'media-insight' ) ),
			error ? el( Notice, { status: 'error', isDismissible: true, onRemove: function () { setError( '' ); }, className: 'media-insight-notice' }, error ) : null,
			el( 'main', { className: 'media-insight-stack media-insight-stack--full' },
				el( ScanSetup, {
					limit: limit,
					status: status,
					busy: busy,
					running: running,
					startDisabled: startDisabled,
					onLimitChange: handleLimitChange,
					onStart: startScan,
					onCancel: cancelScan
				} ),
				el( Results, { report: report, status: status, scanId: status && status.scan_id } )
			)
		);
	}

	var root = document.getElementById( 'media-insight-root' );
	if ( root ) {
		if ( createRoot ) {
			createRoot( root ).render( el( App ) );
		} else if ( render ) {
			render( el( App ), root );
		}
	}
}() );
