(function () {
	'use strict';

	if (typeof diusImageUsageScannerSettings === 'undefined') {
		return;
	}

	var shouldStop = false;

	function serializeForm(form) {
		var data = new FormData(form);
		var params = new URLSearchParams();

		data.forEach(function (value, key) {
			params.append(key, value);
		});

		return params;
	}

	function speak(message) {
		if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
			window.wp.a11y.speak(message);
		}
	}

	function postAjax(params) {
		return fetch(diusImageUsageScannerSettings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: params.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				var json = null;

				try {
					json = text ? JSON.parse(text) : null;
				} catch (error) {
					throw new Error(diusImageUsageScannerSettings.i18n.failed + ' HTTP ' + response.status);
				}

				if (!response.ok || !json || !json.success) {
					throw new Error(json && json.data && json.data.message ? json.data.message : diusImageUsageScannerSettings.i18n.failed);
				}

				return json.data;
			});
		});
	}

	function updateProgress(container, processed, total, message) {
		var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		var track = container.querySelector('[data-dius-progress-track]');
		var bar = container.querySelector('[data-dius-progress-bar]');
		var text = container.querySelector('[data-dius-progress-text]');
		var label = total > 0 ? message + ' ' + processed + ' / ' + total + ' (' + percent + '%)' : message;

		if (bar) {
			bar.style.width = percent + '%';
		}

		if (track) {
			track.setAttribute('aria-valuenow', String(percent));
			track.setAttribute('aria-label', label);
		}

		if (text) {
			text.textContent = label;
		}
	}

	function setBusy(form, busy) {
		form.setAttribute('aria-busy', busy ? 'true' : 'false');
		var controls = form.querySelectorAll('button, input[type="submit"], input[type="checkbox"], input[type="number"]');
		controls.forEach(function (control) {
			if (control.hasAttribute('data-dius-stop-scan')) {
				control.disabled = !busy;
				control.hidden = !busy;
				return;
			}

			control.disabled = busy;
		});
	}

	function finishScan(form, progress, message, isError) {
		setBusy(form, false);
		if (isError) {
			progress.classList.add('dius-progress-error');
		}
		var text = progress.querySelector('[data-dius-progress-text]');
		if (text) {
			text.textContent = message;
		}
		speak(message);
	}

	function runBatch(scanId, container, resultsContainer, form) {
		if (shouldStop) {
			finishScan(form, container, diusImageUsageScannerSettings.i18n.stopped, false);
			return;
		}

		var params = new URLSearchParams();
		params.append('action', 'dius_process_scan_batch');
		params.append('nonce', diusImageUsageScannerSettings.nonce);
		params.append('scan_id', scanId);

		postAjax(params).then(function (data) {
			updateProgress(container, data.processed, data.total, data.done ? diusImageUsageScannerSettings.i18n.complete : diusImageUsageScannerSettings.i18n.scanning);

			if (data.done) {
				resultsContainer.innerHTML = data.html || '';
				setBusy(form, false);
				speak(diusImageUsageScannerSettings.i18n.complete);
				return;
			}

			runBatch(scanId, container, resultsContainer, form);
		}).catch(function (error) {
			finishScan(form, container, error.message || diusImageUsageScannerSettings.i18n.failed, true);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('[data-dius-scan-form]');
		var ajaxButton = document.querySelector('[data-dius-ajax-start]');
		var stopButton = document.querySelector('[data-dius-stop-scan]');
		var progress = document.querySelector('[data-dius-progress]');
		var results = document.querySelector('[data-dius-results]');

		if (!form || !ajaxButton || !progress || !results || typeof fetch === 'undefined') {
			return;
		}

		ajaxButton.hidden = false;

		if (stopButton) {
			stopButton.addEventListener('click', function (event) {
				event.preventDefault();
				shouldStop = true;
			});
		}

		ajaxButton.addEventListener('click', function (event) {
			event.preventDefault();
			shouldStop = false;

			var params = serializeForm(form);
			params.set('action', 'dius_start_scan');
			params.set('nonce', diusImageUsageScannerSettings.nonce);

			results.innerHTML = '';
			progress.hidden = false;
			progress.classList.remove('dius-progress-error');
			setBusy(form, true);
			updateProgress(progress, 0, 0, diusImageUsageScannerSettings.i18n.starting);

			postAjax(params).then(function (data) {
				if (data.total < 1) {
					updateProgress(progress, 0, 0, diusImageUsageScannerSettings.i18n.noItems);
					setBusy(form, false);
					return;
				}

				updateProgress(progress, 0, data.total, diusImageUsageScannerSettings.i18n.scanning);
				runBatch(data.scan_id, progress, results, form);
			}).catch(function (error) {
				finishScan(form, progress, error.message || diusImageUsageScannerSettings.i18n.failed, true);
			});
		});
	});
}());
