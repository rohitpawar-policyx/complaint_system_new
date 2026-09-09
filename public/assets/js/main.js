'use strict';

document.documentElement.classList.add('js');

// Render every <i data-lucide="..."> placeholder as an inline SVG icon.
// See app/shared/helpers/icons.php for the PHP-side icon() helper that
// emits these placeholders — this is the single place that activates them.
if (window.lucide) {
	window.lucide.createIcons();
}

function confirmAction(message) {
	if (window.Swal) {
		return window.Swal.fire({
			title: 'Are you sure?',
			text: message,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Confirm',
		}).then((result) => result.isConfirmed);
	}

	return Promise.resolve(window.confirm(message));
}

function notify(success, message) {
	if (window.Swal) {
		window.Swal.fire({
			icon: success ? 'success' : 'error',
			title: message,
			timer: success ? 1800 : undefined,
			showConfirmButton: !success,
		});
		return;
	}

	window.alert(message);
}

function submitForm(form) {
	if (form.dataset.ajax === 'true' && window.axios) {
		window.axios.post(form.action, new FormData(form), {
			headers: {'X-Requested-With': 'XMLHttpRequest'},
		}).then((response) => {
			notify(Boolean(response.data.success), response.data.message || 'Operation completed.');
			if (response.data.success) {
				window.setTimeout(() => window.location.reload(), window.Swal ? 1200 : 0);
			}
		}).catch((error) => {
			notify(false, error.response?.data?.message || 'The operation failed.');
		});
		return;
	}

	form.submit();
}

document.addEventListener('submit', (event) => {
	const form = event.target;
	const confirmMessage = form.dataset.confirm;

	if (confirmMessage) {
		event.preventDefault();
		confirmAction(confirmMessage).then((confirmed) => {
			if (confirmed) submitForm(form);
		});
		return;
	}

	if (form.dataset.ajax === 'true') {
		event.preventDefault();
		submitForm(form);
	}
});

document.querySelectorAll('[data-select-all]').forEach((selectAll) => {
	selectAll.addEventListener('change', () => {
		const form = selectAll.closest('form');
		form?.querySelectorAll('[data-row-select]').forEach((checkbox) => {
			checkbox.checked = selectAll.checked;
		});
		updateSelectedCount(form);
	});
});

document.querySelectorAll('[data-row-select]').forEach((checkbox) => {
	checkbox.addEventListener('change', () => updateSelectedCount(checkbox.closest('form')));
});

function updateSelectedCount(form) {
	if (!form) return;
	const selected = form.querySelectorAll('[data-row-select]:checked').length;
	const target = form.querySelector('[data-selected-count]');
	if (target) target.textContent = `${selected} selected`;
}

document.querySelectorAll('.bulk-form').forEach((form) => updateSelectedCount(form));

// --- Admin sidebar (mobile off-canvas navigation) -------------------------
// Isolated from the rest of this file: only wires up the hamburger toggle,
// close button, overlay, outside-click, escape key, and closing on
// navigation for the admin sidebar. No effect on non-admin pages.
(function initAdminSidebar() {
	const shell = document.querySelector('[data-admin-shell]');
	const toggle = document.querySelector('[data-sidebar-toggle]');
	const sidebar = document.querySelector('[data-sidebar]');

	if (!shell || !toggle || !sidebar) return;

	const closeButton = document.querySelector('[data-sidebar-close]');
	const overlay = document.querySelector('[data-sidebar-overlay]');

	function openSidebar() {
		shell.classList.add('sidebar-open');
		toggle.setAttribute('aria-expanded', 'true');
		overlay?.removeAttribute('hidden');
	}

	function closeSidebar() {
		shell.classList.remove('sidebar-open');
		toggle.setAttribute('aria-expanded', 'false');
		overlay?.setAttribute('hidden', '');
	}

	toggle.addEventListener('click', () => {
		if (shell.classList.contains('sidebar-open')) {
			closeSidebar();
		} else {
			openSidebar();
		}
	});

	closeButton?.addEventListener('click', closeSidebar);
	overlay?.addEventListener('click', closeSidebar);

	sidebar.querySelectorAll('a').forEach((link) => {
		link.addEventListener('click', closeSidebar);
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && shell.classList.contains('sidebar-open')) {
			closeSidebar();
			toggle.focus();
		}
	});
})();

