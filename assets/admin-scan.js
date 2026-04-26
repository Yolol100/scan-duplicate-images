(function () {
	'use strict';

	if (typeof diusImageUsageScannerSettings === 'undefined') {
		return;
	}

	function serializeForm(form) {
		var data = new FormData(form);
		var params = new URLSearchParams();

		data.forEach(function (value, key) {
			params.append(key, value);
		});

		return params;
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
			return response.json();
		}).then(function (json) {
			if (!json || !json.success) {
				throw new Error(json && json.data && json.data.message ? json.data.message : diusImageUsageScannerSettings.i18n.failed);
			}

			return json.data;
		});
	}

	function updateProgress(container, processed, total, message) {
		var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		var bar = container.querySelector('[data-dius-progress-bar]');
		var text = container.querySelector('[data-dius-progress-text]');

		if (bar) {
			bar.style.width = percent + '%';
			bar.setAttribute('aria-valuenow', String(percent));
		}

		if (text) {
			text.textContent = message + ' ' + processed + ' / ' + total + ' (' + percent + '%)';
		}
	}

	function setBusy(form, busy) {
		var buttons = form.querySelectorAll('button, input[type="submit"], input[type="checkbox"], input[type="number"]');
		buttons.forEach(function (button) {
			button.disabled = busy;
		});
	}

	function runBatch(scanId, container, resultsContainer, form) {
		var params = new URLSearchParams();
		params.append('action', 'dius_process_scan_batch');
		params.append('nonce', diusImageUsageScannerSettings.nonce);
		params.append('scan_id', scanId);

		postAjax(params).then(function (data) {
			updateProgress(container, data.processed, data.total, data.done ? diusImageUsageScannerSettings.i18n.complete : diusImageUsageScannerSettings.i18n.scanning);

			if (data.done) {
				resultsContainer.innerHTML = data.html || '';
				setBusy(form, false);
				return;
			}

			runBatch(scanId, container, resultsContainer, form);
		}).catch(function (error) {
			setBusy(form, false);
			container.classList.add('dius-progress-error');
			var text = container.querySelector('[data-dius-progress-text]');
			if (text) {
				text.textContent = error.message || diusImageUsageScannerSettings.i18n.failed;
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.querySelector('[data-dius-scan-form]');
		var ajaxButton = document.querySelector('[data-dius-ajax-start]');
		var progress = document.querySelector('[data-dius-progress]');
		var results = document.querySelector('[data-dius-results]');

		if (!form || !ajaxButton || !progress || !results || typeof fetch === 'undefined') {
			return;
		}

		ajaxButton.hidden = false;

		ajaxButton.addEventListener('click', function (event) {
			event.preventDefault();

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
				setBusy(form, false);
				progress.classList.add('dius-progress-error');
				var text = progress.querySelector('[data-dius-progress-text]');
				if (text) {
					text.textContent = error.message || diusImageUsageScannerSettings.i18n.failed;
				}
			});
		});
	});
}());
