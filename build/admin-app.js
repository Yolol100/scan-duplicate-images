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
	var render = wp.element.render;
	var apiFetch = wp.apiFetch;
	var components = wp.components;
	var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( text ) { return text; };
	var sprintf = wp.i18n && wp.i18n.sprintf ? wp.i18n.sprintf : function ( text ) { return text; };
	var speak = wp.a11y && wp.a11y.speak ? wp.a11y.speak : function () {};
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
		delete buttonProps.isBusy;
		delete buttonProps.variant;
		return el( 'button', buttonProps, props.children );
	};
	var Notice = components.Notice || function ( props ) { return el( 'div', props, props.children ); };
	var TextControl = components.TextControl || function ( props ) {
		return el( 'label', { className: 'media-insight-text-control-fallback' },
			el( 'span', {}, props.label ),
			el( 'input', { type: props.type || 'text', min: props.min, max: props.max, value: props.value, onChange: function ( event ) { props.onChange( event.target.value ); } } ),
			props.help ? el( 'span', { className: 'description' }, props.help ) : null
		);
	};

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

	function isTerminalStatus( status ) {
		return status && [ 'complete', 'failed', 'cancelled' ].indexOf( status.status ) !== -1;
	}

	function isRunningStatus( status ) {
		return status && [ 'queued', 'running' ].indexOf( status.status ) !== -1;
	}

	function safeSpeak( message ) {
		try {
			speak( message );
		} catch ( error ) {}
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
			return __( 'No scan is running. Start with a small limit on staging, or use 0 to scan all supported content.', 'media-insight' );
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

	function HeaderSlot( props ) {
		if ( CardHeader ) {
			return el( CardHeader, { className: props.className }, props.children );
		}

		return el( 'div', { className: props.className || 'components-card__header' }, props.children );
	}

	function StatusBadge( props ) {
		return el( 'span', { className: 'media-insight-status-badge ' + statusClass( props.status ) }, props.children || statusLabel( props.status ) );
	}

	function Panel( props ) {
		return el( Card, { className: props.className ? 'media-insight-panel ' + props.className : 'media-insight-panel' },
			props.title ? el( HeaderSlot, {},
				el( 'div', { className: 'media-insight-panel-title-row' },
					el( 'div', {},
						el( 'h2', {}, props.title ),
						props.description ? el( 'p', {}, props.description ) : null
					),
					props.actions ? el( 'div', { className: 'media-insight-inline-actions' }, props.actions ) : null
				)
			) : null,
			el( CardBody, {}, props.children )
		);
	}


	function InlineStatus( props ) {
		var status = props.status;

		if ( ! status || ! status.status ) {
			return null;
		}

		var progress = percent( status );
		var hasProgress = status && ( isRunningStatus( status ) || status.status === 'complete' );

		return el( 'div', { className: 'media-insight-inline-status ' + statusClass( status ), 'aria-live': 'polite' },
			el( 'div', { className: 'media-insight-inline-status-header' },
				el( 'strong', {}, statusLabel( status ) ),
				el( StatusBadge, { status: status } )
			),
			el( 'p', { className: 'media-insight-status-message' }, statusDescription( status ) ),
			hasProgress ? el( 'div', { className: 'media-insight-progress-wrap' },
				el( 'div', { className: 'media-insight-progress-track', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': progress, 'aria-label': __( 'Scan progress', 'media-insight' ) },
					el( 'span', { className: 'media-insight-progress-bar', style: { width: progress + '%' } } )
				),
				el( 'div', { className: 'media-insight-progress-meta' },
					el( 'span', {}, String( progress ) + '%' ),
					el( 'span', {}, String( status.processed || 0 ) + ' / ' + String( status.total || 0 ) )
				)
			) : null
		);
	}

	function Stat( props ) {
		return el( Card, { className: 'media-insight-stat-card' },
			el( CardBody, {},
				el( 'strong', {}, String( props.value || 0 ) ),
				el( 'span', {}, props.label )
			)
		);
	}

	function ScopeList() {
		var items = [
			__( 'Pages: featured images', 'media-insight' ),
			__( 'Pages: ACF image and gallery fields', 'media-insight' ),
			__( 'Posts: featured images', 'media-insight' )
		];

		return el( 'ul', { className: 'media-insight-scope-list', role: 'list' }, items.map( function ( item ) {
			return el( 'li', { key: item },
				el( 'span', { className: 'media-insight-scope-icon', 'aria-hidden': 'true' }, '✓' ),
				el( 'span', {}, item )
			);
		} ) );
	}


	function EmptyResults() {
		return el( Card, { className: 'media-insight-result-card media-insight-empty-state' },
			el( CardBody, {},
				el( 'strong', {}, __( 'No repeated media found', 'media-insight' ) ),
				el( 'p', {}, __( 'The latest complete scan did not find repeated featured images or supported ACF image fields.', 'media-insight' ) )
			)
		);
	}

	function UsageSource( source ) {
		if ( source === 'acf_page_image' ) {
			return __( 'ACF', 'media-insight' );
		}

		if ( source === 'acf_page_gallery' ) {
			return __( 'ACF gallery', 'media-insight' );
		}

		return __( 'Featured', 'media-insight' );
	}

	function Results( props ) {
		var report = props.report;
		var status = props.status;
		var canExport = report && status && status.status === 'complete' && props.scanId;

		if ( ! report ) {
			return el( Panel, {
				title: __( 'Results', 'media-insight' ),
				description: __( 'Results will appear here after a complete scan.', 'media-insight' )
			},
				el( 'div', { className: 'media-insight-empty-state' },
					el( 'strong', {}, __( 'No report yet', 'media-insight' ) ),
					el( 'p', {}, __( 'Start a scan to list repeated images with their post links and usage context.', 'media-insight' ) )
				)
			);
		}

		var stats = report.stats || {};
		var duplicates = report.duplicates || [];
		var exportAction = canExport ? el( Button, {
			variant: 'secondary',
			onClick: function () {
				window.location.href = settings.adminPostUrl + '?action=media_insight_export_csv&scan_id=' + encodeURIComponent( props.scanId ) + '&media_insight_export_nonce=' + encodeURIComponent( settings.exportNonce );
			}
		}, settings.i18n.exportCsv || __( 'Export CSV', 'media-insight' ) ) : null;

		return el( Panel, {
			title: __( 'Results', 'media-insight' ),
			description: __( 'Repeated media usage found by the latest complete scan.', 'media-insight' ),
			actions: exportAction
		},
			el( 'div', { className: 'media-insight-stat-grid' },
				el( Stat, { label: __( 'Scanned items', 'media-insight' ), value: stats.scanned_items } ),
				el( Stat, { label: __( 'Images found', 'media-insight' ), value: stats.unique_images } ),
				el( Stat, { label: __( 'Repeated images', 'media-insight' ), value: stats.repeated_images } )
			),
			stats.acf_enabled ? null : el( Notice, { status: 'warning', isDismissible: false }, __( 'ACF is not active, so only featured images were scanned.', 'media-insight' ) ),
			duplicates.length ? el( 'div', { className: 'media-insight-results-list' }, duplicates.map( function ( image ) {
				return el( Card, { className: 'media-insight-result-card', key: image.key },
					el( CardBody, {},
						el( 'div', { className: 'media-insight-result-title-row' },
							el( 'div', {},
								el( 'h3', {}, image.url ? el( 'a', { href: image.url, target: '_blank', rel: 'noopener noreferrer' }, image.filename || image.key ) : ( image.filename || image.key ) ),
								el( 'p', { className: 'media-insight-muted' }, sprintf( __( '%1$d pages/posts, %2$d references', 'media-insight' ), image.unique_post_count || 0, image.usage_count || 0 ) )
							),
							el( 'span', { className: 'media-insight-badge is-warning' }, __( 'Repeated', 'media-insight' ) )
						),
						el( 'ul', { className: 'media-insight-usage-list', role: 'list' }, ( image.usages || [] ).map( function ( usage, index ) {
							return el( 'li', { key: index },
								usage.edit_url ? el( 'a', { href: usage.edit_url }, usage.post_title || __( 'Untitled', 'media-insight' ) ) : ( usage.post_title || __( 'Untitled', 'media-insight' ) ),
								el( 'span', { className: 'media-insight-pill' }, usage.post_type || '' ),
								el( 'span', { className: 'media-insight-pill' }, UsageSource( usage.source ) )
							);
						} ) )
					)
				);
			} ) ) : el( EmptyResults )
		);
	}

	function App() {
		var limitState = useState( '0' );
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

		function updateFromResponse( response ) {
			setStatus( response );
			if ( response && response.report && response.status === 'complete' ) {
				setReport( response.report );
			} else if ( ! response || response.status !== 'complete' ) {
				setReport( null );
			}
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

			var requestedLimit = parseInt( limit, 10 );
			requestedLimit = isFinite( requestedLimit ) ? Math.max( 0, requestedLimit ) : 0;

			apiFetch( {
				path: restPath( '/scans' ),
				method: 'POST',
				data: { limit: requestedLimit }
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
			error ? el( Notice, { status: 'error', isDismissible: true, onRemove: function () { setError( '' ); } }, error ) : null,
			el( 'main', { className: 'media-insight-stack media-insight-stack--full' },
				el( Panel, {
					className: 'media-insight-hero-card',
					title: __( 'Scan setup', 'media-insight' ),
					description: __( 'Select a safe limit and start a read-only scan. No media or content will be changed.', 'media-insight' )
				},
					el( 'div', { className: 'media-insight-setup-grid' },
						el( 'div', {},
							el( TextControl, {
								label: __( 'Optional scan limit', 'media-insight' ),
								help: __( 'Use 0 for all supported pages and posts. Positive limits are capped at 50,000.', 'media-insight' ),
								type: 'number',
								min: 0,
								max: 50000,
								value: limit,
								onChange: function ( value ) { setLimit( value ); }
							} ),
							el( 'div', { className: 'media-insight-actions' },
								el( Button, { variant: 'primary', isBusy: running || busy, disabled: startDisabled, onClick: startScan }, running || busy ? __( 'Scanning…', 'media-insight' ) : __( 'Start scan', 'media-insight' ) ),
								running ? el( Button, { variant: 'secondary', onClick: cancelScan }, __( 'Cancel scan', 'media-insight' ) ) : null
							),
							el( 'p', { className: 'media-insight-help-text' }, __( 'Tip: use 100 or 500 first on large staging sites to check runtime and ACF coverage.', 'media-insight' ) )
						),
						el( 'div', { className: 'media-insight-scope-column' },
							el( 'h3', {}, __( 'Scan scope', 'media-insight' ) ),
							el( 'p', { className: 'media-insight-muted' }, __( 'The scope is intentionally fixed, so results stay predictable and safe for client review.', 'media-insight' ) ),
							el( ScopeList )
						)
					),
					el( InlineStatus, { status: status } )
				),
				el( Results, { report: report, status: status, scanId: status && status.scan_id } )
			)
		);
	}

	var root = document.getElementById( 'media-insight-root' );
	if ( root ) {
		if ( render ) {
			render( el( App ), root );
		} else if ( wp.element.createRoot ) {
			wp.element.createRoot( root ).render( el( App ) );
		}
	}
}() );
