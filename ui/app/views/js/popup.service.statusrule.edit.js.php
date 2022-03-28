<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>


window.service_status_rule_edit_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('service_status_rule_edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		const type_selector = document.getElementById('service-status-rule-type');

		type_selector.addEventListener('change', (e) => this.typeChange(e.target.value));
		this.typeChange(type_selector.value)
	}

	typeChange(type) {
		const label = document.getElementById('service-status-rule-limit-value-label');
		const unit = document.getElementById('service-status-rule-limit-value-unit');

		switch (type) {
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_N_L ?>':
				label.innerText = 'N';
				unit.style.display = 'none';
				break;
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_NP_L ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_WP_L ?>':
				label.innerText = 'N';
				unit.style.display = '';
				break;
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_GE ?>':
			case '<?= ZBX_SERVICE_STATUS_RULE_TYPE_W_L ?>':
				label.innerText = 'W';
				unit.style.display = 'none';
				break;
		}
	}

	submit() {
		this.overlay.setLoading();

		const curl = new Curl('zabbix.php', false);

		curl.setArgument('action', 'service.statusrule.validate');

		fetch(curl.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(getFormFields(this.form))
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				overlayDialogueDestroy(this.overlay.dialogueid);

				this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.body}));
			})
			.catch((exception) => {
				for (const el of this.form.parentNode.children) {
					if (el.matches('.msg-good, .msg-bad, .msg-warning')) {
						el.parentNode.removeChild(el);
					}
				}

				let title;
				let messages = [];

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					title = <?= json_encode(_('Unexpected server error.')) ?>;
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}
};