// --- Notification bell (dropdown loaded via Axios, mark-read/mark-all) ----
// Isolated from the rest of this file. Each [data-notification-bell]
// (admin header, user header) wires up independently; normally there is
// exactly one per page. Backed by RESTful Laravel routes (POST
// /notifications/{id}/read, POST /notifications/read-all) rather than a
// single handler with an "action" field, so there's no risk of a form
// field named "action" shadowing the DOM's form.action property.
document.querySelectorAll('[data-notification-bell]').forEach((bell) => {
	const toggle = bell.querySelector('[data-notification-toggle]');
	const dropdown = bell.querySelector('[data-notification-dropdown]');
	const list = bell.querySelector('[data-notification-list]');
	const badge = bell.querySelector('[data-notification-badge]');
	const markAllButton = bell.querySelector('[data-notification-mark-all]');
	const feedUrl = bell.dataset.notifyFeed;
	const readUrlBase = bell.dataset.notifyHandlerRead;
	const readAllUrl = bell.dataset.notifyHandlerReadAll;
	const csrfToken = bell.dataset.csrfToken || '';

	if (!toggle || !dropdown || !list || !feedUrl || !window.axios) return;

	function escapeHtml(value) {
		const div = document.createElement('div');
		div.textContent = value;
		return div.innerHTML;
	}

	function setBadge(count) {
		if (!badge) return;
		if (count > 0) {
			badge.textContent = count > 99 ? '99+' : String(count);
			badge.removeAttribute('hidden');
		} else {
			badge.setAttribute('hidden', '');
		}
	}

	function postJson(url) {
		return window.axios.post(url, {}, {
			headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken},
		}).then((response) => {
			if (response.data?.data?.unread_count !== undefined) {
				setBadge(response.data.data.unread_count);
			}
			return response.data;
		}).catch(() => null);
	}

	function markRead(notificationId) {
		if (!readUrlBase) return;
		postJson(`${readUrlBase}/${notificationId}/read`);
	}

	function renderNotifications(notifications) {
		if (notifications.length === 0) {
			list.innerHTML = '<p class="notification-dropdown-status">No notifications yet.</p>';
			return;
		}

		list.innerHTML = notifications.map((item) => {
			const href = item.path || null;
			const tag = href ? 'a' : 'div';
			const hrefAttribute = href ? ` href="${escapeHtml(href)}"` : '';
			const unreadClass = item.is_read ? '' : ' notification-dropdown-item--unread';

			return `<${tag} class="notification-dropdown-item${unreadClass}"${hrefAttribute} `
				+ `data-notification-item data-notification-id="${item.id}" data-is-read="${item.is_read ? '1' : '0'}">`
				+ `<span class="notification-dropdown-item-title">${escapeHtml(item.title)}</span>`
				+ `<p class="notification-dropdown-item-message">${escapeHtml(item.message)}</p>`
				+ `<time class="notification-dropdown-item-time">${escapeHtml(item.created_at)}</time>`
				+ `</${tag}>`;
		}).join('');

		list.querySelectorAll('[data-notification-item]').forEach((element) => {
			element.addEventListener('click', () => {
				if (element.dataset.isRead === '1') return;
				markRead(element.dataset.notificationId);
				element.dataset.isRead = '1';
				element.classList.remove('notification-dropdown-item--unread');
			});
		});
	}

	function loadFeed() {
		list.innerHTML = '<p class="notification-dropdown-status">Loading…</p>';
		window.axios.get(feedUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then((response) => {
			if (!response.data.success) {
				list.innerHTML = '<p class="notification-dropdown-status">Unable to load notifications.</p>';
				return;
			}
			setBadge(response.data.data.unread_count);
			renderNotifications(response.data.data.notifications);
		}).catch(() => {
			list.innerHTML = '<p class="notification-dropdown-status">Unable to load notifications.</p>';
		});
	}

	// Background refresh: keeps the unread badge current without a page
	// reload. Only updates the visible list too if the dropdown happens to
	// be open at the time; otherwise it's just a lightweight count check.
	function refreshUnreadCount() {
		window.axios.get(feedUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then((response) => {
			if (!response.data.success) return;
			setBadge(response.data.data.unread_count);
			if (!dropdown.hasAttribute('hidden')) {
				renderNotifications(response.data.data.notifications);
			}
		}).catch(() => {});
	}

	window.setInterval(refreshUnreadCount, 120000);

	function openDropdown() {
		dropdown.removeAttribute('hidden');
		toggle.setAttribute('aria-expanded', 'true');
		loadFeed();
	}

	function closeDropdown() {
		dropdown.setAttribute('hidden', '');
		toggle.setAttribute('aria-expanded', 'false');
	}

	toggle.addEventListener('click', () => {
		if (dropdown.hasAttribute('hidden')) {
			openDropdown();
		} else {
			closeDropdown();
		}
	});

	markAllButton?.addEventListener('click', () => {
		if (!readAllUrl) return;
		postJson(readAllUrl).then(() => {
			list.querySelectorAll('[data-notification-item]').forEach((element) => {
				element.dataset.isRead = '1';
				element.classList.remove('notification-dropdown-item--unread');
			});
		});
	});

	document.addEventListener('click', (event) => {
		if (!dropdown.hasAttribute('hidden') && !bell.contains(event.target)) {
			closeDropdown();
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !dropdown.hasAttribute('hidden')) {
			closeDropdown();
			toggle.focus();
		}
	});
});
